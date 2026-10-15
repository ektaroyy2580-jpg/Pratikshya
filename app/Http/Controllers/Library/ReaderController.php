<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveProgressRequest;
use App\Http\Requests\Library\StoreBookmarkRequest;
use App\Models\Book;
use App\Models\BookBookmark;
use App\Models\BookChapter;
use App\Models\BookFavorite;
use App\Models\Ticket;
use App\Services\Library\ComicArt;
use App\Services\Library\ReadingProgressService;
use Illuminate\Http\Request;

class ReaderController extends Controller
{
    public function __construct(private ReadingProgressService $progress) {}

    public function read(Request $request, Book $book, ?int $chapter = null)
    {
        $user = $request->user();
        $book->load('category', 'chapters');
        abort_unless($book->isVisible() || $user->isAdmin(), 404);
        abort_if($book->chapters->isEmpty(), 404);

        $saved = $this->progress->find($user, $book);
        if ($chapter !== null) {
            $current = $book->chapters->firstWhere('position', $chapter);
        } else {
            $current = ($saved && ! $saved->completed ? $book->chapters->firstWhere('id', $saved->book_chapter_id) : null)
                ?? $book->chapters->first();
        }
        abort_unless($current, 404);

        // Where to start: an explicit bookmark target (?at= / ?page=), else the saved place in this chapter, else the top.
        $startPosition = 0.0;
        $startPage = 0;
        if ($request->filled('at') || $request->filled('page')) {
            $startPosition = min(100, max(0, (float) $request->query('at', 0)));
            $startPage = max(0, (int) $request->query('page', 1) - 1);
        } elseif ($saved && (int) $saved->book_chapter_id === (int) $current->id && ! $saved->completed) {
            $startPosition = (float) $saved->position;
            $startPage = (int) ($saved->page_index ?? 0);
        }
        $startPage = min($startPage, $current->pageCount() - 1);

        // Audiobook: resume at the saved sentence when the saved place is this chapter (and still valid).
        $listenStart = null;
        if ($saved && $saved->isListening() && (int) $saved->book_chapter_id === (int) $current->id && ! $saved->completed && ! $request->filled('at')) {
            $max = $current->isComic() ? $current->comicSpeechCount($startPage) : $current->sentenceCount();
            $listenStart = $saved->listen_sentence_index < $max ? (int) $saved->listen_sentence_index : null;
        }

        $index = $book->chapters->search(fn ($c) => $c->id === $current->id);

        return view('library.read', [
            'book' => $book,
            'chapter' => $current,
            'prev' => $index > 0 ? $book->chapters[$index - 1] : null,
            'next' => $book->chapters[$index + 1] ?? null,
            'saved' => $saved,
            'startPosition' => $startPosition,
            'startPage' => $startPage,
            'listenStart' => $listenStart,
            'openListen' => $request->boolean('listen'),
            'isFavorite' => BookFavorite::where('user_id', $user->id)->where('book_id', $book->id)->exists(),
            'bookmarks' => BookBookmark::with('chapter')->where('user_id', $user->id)->where('book_id', $book->id)->latest()->get(),
            'art' => app(ComicArt::class),
            // Read-only: lets the reader show "called!" alerts while you read. Reading never changes the ticket.
            'ticket' => Ticket::with('service')->where('user_id', $user->id)->whereIn('status', Ticket::ACTIVE)->latest('id')->first(),
        ]);
    }

