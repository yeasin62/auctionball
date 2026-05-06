<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Mirror of the player public-registration controls — allows
            // captains/owners to self-submit teams. Admin reviews + approves.
            $table->boolean('team_registration_open')->default(false)->after('registration_form_schema');
            $table->string('team_registration_token', 32)->nullable()->unique()->after('team_registration_open');
            $table->unsignedInteger('team_registration_fee')->default(0)->after('team_registration_token');
            $table->text('team_registration_instructions')->nullable()->after('team_registration_fee');
        });

        Schema::table('teams', function (Blueprint $table) {
            // Public form fields — `owner_user_id` is the optional User pivot for
            // login bidding; `owner_name` is the human name from the registration.
            $table->string('owner_name')->nullable()->after('logo_url');
            // pending = awaiting admin review; approved = active. Admin-created
            // teams default to approved (TeamController::store overrides).
            $table->string('registration_status')->default('approved')->after('owner_name');
            $table->string('registration_txn_id')->nullable()->after('registration_status');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['team_registration_open', 'team_registration_token', 'team_registration_fee', 'team_registration_instructions']);
        });
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'registration_status', 'registration_txn_id']);
        });
    }
};
