<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens community posts from "text + one image" to a richer Instagram-style
 * post: more categories, image/video/GIF media, and an optional music track.
 */
return new class extends Migration
{
    private const TYPES_NEW = "'story','tip','review','announcement','companion','question','alert','gear','budget','safety'";
    private const TYPES_OLD = "'story','tip','review','announcement'";

    public function up(): void
    {
        // MySQL enums can't be altered through the schema builder without doctrine/dbal.
        DB::statement("ALTER TABLE community_posts MODIFY COLUMN type ENUM(" . self::TYPES_NEW . ") NOT NULL DEFAULT 'story'");

        Schema::table('community_posts', function (Blueprint $table) {
            // Replaces `image`. Holds a stored path OR an external URL (a Giphy
            // GIF is hotlinked rather than copied onto our disk).
            $table->string('media_url')->nullable()->after('body');
            $table->enum('media_type', ['image', 'video', 'gif'])->nullable()->after('media_url');

            // Track chosen from the royalty-free catalogue:
            // { provider, id, title, artist, audio_url, cover }
            $table->json('audio')->nullable()->after('media_type');
        });

        // Carry existing images across so nothing loses its picture.
        DB::statement("UPDATE community_posts SET media_url = image, media_type = 'image' WHERE image IS NOT NULL AND image <> ''");

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->string('image')->nullable()->after('body');
        });

        DB::statement("UPDATE community_posts SET image = media_url WHERE media_type = 'image'");

        // Anything using a new category falls back to a story before the enum shrinks.
        DB::statement("UPDATE community_posts SET type = 'story' WHERE type NOT IN (" . self::TYPES_OLD . ")");
        DB::statement("ALTER TABLE community_posts MODIFY COLUMN type ENUM(" . self::TYPES_OLD . ") NOT NULL DEFAULT 'story'");

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type', 'audio']);
        });
    }
};
