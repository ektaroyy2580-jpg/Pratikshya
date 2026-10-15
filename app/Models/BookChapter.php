<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookChapter extends Model
{
    protected $fillable = ['position', 'title', 'kind', 'body', 'pages'];

    /** illustrations: [{path, alt, caption, after, placeholder, width, height, credit{title,author,license,license_url,source_url}}] — set by the seeder only. */
    protected $casts = ['pages' => 'array', 'illustrations' => 'array', 'position' => 'integer', 'word_count' => 'integer'];

    private ?array $segmentCache = null;

    /**
     * Blocks with every sentence numbered for the Listen mode:
     * [['type' => h|quote|p, 'lines' => [[[index, sentence], ...], ...]], ...]. Index is global within the chapter.
     */
    public function segments(): array
    {
        if ($this->segmentCache !== null) {
            return $this->segmentCache;
        }
        $i = 0;
        $out = [];
        foreach ($this->blocks() as [$type, $lines]) {
            $outLines = [];
            foreach ($lines as $line) {
                $outLines[] = array_map(function ($s) use (&$i) { return [$i++, $s]; }, \App\Services\Library\Sentences::split($line));
            }
            $out[] = ['type' => $type, 'lines' => $outLines];
        }

        return $this->segmentCache = $out;
    }

    /** Number of spoken sentences (text) — the valid range for the saved listening index. */
    public function sentenceCount(): int
    {
        if ($this->isComic()) {
            return collect(range(0, $this->pageCount() - 1))->sum(fn ($p) => $this->comicSpeechCount($p));
        }

        return collect($this->segments())->sum(fn ($b) => collect($b['lines'])->sum(fn ($l) => count($l)));
    }

    /** Spoken items on one comic page (captions + non-sound-effect bubbles, in panel order). */
    public function comicSpeechCount(int $page): int
    {
        $panels = $this->pages[$page]['panels'] ?? [];

        return collect($panels)->sum(fn ($p) => (trim((string) ($p['caption'] ?? '')) !== '' ? 1 : 0)
            + collect($p['bubbles'] ?? [])->filter(fn ($b) => ($b['kind'] ?? 'speech') !== 'sfx' && trim((string) ($b['text'] ?? '')) !== '')->count());
    }

    /** Illustrations grouped by the block index they follow (only those whose file exists locally). */
    public function illustrationsByBlock(): array
    {
        $out = [];
        foreach ((array) $this->illustrations as $ill) {
            if (! is_array($ill) || empty($ill['path']) || ! Book::publicFileExists($ill['path'])) {
                continue;
            }
            $out[(int) ($ill['after'] ?? 0)][] = $ill;
        }

        return $out;
    }

    protected static function booted(): void
    {
        static::saving(fn (BookChapter $c) => $c->word_count = Book::countWords($c->plainText()));
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function isComic(): bool
    {
        return $this->kind === 'comic';
    }

    public function pageCount(): int
    {
        return $this->isComic() ? max(1, count($this->pages ?? [])) : 1;
    }

    /**
     * Parse the plain-text body into blocks: ['h', [text]] | ['quote', lines] | ['p', lines].
     * Views escape everything, so admin-entered text can never inject HTML.
     */
    public function blocks(): array
    {
        $out = [];
        foreach (preg_split("/\n\s*\n/u", trim(str_replace("\r", '', (string) $this->body))) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (str_starts_with($chunk, '## ')) {
                $out[] = ['h', [trim(substr($chunk, 3))]];
            } elseif (str_starts_with($chunk, '>')) {
                $out[] = ['quote', array_map(fn ($l) => trim(preg_replace('/^>\s?/', '', $l)), explode("\n", $chunk))];
            } else {
                $out[] = ['p', array_map('trim', explode("\n", $chunk))];
            }
        }

        return $out;
    }

    /** All readable text in the chapter (for word counts), including comic captions and speech bubbles. */
    public function plainText(): string
    {
        if (! $this->isComic()) {
            return (string) $this->body;
        }

        return collect($this->pages ?? [])->flatMap(fn ($p) => $p['panels'] ?? [])
            ->map(fn ($panel) => trim(($panel['caption'] ?? '').' '.collect($panel['bubbles'] ?? [])->pluck('text')->implode(' ')))
            ->implode(' ');
    }
}
