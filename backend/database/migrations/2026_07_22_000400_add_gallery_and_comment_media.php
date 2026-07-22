<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posts gain a media gallery (several photos/GIFs/videos per post) and comments
 * gain a single attachment, so people can reply with a photo or a GIF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            // [{ url, type }, …] — media_url/media_type stay as the first item
            // so existing readers keep working.
            $table->json('gallery')->nullable()->after('media_type');
        });

        // Backfill: an existing single image becomes a one-item gallery.
        DB::table('community_posts')
            ->whereNotNull('media_url')
            ->orderBy('id')
            ->chunkById(200, function ($posts) {
                foreach ($posts as $post) {
                    DB::table('community_posts')->where('id', $post->id)->update([
                        'gallery' => json_encode([[
                            'url'  => $post->media_url,
                            'type' => $post->media_type ?: 'image',
                        ]]),
                    ]);
                }
            });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->string('media_url')->nullable()->after('body');
            $table->enum('media_type', ['image', 'gif'])->nullable()->after('media_url');
        });

        // A comment may now be just an image or GIF, so text can't be required.
        DB::statement('ALTER TABLE post_comments MODIFY COLUMN body TEXT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE post_comments SET body = '' WHERE body IS NULL");
        DB::statement('ALTER TABLE post_comments MODIFY COLUMN body TEXT NOT NULL');

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type']);
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('gallery');
        });
    }
};