    /** Auto-save (debounced fetch) and page-hide beacon. Keyed by user + book only — never by ticket. */
    public function saveProgress(SaveProgressRequest $request, Book $book)
    {
        abort_unless($book->isVisible() || $request->user()->isAdmin(), 404);
        $chapter = $this->chapterOf($book, (int) $request->validated('chapter_id'));

        $p = $this->progress->save(
            $request->user(), $book, $chapter,
            (float) $request->validated('position'),
            $request->validated('page_index') !== null ? (int) $request->validated('page_index') : null,
            $request->validated('listen_sentence_index') !== null ? (int) $request->validated('listen_sentence_index') : null,
            (string) ($request->validated('mode') ?? 'read'),
        );

        return response()->json(['message' => 'Progress saved', 'data' => [
            'chapter_id' => $p->book_chapter_id,
            'position' => $p->position,
            'page_index' => $p->page_index,
            'mode' => $p->mode,
            'listen_sentence_index' => $p->listen_sentence_index,
            'overall_percent' => $p->overall_percent,
            'completed' => $p->completed,
            'place' => $p->load('chapter')->placeLabel(),
            'saved_at' => $p->last_read_at->toIso8601String(),
        ]]);
    }

    /** JSON for the continue-reading card (lets a widget refresh without a page load). */
    public function continue(Request $request)
    {
        $p = $this->progress->continueFor($request->user());

        return response()->json(['message' => $p ? ($p->isListening() ? 'Continue listening where you left off' : 'Continue reading where you left off') : 'Nothing in progress', 'data' => $p ? [
            'title' => $p->book->title,
            'author' => $p->book->author,
            'place' => $p->placeLabel(),
            'mode' => $p->mode,
            'listen_sentence_index' => $p->listen_sentence_index,
            'overall_percent' => $p->overall_percent,
            'last_read_at' => $p->last_read_at->toIso8601String(),
            'url' => $p->resumeUrl(),
        ] : null]);
    }

    public function toggleFavorite(Request $request, Book $book)
    {
        abort_unless($book->isVisible(), 404);
        $fav = BookFavorite::where('user_id', $request->user()->id)->where('book_id', $book->id)->first();
        $fav ? $fav->delete() : BookFavorite::create(['user_id' => $request->user()->id, 'book_id' => $book->id]);
        $on = ! $fav;

        if ($request->expectsJson()) {
            return response()->json(['message' => $on ? 'Added to favourites' : 'Removed from favourites', 'data' => ['favorite' => $on]]);
        }

        return back()->with('ok', $on ? 'Added to your favourites.' : 'Removed from your favourites.');
    }

    public function storeBookmark(StoreBookmarkRequest $request, Book $book)
    {
        abort_unless($book->isVisible() || $request->user()->isAdmin(), 404);
        $chapter = $this->chapterOf($book, (int) $request->validated('chapter_id'));

        $bm = new BookBookmark(['note' => $request->validated('note')]);
        $bm->forceFill([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'book_chapter_id' => $chapter->id,
            'position' => $chapter->isComic() ? 0 : round((float) $request->validated('position'), 2),
            'page_index' => $chapter->isComic() ? min((int) $request->validated('page_index'), $chapter->pageCount() - 1) : null,
        ])->save();
        $bm->setRelation('chapter', $chapter)->setRelation('book', $book);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Bookmark saved', 'data' => [
                'id' => $bm->id, 'label' => $bm->placeLabel(), 'note' => $bm->note, 'url' => $bm->url(),
                'delete_url' => route('library.bookmarks.destroy', $bm),
            ]], 201);
        }

        return back()->with('ok', 'Bookmark saved.');
    }

    public function destroyBookmark(Request $request, BookBookmark $bookmark)
    {
        $this->authorize('delete', $bookmark);
        $bookmark->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Bookmark removed', 'data' => ['id' => $bookmark->id]]);
        }

        return back()->with('ok', 'Bookmark removed.');
    }

    public function hideHistory(Request $request, Book $book)
    {
        // Scoped to the signed-in user: there is no way to name someone else's history row.
        $this->progress->hideFromHistory($request->user(), $book);

        return back()->with('ok', 'Removed from your reading history. Your saved place is kept if you open it again.');
    }

    private function chapterOf(Book $book, int $chapterId): BookChapter
    {
        $chapter = BookChapter::where('book_id', $book->id)->find($chapterId);
        if (! $chapter) {
            abort(response()->json(['message' => 'That chapter does not belong to this item.', 'errors' => ['chapter_id' => ['Invalid chapter.']]], 422));
        }

        return $chapter;
    }
}
