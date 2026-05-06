<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /** Public list of event slugs we expose to the filter UI. */
    public const EVENT_TYPES = [
        'auction.sold'          => ['label_en' => 'Sale completed',     'label_bn' => 'বিক্রি সম্পন্ন',  'icon' => 'sold'],
        'auction.unsold'        => ['label_en' => 'Marked unsold',      'label_bn' => 'অবিক্রিত করা',   'icon' => 'unsold'],
        'auction.reset'         => ['label_en' => 'Lot reset',          'label_bn' => 'লট রিসেট',       'icon' => 'reset'],
        'plan.changed'          => ['label_en' => 'Plan changed',       'label_bn' => 'প্ল্যান পরিবর্তন', 'icon' => 'plan'],
        'user.impersonated'     => ['label_en' => 'Impersonation',      'label_bn' => 'ইম্পার্সোনেট',    'icon' => 'shield'],
        'payment.completed'     => ['label_en' => 'Payment completed',  'label_bn' => 'পেমেন্ট সম্পন্ন', 'icon' => 'card'],
        'invitation.accepted'   => ['label_en' => 'Invite accepted',    'label_bn' => 'আমন্ত্রণ গৃহীত', 'icon' => 'user'],
    ];

    public function index(Request $request): Response
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        $filters = $request->validate([
            'q'        => 'nullable|string|max:100',
            'event'    => 'nullable|string|max:64',
            'date_from'=> 'nullable|date',
            'date_to'  => 'nullable|date',
        ]);

        $query = AuditLog::where('organization_id', $org->id)
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($q = $filters['q'] ?? null) {
            $query->where(function ($q1) use ($q) {
                $q1->where('summary', 'like', "%{$q}%")
                   ->orWhere('actor_name', 'like', "%{$q}%");
            });
        }
        if ($e = $filters['event'] ?? null)        $query->where('event', $e);
        if ($from = $filters['date_from'] ?? null) $query->where('created_at', '>=', $from);
        if ($to = $filters['date_to'] ?? null)     $query->where('created_at', '<=', $to . ' 23:59:59');

        $logs = $query->paginate(50)->withQueryString();

        // Counts per event for the filter chips (cached briefly to avoid recomputing on every page hit)
        $cacheKey = "audit:counts:{$org->id}";
        $counts = cache()->remember($cacheKey, 30, fn () =>
            AuditLog::where('organization_id', $org->id)
                ->selectRaw('event, COUNT(*) as c')
                ->groupBy('event')->pluck('c', 'event')->all()
        );

        return Inertia::render('Dashboard/Audit/Index', [
            'logs'    => $logs,
            'filters' => $filters,
            'eventTypes' => self::EVENT_TYPES,
            'counts'  => $counts,
        ]);
    }
}
