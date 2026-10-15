<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per user + book — never per ticket or queue — so a reader can leave one queue,
 * join another and continue at the exact same place. Only ReadingProgressService writes it.
 */
class ReadingProgress extends Model
{
    protected $table = 'reading_progress';

    protected $fillable = [];

    protected $casts = [
        'position' => 'float',
        'overall_percent' => 'float',
        'page_index' => 'integer',
        'listen_sentence_index' => 'integer',
        'completed' => 'boolean',
        'hidden_from_history' => 'boolean',
        'started_at' => 'datetime',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function chapter()
    {
        return $this->belongsTo(BookChapter::class, 'book_chapter_id');
    }

    /** Exact place, e.g. "Chapter 2 · The Fire — 45% through" or "Chapter 1 · Market Day — page 3 of 5". */
    public function placeLabel(): string
    {
        $ch = $this->chapter;
        if (! $ch) {
            return 'Not started yet';
        }
        $where = 'Chapter '.$ch->position.' · '.$ch->title;
        if ($this->isListening() && ! $ch->isComic()) {
            return $where.' — listening, sentence '.($this->listen_sentence_index + 1).' of '.max(1, $ch->sentenceCount());
        }
        if ($ch->isComic()) {
            return $where.' — page '.(($this->page_index ?? 0) + 1).' of '.$ch->pageCount();
        }

        return $where.' — '.(int) round($this->position).'% through';
    }

    /** The last session was in audiobook mode and we know the exact sentence. */
    public function isListening(): bool
    {
        return $this->mode === 'listen' && $this->listen_sentence_index !== null;
    }

    /** Reader URL that lands on the exact saved place (and reopens the Listen bar if they were listening). */
    public function resumeUrl(): string
    {
        return route('library.read', array_filter([
            'book' => $this->book,
            'chapter' => $this->chapter?->position,
            'listen' => $this->isListening() ? 1 : null,
        ]));
    }
}
