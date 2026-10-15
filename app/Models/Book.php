<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    public const TYPES = ['book', 'comic', 'article', 'news', 'blog'];

    public const LANGUAGES = ['en' => 'English', 'ne' => 'नेपाली'];

    /** Average silent reading speed used for the estimate (words per minute). */
    public const WPM = 200;

    /** Average text-to-speech speed at 1× (words per minute). */
    public const LISTEN_WPM = 150;

    protected $fillable = [
        'book_category_id', 'slug', 'title', 'author', 'type', 'description', 'language', 'license',
        'cover', 'reading_direction', 'is_featured', 'is_active',
    ];

    protected $casts = [
        'cover' => 'array',
        'image_credit' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'word_count' => 'integer',
        'reading_minutes' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category()
    {
        return $this->belongsTo(BookCategory::class, 'book_category_id');
    }

    public function chapters()
    {
        return $this->hasMany(BookChapter::class)->orderBy('position');
    }

    /** Progress rows (one per reader) — used only for aggregate "popular" counts. */
    public function readers()
    {
        return $this->hasMany(ReadingProgress::class);
    }

    /** Visible to readers: the item and its category are both enabled. */
    public function scopeActive($q)
    {
        return $q->where('books.is_active', true)
            ->whereHas('category', fn ($c) => $c->where('is_active', true));
    }

    /** Items with a full "Listen" (audiobook) mode: every text item. Comics get "Read aloud" of their bubbles instead. */
    public function scopeAudiobooks($q)
    {
        return $q->where('books.type', '!=', 'comic');
    }

    public function isVisible(): bool
    {
        return $this->is_active && ($this->category?->is_active ?? false);
    }

    public function isComic(): bool
    {
        return $this->type === 'comic';
    }

    public function isAudiobook(): bool
    {
        return ! $this->isComic();
    }

    public function isRtl(): bool
    {
        return $this->reading_direction === 'rtl';
    }

    public function typeLabel(): string
    {
        return ['book' => 'Book', 'comic' => 'Comic', 'article' => 'Article', 'news' => 'News', 'blog' => 'Blog'][$this->type] ?? 'Book';
    }

    public function typeIcon(): string
    {
        return ['book' => 'bi-book', 'comic' => 'bi-chat-square-quote', 'article' => 'bi-file-earmark-text', 'news' => 'bi-newspaper', 'blog' => 'bi-pencil-square'][$this->type] ?? 'bi-book';
    }

    public function readingTimeLabel(): string
    {
        $m = max(1, (int) $this->reading_minutes);

        return $m < 60 ? $m.' min read' : intdiv($m, 60).' h '.($m % 60).' min read';
    }

    public function listeningMinutes(): int
    {
        return max(1, (int) ceil((int) $this->word_count / self::LISTEN_WPM));
    }

    public function listeningTimeLabel(): string
    {
        $m = $this->listeningMinutes();

        return $m < 60 ? '~'.$m.' min listen' : '~'.intdiv($m, 60).' h '.($m % 60).' min listen';
    }

    // ------------------------------------------------------------------ images

    /** True only when a cover photo is set AND the file is actually on disk (otherwise the SVG cover is used). */
    public function hasImage(): bool
    {
        return $this->image_path && self::publicFileExists($this->image_path);
    }

    public function imageUrl(bool $small = false): ?string
    {
        if (! $this->hasImage()) {
            return null;
        }
        $path = $small && $this->image_path_sm && self::publicFileExists($this->image_path_sm) ? $this->image_path_sm : $this->image_path;

        return asset($path);
    }

    /** Only data:image/jpeg|png;base64 placeholders are allowed into a style attribute. */
    public function safePlaceholder(): ?string
    {
        return self::cleanPlaceholder($this->image_placeholder);
    }

    public static function cleanPlaceholder(?string $p): ?string
    {
        return $p && preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $p) ? $p : null;
    }

    /** Paths are relative to public/ and must stay inside public/images/library. */
    public static function publicFileExists(?string $path): bool
    {
        static $cache = [];
        if (! $path || ! preg_match('#^images/library/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$#', $path)) {
            return false;
        }

        return $cache[$path] ??= is_file(public_path($path));
    }

    /** One-line attribution, e.g. "Photo: Krish Dulal · CC BY-SA 3.0 · Wikimedia Commons". */
    public static function creditLine(?array $credit): ?string
    {
        if (! $credit || empty($credit['license'])) {
            return null;
        }

        return trim(($credit['author'] ?? 'Unknown').' · '.$credit['license'].' · Wikimedia Commons');
    }

    public function recalculateStats(): void
    {
        $chapters = $this->chapters()->get();
        $words = (int) $chapters->sum('word_count');
        $panels = $chapters->sum(fn ($c) => collect($c->pages ?? [])->sum(fn ($p) => count($p['panels'] ?? [])));
        // Comics: ~4 seconds of looking per panel on top of the words in the speech bubbles.
        $minutes = (int) ceil($words / self::WPM + $panels * 4 / 60);
        $this->forceFill(['word_count' => $words, 'reading_minutes' => max(1, $minutes)])->save();
    }

    public static function countWords(?string $text): int
    {
        $text = trim((string) $text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }
}
