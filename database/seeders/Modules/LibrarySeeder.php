<?php

namespace Database\Seeders\Modules;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookChapter;
use App\Models\BookFavorite;
use App\Models\User;
use App\Services\Library\ReadingProgressService;
use Illuminate\Database\Seeder;

/**
 * E-Library demo content: public-domain retellings + original stories, articles, sample news/blogs
 * and three original SVG comics. Idempotent: rows are matched on natural keys (slug / book+position).
 * Existing items are left untouched so admin edits survive re-seeding; set LIBRARY_SEED_REFRESH=1 to
 * overwrite seeded items with the shipped content.
 *
 *   php artisan db:seed --class="Database\Seeders\Modules\LibrarySeeder" --force
 */
class LibrarySeeder extends Seeder
{
    public const CATEGORIES = [
        // slug, name, Nepali name, kind, icon, description
        ['fiction', 'Fiction', 'कथा साहित्य', 'genre', 'bi-book-half', 'Stories, classics and poems to get lost in.'],
        ['mystery', 'Mystery', 'रहस्य', 'genre', 'bi-search', 'Clues, detectives and clever twists.'],
        ['thriller', 'Thriller', 'थ्रिलर', 'genre', 'bi-lightning-charge', 'Fast pages for long waits.'],
        ['romance', 'Romance', 'प्रेम कथा', 'genre', 'bi-heart', 'Gentle stories of connection.'],
        ['science-fiction', 'Science fiction', 'विज्ञान कथा', 'genre', 'bi-rocket-takeoff', 'Futures, signals and big ideas.'],
        ['fantasy', 'Fantasy', 'काल्पनिक', 'genre', 'bi-stars', 'Magic, myths and fairy tales.'],
        ['biography', 'Biography', 'जीवनी', 'genre', 'bi-person-vcard', 'Real lives, briefly told.'],
        ['personal-development', 'Personal development', 'आत्म विकास', 'genre', 'bi-graph-up-arrow', 'Small habits, better days.'],
        ['education', 'Education', 'शिक्षा', 'genre', 'bi-mortarboard', 'Learn something in one wait.'],
        ['technology', 'Technology', 'प्रविधि', 'genre', 'bi-cpu', 'How the things around you work.'],
        ['history', 'History', 'इतिहास', 'genre', 'bi-bank', 'Places and people that shaped Nepal.'],
        ['nepali-literature', 'Nepali literature', 'नेपाली साहित्य', 'genre', 'bi-translate', 'Original stories in Nepali.'],
        ['comics', 'Comics', 'कमिक्स', 'genre', 'bi-chat-square-quote', 'Panel-by-panel fun, drawn for this app.'],
        ['manga', 'Manga-style', 'मांगा शैली', 'genre', 'bi-arrow-left-right', 'Original manga-style comics, read right to left.'],
        ['childrens', "Children's", 'बालबालिका', 'genre', 'bi-balloon', 'Fables and comics for young readers.'],
        ['news', 'News', 'समाचार', 'periodical', 'bi-newspaper', 'Sample news items (fictional demo content).'],
        ['blogs', 'Blogs', 'ब्लग', 'periodical', 'bi-pencil-square', 'Personal posts from fictional writers.'],
        ['articles', 'Articles', 'लेख', 'periodical', 'bi-file-earmark-text', 'Short, useful reads.'],
    ];

