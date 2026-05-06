<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Append-only audit recorder. Failures swallow + log_error so a misbehaving
 * audit row can never break the actual business action it's tracking.
 */
class Audit
{
    /**
     * @param string      $event    slug, e.g. "auction.sold"
     * @param string      $summary  one-line human-readable description
     * @param array       $payload  any extra metadata for inspection
     * @param Model|null  $subject  optional target row (Player, Team, Subscription, …)
     * @param int|null    $orgId    overrides the actor's current org (rare — for super-admin actions)
     */
    public static function log(
        string $event,
        string $summary,
        array $payload = [],
        ?Model $subject = null,
        ?int $orgId = null,
    ): ?AuditLog {
        try {
            $user = Auth::user();
            $request = app(\Illuminate\Http\Request::class);
            $resolvedOrg = $orgId ?? session('current_organization_id');

            return AuditLog::create([
                'organization_id' => $resolvedOrg,
                'user_id'         => $user?->id,
                'actor_name'      => $user?->name ?? 'System',
                'event'           => $event,
                'subject_type'    => $subject ? class_basename($subject) : null,
                'subject_id'      => $subject?->getKey(),
                'summary'         => mb_substr($summary, 0, 250),
                'payload'         => $payload ?: null,
                'ip_address'      => $request?->ip(),
                'user_agent'      => mb_substr($request?->userAgent() ?? '', 0, 255),
            ]);
        } catch (Throwable $e) {
            // Never let an audit-row failure cascade into the actual action it's auditing.
            \Log::warning('audit.log_failed', ['event' => $event, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
