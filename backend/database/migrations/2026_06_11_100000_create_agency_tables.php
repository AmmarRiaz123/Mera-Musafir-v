<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('tier', ['basic', 'pro', 'premium'])->default('basic');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('license_number')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->unsignedInteger('follower_count')->default(0);
            $table->unsignedInteger('total_trips')->default(0);
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('agency_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->unsignedInteger('price_per_person');
            $table->unsignedInteger('max_capacity');
            $table->unsignedInteger('booked_count')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('duration_days');
            $table->json('includes')->nullable();
            $table->json('itinerary_overview')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->enum('type', ['day_trip', 'weekend', 'extended', 'custom']);
            $table->enum('status', ['draft', 'published', 'closed', 'archived'])->default('draft');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agency_package_id')->constrained('agency_packages')->cascadeOnDelete();
            $table->unsignedInteger('travelers_count')->default(1);
            $table->unsignedInteger('total_amount');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['agency_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_followers');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('agency_packages');
        Schema::dropIfExists('agencies');
    }
};