    public function run(): void
    {
        $refresh = (bool) env('LIBRARY_SEED_REFRESH', false);

        $cats = [];
        foreach (self::CATEGORIES as $i => [$slug, $name, $ne, $kind, $icon, $desc]) {
            $cats[$slug] = BookCategory::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'name_ne' => $ne, 'kind' => $kind, 'icon' => $icon, 'description' => $desc, 'sort' => $i, 'is_active' => true,
            ]);
        }

        $items = array_merge(
            require __DIR__.'/LibraryContent/fiction.php',
            require __DIR__.'/LibraryContent/nonfiction.php',
            require __DIR__.'/LibraryContent/comics.php',
        );

        foreach ($items as $item) {
            $this->seedItem($item, $cats, $refresh);
        }

        $this->seedMedia(require __DIR__.'/LibraryContent/media.php');

        $this->seedDemoReaders();
    }

    /**
     * Cover photos + in-chapter illustrations (freely licensed, downloaded once from Wikimedia Commons into
     * public/images/library, credited per image). Images are seeder-owned (there is no admin UI for them), so they
     * are synced on every run. Missing files are skipped and the generated SVG cover stays as the fallback.
     */
    private function seedMedia(array $media): void
    {
        foreach ($media as $slug => $m) {
            $book = Book::where('slug', $slug)->first();
            if (! $book) {
                continue;
            }
            $c = $m['cover'] ?? null;
            if ($c && Book::publicFileExists($c['path'])) {
                $book->forceFill([
                    'image_path' => $c['path'],
                    'image_path_sm' => Book::publicFileExists($c['path_sm'] ?? null) ? $c['path_sm'] : null,
                    'image_alt' => $c['alt'],
                    'image_placeholder' => Book::cleanPlaceholder($c['placeholder'] ?? null),
                    'image_credit' => self::credit($c),
                ])->save();
            }

            $byChapter = collect($m['illus'] ?? [])->filter(fn ($i) => Book::publicFileExists($i['path']))->groupBy('chapter');
            foreach ($book->chapters()->where('kind', 'text')->get() as $chapter) {
                $list = [];
                foreach ($byChapter->get($chapter->position, []) as $ill) {
                    $list[] = [
                        'path' => $ill['path'],
                        'alt' => $ill['alt'],
                        'caption' => $ill['caption'],
                        'after' => $this->placeAfter($chapter, $ill['near'] ?? null),
                        'placeholder' => Book::cleanPlaceholder($ill['placeholder'] ?? null),
                        'width' => $ill['width'] ?? null,
                        'height' => $ill['height'] ?? null,
                        'credit' => self::credit($ill),
                    ];
                }
                $chapter->forceFill(['illustrations' => $list ?: null])->saveQuietly();
            }
        }
    }

    private static function credit(array $i): array
    {
        return [
            'title' => preg_replace('/^File:/', '', $i['file']),
            'author' => mb_substr((string) ($i['author'] ?? 'Unknown'), 0, 120),
            'license' => $i['license'],
            'license_url' => $i['license_url'] ?: null,
            'source_url' => $i['source_url'],
        ];
    }

    /** Index of the block an illustration follows: the first paragraph mentioning $near, else after the second block. */
    private function placeAfter(BookChapter $chapter, ?string $near): int
    {
        $blocks = $chapter->blocks();
        if ($near) {
            foreach ($blocks as $i => [$type, $lines]) {
                if ($type === 'p' && mb_stripos(implode(' ', $lines), $near) !== false) {
                    return $i;
                }
            }
        }

        return min(1, max(0, count($blocks) - 1));
    }

    private function seedItem(array $item, array $cats, bool $refresh): void
    {
        $category = $cats[$item['category']] ?? $cats['fiction'];
        $existing = Book::where('slug', $item['slug'])->first();
        if ($existing && ! $refresh) {
            return;
        }

        $book = $existing ?? new Book(['slug' => $item['slug']]);
        $book->fill([
            'book_category_id' => $category->id,
            'title' => $item['title'],
            'author' => $item['author'],
            'type' => $item['type'] ?? 'book',
            'description' => $item['description'] ?? null,
            'language' => $item['language'] ?? 'en',
            'license' => $item['license'],
            'cover' => $item['cover'] ?? null,
            'reading_direction' => $item['reading_direction'] ?? 'ltr',
            'is_featured' => (bool) ($item['featured'] ?? false),
            'is_active' => $existing?->is_active ?? true,
        ])->save();

        foreach (array_values($item['chapters']) as $i => $ch) {
            $isComic = isset($ch['pages']);
            $chapter = BookChapter::firstOrNew(['book_id' => $book->id, 'position' => $i + 1]);
            $chapter->book_id = $book->id;
            $chapter->fill([
                'title' => $ch['title'],
                'kind' => $isComic ? 'comic' : 'text',
                'body' => $isComic ? null : trim($ch['body']),
                'pages' => $isComic ? $ch['pages'] : null,
            ])->save();
        }

        $book->recalculateStats();
    }

    /** Two demo readers so "continue reading" and IDOR checks can be tried by hand. */
    private function seedDemoReaders(): void
    {
        $reader = User::firstOrCreate(['email' => 'library.demo@waitwise.np'], ['name' => 'Kabita Library Demo', 'password' => 'Demo@1234']);
        User::firstOrCreate(['email' => 'library.demo2@waitwise.np'], ['name' => 'Bikash Library Demo', 'password' => 'Demo@1234']);

        $progress = app(ReadingProgressService::class);
        $holmes = Book::where('slug', 'a-scandal-in-bohemia-abridged')->first();
        if ($holmes && ! $progress->find($reader, $holmes)) {
            $progress->save($reader, $holmes, $holmes->chapters()->where('position', 2)->first(), 42);
        }
        $momo = Book::where('slug', 'momo-and-the-missing-token')->first();
        if ($momo && ! $progress->find($reader, $momo)) {
            $progress->save($reader, $momo, $momo->chapters()->first(), 0, 1);
            \App\Models\ReadingProgress::where('user_id', $reader->id)->where('book_id', $momo->id)
                ->update(['last_read_at' => now()->subDay()]);
        }
        foreach (['a-scandal-in-bohemia-abridged', 'ronin-of-counter-nine'] as $slug) {
            if ($b = Book::where('slug', $slug)->first()) {
                BookFavorite::query()->firstOrCreate(['user_id' => $reader->id, 'book_id' => $b->id]);
            }
        }
    }
}
