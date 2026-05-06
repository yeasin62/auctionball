<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Tenant-scoped — but nullable so platform-level events (super-admin actions
            // not bound to a single org, e.g. global plan price changes) can be recorded.
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            // The user who triggered the event. Nullable so system jobs (renewal cron,
            // webhook callbacks) can still log without a user.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Cached display name — survives user deletion so old rows stay readable.
            $table->string('actor_name')->nullable();

            // Event slug, e.g. "auction.sold", "plan.changed", "user.impersonated".
            $table->string('event', 64);

            // Polymorphic-ish: lets a row point at a Player, Team, Subscription, Invitation, etc.
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Pre-rendered human summary so the audit page doesn't re-derive on every read.
            $table->string('summary');

            // Free-form JSON for the diff / numbers / IDs we may want to inspect later.
            $table->json('payload')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['event']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
