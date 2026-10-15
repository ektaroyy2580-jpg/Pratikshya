<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookBookmark;
use App\Models\BookCategory;
use App\Models\BookFavorite;
use App\Services\Library\Catalog;
use App\Services\Library\ReadingProgressService;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function __construct(private Catalog $catalog, private ReadingProgressService $progress) {}

    public function home(Request $request)
    {
        $user = $request->user();
        $categories = BookCategory::active()->withCount(['books' => fn ($q) => $q->where('is_active', true)])->orderBy('sort')->get();
        $all = $this->catalog->visible()->get();

        return view('library.home', [
            'continue' => $this->progress->continueFor($user),
            'recent' => $this->progress->history($user, 6),
            'featured' => $all->where('is_featured', true)->values(),
            'popular' => $this->catalog->popular(8),
            'comics' => $all->where('type', 'comic')->values(),
            'audiobooks' => $all->filter(fn (Book $b) => $b->isAudiobook())->sortByDesc('is_featured')->take(12)->values(),
            'nepali' => $all->where('language', 'ne')->values(),
            'periodicals' => $all->whereIn('type', ['news', 'blog', 'article'])->sortByDesc('created_at')->values(),
            'genres' => $categories->where('kind', 'genre')->values(),
            'periodicalCats' => $categories->where('kind', 'periodical')->values(),
            'totalItems' => $all->count(),
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'in:'.implode(',', Book::TYPES)],
            'lang' => ['nullable', 'string', 'in:en,ne'],
            'audio' => ['nullable', 'boolean'],
        ]);
        $q = (string) ($data['q'] ?? '');
        $audio = (bool) ($data['audio'] ?? false);
        $results = $this->catalog->search($q, $data['type'] ?? null, $data['lang'] ?? null, $audio);

        if ($request->expectsJson()) {
            $items = $results->limit(8)->get()->map(fn (Book $b) => [
                'title' => $b->title, 'author' => $b->author, 'category' => $b->category->name,
                'type' => $b->typeLabel(), 'minutes' => $b->reading_minutes, 'url' => route('library.book', $b),
                'audiobook' => $b->isAudiobook(),
            ]);

            return response()->json(['message' => $items->isEmpty() ? 'No matches' : 'OK', 'data' => $items]);
        }

        return view('library.search', [
            'q' => $q, 'type' => $data['type'] ?? null, 'lang' => $data['lang'] ?? null, 'audio' => $audio,
            'results' => $results->paginate(24)->withQueryString(),
        ]);
    }

    public function category(BookCategory $category)
    {
        abort_unless($category->is_active, 404);

        return view('library.category', [
            'category' => $category,
            'books' => $this->catalog->visible()->where('book_category_id', $category->id)
                ->orderByDesc('is_featured')->orderBy('title')->paginate(24),
            'others' => BookCategory::active()->where('id', '!=', $category->id)->orderBy('sort')->get(),
        ]);
    }

    public function show(Request $request, Book $book)
    {
        $user = $request->user();
        $book->load('category', 'chapters');
        // Disabled items are hidden from readers; admins may preview them.
        abort_unless($book->isVisible() || $user->isAdmin(), 404);

        return view('library.show', [
            'book' => $book,
            'progress' => $this->progress->find($user, $book),
            'isFavorite' => BookFavorite::where('user_id', $user->id)->where('book_id', $book->id)->exists(),
            'bookmarks' => BookBookmark::with('chapter')->where('user_id', $user->id)->where('book_id', $book->id)->latest()->get(),
            'related' => $this->catalog->visible()->where('book_category_id', $book->book_category_id)->where('books.id', '!=', $book->id)->limit(4)->get(),
        ]);
    }

    /** Image credits: every cover photo and in-chapter illustration with author, licence and source link. */
    public function credits()
    {
        $books = $this->catalog->visible()->with('chapters:id,book_id,position,title,illustrations')->orderBy('title')->get()
            ->filter(fn (Book $b) => $b->image_credit || $b->chapters->contains(fn ($c) => ! empty($c->illustrations)))
            ->values();

        return view('library.credits', ['books' => $books]);
    }

    public function mine(Request $request)
    {
        $user = $request->user();

        return view('library.mine', [
            'continue' => $this->progress->continueFor($user),
            'history' => $this->progress->history($user, 50),
            'favorites' => BookFavorite::with('book.category')->where('user_id', $user->id)
                ->whereHas('book', fn ($b) => $b->active())->latest()->get(),
            'bookmarks' => BookBookmark::with(['book', 'chapter'])->where('user_id', $user->id)
                ->whereHas('book', fn ($b) => $b->active())->latest()->get(),
            'tab' => in_array($request->query('tab'), ['history', 'favorites', 'bookmarks'], true) ? $request->query('tab') : 'history',
        ]);
    }
}
