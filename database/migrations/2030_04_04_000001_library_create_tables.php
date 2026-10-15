<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Library (module: library). Reading progress is keyed by user + book — never by ticket/queue —
 * so "continue reading" survives leaving one queue and joining another. Nothing here touches queue order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('name', 80);
            $t->string('name_ne', 80)->nullable();
            $t->string('kind', 20)->default('genre');          // genre | periodical (news/blogs/articles)
            $t->string('icon', 40)->default('bi-book');
            $t->string('description', 255)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['is_active', 'sort']);
        });

        Schema::create('books', function (Blueprint $t) {
            $t->id();
            $t->foreignId('book_category_id')->constrained('book_categories')->restrictOnDelete();
            $t->string('slug', 120)->unique();
            $t->string('title', 160);
            $t->string('author', 160);
            $t->string('type', 12)->default('book');           // book | comic | article | news | blog
            $t->text('description')->nullable();
            $t->string('language', 5)->default('en');          // en | ne
            $t->string('license', 255);                        // licence / source line, required on every item
            $t->json('cover')->nullable();                     // {palette, motif} → generated SVG cover (no external images)
            $t->string('reading_direction', 3)->default('ltr'); // ltr | rtl (manga-style comics)
            $t->unsignedInteger('word_count')->default(0);
            $t->unsignedSmallInteger('reading_minutes')->default(1);
            $t->boolean('is_featured')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['is_active', 'type']);
            $t->index(['book_category_id', 'is_active']);
        });

        Schema::create('book_chapters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->string('title', 160);
            $t->string('kind', 10)->default('text');           // text | comic
            $t->longText('body')->nullable();                  // plain text (paragraphs), rendered escaped
            $t->json('pages')->nullable();                     // comic: [[panel, panel...], ...] drawn as inline SVG
            $t->unsignedInteger('word_count')->default(0);
            $t->timestamps();
            $t->unique(['book_id', 'position']);
        });

        Schema::create('reading_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_chapter_id')->nullable()->constrained('book_chapters')->nullOnDelete();
            $t->decimal('position', 5, 2)->default(0);         // % scrolled within the chapter (text)
            $t->unsignedSmallInteger('page_index')->nullable(); // comic page index within the chapter
            $t->decimal('overall_percent', 5, 2)->default(0);
            $t->boolean('completed')->default(false);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('last_read_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->boolean('hidden_from_history')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'book_id']);
            $t->index(['user_id', 'last_read_at']);
        });

        Schema::create('book_bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_chapter_id')->constrained('book_chapters')->cascadeOnDelete();
            $t->decimal('position', 5, 2)->default(0);
            $t->unsignedSmallInteger('page_index')->nullable();
            $t->string('note', 280)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'book_id']);
        });

        Schema::create('book_favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_favorites');
        Schema::dropIfExists('book_bookmarks');
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('book_chapters');
        Schema::dropIfExists('books');
        Schema::dropIfExists('book_categories');
    }
};
