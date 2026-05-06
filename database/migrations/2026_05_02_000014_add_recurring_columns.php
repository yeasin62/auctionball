<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // True when the provider holds a stored agreement / subscription / saved card
            // and we can charge them server-side without redirecting the customer.
            $table->boolean('is_recurring')->default(false)->after('auto_renew');

            // T-7/T-3/T-1 reminders — last day-count we sent so we don't double-send.
            $table->timestamp('last_reminder_sent_at')->nullable()->after('grace_until');
            $table->unsignedSmallInteger('last_reminder_days_before')->nullable()->after('last_reminder_sent_at');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            // Marks a checkout that established a recurring agreement (vs a one-off).
            $table->boolean('is_recurring_setup')->default(false)->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'last_reminder_sent_at', 'last_reminder_days_before']);
        });
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('is_recurring_setup');
        });
    }
};
