<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visitor_events', function (Blueprint $t) {
            // One row per debounced page-hit. Used to compute:
            //   - real-time visitor count (distinct session_id in last 5 min)
            //   - total unique visitors (all-time / daily distinct session_id)
            //   - top pages (group by path)
            //
            // To keep volume manageable, the RecordVisitor middleware debounces
            // identical session+path hits to once per 30 seconds.
            $t->id();
            // sha256(session_id) — never store the raw session id.
            $t->string('session_id', 64)->index();
            // sha256(ip + APP_KEY) — anonymised fallback / abuse tracing.
            $t->string('ip_hash', 64)->nullable()->index();
            $t->string('path', 255);
            $t->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_events');
    }
};
