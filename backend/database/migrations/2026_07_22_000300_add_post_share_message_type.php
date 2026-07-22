<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a community post be shared into a DM as its own message type, so the
 * chat can render a preview card instead of a bare link.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE conversation_messages MODIFY COLUMN type ENUM('text','trip_invite','post_share') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        // Anything already shared becomes a plain text message before the enum shrinks.
        DB::statement("UPDATE conversation_messages SET type = 'text' WHERE type = 'post_share'");
        DB::statement("ALTER TABLE conversation_messages MODIFY COLUMN type ENUM('text','trip_invite') NOT NULL DEFAULT 'text'");
    }
};
