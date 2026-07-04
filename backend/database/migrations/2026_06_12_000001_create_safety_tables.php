<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('reported_id');
            $table->string('reported_type');
            $table->enum('reason', ['spam', 'harassment', 'inappropriate_content', 'fake_profile', 'scam', 'other']);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'actioned', 'dismissed'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('blocked_users');
    }
};
