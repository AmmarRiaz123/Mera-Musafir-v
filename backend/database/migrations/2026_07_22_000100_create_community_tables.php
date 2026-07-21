<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Posts may be tied to a place ("tips for Hunza") or be general.
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['story', 'tip', 'review', 'announcement'])->default('story');
            $table->text('body');
            $table->string('image')->nullable();

            // Denormalised counters — the feed sorts on these, so recomputing
            // them per request would mean a COUNT per post per page.
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);

            // Moderation: hidden posts disappear from every feed.
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_hidden')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['destination_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One like per person per post.
            $table->unique(['community_post_id', 'user_id']);
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['community_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('community_posts');
    }
};
