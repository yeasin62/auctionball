<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Customer's own bKash number — collected on the manual bKash flow
            // so super admin can cross-check the sender against the receipt.
            $table->string('sender_bkash_number', 32)->nullable()->after('provider_txn_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('sender_bkash_number');
        });
    }
};
