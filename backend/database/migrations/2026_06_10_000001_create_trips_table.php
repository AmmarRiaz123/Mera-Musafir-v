<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('max_travelers');
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->enum('type', ['adventure', 'cultural', 'budget', 'luxury', 'backpacking']);
            $table->enum('visibility', ['public', 'women_only', 'invite_only'])->default('public');
            $table->enum('status', ['planning', 'active', 'completed', 'archived'])->default('planning');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trip_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'joined', 'declined', 'left'])->default('pending');
            $table->enum('role', ['host', 'member'])->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_members');
        Schema::dropIfExists('trips');
    }
};