<?php

namespace App\Http\Controllers;

use App\Events\PendingPaymentsChanged;
use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentRejectedMail;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Models\PlanPricing;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $orgs = Organization::query()
            ->withCount(['users', 'seasons', 'players'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id'             => $o->id,
                'name'           => $o->name,
                'slug'           => $o->slug,
                'plan'           => $o->plan,
                'created_at'     => $o->created_at?->format('Y-m-d'),
                'users_count'    => $o->users_count,
                'seasons_count'  => $o->seasons_count,
                'players_count'  => $o->players_count,
            ]);

        $stats = [
            'orgs_total'        => Organization::count(),
            'users_total'       => User::count(),
            'seasons_active'    => DB::table('seasons')->where('is_active', true)->count(),
            'auctions_running'  => DB::table('auction_states')->where('status', 'running')->count(),
            'mrr_estimate_bdt'  => $this->estimateMrr(),
            'plans_breakdown'   => Organization::groupBy('plan')->selectRaw('plan, count(*) as c')->pluck('c', 'plan'),
            'subs_past_due'     => Subscription::where('status', 'past_due')->count(),
            'subs_expiring_7d'  => Subscription::where('status', 'active')
                ->whereBetween('current_period_end', [now(), now()->addDays(7)])->count(),
        ];

        $duesoonSubs = Subscription::with('organization:id,name,slug,plan')
            ->whereIn('status', ['active', 'past_due'])
            ->where(function ($q) {
                $q->where('status', 'past_due')
                  ->orWhereBetween('current_period_end', [now(), now()->addDays(7)]);
            })
            ->orderBy('current_period_end')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'id'                => $s->id,
                'org'               => $s->organization?->name,
                'org_slug'          => $s->organization?->slug,
                'plan'              => $s->plan,
                'status'            => $s->status,
                'provider'          => $s->provider,
                'auto_renew'        => $s->auto_renew,
                'current_period_end'=> $s->current_period_end?->format('Y-m-d'),
                'next_attempt_at'   => $s->next_attempt_at?->format('Y-m-d H:i'),
                'attempts'          => $s->renewal_attempts,
                'last_failure'      => $s->last_failure_reason,
            ]);

        $recentTxns = PaymentTransaction::with('organization:id,name,slug')
            ->latest()->limit(15)->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'org'        => $t->organization?->name,
                'org_slug'   => $t->organization?->slug,
                'provider'   => $t->provider,
                'plan'       => $t->plan,
                'amount'     => $t->amount,
                'currency'   => $t->currency,
                'status'     => $t->status,
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('SuperAdmin/Index', [
            'orgs'         => $orgs,
            'stats'        => $stats,
            'recent_txns'  => $recentTxns,
            'duesoon_subs' => $duesoonSubs,
            'plans'        => array_keys(Organization::PLAN_LIMITS),
        ]);
    }

    /** Dedicated organizations page — full list with filters, plan switcher, impersonate, delete. */
    public function orgs(Request $request): Response
    {
        $filters = $request->validate([
            'q'    => 'nullable|string|max:100',
            'plan' => 'nullable|string|max:32',
        ]);

        $q = Organization::query()->withCount(['users', 'seasons', 'players']);
        if ($s = $filters['q'] ?? null) {
            $q->where(fn ($qq) => $qq->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"));
        }
        if ($p = $filters['plan'] ?? null) {
            $q->where('plan', $p);
        }

        $orgs = $q->orderByDesc('id')->paginate(40)->withQueryString();

        $orgs->getCollection()->transform(function ($o) {
            $sub = $o->subscriptions()->whereIn('status', ['active','past_due'])->latest()->first();
            return [
                'id'              => $o->id,
                'name'            => $o->name,
                'slug'            => $o->slug,
                'plan'            => $o->plan,
                'created_at'      => $o->created_at?->format('Y-m-d'),
                'users_count'     => $o->users_count,
                'seasons_count'   => $o->seasons_count,
                'players_count'   => $o->players_count,
                'custom_domain'   => $o->custom_domain,
                'sub_status'      => $sub?->status,
                'sub_until'       => $sub?->current_period_end?->format('Y-m-d'),
                'sub_provider'    => $sub?->provider,
            ];
        });

        return Inertia::render('SuperAdmin/Orgs', [
            'orgs'    => $orgs,
            'filters' => $filters,
            'plans'   => array_keys(Organization::PLAN_LIMITS),
        ]);
    }

    public function deleteOrg(Request $request, Organization $organization): RedirectResponse
    {
        $name = $organization->name;
        $slug = $organization->slug;

        Audit::log(
            'org.deleted',
            "Deleted organization {$name} ({$slug}) — all seasons, players, teams, bids cascaded",
            ['org_id' => $organization->id, 'plan' => $organization->plan],
            $organization,
            $organization->id,
        );

        $organization->delete();

        return back()->with('success', "Organization “{$name}” deleted.");
    }

    public function setPlan(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(Organization::PLAN_LIMITS))],
        ]);

        $oldPlan = $organization->plan;
        $organization->forceFill(['plan' => $data['plan']])->save();

        Audit::log(
            'plan.changed',
            "Plan override: {$oldPlan} → {$data['plan']} ({$organization->name})",
            ['from' => $oldPlan, 'to' => $data['plan'], 'override' => 'super_admin'],
            $organization,
            $organization->id,
        );

        return back()->with('success', "{$organization->name} → {$data['plan']} plan.");
    }

    public function impersonate(Request $request, Organization $organization): RedirectResponse
    {
        $impersonator = Auth::user();

        $target = $organization->users()->wherePivot('role', 'org_admin')->first()
               ?? $organization->users()->first();

        if (! $target) return back()->with('error', 'That organization has no users yet.');

        Audit::log(
            'user.impersonated',
            "{$impersonator->name} started impersonating {$target->name} in {$organization->name}",
            ['impersonator_id' => $impersonator->id, 'target_id' => $target->id],
            $organization,
            $organization->id,
        );

        session([
            'impersonating_from'         => $impersonator->id,
            'current_organization_id'    => $organization->id,
        ]);
        Auth::login($target);

        return redirect()->route('dashboard.home')
            ->with('success', "Impersonating {$target->name} in {$organization->name}.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $originalId = session('impersonating_from');
        if (! $originalId) return redirect()->route('dashboard.home');

        $original = User::find($originalId);
        // Defence-in-depth: refuse to re-login the "original" account if it isn't
        // actually a super-admin. Without this, a session-fixation or replay of
        // an `impersonating_from` value into a non-super session would let the
        // attacker hop into an arbitrary user. The session key should only ever
        // be set by impersonate() (which itself is super-admin-gated).
        if (! $original || ! $original->isSuperAdmin()) {
            session()->forget('impersonating_from');
            Auth::logout();
            return redirect()->route('login')->with('error', 'Impersonation session invalid — please log in again.');
        }

        session()->forget('impersonating_from');
        Auth::login($original);

        return redirect()->route('admin.index')->with('success', 'Returned to super-admin.');
    }

    /**
     * Platform-level analytics — what's happening across every org. The
     * org-scoped /dashboard/analytics view answers "how is THIS auction
     * going"; this one answers "how is the SaaS doing".
     */
    public function analytics(Request $request): Response
    {
        $now = now();

        // ---------- Time-series buckets (last 30 days, daily) ----------
        $signups30 = $this->dailyCount(Organization::query(), 'created_at', 30);
        $users30   = $this->dailyCount(User::query(), 'created_at', 30);

        // Bid activity across all orgs (real auction usage signal)
        $bids30 = $this->dailyCountFromTable('bids', 'placed_at', 30);

        // ---------- Revenue ----------
        // Completed transactions in the last 6 months, bucketed by month.
        $revenue6m = DB::table('payment_transactions')
            ->select('amount', 'currency', 'created_at')
            ->where('status', 'completed')
            ->where('created_at', '>=', $now->copy()->subMonths(6)->startOfMonth())
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($r) => \Carbon\Carbon::parse($r->created_at)->format('Y-m'))
            ->map(fn ($rows, $month) => [
                'month'  => $month,
                'count'  => $rows->count(),
                'amount' => (int) $rows->sum('amount'),     // BDT (PaymentTransaction.amount stored in org's billing currency, mostly BDT)
            ])
            ->values();

        $revenueByProvider = DB::table('payment_transactions')
            ->where('status', 'completed')
            ->groupBy('provider')
            ->selectRaw('provider, COUNT(*) as count, SUM(amount) as amount')
            ->get()
            ->map(fn ($r) => [
                'provider' => $r->provider,
                'count'    => (int) $r->count,
                'amount'   => (int) $r->amount,
            ])
            ->values();

        // ---------- Distributions ----------
        $planDistribution = Organization::query()
            ->groupBy('plan')
            ->selectRaw('plan, count(*) as c')
            ->pluck('c', 'plan')
            ->map(fn ($v) => (int) $v);

        $sportDistribution = DB::table('seasons')
            ->groupBy('sport')
            ->selectRaw('sport, count(*) as c')
            ->pluck('c', 'sport')
            ->map(fn ($v) => (int) $v);

        $subStatusDistribution = Subscription::query()
            ->groupBy('status')
            ->selectRaw('status, count(*) as c')
            ->pluck('c', 'status')
            ->map(fn ($v) => (int) $v);

        // ---------- Top active orgs (by bids placed in last 30 days) ----------
        $topOrgs = DB::table('bids')
            ->select('organization_id', DB::raw('COUNT(*) as bid_count'))
            ->where('placed_at', '>=', $now->copy()->subDays(30))
            ->groupBy('organization_id')
            ->orderByDesc('bid_count')
            ->limit(10)
            ->get();

        $topOrgIds = $topOrgs->pluck('organization_id');
        $orgLookup = Organization::whereIn('id', $topOrgIds)->get(['id','name','slug','plan'])->keyBy('id');
        $topActiveOrgs = $topOrgs->map(fn ($r) => [
            'id'    => $r->organization_id,
            'name'  => $orgLookup[$r->organization_id]?->name ?? '—',
            'slug'  => $orgLookup[$r->organization_id]?->slug ?? '',
            'plan'  => $orgLookup[$r->organization_id]?->plan ?? null,
            'bids'  => (int) $r->bid_count,
        ])->values();

        // ---------- Visitor analytics ----------
        // Distinct visitors are counted by hashed session id; "real-time" = active
        // in last 5 minutes (events get debounced so this is a faithful count).
        $visitors = [
            'total_unique'     => (int) DB::table('visitor_events')
                ->select('session_id')->distinct()->get()->count(),
            'total_pageviews'  => (int) DB::table('visitor_events')->count(),
            'realtime_now'     => (int) DB::table('visitor_events')
                ->where('created_at', '>=', $now->copy()->subMinutes(5))
                ->select('session_id')->distinct()->get()->count(),
            'today_unique'     => (int) DB::table('visitor_events')
                ->where('created_at', '>=', $now->copy()->startOfDay())
                ->select('session_id')->distinct()->get()->count(),
            'unique_30d'       => (int) DB::table('visitor_events')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->select('session_id')->distinct()->get()->count(),
        ];

        // Daily unique visitors (last 30 days) — DB-agnostic via PHP bucketing
        $visitorsTimeline = $this->dailyDistinctSessionsFromTable('visitor_events', 'created_at', 30);

        // Top pages last 30 days — visits + unique visitors per path
        $topPages = DB::table('visitor_events')
            ->select('path', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT session_id) as visitors'))
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'path'     => $r->path,
                'views'    => (int) $r->views,
                'visitors' => (int) $r->visitors,
            ]);

        // ---------- Traffic sources (last 30 days, attributed to first event of session) ----------
        // Take the earliest event per session so we attribute the source to the
        // session's *entry point*, not to internal navigation later in the visit.
        $firstPerSession = DB::table('visitor_events')
            ->select('session_id', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'created_at')
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->orderBy('created_at')
            ->get()
            ->groupBy('session_id')
            ->map(fn ($events) => $events->first());

        $sourceBuckets = [];                     // by full label e.g. "Search: google"
        $sourceKindTotals = ['direct' => 0, 'search' => 0, 'social' => 0, 'referral' => 0, 'campaign' => 0];
        $campaignBuckets = [];                   // by utm_source

        foreach ($firstPerSession as $first) {
            $cat = $this->categorizeSource($first->referrer, $first->utm_source);
            $sourceKindTotals[$cat['kind']] = ($sourceKindTotals[$cat['kind']] ?? 0) + 1;

            $key = $cat['label'];
            if (! isset($sourceBuckets[$key])) {
                $sourceBuckets[$key] = ['kind' => $cat['kind'], 'label' => $cat['label'], 'host' => $cat['host'], 'visitors' => 0];
            }
            $sourceBuckets[$key]['visitors']++;

            if ($first->utm_source) {
                $ck = $first->utm_source . ' / ' . ($first->utm_medium ?? '—') . ' / ' . ($first->utm_campaign ?? '—');
                if (! isset($campaignBuckets[$ck])) {
                    $campaignBuckets[$ck] = [
                        'utm_source'   => $first->utm_source,
                        'utm_medium'   => $first->utm_medium,
                        'utm_campaign' => $first->utm_campaign,
                        'visitors'     => 0,
                    ];
                }
                $campaignBuckets[$ck]['visitors']++;
            }
        }

        $topSources = collect($sourceBuckets)->sortByDesc('visitors')->values()->take(15);
        $topCampaigns = collect($campaignBuckets)->sortByDesc('visitors')->values()->take(10);

        // ---------- High-level KPIs ----------
        $kpi = [
            'orgs_total'             => Organization::count(),
            'orgs_new_30d'           => Organization::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'users_total'            => User::count(),
            'users_new_30d'          => User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'seasons_total'          => DB::table('seasons')->count(),
            'seasons_active'         => DB::table('seasons')->where('is_active', true)->count(),
            'players_total'          => DB::table('players')->count(),
            'teams_total'            => DB::table('teams')->count(),
            'bids_total'             => DB::table('bids')->count(),
            'bids_30d'               => DB::table('bids')->where('placed_at', '>=', $now->copy()->subDays(30))->count(),
            'auctions_running_now'   => DB::table('auction_states')->where('status', 'running')->count(),
            'players_sold_total'     => DB::table('players')->where('auction_status', 'sold')->count(),
            'gmv_sold_total'         => (int) DB::table('players')->where('auction_status', 'sold')->sum('sold_price'),
            'revenue_total'          => (int) DB::table('payment_transactions')->where('status', 'completed')->sum('amount'),
            'revenue_30d'            => (int) DB::table('payment_transactions')
                ->where('status', 'completed')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->sum('amount'),
            'mrr_estimate'           => $this->estimateMrr(),
            'subs_active'            => Subscription::where('status', 'active')->count(),
            'subs_past_due'          => Subscription::where('status', 'past_due')->count(),
        ];

        return Inertia::render('SuperAdmin/Analytics', [
            'kpi'                   => $kpi,
            'visitors'              => $visitors,
            'visitors_timeline'     => $visitorsTimeline,
            'top_pages'             => $topPages,
            'source_kinds'          => $sourceKindTotals,
            'top_sources'           => $topSources,
            'top_campaigns'         => $topCampaigns,
            'signups_30d'           => $signups30,
            'users_30d'             => $users30,
            'bids_30d'              => $bids30,
            'revenue_6m'            => $revenue6m,
            'revenue_by_provider'   => $revenueByProvider,
            'plan_distribution'     => $planDistribution,
            'sport_distribution'    => $sportDistribution,
            'sub_status_distribution' => $subStatusDistribution,
            'top_active_orgs'       => $topActiveOrgs,
        ]);
    }

    /**
     * Classify a session's traffic source from its first event's referrer + UTM tags.
     *
     * Returns ['kind' => ..., 'label' => human-readable, 'host' => grouping key].
     * Kind is one of: campaign | direct | search | social | referral.
     */
    private function categorizeSource(?string $referrer, ?string $utmSource): array
    {
        if ($utmSource) {
            return ['kind' => 'campaign', 'label' => 'Campaign · ' . $utmSource, 'host' => $utmSource];
        }

        if (! $referrer) {
            return ['kind' => 'direct', 'label' => 'Direct', 'host' => 'direct'];
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (! $host) {
            return ['kind' => 'direct', 'label' => 'Direct', 'host' => 'direct'];
        }
        $host = strtolower($host);

        // Self-referral counts as direct (internal nav from the same domain)
        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if ($appHost && ($host === $appHost || str_ends_with($host, '.' . $appHost))) {
            return ['kind' => 'direct', 'label' => 'Direct', 'host' => 'direct'];
        }

        // Search engines — match domain segment, not anywhere in string, so a
        // site like "googlesearchclone.com" doesn't get bucketed as Google.
        $searchEngines = [
            'google'     => 'Google',
            'bing'       => 'Bing',
            'duckduckgo' => 'DuckDuckGo',
            'yahoo'      => 'Yahoo',
            'yandex'     => 'Yandex',
            'baidu'      => 'Baidu',
            'ecosia'     => 'Ecosia',
            'brave'      => 'Brave Search',
        ];
        foreach ($searchEngines as $needle => $label) {
            if (preg_match('/(^|\.)' . preg_quote($needle, '/') . '\./i', $host)) {
                return ['kind' => 'search', 'label' => 'Search · ' . $label, 'host' => $label];
            }
        }

        // Social platforms — checked as substring since they have many TLD variants
        $socials = [
            'facebook'  => 'Facebook',  'fb.com'    => 'Facebook',     'fb.me'   => 'Facebook',
            'instagram' => 'Instagram',
            'twitter'   => 'Twitter',   'x.com'     => 'Twitter',      't.co'    => 'Twitter',
            'linkedin'  => 'LinkedIn',  'lnkd.in'   => 'LinkedIn',
            'youtube'   => 'YouTube',   'youtu.be'  => 'YouTube',
            'tiktok'    => 'TikTok',
            'reddit'    => 'Reddit',
            'whatsapp'  => 'WhatsApp',  'wa.me'     => 'WhatsApp',
            'telegram'  => 'Telegram',  't.me'      => 'Telegram',
            'discord'   => 'Discord',
            'pinterest' => 'Pinterest',
            'snapchat'  => 'Snapchat',
            'threads'   => 'Threads',
        ];
        foreach ($socials as $needle => $label) {
            if (str_contains($host, $needle)) {
                return ['kind' => 'social', 'label' => 'Social · ' . $label, 'host' => $label];
            }
        }

        // Strip leading www. for cleaner display
        $cleanHost = preg_replace('/^www\./', '', $host);
        return ['kind' => 'referral', 'label' => 'Referral · ' . $cleanHost, 'host' => $cleanHost];
    }

    /** Daily distinct session counts (visitor uniques) from a raw table. */
    private function dailyDistinctSessionsFromTable(string $table, string $column, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table($table)
            ->where($column, '>=', $from)
            ->get([$column, 'session_id'])
            ->groupBy(fn ($r) => \Carbon\Carbon::parse($r->{$column})->format('Y-m-d'));

        $out = [];
        $cursor = now()->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->format('Y-m-d');
            $out[] = [
                'date'  => $key,
                'count' => isset($rows[$key]) ? $rows[$key]->pluck('session_id')->unique()->count() : 0,
            ];
            $cursor->addDay();
        }
        return $out;
    }

    /** Daily count from an Eloquent query — DB-agnostic via PHP bucketing. */
    private function dailyCount($query, string $column, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = $query->where($column, '>=', $from)->get([$column])->groupBy(
            fn ($r) => \Carbon\Carbon::parse($r->{$column})->format('Y-m-d')
        );
        return $this->fillDailyBuckets($rows, $days);
    }

    /** Daily count from a raw DB table. */
    private function dailyCountFromTable(string $table, string $column, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table($table)->where($column, '>=', $from)->get([$column])->groupBy(
            fn ($r) => \Carbon\Carbon::parse($r->{$column})->format('Y-m-d')
        );
        return $this->fillDailyBuckets($rows, $days);
    }

    /** Pad the timeseries with zeros so charts have continuous x-axis. */
    private function fillDailyBuckets($groupedRows, int $days): array
    {
        $out = [];
        $cursor = now()->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->format('Y-m-d');
            $out[] = [
                'date'  => $key,
                'count' => isset($groupedRows[$key]) ? $groupedRows[$key]->count() : 0,
            ];
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * Manually set / extend a subscription end date — typically used to grant
     * free trial / test / comp access without going through bKash or PayPal.
     *
     * Behaviour:
     *   - Latest active|past_due sub is updated; if none exists, a new one is
     *     created with provider='manual'.
     *   - Status forced to 'active', auto_renew off (super admin must extend
     *     again when it runs out — no surprise charges).
     *   - Optional plan override updates both Organization.plan and the sub.
     */
    public function extendSubscription(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'until' => 'required|date|after:today',
            'plan'  => ['nullable', Rule::in(array_keys(Organization::PLAN_LIMITS))],
            'note'  => 'nullable|string|max:500',
        ]);

        $until = \Carbon\Carbon::parse($data['until'])->endOfDay();
        $plan  = $data['plan'] ?? $organization->plan;

        if ($plan !== $organization->plan) {
            $oldPlan = $organization->plan;
            $organization->forceFill(['plan' => $plan])->save();
            Audit::log(
                'plan.changed',
                "Plan override: {$oldPlan} → {$plan} ({$organization->name}) — via manual sub extension",
                ['from' => $oldPlan, 'to' => $plan, 'override' => 'super_admin_manual_sub'],
                $organization,
                $organization->id,
            );
        }

        $sub = $organization->subscriptions()
            ->whereIn('status', ['active', 'past_due', 'expired', 'canceled'])
            ->latest()
            ->first();

        if ($sub) {
            $sub->update([
                'status'             => 'active',
                'plan'               => $plan,
                'current_period_end' => $until,
                'auto_renew'         => false,
                'next_attempt_at'    => null,
                'renewal_attempts'   => 0,
                'canceled_at'        => null,
            ]);
        } else {
            $sub = $organization->subscriptions()->create([
                'plan'                 => $plan,
                'status'               => 'active',
                'provider'             => 'manual',
                'amount'               => 0,
                'currency'             => 'BDT',
                'billing_cycle'        => 'monthly',
                'auto_renew'           => false,
                'is_recurring'         => false,
                'current_period_start' => now(),
                'current_period_end'   => $until,
            ]);
        }

        Audit::log(
            'subscription.manual_extended',
            "Manual sub set: {$organization->name} → {$plan} until {$until->format('Y-m-d')}"
                . (isset($data['note']) && $data['note'] ? " — note: {$data['note']}" : ''),
            [
                'org_id' => $organization->id,
                'plan'   => $plan,
                'until'  => $until->toIso8601String(),
                'note'   => $data['note'] ?? null,
            ],
            $organization,
            $organization->id,
        );

        return back()->with('success', "{$organization->name}: active on {$plan} until {$until->format('Y-m-d')}.");
    }

    public function forceRenew(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'next_attempt_at'    => null,
            'current_period_end' => now()->subSecond(),
        ]);

        \App\Jobs\RenewSubscriptionJob::dispatchSync($subscription->id);

        return back()->with('success', "Renewal triggered for #{$subscription->id}.");
    }

    /* ============== USERS PANEL ============== */

    public function users(Request $request): Response
    {
        $filters = $request->validate([
            'q'              => 'nullable|string|max:100',
            'role'           => 'nullable|string|max:32',
            'is_super_admin' => 'nullable|boolean',
        ]);

        $q = User::query()->withCount('organizations');

        if ($s = $filters['q'] ?? null) {
            $q->where(fn ($qq) => $qq->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if (array_key_exists('is_super_admin', $filters) && $filters['is_super_admin'] !== null) {
            $q->where('is_super_admin', (bool) $filters['is_super_admin']);
        }

        $users = $q->orderByDesc('id')->paginate(40)->withQueryString();

        // Append role hints + full org list so the "Grant subscription" modal
        // can target a specific org without an extra round trip.
        $users->getCollection()->transform(function ($u) {
            $orgs = $u->organizations()->orderBy('name')->get();
            $first = $orgs->first();
            return [
                'id'              => $u->id,
                'name'            => $u->name,
                'email'           => $u->email,
                'is_super_admin'  => (bool) $u->is_super_admin,
                'created_at'      => $u->created_at?->format('Y-m-d'),
                'organizations_count' => $u->organizations_count,
                'sample_org'      => $first ? ['name' => $first->name, 'slug' => $first->slug, 'role' => $first->pivot->role] : null,
                // Compact org list — what the grant-subscription modal needs.
                // Includes current active/past_due sub end date so the admin can
                // see at a glance "this org is already active until X".
                'orgs_for_grant'  => $orgs->map(function ($o) {
                    $sub = $o->subscriptions()->whereIn('status', ['active', 'past_due'])->latest()->first();
                    return [
                        'id'        => $o->id,
                        'name'      => $o->name,
                        'slug'      => $o->slug,
                        'plan'      => $o->plan,
                        'sub_until' => $sub?->current_period_end?->format('Y-m-d'),
                        'sub_status'=> $sub?->status,
                    ];
                })->values(),
            ];
        });

        return Inertia::render('SuperAdmin/Users', [
            'users'   => $users,
            'filters' => $filters,
            'orgs'    => Organization::orderBy('name')->get(['id','name','slug','plan']),
            'plans'   => array_keys(Organization::PLAN_LIMITS),
            'roles'   => ['org_admin', 'auctioneer', 'team_owner', 'viewer'],
        ]);
    }

    /**
     * Super admin creates a user account directly. Three modes for org attachment:
     *   - new_org      : also create an org with any plan slug (plan is gifted —
     *                    no payment row is created, but a manual Subscription is
     *                    seeded so usage limits + period_end exist; auto_renew=false)
     *   - existing_org : attach to an existing org with the given pivot role
     *   - none         : standalone user (useful for super-admin-only accounts)
     *
     * Password is either provided or auto-generated; the generated value is
     * flashed back to super admin so they can hand it off securely.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|string|lowercase|email|max:255|unique:users,email',
            'password'          => 'nullable|string|min:8|max:255',
            'is_super_admin'    => 'sometimes|boolean',
            'attach_mode'       => ['required', Rule::in(['new_org', 'existing_org', 'none'])],

            // new_org branch
            'org_name'          => 'required_if:attach_mode,new_org|string|max:255',
            'org_slug'          => [
                'required_if:attach_mode,new_org',
                'nullable', 'string', 'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/',
                Rule::unique('organizations', 'slug'),
            ],
            'plan'              => ['required_if:attach_mode,new_org', 'nullable', Rule::in(array_keys(Organization::PLAN_LIMITS))],
            'gift_months'       => 'nullable|integer|min:1|max:60',

            // existing_org branch
            'organization_id'   => 'required_if:attach_mode,existing_org|nullable|exists:organizations,id',
            'role'              => ['required_if:attach_mode,existing_org', 'nullable', Rule::in(['org_admin', 'auctioneer', 'team_owner', 'viewer'])],
        ], [
            'org_slug.regex' => 'Slug must be lowercase letters, numbers and dashes only.',
        ]);

        $generatedPassword = empty($data['password']) ? Str::random(14) : null;

        $user = DB::transaction(function () use ($data, $generatedPassword) {
            $user = new User([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($generatedPassword ?? $data['password']),
            ]);
            // email_verified_at + is_super_admin are guarded against mass-assignment;
            // super admin attests this account so we set them explicitly.
            $user->email_verified_at = now();
            $user->is_super_admin    = (bool) ($data['is_super_admin'] ?? false);
            $user->save();

            if ($data['attach_mode'] === 'new_org') {
                $org = new Organization([
                    'name' => $data['org_name'],
                    'slug' => $data['org_slug'],
                ]);
                $org->plan = $data['plan'];   // explicit; bypasses $fillable guard
                $org->save();

                $org->users()->attach($user->id, [
                    'role'           => 'org_admin',
                    'last_active_at' => now(),
                ]);

                // Gift the plan: seed a manual Subscription so the org has a real
                // active period instead of relying on raw plan column alone.
                if ($data['plan'] !== 'free') {
                    $months = (int) ($data['gift_months'] ?? 1);
                    Subscription::create([
                        'organization_id'      => $org->id,
                        'plan'                 => $data['plan'],
                        'status'               => 'active',
                        'provider'             => 'manual',
                        'is_recurring'         => false,
                        'auto_renew'           => false,
                        'amount'               => 0,
                        'currency'             => 'BDT',
                        'billing_cycle'        => 'monthly',
                        'current_period_start' => now(),
                        'current_period_end'   => now()->addMonths($months),
                    ]);
                }
            } elseif ($data['attach_mode'] === 'existing_org') {
                $org = Organization::findOrFail($data['organization_id']);
                $org->users()->attach($user->id, [
                    'role'           => $data['role'],
                    'last_active_at' => now(),
                ]);
            }

            return $user;
        });

        Audit::log(
            'superadmin.user_created',
            "Created {$user->name} ({$user->email})" .
                ($data['attach_mode'] === 'new_org'      ? " + new org {$data['org_name']} on {$data['plan']}" : '') .
                ($data['attach_mode'] === 'existing_org' ? " attached to org #{$data['organization_id']} as {$data['role']}" : '') .
                ((bool) ($data['is_super_admin'] ?? false) ? ' [super admin]' : ''),
            ['target_user_id' => $user->id, 'mode' => $data['attach_mode'], 'plan' => $data['plan'] ?? null],
            $user,
        );

        $msg = "Created {$user->name}.";
        if ($generatedPassword) {
            $msg .= " Temporary password (share securely): {$generatedPassword}";
        }

        return back()->with('success', $msg);
    }

    public function toggleSuperAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "You can't change your own super-admin flag.");
        }

        $user->forceFill(['is_super_admin' => ! $user->is_super_admin])->save();

        Audit::log(
            $user->is_super_admin ? 'user.promoted' : 'user.demoted',
            ($user->is_super_admin ? 'Promoted ' : 'Demoted ') . "{$user->name} ({$user->email})" .
                ($user->is_super_admin ? ' to super admin' : ' from super admin'),
            ['target_user_id' => $user->id, 'is_super_admin' => $user->is_super_admin],
            $user,
        );

        return back()->with('success', $user->is_super_admin
            ? "{$user->name} is now a super admin."
            : "{$user->name} no longer has super-admin access.");
    }

    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "Use the profile page to change your own password.");
        }

        $newPassword = Str::random(14);
        $user->update(['password' => Hash::make($newPassword)]);

        Audit::log(
            'user.password_reset',
            "Password reset for {$user->name} ({$user->email}) by super admin",
            ['target_user_id' => $user->id],
            $user,
        );

        return back()->with('success', "Password reset. Temporary password (share securely): {$newPassword}");
    }

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "You can't delete yourself.");
        }

        $name  = $user->name;
        $email = $user->email;

        Audit::log('user.deleted', "Deleted user {$name} ({$email})", ['target_user_id' => $user->id], $user);
        $user->delete();

        return back()->with('success', "Deleted {$name}.");
    }

    /* ============== SUBSCRIPTIONS PANEL ============== */

    public function subscriptions(Request $request): Response
    {
        $filters = $request->validate([
            'q'      => 'nullable|string|max:100',
            'status' => 'nullable|string|max:32',
        ]);

        $q = Subscription::query()->with('organization:id,name,slug,plan');

        if ($s = $filters['q'] ?? null) {
            $q->whereHas('organization', fn ($oq) => $oq
                ->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"));
        }
        if ($status = $filters['status'] ?? null) {
            $q->where('status', $status);
        }

        $subs = $q->orderByDesc('created_at')->paginate(40)->withQueryString();
        $subs->getCollection()->transform(fn ($s) => [
            'id'                  => $s->id,
            'org'                 => $s->organization?->name,
            'org_slug'            => $s->organization?->slug,
            'plan'                => $s->plan,
            'status'              => $s->status,
            'provider'            => $s->provider,
            'amount'              => (int) $s->amount,
            'currency'            => $s->currency,
            'auto_renew'          => (bool) $s->auto_renew,
            'is_recurring'        => (bool) $s->is_recurring,
            'current_period_end'  => $s->current_period_end?->format('Y-m-d'),
            'attempts'            => $s->renewal_attempts,
        ]);

        $counts = Subscription::groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status');

        return Inertia::render('SuperAdmin/Subscriptions', [
            'subs'    => $subs,
            'filters' => $filters,
            'counts'  => $counts,
        ]);
    }

    public function cancelSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'auto_renew'  => false,
            'canceled_at' => now(),
        ]);

        Audit::log(
            'subscription.canceled',
            "Cancelled #{$subscription->id} ({$subscription->organization?->name}) by super admin",
            ['subscription_id' => $subscription->id],
            $subscription,
            $subscription->organization_id,
        );

        return back()->with('success', "Subscription #{$subscription->id} canceled.");
    }

    /* ============== CROSS-ORG AUDIT LOG ============== */

    public function audit(Request $request): Response
    {
        $filters = $request->validate([
            'q'         => 'nullable|string|max:100',
            'event'     => 'nullable|string|max:64',
            'org_id'    => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $q = AuditLog::query()->with(['user:id,name,email', 'organization:id,name,slug']);

        if ($s = $filters['q'] ?? null) {
            $q->where(fn ($qq) => $qq
                ->where('summary', 'like', "%{$s}%")
                ->orWhere('actor_name', 'like', "%{$s}%"));
        }
        if ($e = $filters['event']     ?? null) $q->where('event', $e);
        if ($o = $filters['org_id']    ?? null) $q->where('organization_id', $o);
        if ($d = $filters['date_from'] ?? null) $q->where('created_at', '>=', $d);
        if ($d = $filters['date_to']   ?? null) $q->where('created_at', '<=', $d.' 23:59:59');

        $logs = $q->orderByDesc('created_at')->paginate(60)->withQueryString();
        $logs->getCollection()->transform(fn ($r) => [
            'id'         => $r->id,
            'event'      => $r->event,
            'summary'    => $r->summary,
            'actor_name' => $r->actor_name,
            'org'        => $r->organization?->name,
            'org_slug'   => $r->organization?->slug,
            'ip_address' => $r->ip_address,
            'payload'    => $r->payload,
            'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
        ]);

        $eventCounts = AuditLog::selectRaw('event, COUNT(*) as c')
            ->groupBy('event')->orderByDesc('c')->pluck('c', 'event');

        return Inertia::render('SuperAdmin/Audit', [
            'logs'        => $logs,
            'filters'     => $filters,
            'event_counts'=> $eventCounts,
            'orgs'        => Organization::orderBy('name')->get(['id','name','slug']),
        ]);
    }

    private function estimateMrr(): int
    {
        $prices = PlanPricing::priceMap();
        $sum = 0;
        foreach (Organization::groupBy('plan')->selectRaw('plan, count(*) as c')->pluck('c', 'plan') as $plan => $count) {
            $sum += ($prices[$plan] ?? 0) * (int) $count;
        }
        return $sum;
    }

    /**
     * Plan pricing & limits — super admin can edit price / seasons / players /
     * teams / feature flags. Saving busts the request cache via the model's
     * `saved` hook so subsequent `Organization::limits()` calls pick up new
     * values immediately.
     */
    public function plans(Request $request): Response
    {
        $plans = PlanPricing::orderBy('sort_order')->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'slug'          => $p->slug,
                'price_bdt'     => $p->price_bdt,
                'seasons_limit' => $p->seasons_limit,
                'players_limit' => $p->players_limit,
                'teams_limit'   => $p->teams_limit,
                'watermark'     => $p->watermark,
                'export_csv'    => $p->export_csv,
                'export_pdf'    => $p->export_pdf,
                'sort_order'    => $p->sort_order,
                'orgs_count'    => Organization::where('plan', $p->slug)->count(),
            ]);

        return Inertia::render('SuperAdmin/Plans', [
            'plans'     => $plans,
            'unlimited' => PlanPricing::UNLIMITED,
        ]);
    }

    /** Manual bKash payments awaiting verification + the platform settings form. */
    public function payments(Request $request): Response
    {
        $pending = PaymentTransaction::where('provider', 'bkash')
            ->where('status', 'pending')
            ->with('organization:id,name,slug,plan')
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id'                  => $t->id,
                'local_ref'           => $t->local_ref,
                'org_name'            => $t->organization?->name,
                'org_slug'            => $t->organization?->slug,
                'current_plan'        => $t->organization?->plan,
                'plan'                => $t->plan,
                'amount'              => $t->amount,
                'currency'            => $t->currency,
                'provider_txn_id'     => $t->provider_txn_id,
                'sender_bkash_number' => $t->sender_bkash_number,
                'submitted_at'        => $t->created_at?->format('Y-m-d H:i'),
            ]);

        $recent = PaymentTransaction::where('provider', 'bkash')
            ->whereIn('status', ['completed', 'failed'])
            ->with('organization:id,name,slug')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($t) => [
                'id'              => $t->id,
                'local_ref'       => $t->local_ref,
                'org_name'        => $t->organization?->name,
                'plan'            => $t->plan,
                'amount'          => $t->amount,
                'provider_txn_id' => $t->provider_txn_id,
                'status'          => $t->status,
                'completed_at'    => $t->completed_at?->format('Y-m-d H:i'),
            ]);

        $settings = \App\Models\PlatformSettings::current();

        return Inertia::render('SuperAdmin/Payments', [
            'pending'  => $pending,
            'recent'   => $recent,
            'settings' => array_merge(
                $settings->only(['app_domain','bkash_merchant_number','bkash_account_type','bkash_instructions','manual_review_hours']),
                ['landing_payment_methods' => $settings->enabledLandingPaymentMethods()],
            ),
            'allLandingPaymentMethods' => \App\Models\PlatformSettings::LANDING_PAYMENT_METHODS,
        ]);
    }

    public function approvePayment(Request $request, PaymentTransaction $txn): RedirectResponse
    {
        abort_unless($txn->provider === 'bkash' && $txn->status === 'pending', 422);

        DB::transaction(function () use ($txn) {
            $txn->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Cancel any prior active/past_due sub so we end up with exactly one
            // active subscription per org — keeps queries (and the customer's
            // billing page) showing only the current plan period.
            Subscription::where('organization_id', $txn->organization_id)
                ->whereIn('status', ['active', 'past_due'])
                ->update(['status' => 'canceled', 'canceled_at' => now(), 'auto_renew' => false]);

            $sub = Subscription::create([
                'organization_id'          => $txn->organization_id,
                'plan'                     => $txn->plan,
                'status'                   => 'active',
                'provider'                 => 'bkash',
                'provider_subscription_id' => null,    // manual flow: no auto-renew agreement
                'is_recurring'             => false,
                'auto_renew'               => false,
                'amount'                   => $txn->amount,
                'currency'                 => $txn->currency,
                'billing_cycle'            => 'monthly',
                'current_period_start'     => now(),
                'current_period_end'       => now()->addMonth(),
            ]);

            $txn->update(['subscription_id' => $sub->id]);

            // Plan flip — Organization::limits() reads PlanPricing::limitsFor($this->plan),
            // so this single column change unlocks every feature gated by the new plan
            // (player/team caps, watermark, CSV/PDF export, white-label eligibility).
            // HandleInertiaRequests re-shares the fresh limits on the customer's next
            // page load — no cache to bust. forceFill because `plan` is guarded.
            $txn->organization->forceFill(['plan' => $txn->plan])->save();
        });

        $this->notifyCustomer($txn->fresh(), new PaymentApprovedMail($txn->fresh()));

        Audit::log(
            'payment.approved',
            "Manually approved bKash payment {$txn->local_ref} → {$txn->plan} for {$txn->organization?->name}",
            ['plan' => $txn->plan, 'amount' => $txn->amount, 'trx_id' => $txn->provider_txn_id, 'org_id' => $txn->organization_id],
            $txn,
        );

        broadcast(new PendingPaymentsChanged());

        return back()->with('success', "Approved — {$txn->organization?->name} is now on {$txn->plan}.");
    }

    public function rejectPayment(Request $request, PaymentTransaction $txn): RedirectResponse
    {
        abort_unless($txn->provider === 'bkash' && $txn->status === 'pending', 422);

        $reason = (string) $request->validate(['reason' => 'nullable|string|max:255'])['reason'] ?? '';

        $txn->update([
            'status'      => 'failed',
            'raw_payload' => array_merge((array) $txn->raw_payload, ['rejected_reason' => $reason, 'rejected_at' => now()->toIso8601String()]),
        ]);

        $this->notifyCustomer($txn->fresh(), new PaymentRejectedMail($txn->fresh(), $reason ?: null));

        Audit::log(
            'payment.rejected',
            "Rejected bKash payment {$txn->local_ref} ({$txn->organization?->name})" . ($reason ? " — {$reason}" : ''),
            ['plan' => $txn->plan, 'trx_id' => $txn->provider_txn_id, 'reason' => $reason],
            $txn,
        );

        broadcast(new PendingPaymentsChanged());

        return back()->with('success', 'Payment marked as failed.');
    }

    /**
     * Send the customer a notification mail. Tries the user who initiated the
     * txn first; if missing, falls back to all org_admin users on the org.
     * Failures are logged and swallowed so a bad SMTP doesn't break approval.
     */
    private function notifyCustomer(PaymentTransaction $txn, $mailable): void
    {
        $recipients = collect();
        if ($txn->initiated_by_user_id) {
            $u = User::find($txn->initiated_by_user_id);
            if ($u) $recipients->push($u);
        }
        if ($recipients->isEmpty() && $txn->organization) {
            $recipients = $txn->organization->users()->wherePivot('role', 'org_admin')->get();
        }

        foreach ($recipients as $u) {
            try {
                // Pass the user object (not email string) so HasLocalePreference
                // renders the mail in their stored locale, not the super-admin's.
                Mail::to($u)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning('payment.mail_failed', [
                    'txn_id' => $txn->id,
                    'user_id' => $u->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function uploadPlatformLogo(Request $request): RedirectResponse
    {
        // SVG intentionally excluded — same-origin SVG can carry inline JS and
        // would XSS any super admin who opens the file URL. Use raster formats only.
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $disk     = config('filesystems.default');
        $path     = $request->file('logo')->store('platform', $disk);
        // For the local public disk we store a relative URL (/storage/...) so
        // changing APP_URL or hosting domain doesn't break already-stored logos.
        // S3/R2 etc. need the absolute URL (different host), so we keep that.
        $url = config("filesystems.disks.{$disk}.driver") === 'local'
            ? '/storage/' . ltrim($path, '/')
            : Storage::disk($disk)->url($path);
        $settings = \App\Models\PlatformSettings::current();

        // Best-effort cleanup of the previous file so we don't litter storage.
        if ($settings->app_logo_url) {
            $this->deleteLogoFile($settings->app_logo_url, $disk);
        }

        $settings->update(['app_logo_url' => $url]);

        Audit::log('platform_settings.logo_updated', 'Platform logo updated', ['url' => $url]);

        return back()->with('success', 'Platform logo updated.');
    }

    public function deletePlatformLogo(Request $request): RedirectResponse
    {
        $settings = \App\Models\PlatformSettings::current();
        if ($settings->app_logo_url) {
            $this->deleteLogoFile($settings->app_logo_url, config('filesystems.default'));
            $settings->update(['app_logo_url' => null]);
        }

        Audit::log('platform_settings.logo_removed', 'Platform logo removed');

        return back()->with('success', 'Platform logo removed.');
    }

    /**
     * Try to delete a stored logo file. Handles both relative (/storage/foo.png)
     * and absolute URLs — strips whichever prefix matches and forwards the
     * remaining path to Storage::delete. Silent failure is fine; orphan files
     * are recoverable, missing logos are not.
     */
    private function deleteLogoFile(string $url, string $disk): void
    {
        // Relative (/storage/foo.png) — local public disk pattern.
        if (str_starts_with($url, '/storage/')) {
            Storage::disk($disk)->delete(substr($url, strlen('/storage/')));
            return;
        }
        // Absolute URL — match against the disk's configured base.
        $base = rtrim((string) config("filesystems.disks.{$disk}.url", ''), '/');
        if ($base && str_starts_with($url, $base)) {
            Storage::disk($disk)->delete(ltrim(substr($url, strlen($base)), '/'));
        }
    }

    public function updatePlatformSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_domain'              => ['required', 'string', 'max:100', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/i'],
            'bkash_merchant_number'   => 'required|string|max:32',
            'bkash_account_type'      => ['required', Rule::in(['Personal', 'Merchant', 'Send Money', 'Agent'])],
            'bkash_instructions'      => 'nullable|string|max:2000',
            'manual_review_hours'     => 'required|integer|min:1|max:72',
            'landing_payment_methods' => 'nullable|array',
            'landing_payment_methods.*' => [Rule::in(\App\Models\PlatformSettings::LANDING_PAYMENT_METHODS)],
        ], [
            'app_domain.regex' => 'Domain must be a valid hostname like auctionball.com or auction.example.bd (no protocol, no path).',
        ]);

        // Normalize to canonical order; empty array (admin disabled everything)
        // is allowed — the landing-page section hides itself when empty.
        $data['landing_payment_methods'] = array_values(array_intersect(
            \App\Models\PlatformSettings::LANDING_PAYMENT_METHODS,
            $data['landing_payment_methods'] ?? [],
        ));

        \App\Models\PlatformSettings::current()->update($data);

        Audit::log(
            'platform_settings.updated',
            "Platform settings updated: bKash {$data['bkash_account_type']} {$data['bkash_merchant_number']} · review {$data['manual_review_hours']}h",
            $data,
        );

        return back()->with('success', 'Platform settings updated.');
    }

    public function updatePlan(Request $request, PlanPricing $plan): RedirectResponse
    {
        $data = $request->validate([
            'price_bdt'     => 'required|integer|min:0',
            'seasons_limit' => 'required|integer|min:0',
            'players_limit' => 'required|integer|min:0',
            'teams_limit'   => 'required|integer|min:0',
            'watermark'     => 'required|boolean',
            'export_csv'    => 'required|boolean',
            'export_pdf'    => 'required|boolean',
        ]);

        $plan->update($data);

        Audit::log(
            'plan_pricing.updated',
            "Updated {$plan->slug}: ৳{$plan->price_bdt}/mo · {$plan->teams_limit} teams",
            ['slug' => $plan->slug, 'price_bdt' => $plan->price_bdt, 'teams' => $plan->teams_limit],
            $plan,
        );

        return back()->with('success', "Plan “{$plan->slug}” updated.");
    }
}
