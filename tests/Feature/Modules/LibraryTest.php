<?php

namespace Tests\Feature\Modules;

use App\Models\Book;
use App\Models\BookBookmark;
use App\Models\BookCategory;
use App\Models\BookFavorite;
use App\Models\Counter;
use App\Models\Location;
use App\Models\ReadingProgress;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Queue\QueueManager;
use Database\Seeders\Modules\LibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug = 'mystery', string $name = 'Mystery'): BookCategory
    {
        return BookCategory::firstOrCreate(['slug' => $slug], ['name' => $name, 'name_ne' => 'रहस्य', 'kind' => 'genre', 'icon' => 'bi-search']);
    }

    private function book(array $attrs = [], int $chapters = 3, ?BookCategory $cat = null): Book
    {
        $book = Book::create(array_merge([
            'book_category_id' => ($cat ?? $this->category())->id,
            'slug' => 'test-book-'.uniqid(),
            'title' => 'The Lantern of Asan',
            'author' => 'Test Author',
            'type' => 'book',
            'language' => 'en',
            'license' => 'Original test content.',
            'cover' => ['palette' => 'indigo', 'motif' => 'moon'],
        ], $attrs));
        for ($i = 1; $i <= $chapters; $i++) {
            $book->chapters()->create(['position' => $i, 'title' => "Part $i", 'kind' => 'text', 'body' => str_repeat("Paragraph of chapter $i words words words.\n\n", 30)]);
        }
        $book->recalculateStats();

        return $book->fresh('chapters');
    }

    private function comic(): Book
    {
        $book = Book::create([
            'book_category_id' => $this->category('manga', 'Manga-style')->id, 'slug' => 'test-comic', 'title' => 'Test Comic',
            'author' => 'Studio', 'type' => 'comic', 'language' => 'en', 'license' => 'Original.', 'reading_direction' => 'rtl',
        ]);
        $panel = ['scene' => 'street', 'figures' => [['who' => 'ronin', 'x' => 50, 'mood' => 'happy']], 'bubbles' => [['text' => 'Hello <b>there</b>', 'x' => 5, 'y' => 5, 'w' => 40, 'tail' => 'bl', 'kind' => 'speech']]];
        $book->chapters()->create(['position' => 1, 'title' => 'Chapter One', 'kind' => 'comic', 'pages' => [['style' => 'manga', 'panels' => [$panel, $panel]], ['panels' => [$panel]], ['panels' => [$panel]]]]);
        $book->recalculateStats();

        return $book->fresh('chapters');
    }

    private function service(string $name = 'Licence Renewal', string $prefix = 'D'): Service
    {
        $loc = Location::create(['name' => 'Office '.uniqid(), 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
        $s = $loc->services()->create(['name' => $name, 'prefix' => $prefix, 'counter' => 2, 'avg_minutes' => 4, 'current_number' => 10, 'last_number' => 14, 'documents' => []]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $s->id, 'number' => 2, 'label' => 'Counter 2']);

        return $s;
    }

    // ---------------------------------------------------------------- progress

    public function test_progress_saves_and_restores_the_exact_position(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $ch2 = $book->chapters[1];

        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $ch2->id, 'position' => 37.25])
            ->assertOk()
            ->assertJsonPath('data.chapter_id', $ch2->id)
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.place', 'Chapter 2 · Part 2 — 37% through');

        $p = ReadingProgress::where('user_id', $user->id)->where('book_id', $book->id)->firstOrFail();
        $this->assertSame(37.25, $p->position);
        $this->assertEqualsWithDelta(45.75, $p->overall_percent, 0.01); // (1 + .3725) / 3

        // Opening the book without a chapter resumes chapter 2 at 37.25%.
        $this->actingAs($user)->get(route('library.read', $book))
            ->assertOk()
            ->assertViewHas('chapter', fn ($c) => $c->id === $ch2->id)
            ->assertViewHas('startPosition', 37.25)
            ->assertSee('data-start-position="37.25"', false);

        // Saving again updates the same single row (user + book is unique).
        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $ch2->id, 'position' => 80])->assertOk();
        $this->assertSame(1, ReadingProgress::where('user_id', $user->id)->count());
        $this->assertSame(80.0, $p->fresh()->position);
    }

    public function test_page_hide_beacon_form_data_is_accepted(): void
    {
        $user = User::factory()->create();
        $book = $this->book();

        // sendBeacon posts multipart form data (with _token) instead of JSON.
        $this->actingAs($user)->post(route('library.progress', $book), ['chapter_id' => $book->chapters[2]->id, 'position' => '12.5'])->assertOk();
        $this->assertSame(12.5, ReadingProgress::where('user_id', $user->id)->value('position') + 0.0);
    }

    public function test_comic_page_index_is_saved_restored_and_clamped(): void
    {
        $user = User::factory()->create();
        $comic = $this->comic();
        $ch = $comic->chapters[0];

        $this->actingAs($user)->postJson(route('library.progress', $comic), ['chapter_id' => $ch->id, 'position' => 0, 'page_index' => 1])
            ->assertOk()->assertJsonPath('data.page_index', 1)->assertJsonPath('data.place', 'Chapter 1 · Chapter One — page 2 of 3');

        $this->actingAs($user)->get(route('library.read', $comic))
            ->assertOk()->assertViewHas('startPage', 1)
            ->assertSee('Hello &lt;b&gt;there&lt;/b&gt;', false)     // bubble text is escaped
            ->assertSee('data-rtl="1"', false);

        // Out-of-range page is clamped to the last page and counts as finished.
        $this->actingAs($user)->postJson(route('library.progress', $comic), ['chapter_id' => $ch->id, 'position' => 0, 'page_index' => 99])
            ->assertOk()->assertJsonPath('data.page_index', 2)->assertJsonPath('data.completed', true);
    }

    public function test_reaching_the_end_marks_complete_and_drops_off_continue_card(): void
    {
        $user = User::factory()->create();
        $book = $this->book([], 2);

        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $book->chapters[1]->id, 'position' => 100])
            ->assertJsonPath('data.completed', true)->assertJsonPath('data.overall_percent', 100);
        $this->actingAs($user)->getJson(route('library.continue'))->assertJsonPath('data', null);
        $this->assertNotNull(ReadingProgress::first()->completed_at);
    }

    // ---------------------------------------------------------------- continue reading across queues

    public function test_progress_follows_the_user_across_different_tickets_and_queues_without_touching_the_queue(): void
    {
        $user = User::factory()->create();
        $book = $this->book(['title' => 'Signal from Sagarmatha']);
        $qm = app(QueueManager::class);

        // Join queue A and read while waiting.
        $ticketA = $qm->join($this->service('Licence Renewal', 'D'), $user);
        $before = $ticketA->only(['number', 'status', 'service_id']);
        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $book->chapters[1]->id, 'position' => 64])->assertOk();
        $this->assertSame($before, $ticketA->fresh()->only(['number', 'status', 'service_id']), 'Reading must never change the ticket.');

        // Leave queue A, join a completely different queue B.
        $qm->leave($ticketA->fresh(), $user);
        $ticketB = $qm->join($this->service('Passport Collection', 'C'), $user);
        $this->assertNotSame($ticketA->id, $ticketB->id);

        // The continue card (as the lead will place it on the ticket page) shows the same exact place.
        $this->actingAs($user);
        $html = view('library.partials.ticket-widget', ['ticket' => $ticketB->fresh()])->render();
        $this->assertStringContainsString('Continue reading where you left off', $html);
        $this->assertStringContainsString('Signal from Sagarmatha', $html);
        $this->assertStringContainsString('Chapter 2 · Part 2 — 64% through', $html);

        $this->getJson(route('library.continue'))
            ->assertJsonPath('data.title', 'Signal from Sagarmatha')
            ->assertJsonPath('data.place', 'Chapter 2 · Part 2 — 64% through');

        // And the reader reopens at that position from the new queue's context.
        $this->get(route('library.read', $book))->assertViewHas('startPosition', 64.0);
        $this->assertSame(1, ReadingProgress::count());
        $this->assertSame('waiting', $ticketB->fresh()->status);
    }

    public function test_continue_card_partial_is_safe_without_progress_or_ticket(): void
    {
        $this->actingAs(User::factory()->create());
        $html = view('library.partials.ticket-widget')->render();
        $this->assertStringContainsString('Nothing in progress yet', $html);
        $this->assertStringContainsString('Reading never changes your place in line', $html);
        $this->assertSame('', trim(view('library.partials.continue-card', ['showEmpty' => false])->render()));
    }

    // ---------------------------------------------------------------- privacy / IDOR

    public function test_users_cannot_read_or_modify_each_others_progress_bookmarks_or_history(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $book = $this->book(['title' => 'Alice Private Read']);
        $ch = $book->chapters[0];

        $this->actingAs($alice)->postJson(route('library.progress', $book), ['chapter_id' => $ch->id, 'position' => 55])->assertOk();
        $this->actingAs($alice)->postJson(route('library.bookmarks.store', $book), ['chapter_id' => $ch->id, 'position' => 55, 'note' => 'Alice secret note'])->assertCreated();
        $bm = BookBookmark::firstOrFail();

        // Bob cannot delete Alice's bookmark.
        $this->actingAs($bob)->deleteJson(route('library.bookmarks.destroy', $bm))->assertForbidden();
        $this->assertModelExists($bm);

        // Bob's pages never show Alice's history, bookmarks or continue card.
        $this->actingAs($bob)->get(route('library.mine', ['tab' => 'history']))->assertOk()->assertDontSee('Alice Private Read');
        $this->actingAs($bob)->get(route('library.mine', ['tab' => 'bookmarks']))->assertOk()->assertDontSee('Alice secret note');
        $this->actingAs($bob)->get(route('library.book', $book))->assertOk()->assertDontSee('Alice secret note')->assertDontSee('You are here');
        $this->actingAs($bob)->getJson(route('library.continue'))->assertJsonPath('data', null);

        // Bob saving progress on the same book creates HIS row; Alice's position is untouched.
        $this->actingAs($bob)->postJson(route('library.progress', $book), ['chapter_id' => $ch->id, 'position' => 5])->assertOk();
        $this->assertSame(55.0, ReadingProgress::where('user_id', $alice->id)->value('position') + 0.0);

        // Hiding history only ever affects the signed-in user's own row.
        $this->actingAs($bob)->post(route('library.history.hide', $book))->assertRedirect();
        $this->assertFalse((bool) ReadingProgress::where('user_id', $alice->id)->value('hidden_from_history'));
        $this->actingAs($alice)->get(route('library.mine'))->assertSee('Alice Private Read');

        // Owner can delete.
        $this->actingAs($alice)->deleteJson(route('library.bookmarks.destroy', $bm))->assertOk();
        $this->assertModelMissing($bm);
    }

    public function test_guests_are_redirected_and_non_admins_cannot_manage_content(): void
    {
        $book = $this->book();
        $this->get(route('library.home'))->assertRedirect(route('login'));
        $this->postJson(route('library.progress', $book), ['chapter_id' => $book->chapters[0]->id, 'position' => 10])->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('library.admin.index'))->assertForbidden();
        $this->actingAs($user)->post(route('library.admin.books.toggle', $book))->assertForbidden();
        $this->assertTrue($book->fresh()->is_active);
    }

    // ---------------------------------------------------------------- validation

    public function test_position_input_is_validated(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $other = $this->book(['title' => 'Other']);
        $url = route('library.progress', $book);
        $ch = $book->chapters[0]->id;

        $this->actingAs($user);
        $this->postJson($url, ['chapter_id' => $ch, 'position' => 101])->assertStatus(422)->assertJsonValidationErrors('position');
        $this->postJson($url, ['chapter_id' => $ch, 'position' => -1])->assertStatus(422)->assertJsonValidationErrors('position');
        $this->postJson($url, ['chapter_id' => $ch, 'position' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('position');
        $this->postJson($url, ['chapter_id' => $ch])->assertStatus(422)->assertJsonValidationErrors('position');
        $this->postJson($url, ['chapter_id' => $ch, 'position' => 10, 'page_index' => -3])->assertStatus(422)->assertJsonValidationErrors('page_index');
        $this->postJson($url, ['chapter_id' => 'x', 'position' => 10])->assertStatus(422)->assertJsonValidationErrors('chapter_id');
        // A chapter from another book cannot be used.
        $this->postJson($url, ['chapter_id' => $other->chapters[0]->id, 'position' => 10])->assertStatus(422);
        $this->assertSame(0, ReadingProgress::count());
        // Bookmark notes are length-limited.
        $this->postJson(route('library.bookmarks.store', $book), ['chapter_id' => $ch, 'position' => 1, 'note' => str_repeat('a', 281)])->assertStatus(422);
    }

    // ---------------------------------------------------------------- search, favourites, visibility

    public function test_search_by_title_author_category_and_nepali(): void
    {
        $user = User::factory()->create();
        $this->book(['title' => 'The Lantern of Asan', 'author' => 'Asha Gurung']);
        $this->book(['title' => 'चिया पसलको कथा', 'author' => 'प्रतीक्षा पुस्तकालय', 'language' => 'ne'], 1, $this->category('nepali-literature', 'Nepali literature'));
        $this->book(['title' => 'Hidden Draft', 'is_active' => false]);

        $this->actingAs($user)->get(route('library.search', ['q' => 'lantern']))->assertOk()->assertSee('The Lantern of Asan')->assertDontSee('चिया पसलको कथा');
        $this->actingAs($user)->get(route('library.search', ['q' => 'Gurung']))->assertSee('The Lantern of Asan');
        $this->actingAs($user)->getJson(route('library.search', ['q' => 'Mystery']))->assertOk()->assertJsonFragment(['title' => 'The Lantern of Asan']);
        $this->actingAs($user)->getJson(route('library.search', ['q' => 'चिया']))->assertJsonCount(1, 'data');
        $this->actingAs($user)->get(route('library.search', ['q' => '', 'lang' => 'ne']))->assertSee('चिया पसलको कथा')->assertDontSee('The Lantern of Asan');
        // Disabled items never appear.
        $this->actingAs($user)->getJson(route('library.search', ['q' => 'Hidden']))->assertJsonCount(0, 'data');
        // LIKE wildcards are treated literally.
        $this->actingAs($user)->getJson(route('library.search', ['q' => '%']))->assertJsonCount(0, 'data');
    }

    public function test_favourite_toggles_on_and_off(): void
    {
        $user = User::factory()->create();
        $book = $this->book();

        $this->actingAs($user)->postJson(route('library.favorite', $book))->assertOk()->assertJsonPath('data.favorite', true);
        $this->assertSame(1, BookFavorite::where('user_id', $user->id)->count());
        $this->actingAs($user)->get(route('library.mine', ['tab' => 'favorites']))->assertSee($book->title);

        $this->actingAs($user)->postJson(route('library.favorite', $book))->assertOk()->assertJsonPath('data.favorite', false);
        $this->assertSame(0, BookFavorite::count());
    }

    public function test_disabled_items_are_hidden_from_readers_but_admins_can_preview_and_manage(): void
    {
        $book = $this->book();
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post(route('library.admin.books.toggle', $book))->assertRedirect();
        $this->assertFalse($book->fresh()->is_active);
        $this->actingAs($user)->get(route('library.book', $book))->assertNotFound();
        $this->actingAs($user)->get(route('library.read', $book))->assertNotFound();
        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $book->chapters[0]->id, 'position' => 5])->assertNotFound();
        $this->actingAs($admin)->get(route('library.book', $book))->assertOk()->assertSee('Admin preview');

        // Admin creates a new item with a text chapter; text is stored as-is and rendered escaped.
        $cat = $this->category();
        $this->actingAs($admin)->post(route('library.admin.books.store'), [
            'title' => 'Admin Story', 'slug' => 'admin-story', 'author' => 'Admin', 'book_category_id' => $cat->id,
            'type' => 'article', 'language' => 'en', 'license' => 'Original.', 'palette' => 'teal', 'motif' => 'leaf',
        ])->assertRedirect(route('library.admin.books.edit', 'admin-story'));
        $new = Book::where('slug', 'admin-story')->firstOrFail();
        $this->assertFalse($new->is_active);
        $this->actingAs($admin)->post(route('library.admin.chapters.store', $new), ['title' => 'One', 'body' => "Hello <script>alert(1)</script>\n\nSecond paragraph."])->assertRedirect();
        $this->actingAs($admin)->post(route('library.admin.books.toggle', $new))->assertRedirect();
        $this->assertTrue($new->fresh()->is_active);
        $this->actingAs($user)->get(route('library.read', $new))->assertOk()
            ->assertSee('Hello &lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        // Licence line is mandatory.
        $this->actingAs($admin)->post(route('library.admin.books.store'), ['title' => 'X', 'slug' => 'x', 'author' => 'A', 'book_category_id' => $cat->id, 'type' => 'book', 'language' => 'en', 'palette' => 'teal', 'motif' => 'leaf'])
            ->assertSessionHasErrors('license');
    }

    // ---------------------------------------------------------------- seeded content + pages render

    public function test_seeded_library_has_twenty_plus_licensed_items_and_all_pages_render(): void
    {
        $this->seed(LibrarySeeder::class);
        $this->seed(LibrarySeeder::class); // idempotent

        $this->assertGreaterThanOrEqual(20, Book::count());
        $this->assertSame(0, Book::where(fn ($q) => $q->whereNull('license')->orWhere('license', ''))->count());
        $this->assertSame(3, Book::where('type', 'comic')->count());
        $this->assertTrue(Book::where('type', 'comic')->where('reading_direction', 'rtl')->exists());
        $this->assertSame(count(LibrarySeeder::CATEGORIES), BookCategory::count());

        $user = User::where('email', 'library.demo@waitwise.np')->firstOrFail();
        $this->actingAs($user)->get(route('library.home'))->assertOk()->assertSee('Continue reading where you left off');
        $this->actingAs($user)->get(route('library.mine'))->assertOk();
        $this->actingAs($user)->get(route('library.category', 'manga'))->assertOk()->assertSee('Ronin of Counter Nine');
        foreach (Book::with('chapters')->get() as $b) {
            $this->actingAs($user)->get(route('library.book', $b))->assertOk();
            foreach ($b->chapters as $ch) {
                $this->actingAs($user)->get(route('library.read', ['book' => $b, 'chapter' => $ch->position]))->assertOk();
            }
        }
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('library.admin.index'))->assertOk();
        $this->actingAs($admin)->get(route('library.admin.books.edit', Book::first()))->assertOk();
        $this->actingAs($admin)->get(route('library.admin.books.create'))->assertOk();
    }

    // ---------------------------------------------------------------- audiobook (Listen mode)

    public function test_sentences_are_split_and_every_sentence_is_numbered_in_the_reader(): void
    {
        $this->assertSame(['Mr. Holmes smiled at me.', 'It was 8:40 p.m. at Ratnapark.', 'Was it late?'], \App\Services\Library\Sentences::split('Mr. Holmes smiled at me. It was 8:40 p.m. at Ratnapark. Was it late?'));
        $this->assertSame(['म घर गएँ।', 'तिमी कहाँ?'], \App\Services\Library\Sentences::split('म घर गएँ। तिमी कहाँ?'));
        $this->assertSame([], \App\Services\Library\Sentences::split('   '));

        $user = User::factory()->create();
        $book = $this->book();
        $ch = $book->chapters[0];
        $count = $ch->sentenceCount();
        $this->assertSame(30, $count);

        $html = $this->actingAs($user)->get(route('library.read', ['book' => $book, 'chapter' => 1]))->assertOk()
            ->assertSee('id="lsBar"', false)->assertSee('library-listen.js', false)->getContent();
        $this->assertSame($count, substr_count($html, 'class="rd-s"'));
        $this->assertStringContainsString('data-s="'.($count - 1).'"', $html);
        $this->assertStringNotContainsString('data-s="'.$count.'"', $html);
    }

    public function test_listening_position_saves_and_restores_across_queues_and_devices(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $ch2 = $book->chapters[1];

        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $ch2->id, 'position' => 40, 'mode' => 'listen', 'listen_sentence_index' => 12])
            ->assertOk()->assertJsonPath('data.mode', 'listen')->assertJsonPath('data.listen_sentence_index', 12);

        $p = ReadingProgress::where('user_id', $user->id)->where('book_id', $book->id)->firstOrFail();
        $this->assertSame('listen', $p->mode);
        $this->assertSame(12, $p->listen_sentence_index);
        $this->assertSame($ch2->id, $p->book_chapter_id);
        $this->assertStringContainsString('listen=1', $p->resumeUrl());
        $this->assertStringContainsString('sentence 13 of 30', $p->placeLabel());

        // Another device: the reader resumes at the same chapter and sentence; widgets say "Continue listening".
        $this->actingAs($user)->get(route('library.read', $book))->assertOk()->assertSee('data-listen-start="12"', false)->assertSee('Part 2');
        $this->actingAs($user)->get(route('library.read', ['book' => $book, 'listen' => 1]))->assertOk()->assertSee('data-open-listen="1"', false);
        $this->actingAs($user)->getJson(route('library.continue'))->assertOk()->assertJsonPath('data.mode', 'listen')->assertJsonPath('data.listen_sentence_index', 12);
        $this->actingAs($user)->get(route('library.home'))->assertOk()->assertSee('Continue listening where you left off')->assertSee('Resume listening');
        $this->actingAs($user)->get(route('library.book', $book))->assertOk()->assertSee('Continue listening');

        // Joining a different queue changes nothing about the saved listening place (keyed by user + book only).
        $s = $this->service('Passport', 'P');
        $ticket = app(QueueManager::class)->join($s, $user);
        $this->actingAs($user)->get(route('library.read', $book))->assertOk()->assertSee('data-listen-start="12"', false);
        $this->assertSame($ticket->fresh()->status, $ticket->status);

        // Beacon (form data) in listen mode also works.
        $this->actingAs($user)->post(route('library.progress', $book), ['chapter_id' => $ch2->id, 'position' => 50, 'mode' => 'listen', 'listen_sentence_index' => '15'])->assertOk();
        $this->assertSame(15, $p->fresh()->listen_sentence_index);

        // Reading (scrolling) in the same chapter keeps the sentence; moving to another chapter clears it.
        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $ch2->id, 'position' => 55])->assertOk()->assertJsonPath('data.mode', 'read');
        $this->assertSame(15, $p->fresh()->listen_sentence_index);
        $this->assertFalse($p->fresh()->isListening());
        $this->actingAs($user)->postJson(route('library.progress', $book), ['chapter_id' => $book->chapters[2]->id, 'position' => 5])->assertOk();
        $this->assertNull($p->fresh()->listen_sentence_index);
        $this->actingAs($user)->get(route('library.read', $book))->assertOk()->assertSee('data-listen-start=""', false);
    }

    public function test_listening_index_is_validated_and_scoped_to_the_signed_in_user(): void
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $book = $this->book();
        $other = $this->book(['slug' => 'other-book']);
        $ch = $book->chapters[0];
        $url = route('library.progress', $book);

        $this->actingAs($a)->postJson($url, ['chapter_id' => $ch->id, 'position' => 10, 'mode' => 'listen', 'listen_sentence_index' => 4])->assertOk();

        foreach ([
            ['listen_sentence_index' => 30],          // == count → out of range
            ['listen_sentence_index' => 999],
            ['listen_sentence_index' => -1],
            ['listen_sentence_index' => 'abc'],
            ['listen_sentence_index' => null],        // required in listen mode
            ['mode' => 'karaoke', 'listen_sentence_index' => 1],
        ] as $bad) {
            $this->actingAs($a)->postJson($url, array_merge(['chapter_id' => $ch->id, 'position' => 10, 'mode' => 'listen'], $bad))->assertStatus(422);
        }
        // A chapter from another book is rejected.
        $this->actingAs($a)->postJson($url, ['chapter_id' => $other->chapters[0]->id, 'position' => 1, 'mode' => 'listen', 'listen_sentence_index' => 0])->assertStatus(422);
        $this->assertSame(4, ReadingProgress::where('user_id', $a->id)->where('book_id', $book->id)->value('listen_sentence_index'));

        // B saving the same book only ever writes B's own row; A's listening place is untouched and never leaks.
        $this->actingAs($b)->postJson($url, ['chapter_id' => $book->chapters[2]->id, 'position' => 90, 'mode' => 'listen', 'listen_sentence_index' => 29])->assertOk();
        $this->assertSame(4, ReadingProgress::where('user_id', $a->id)->where('book_id', $book->id)->value('listen_sentence_index'));
        $this->assertSame($ch->id, ReadingProgress::where('user_id', $a->id)->where('book_id', $book->id)->value('book_chapter_id'));
        $this->actingAs($b)->get(route('library.read', ['book' => $book, 'chapter' => 1]))->assertOk()->assertSee('data-listen-start=""', false);
        $this->actingAs($b)->getJson(route('library.continue'))->assertJsonPath('data.listen_sentence_index', 29);

        // Comics: the index is a spoken bubble on the saved page (each test page has 1-2 bubbles).
        $comic = $this->comic();
        $cch = $comic->chapters[0];
        $this->actingAs($a)->postJson(route('library.progress', $comic), ['chapter_id' => $cch->id, 'position' => 0, 'page_index' => 0, 'mode' => 'listen', 'listen_sentence_index' => 1])->assertOk();
        $this->actingAs($a)->postJson(route('library.progress', $comic), ['chapter_id' => $cch->id, 'position' => 0, 'page_index' => 1, 'mode' => 'listen', 'listen_sentence_index' => 1])->assertStatus(422);
        $this->actingAs($a)->get(route('library.read', $comic))->assertOk()->assertSee('Read aloud');
    }

    // ---------------------------------------------------------------- images + credits

    public function test_images_fall_back_to_the_generated_cover_and_unsafe_values_are_ignored(): void
    {
        $user = User::factory()->create();
        $missing = $this->book(['title' => 'Missing Image Book']);
        $missing->forceFill(['image_path' => 'images/library/does-not-exist-'.uniqid().'.jpg', 'image_credit' => ['author' => 'X', 'license' => 'CC BY 4.0', 'source_url' => 'https://commons.wikimedia.org/wiki/File:X.jpg']])->save();
        $evil = $this->book(['title' => 'Evil Path Book']);
        $evil->forceFill(['image_path' => '../../.env', 'image_placeholder' => "data:image/jpeg;base64,AA');background:url(https://evil.test/x"])->save();

        $this->assertFalse($missing->hasImage());
        $this->assertNull($missing->imageUrl());
        $this->assertFalse($evil->hasImage());
        $this->assertNull($evil->safePlaceholder());
        $this->assertNull(Book::cleanPlaceholder('javascript:alert(1)'));

        foreach ([$missing, $evil] as $b) {
            $html = $this->actingAs($user)->get(route('library.book', $b))->assertOk()->getContent();
            $this->assertStringContainsString("viewBox='0 0 200 300'", $html); // SVG cover still rendered
            $this->assertStringNotContainsString('lib-cover-photo', $html);
            $this->assertStringNotContainsString('.env', $html);
            $this->assertStringNotContainsString('evil.test', $html);
            $this->actingAs($user)->get(route('library.read', ['book' => $b, 'chapter' => 1]))->assertOk()->assertDontSee('rd-hero', false);
        }
        $this->actingAs($user)->get(route('library.home'))->assertOk();

        // With a real file on disk the photo is used (lazy, blur-up) and credited; the SVG stays underneath as fallback.
        $dir = public_path('images/library');
        @mkdir($dir, 0777, true);
        $name = 'phpunit-cover-'.uniqid().'.jpg';
        file_put_contents("$dir/$name", base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));
        try {
            $ok = $this->book(['title' => 'Photo Book']);
            $ok->forceFill(['image_path' => "images/library/$name", 'image_alt' => 'A test photo', 'image_placeholder' => 'data:image/jpeg;base64,QUJD',
                'image_credit' => ['title' => 'Test.jpg', 'author' => 'Jane Doe', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Test.jpg']])->save();
            $this->assertTrue($ok->hasImage());
            $this->actingAs($user)->get(route('library.book', $ok))->assertOk()
                ->assertSee($name, false)->assertSee('loading="eager"', false)->assertSee("viewBox='0 0 200 300'", false)
                ->assertSee('Jane Doe · CC BY-SA 4.0 · Wikimedia Commons')->assertSee('A test photo');
            $this->actingAs($user)->get(route('library.search', ['q' => 'Photo Book']))->assertOk()->assertSee('loading="lazy"', false);
            $this->actingAs($user)->get(route('library.read', ['book' => $ok, 'chapter' => 1]))->assertOk()->assertSee('rd-hero', false);
            $this->actingAs($user)->get(route('library.credits'))->assertOk()
                ->assertSee('Photo Book')->assertSee('Jane Doe')->assertSee('https://commons.wikimedia.org/wiki/File:Test.jpg', false)
                ->assertSee('https://creativecommons.org/licenses/by-sa/4.0', false);
        } finally {
            @unlink("$dir/$name");
        }
    }

    public function test_credits_page_renders_for_readers_and_only_links_to_trusted_sources(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('library.credits'))->assertOk()->assertSee('Image credits')->assertSee('No photos have been added yet');

        $b = $this->book(['title' => 'Linked Book']);
        $b->forceFill(['image_credit' => ['title' => 'Bad.jpg', 'author' => '<script>alert(1)</script>', 'license' => 'Public domain', 'source_url' => 'https://evil.test/x', 'license_url' => 'javascript:alert(1)']])->save();
        $html = $this->actingAs($user)->get(route('library.credits'))->assertOk()->assertSee('Linked Book')->assertSee('Public domain')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->get(route('library.credits'))->assertOk(); // still signed in
        auth()->logout();
        $this->get(route('library.credits'))->assertRedirect();
    }

    public function test_audiobook_badge_filter_and_shelf(): void
    {
        $user = User::factory()->create();
        $this->book(['title' => 'Spoken Story']);
        $this->comic();

        $this->actingAs($user)->get(route('library.search', ['audio' => 1]))->assertOk()
            ->assertSee('Spoken Story')->assertDontSee('Test Comic')->assertSee('Audiobook');
        $this->actingAs($user)->get(route('library.search'))->assertOk()->assertSee('Test Comic');
        $this->actingAs($user)->get(route('library.search', ['audio' => 'yes-please']))->assertSessionHasErrors('audio');
        $this->actingAs($user)->get(route('library.home'))->assertOk()->assertSee('Audiobooks · Listen while you wait')->assertSee('bi-headphones', false);
        $this->actingAs($user)->getJson(route('library.search', ['q' => 'Spoken']))->assertOk()->assertJsonPath('data.0.audiobook', true);
    }

    public function test_seeded_media_has_credits_and_files_on_disk(): void
    {
        $this->seed(LibrarySeeder::class);
        $withImage = Book::whereNotNull('image_path')->get();
        // Images ship in public/images/library; every seeded image must be credited with a free licence.
        foreach ($withImage as $b) {
            $this->assertTrue($b->hasImage(), $b->slug.' image missing on disk');
            $this->assertMatchesRegularExpression('/^(Public domain|CC0|CC BY(-SA)? [0-9.]+)/i', $b->image_credit['license'] ?? '', $b->slug);
            $this->assertStringStartsWith('https://commons.wikimedia.org/', $b->image_credit['source_url']);
        }
        $this->assertSame(0, Book::where('type', 'comic')->whereNotNull('image_path')->count(), 'comics keep their original SVG art');
        $this->assertGreaterThanOrEqual(20, $withImage->count());
        $user = User::where('email', 'library.demo@waitwise.np')->firstOrFail();
        $this->actingAs($user)->get(route('library.credits'))->assertOk()->assertSee('Wikimedia Commons')->assertSee('Sidney Paget');
        $holmes = Book::where('slug', 'a-scandal-in-bohemia-abridged')->firstOrFail();
        $this->actingAs($user)->get(route('library.read', ['book' => $holmes, 'chapter' => 2]))->assertOk()->assertSee('rd-fig', false)->assertSee('clergyman');
    }
}
