<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Who sees it.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Who caused it (null for system/automated ones).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 32);

            // What it's about, so a click can go straight there. Polymorphic
            // because the subject is a post, a booking, a conversation or a trip
            // depending on type.
            $table->nullableMorphs('subject');

            // A denormalised snapshot — actor name/avatar, a snippet, the link.
            // Kept on the row so the feed renders without joining four tables,
            // and so it still reads correctly if the subject is later deleted.
            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The two queries that run constantly: a user's feed, and their
            // unread count per category.
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
