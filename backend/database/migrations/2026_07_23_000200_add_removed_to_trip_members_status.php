<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A host-removed member is distinct from one who left on their own: a
        // kick has to be sticky, so rejoining needs host approval. Raw SQL —
        // altering an enum needs doctrine/dbal otherwise.
        DB::statement(
            "ALTER TABLE trip_members MODIFY COLUMN status
             ENUM('pending','joined','declined','left','removed')
             NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        // Fold any removed rows back to 'left' before shrinking the enum.
        DB::table('trip_members')->where('status', 'removed')->update(['status' => 'left']);

        DB::statement(
            "ALTER TABLE trip_members MODIFY COLUMN status
             ENUM('pending','joined','declined','left')
             NOT NULL DEFAULT 'pending'"
        );
    }
};
