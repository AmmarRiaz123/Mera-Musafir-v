<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();

            $table->enum('tier', ['pro', 'premium']);      // basic is free, never a subscription
            $table->enum('period', ['monthly', 'yearly']);
            $table->unsignedInteger('amount');

            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])
                ->default('pending');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_subscriptions');
    }
};
