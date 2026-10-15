<?php

namespace App\Services\Library;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reading progress is stored per user + book. It never reads or writes tickets, so reading can
 * never affect queue position, and the same place is restored in any queue, on any device.
 */
class ReadingProgressService
{
    public function find(User $user, Book $book): ?ReadingProgress
    {
        return ReadingProgress::where('user_id', $user->id)->where('book_id', $book->id)->first();
    }

    /**
     * Save the exact reading position. $position = % scrolled within a text chapter (0–100);
     * $pageIndex = zero-based page for comic chapters.
     * $mode = 'listen' saves the audiobook place too: $listenIndex = sentence within the chapter (text)
     * or spoken item within the page (comics). Out-of-range indices are rejected, never clamped silently.
     */
    public function save(User $user, Book $book, BookChapter $chapter, float $position, ?int $pageIndex = null, ?int $listenIndex = null, string $mode = 'read'): ReadingProgress
    {
        if ((int) $chapter->book_id !== (int) $book->id) {
            throw ValidationException::withMessages(['chapter_id' => 'That chapter does not belong to this item.']);
        }

        $position = round(min(100, max(0, $position)), 2);
        if ($chapter->isComic()) {
            $pageIndex = min(max(0, (int) $pageIndex), $chapter->pageCount() - 1);
            $fraction = ($pageIndex + 1) / $chapter->pageCount();
            $position = round($fraction * 100, 2);
        } else {
            $pageIndex = null;
            $fraction = $position / 100;
        }

        $mode = $mode === 'listen' && $listenIndex !== null ? 'listen' : 'read';
        if ($mode === 'listen') {
            $max = $chapter->isComic() ? $chapter->comicSpeechCount((int) $pageIndex) : $chapter->sentenceCount();
            if ($listenIndex < 0 || $listenIndex >= max(1, $max)) {
                throw ValidationException::withMessages(['listen_sentence_index' => 'That sentence is not in this chapter.']);
            }
        }

        $positions = $book->chapters()->pluck('position')->values();
        $count = max(1, $positions->count());
        $index = max(0, (int) $positions->search($chapter->position));
        $overall = round(min(100, ($index + $fraction) / $count * 100), 2);
        $reachedEnd = $index === $count - 1 && $fraction >= 0.97;

        $attempt = function () use ($user, $book, $chapter, $position, $pageIndex, $overall, $reachedEnd, $mode, $listenIndex) {
            return DB::transaction(function () use ($user, $book, $chapter, $position, $pageIndex, $overall, $reachedEnd, $mode, $listenIndex) {
                $p = ReadingProgress::where('user_id', $user->id)->where('book_id', $book->id)->lockForUpdate()->first()
                    ?? new ReadingProgress;
                $now = now();
                // Reading (scrolling) keeps the last listening sentence only while it is still in the same chapter.
                $keepListen = (int) $p->book_chapter_id === (int) $chapter->id ? $p->listen_sentence_index : null;
                $p->forceFill([
                    'mode' => $mode,
                    'listen_sentence_index' => $mode === 'listen' ? $listenIndex : $keepListen,
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'book_chapter_id' => $chapter->id,
                    'position' => $position,
                    'page_index' => $pageIndex,
                    'overall_percent' => $reachedEnd ? 100 : $overall,
                    'completed' => $reachedEnd,
                    'completed_at' => $reachedEnd ? ($p->completed_at ?? $now) : $p->completed_at,
                    'started_at' => $p->started_at ?? $now,
                    'last_read_at' => $now,
                    'hidden_from_history' => false,
                ])->save();

                return $p;
            });
        };

        try {
            return $attempt();
        } catch (QueryException $e) {
            // Two tabs saving the first position at the same instant: the unique (user, book) key wins, retry as update.
            return $attempt();
        }
    }

    /** Most recent unfinished item for the "Continue reading where you left off" card. */
    public function continueFor(User $user): ?ReadingProgress
    {
        return $this->visible($user)->where('completed', false)->where('hidden_from_history', false)->first();
    }

    /** Reading history, newest first (derived from progress rows). */
    public function history(User $user, int $limit = 30): Collection
    {
        return $this->visible($user)->where('hidden_from_history', false)->limit($limit)->get();
    }

    /** Hide an item from history without deleting the saved place (user-initiated, reversible by reading again). */
    public function hideFromHistory(User $user, Book $book): bool
    {
        return (bool) ReadingProgress::where('user_id', $user->id)->where('book_id', $book->id)
            ->update(['hidden_from_history' => true]);
    }

    private function visible(User $user)
    {
        return ReadingProgress::with(['book.category', 'chapter'])
            ->where('user_id', $user->id)
            ->whereNotNull('last_read_at')
            ->whereHas('book', fn ($b) => $b->active())
            ->orderByDesc('last_read_at')->orderByDesc('id');
    }
}
