<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agencies vet before money changes hands, so a booking needs a state
        // between "asked" and "paid for": approved, and waiting to be paid.
        // Raw SQL because altering an enum needs doctrine/dbal otherwise.
        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','approved','confirmed','cancelled','completed')
             NOT NULL DEFAULT 'pending'"
        );

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('confirmed_at');
            // An approved seat can't be held forever — it's capacity nobody paid for.
            $table->timestamp('payment_due_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'payment_due_at']);
        });

        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled','completed')
             NOT NULL DEFAULT 'pending'"
        );
    }
};
