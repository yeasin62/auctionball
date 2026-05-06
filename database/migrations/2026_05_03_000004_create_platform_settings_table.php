<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            // bKash manual-payment block — these surface on the customer's
            // checkout modal when they pick bKash on /dashboard/billing.
            $table->string('bkash_merchant_number', 32)->nullable();
            $table->string('bkash_account_type', 32)->default('Personal'); // Personal | Merchant | "Send Money"
            $table->text('bkash_instructions')->nullable();
            $table->unsignedSmallInteger('manual_review_hours')->default(6);
            $table->timestamps();
        });

        DB::table('platform_settings')->insert([
            'bkash_merchant_number' => '01XXXXXXXXX',
            'bkash_account_type'    => 'Personal',
            'bkash_instructions'    => "Send Money to the number above. After payment, paste your bKash transaction ID below — we verify within 6 hours and activate your plan.",
            'manual_review_hours'   => 6,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
