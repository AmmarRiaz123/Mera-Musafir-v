<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->enum('type', ['traveler', 'agency', 'admin'])->default('traveler');
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('city')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->decimal('reputation_score', 5, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};