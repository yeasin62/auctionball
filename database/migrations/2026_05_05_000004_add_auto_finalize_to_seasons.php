<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // When ON: server auto-marks the lot SOLD (if a bidder exists) or
            // UNSOLD (if no bids) the moment the timer expires. When OFF: status
            // remains 'running' past timer_end and the auctioneer must click
            // sold/unsold themselves. Default true matches the natural live-
            // auction expectation; admin can flip it on the control panel.
            $table->boolean('auto_finalize')->default(true)->after('bid_increment');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('auto_finalize');
        });
    }
};
