<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Lets a public or women-only trip still vet each joiner. Invite-only
            // trips always require approval regardless of this flag.
            $table->boolean('requires_approval')->default(false)->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }
};
