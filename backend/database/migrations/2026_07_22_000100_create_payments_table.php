<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Who paid, and for what. Polymorphic because a payment is either a
            // package booking or an agency subscription, and both need the same
            // gateway plumbing, receipts and history.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');

            $table->string('gateway', 32);                 // sandbox|jazzcash|easypaisa|stripe
            $table->string('reference', 64)->unique();     // ours — what the user sees
            $table->string('gateway_reference')->nullable(); // theirs, once known

            // PKR has no subunit in practice and the rest of the schema already
            // stores whole rupees, so integers stay consistent and exact.
            $table->unsignedInteger('amount');
            $table->unsignedInteger('commission')->default(0);
            $table->unsignedInteger('net_amount')->default(0);
            // Rate is stored per payment: changing the platform's cut later must
            // not rewrite what past agencies were actually charged.
            $table->decimal('commission_rate', 5, 4)->default(0);

            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])
                ->default('pending');
            $table->string('failure_reason')->nullable();

            // Whatever the gateway sent back, kept verbatim for disputes.
            $table->json('gateway_payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
