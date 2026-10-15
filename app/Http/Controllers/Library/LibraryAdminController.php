<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\BookRequest;
use App\Http\Requests\Library\CategoryRequest;
use App\Http\Requests\Library\ChapterRequest;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookChapter;
use App\Services\Library\CoverArt;

/** Simple content management for admins (route group is behind role:admin). Nothing is hard-deleted: items are disabled. */
class LibraryAdminController extends Controller
{
    public function index()
    {
        return view('library.admin.index', [
            'categories' => BookCategory::withCount('books')->orderBy('sort')->get(),
            'books' => Book::with('category')->withCount('chapters', 'readers')->orderByDesc('updated_at')->get(),
        ]);
    }

    public function storeCategory(CategoryRequest $request)
    {
        $cat = BookCategory::create($request->validated() + ['icon' => 'bi-book', 'sort' => (int) BookCategory::max('sort') + 1]);
        AuditLog::record('library.category.created', $cat, ['slug' => $cat->slug]);

        return back()->with('ok', 'Category “'.$cat->name.'” created.');
    }

    public function updateCategory(CategoryRequest $request, BookCategory $category)
    {
        $category->update(array_filter($request->validated(), fn ($v) => $v !== null) + ['icon' => $category->icon]);
        AuditLog::record('library.category.updated', $category, ['slug' => $category->slug]);

        return back()->with('ok', 'Category updated.');
    }

    public function toggleCategory(BookCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        AuditLog::record('library.category.'.($category->is_active ? 'enabled' : 'disabled'), $category, []);

        return back()->with('ok', $category->name.($category->is_active ? ' is visible again.' : ' is now hidden from readers.'));
    }

    public function createBook()
    {
        return view('library.admin.book-form', ['book' => new Book(['cover' => ['palette' => 'indigo', 'motif' => 'book'], 'language' => 'en', 'type' => 'book']), 'categories' => BookCategory::orderBy('sort')->get()]);
    }

    public function storeBook(BookRequest $request)
    {
        $book = new Book;
        $this->fillBook($book, $request);
        $book->forceFill(['is_active' => false])->save(); // new items start hidden until they have a chapter
        AuditLog::record('library.item.created', $book, ['slug' => $book->slug]);

        return redirect()->route('library.admin.books.edit', $book)->with('ok', 'Item created. Add a chapter, then enable it.');
    }

    public function editBook(Book $book)
    {
        return view('library.admin.book-form', ['book' => $book->load('chapters'), 'categories' => BookCategory::orderBy('sort')->get()]);
    }

    public function updateBook(BookRequest $request, Book $book)
    {
        $this->fillBook($book, $request);
        $book->save();
        AuditLog::record('library.item.updated', $book, ['slug' => $book->slug]);

        return redirect()->route('library.admin.books.edit', $book)->with('ok', 'Item saved.');
    }

    public function toggleBook(Book $book)
    {
        if (! $book->is_active && ! $book->chapters()->exists()) {
            return back()->withErrors(['queue' => 'Add at least one chapter before enabling this item.']);
        }
        $book->forceFill(['is_active' => ! $book->is_active])->save();
        AuditLog::record('library.item.'.($book->is_active ? 'enabled' : 'disabled'), $book, []);

        return back()->with('ok', '“'.$book->title.'” '.($book->is_active ? 'is live.' : 'is hidden from readers. Saved reading progress is kept.'));
    }

    public function storeChapter(ChapterRequest $request, Book $book)
    {
        abort_if($book->isComic(), 422, 'Comic pages are managed as seeded content.');
        $book->chapters()->create([
            'position' => (int) $book->chapters()->max('position') + 1,
            'title' => $request->validated('title'),
            'kind' => 'text',
            'body' => $request->validated('body'),
        ]);
        $book->recalculateStats();

        return back()->with('ok', 'Chapter added.');
    }

    public function updateChapter(ChapterRequest $request, Book $book, BookChapter $chapter)
    {
        abort_unless((int) $chapter->book_id === (int) $book->id, 404);
        abort_if($chapter->isComic(), 422, 'Comic pages are managed as seeded content.');
        $chapter->update($request->validated());
        $book->recalculateStats();

        return back()->with('ok', 'Chapter “'.$chapter->title.'” saved.');
    }

    private function fillBook(Book $book, BookRequest $request): void
    {
        $v = $request->validated();
        $book->fill([
            'title' => $v['title'], 'slug' => $v['slug'], 'author' => $v['author'],
            'book_category_id' => $v['book_category_id'], 'type' => $v['type'], 'language' => $v['language'],
            'description' => $v['description'] ?? null, 'license' => $v['license'],
            'cover' => ['palette' => $v['palette'], 'motif' => $v['motif']],
            'is_featured' => (bool) ($v['is_featured'] ?? false),
        ]);
    }
}
