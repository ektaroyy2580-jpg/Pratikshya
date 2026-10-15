<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Library (module: library) — media + audiobook listening. Adds columns only.
 *  - books: a locally stored, freely licensed cover photo/illustration with its attribution
 *    (the generated SVG cover stays as the fallback when image_path is null or the file is missing).
 *  - book_chapters: optional in-chapter illustrations (JSON list, each with its own credit).
 *  - reading_progress: where the listener is (sentence index within the saved chapter) and the last mode used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $t) {
            $t->string('image_path', 190)->nullable();        // relative to public/, e.g. images/library/<slug>.jpg
            $t->string('image_path_sm', 190)->nullable();     // smaller variant for cards
            $t->string('image_alt', 255)->nullable();
            $t->text('image_placeholder')->nullable();        // tiny base64 JPEG for blur-up
            $t->json('image_credit')->nullable();             // {title, author, license, license_url, source_url}
        });

        Schema::table('book_chapters', function (Blueprint $t) {
            $t->json('illustrations')->nullable();            // [{path, alt, caption, after, placeholder, width, height, credit{}}]
        });

        Schema::table('reading_progress', function (Blueprint $t) {
            $t->unsignedInteger('listen_sentence_index')->nullable(); // sentence within book_chapter_id (audiobook mode)
            $t->string('mode', 8)->default('read');                  // read | listen (last mode used)
        });
    }

    public function down(): void
    {
        Schema::table('reading_progress', fn (Blueprint $t) => $t->dropColumn(['listen_sentence_index', 'mode']));
        Schema::table('book_chapters', fn (Blueprint $t) => $t->dropColumn('illustrations'));
        Schema::table('books', fn (Blueprint $t) => $t->dropColumn(['image_path', 'image_path_sm', 'image_alt', 'image_placeholder', 'image_credit']));
    }
};
