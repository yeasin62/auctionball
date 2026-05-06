<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plan');                            // free | starter | pro | enterprise
            $table->string('status')->default('active');       // active | past_due | canceled | expired
            $table->string('provider')->nullable();            // paypal | bkash | manual | dev
            $table->string('provider_subscription_id')->nullable();
            $table->unsignedInteger('amount')->default(0);     // in smallest unit (paisa for BDT, cents for USD)
            $table->string('currency', 8)->default('BDT');
            $table->string('billing_cycle')->default('monthly'); // monthly | yearly | lifetime
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
