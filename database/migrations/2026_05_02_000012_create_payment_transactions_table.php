<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('provider');                        // paypal | bkash | manual | dev
            $table->string('provider_txn_id')->nullable();     // their reference id
            $table->string('local_ref', 32)->unique();         // our reference (in URLs)

            $table->string('plan');
            $table->string('billing_cycle')->default('monthly');
            $table->unsignedInteger('amount');                 // smallest unit
            $table->string('currency', 8)->default('BDT');

            $table->string('status')->default('pending');      // pending | completed | failed | refunded
            $table->json('raw_payload')->nullable();           // last provider response for audit

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['provider', 'provider_txn_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
