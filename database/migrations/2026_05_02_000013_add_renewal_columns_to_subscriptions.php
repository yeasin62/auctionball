<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('billing_cycle');
            $table->unsignedSmallInteger('renewal_attempts')->default(0)->after('auto_renew');
            $table->timestamp('last_attempt_at')->nullable()->after('renewal_attempts');
            $table->timestamp('next_attempt_at')->nullable()->after('last_attempt_at');
            $table->timestamp('grace_until')->nullable()->after('next_attempt_at');
            $table->string('last_failure_reason')->nullable()->after('grace_until');

            // Indexes for the renewal job query
            $table->index(['status', 'auto_renew', 'current_period_end']);
            $table->index('next_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'auto_renew', 'current_period_end']);
            $table->dropIndex(['next_attempt_at']);
            $table->dropColumn([
                'auto_renew', 'renewal_attempts', 'last_attempt_at',
                'next_attempt_at', 'grace_until', 'last_failure_reason',
            ]);
        });
    }
};
