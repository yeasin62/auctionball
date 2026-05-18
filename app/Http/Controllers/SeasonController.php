<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        $seasons = $org->seasons()
            ->withCount(['players', 'teams', 'bids'])
            ->orderByDesc('is_active')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($s) => [
                'id'                        => $s->id,
                'name'                      => $s->name,
                'year'                      => $s->year,
                'sport'                     => $s->sport ?? 'cricket',
                'registration_form_schema'  => $s->registration_form_schema ?? [],
                'status'                    => $s->status,
                'is_active'                 => $s->is_active,
                'budget_per_team'           => $s->budget_per_team,
                'bid_increment'             => (int) ($s->bid_increment ?: 1000),
                'bid_increment_usd'         => (int) ($s->bid_increment_usd ?: 10),
                'players_count'             => $s->players_count,
                'teams_count'               => $s->teams_count,
                'bids_count'                => $s->bids_count,
                'start_date'                => $s->start_date?->format('Y-m-d'),
                'end_date'                  => $s->end_date?->format('Y-m-d'),
                'registration_open'         => (bool) $s->registration_open,
                'registration_token'        => $s->registration_token,
                'registration_fee'          => (int) $s->registration_fee,
                'registration_instructions' => $s->registration_instructions,
                'player_categories'         => $s->categoryList(),
            ]);

        return Inertia::render('Dashboard/Seasons/Index', [
            'seasons' => $seasons,
            'limits'  => $org->limits(),
            'used'    => $org->seasons()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        $limits = $org->limits();
        if ($org->seasons()->count() >= $limits['seasons']) {
            return back()->with('error', "Your {$org->plan} plan allows {$limits['seasons']} season(s). Upgrade to add more.");
        }

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'year'            => 'required|integer|min:2020|max:2100',
            'sport'           => ['required', Rule::in(Season::SPORTS)],
            'budget_per_team' => 'required|integer|min:0',
            'bid_increment'   => 'nullable|integer|min:1|max:1000000',
            'bid_increment_usd' => 'nullable|integer|min:1|max:1000000',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'status'          => ['nullable', Rule::in(['upcoming','active','completed'])],
        ]);

        $data['bid_increment']     = $data['bid_increment']     ?? 1000;
        $data['bid_increment_usd'] = $data['bid_increment_usd'] ?? 10;

        // Auto-activate when there's no active season already — otherwise the
        // user creates their first season and every dashboard page still says
        // "No active season. Create one first." because they didn't realize a
        // separate "Set active" click was needed. If another season is already
        // active, leave the new one as upcoming so the in-flight auction isn't
        // disrupted.
        $hasActive = $org->seasons()->where('is_active', true)->exists();
        $shouldActivate = ! $hasActive;

        $season = $org->seasons()->create([
            ...$data,
            'status'    => $data['status'] ?? ($shouldActivate ? 'active' : 'upcoming'),
            'is_active' => $shouldActivate,
        ]);

        $msg = $shouldActivate
            ? "Season “{$season->name}” created and set as active."
            : "Season “{$season->name}” created. Click “Set active” to switch to it.";

        return redirect()->route('dashboard.seasons.index')->with('success', $msg);
    }

    public function activate(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        DB::transaction(function () use ($org, $season) {
            $org->seasons()->update(['is_active' => false]);
            $season->update(['is_active' => true, 'status' => 'active']);
        });

        return back()->with('success', "“{$season->name}” is now your active season.");
    }

    /**
     * Mark a season as inactive without deleting it. Useful after a tournament
     * ends — the season + data stays around for audit / PDF export, but the
     * dashboard pages stop targeting it as the auction context.
     *
     * Leaves the org with NO active season; user must explicitly activate
     * another (or this one again) before live-auction features work.
     */
    public function deactivate(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $season->update(['is_active' => false, 'status' => 'completed']);

        return back()->with('success', "“{$season->name}” is now inactive. Dashboard pages will show no active season until you activate one.");
    }

    public function update(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $data = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'year'              => 'sometimes|required|integer|min:2020|max:2100',
            'sport'             => ['sometimes', 'required', Rule::in(Season::SPORTS)],
            'budget_per_team'   => 'sometimes|required|integer|min:0',
            'bid_increment'     => 'sometimes|integer|min:1|max:1000000',
            'bid_increment_usd' => 'sometimes|integer|min:1|max:1000000',
            'start_date'        => 'sometimes|nullable|date',
            'end_date'          => 'sometimes|nullable|date|after_or_equal:start_date',
        ]);
        $season->update($data);

        return back()->with('success', 'Season updated.');
    }

    /**
     * Permanently delete a season — cascades to players, teams, bids,
     * auction_state, and any registrations (via FK cascade in migrations).
     * Org-scoped: a super admin in a different org can't reach another org's
     * season through this endpoint (abort 404 first).
     */
    public function destroy(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $name           = $season->name;
        $playersCount   = $season->players()->count();
        $teamsCount     = $season->teams()->count();
        $bidsCount      = $season->bids()->count();

        \App\Support\Audit::log(
            'season.deleted',
            "Deleted season “{$name}” — cascaded {$playersCount} players, {$teamsCount} teams, {$bidsCount} bids",
            ['season_id' => $season->id, 'players' => $playersCount, 'teams' => $teamsCount, 'bids' => $bidsCount],
            null,
            $org->id,
        );

        $season->delete();

        return redirect()->route('dashboard.seasons.index')
            ->with('success', "Season “{$name}” deleted.");
    }

    /** Toggle public registration on/off; mints a token the first time. */
    public function toggleRegistration(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $data = $request->validate([
            'open'                      => 'required|boolean',
            'registration_fee'          => 'nullable|integer|min:0',
            'registration_instructions' => 'nullable|string|max:2000',
        ]);

        $update = [
            'registration_open'         => (bool) $data['open'],
            'registration_fee'          => $data['registration_fee'] ?? $season->registration_fee,
            'registration_instructions' => $data['registration_instructions'] ?? $season->registration_instructions,
        ];

        if ($data['open'] && ! $season->registration_token) {
            $update['registration_token'] = \Illuminate\Support\Str::random(20);
        }

        $season->update($update);

        return back()->with('success', $data['open'] ? 'Public registration is open.' : 'Public registration closed.');
    }

    /**
     * Save the org-defined custom registration field schema. Each field is
     * `{id, type, label, placeholder?, required?, options?}`. Built-in fields
     * (name, category, position, jersey, batting/bowling, profession, photo,
     * txn id) are always shown; this schema is appended below them.
     */
    public function updateRegistrationForm(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $validated = $request->validate([
            'fields'                          => 'array|max:30',          // a sane upper bound
            'fields.*.id'                     => 'required|string|max:32',
            'fields.*.type'                   => ['required', Rule::in(['heading','text','textarea','number','email','phone','url','date','time','select','radio','multi','checkbox','image','payment'])],
            'fields.*.label'                  => 'required|string|max:120',
            'fields.*.placeholder'            => 'nullable|string|max:160',
            'fields.*.required'               => 'sometimes|boolean',
            'fields.*.options'                => 'nullable|array|max:20',
            'fields.*.options.*'              => 'string|max:80',
            'fields.*.size'                   => 'nullable|integer|min:50|max:2000',
            // Payment field — array of methods with per-kind shape.
            'fields.*.methods'                => 'nullable|array|max:10',
            'fields.*.methods.*.kind'         => ['required_with:fields.*.methods', Rule::in(['bkash','nagad','rocket','bank','other'])],
            'fields.*.methods.*.label'        => 'nullable|string|max:80',
            'fields.*.methods.*.number'       => 'nullable|string|max:32',
            'fields.*.methods.*.instructions' => 'nullable|string|max:120',
            'fields.*.methods.*.bank'         => 'nullable|string|max:80',
            'fields.*.methods.*.account'      => 'nullable|string|max:64',
            'fields.*.methods.*.holder'       => 'nullable|string|max:80',
            'fields.*.methods.*.branch'       => 'nullable|string|max:80',
            // Optional show-when conditional. `field` = id of another field on
            // the same form. `operator` = how to compare. `value` = comparison
            // operand (string for equals/not_equals; ignored for is_set/is_empty).
            'fields.*.conditional'            => 'nullable|array',
            'fields.*.conditional.field'      => 'required_with:fields.*.conditional|string|max:32',
            'fields.*.conditional.operator'   => ['required_with:fields.*.conditional', Rule::in(['equals','not_equals','is_set','is_empty'])],
            'fields.*.conditional.value'      => 'nullable|string|max:160',
        ]);

        // Normalise — drop irrelevant keys per type, ensure id is unique.
        $seen = [];
        $clean = collect($validated['fields'] ?? [])->map(function ($f) use (&$seen) {
            $id = $f['id'];
            // Defensive: if duplicate id slipped in, regenerate.
            if (isset($seen[$id])) $id = \Illuminate\Support\Str::random(10);
            $seen[$id] = true;

            $row = [
                'id'       => $id,
                'type'     => $f['type'],
                'label'    => trim($f['label']),
                'required' => (bool) ($f['required'] ?? false),
            ];
            if (! empty($f['placeholder'])) $row['placeholder'] = $f['placeholder'];
            if (in_array($f['type'], ['select','radio','multi'], true)) {
                $row['options'] = array_values($f['options'] ?? []);
            }
            if ($f['type'] === 'image') $row['size'] = (int) ($f['size'] ?? 600);
            if ($f['type'] === 'payment') {
                $row['methods'] = array_values(array_map(function ($m) {
                    $m = ['kind' => $m['kind']] + array_filter($m, fn ($v, $k) => $v !== null && $v !== '' && $k !== 'kind', ARRAY_FILTER_USE_BOTH);
                    return $m;
                }, $f['methods'] ?? []));
            }
            if (! empty($f['conditional']) && ! empty($f['conditional']['field']) && ! empty($f['conditional']['operator'])) {
                $row['conditional'] = [
                    'field'    => $f['conditional']['field'],
                    'operator' => $f['conditional']['operator'],
                    'value'    => $f['conditional']['value'] ?? null,
                ];
            }
            return $row;
        })->values()->all();

        $season->update(['registration_form_schema' => $clean]);

        return back()->with('success', 'Registration form updated.');
    }

    public function regenerateRegistrationToken(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $season->update(['registration_token' => \Illuminate\Support\Str::random(20)]);
        return back()->with('success', 'New registration link generated. Old link is now dead.');
    }

    /**
     * Replace the season's player-category list. Each row = {name, base_price}.
     * Names that disappear leave existing players with that category nulled —
     * forces admins to re-assign instead of silently corrupting bid analytics.
     */
    public function updatePlayerCategories(Request $request, Season $season): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $data = $request->validate([
            'categories'              => 'required|array|min:1|max:20',
            'categories.*.name'       => 'required|string|max:40',
            'categories.*.base_price' => 'required|integer|min:0|max:100000000',
        ]);

        // Dedupe by name (case-insensitive) — last wins. Avoids "Elite" + "elite"
        // both surviving and then the dropdown showing two visually-identical rows.
        $byName = [];
        foreach ($data['categories'] as $row) {
            $key = mb_strtolower(trim($row['name']));
            if ($key === '') continue;
            $byName[$key] = [
                'name'       => trim($row['name']),
                'base_price' => (int) $row['base_price'],
            ];
        }
        $clean = array_values($byName);

        if (empty($clean)) {
            return back()->with('error', 'At least one category is required.');
        }

        $oldNames = $season->categoryNames();
        $newNames = array_map(fn ($c) => $c['name'], $clean);
        $removed  = array_values(array_diff($oldNames, $newNames));

        DB::transaction(function () use ($season, $clean, $removed) {
            $season->update(['player_categories' => $clean]);

            if ($removed) {
                $season->players()->whereIn('category', $removed)->update(['category' => null]);
            }
        });

        $msg = 'Player categories updated.';
        if ($removed) {
            $msg .= ' Players in removed categories ('.implode(', ', $removed).') have been left uncategorised — please re-assign them.';
        }

        return back()->with('success', $msg);
    }
}
