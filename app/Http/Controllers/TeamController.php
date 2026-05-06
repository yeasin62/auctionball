<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        $teams = $season
            ? $season->teams()->withCount(['players' => fn ($q) => $q->where('auction_status', 'sold')])
                ->orderBy('name')->get()
                ->map(fn ($t) => [
                    'id'                  => $t->id,
                    'name'                => $t->name,
                    'short'               => $t->short_code,
                    'short_code'          => $t->short_code,
                    'logo_url'            => $t->logo_url,
                    'owner_name'          => $t->owner_name,
                    'initial'             => $t->initial_budget,
                    'initial_budget'      => $t->initial_budget,
                    'remaining'           => $t->remaining_budget,
                    'spent'               => $t->spent(),
                    'pct'                 => $t->initial_budget > 0
                        ? (int) round(($t->spent() / $t->initial_budget) * 100) : 0,
                    'players_count'       => $t->players_count,
                    'registration_status' => $t->registration_status ?? 'approved',
                    'registration_txn_id' => $t->registration_txn_id,
                ])
            : collect();

        $pendingCount = $season ? $season->teams()->where('registration_status', 'pending')->count() : 0;

        return Inertia::render('Dashboard/Teams/Index', [
            'season' => $season ? [
                'id'                              => $season->id,
                'name'                            => $season->name,
                'budget_per_team'                 => $season->budget_per_team,
                'team_registration_open'          => (bool) $season->team_registration_open,
                'team_registration_token'         => $season->team_registration_token,
                'team_registration_fee'           => (int) $season->team_registration_fee,
                'team_registration_instructions'  => $season->team_registration_instructions,
            ] : null,
            'teams'         => $teams,
            'limits'        => $org->limits(),
            'used'          => $season ? $season->teams()->count() : 0,
            'pending_count' => $pendingCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        if (! $season) {
            return back()->with('error', 'Create and activate a season before adding teams.');
        }

        $limits = $org->limits();
        if ($season->teams()->count() >= $limits['teams']) {
            return back()->with('error', "Your {$org->plan} plan allows {$limits['teams']} teams per season.");
        }

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'short_code'     => 'nullable|string|max:10',
            'initial_budget' => 'nullable|integer|min:0',
        ]);

        $budget = $data['initial_budget'] ?? $season->budget_per_team;

        $season->teams()->create([
            'organization_id'  => $org->id,
            'name'             => $data['name'],
            'short_code'       => $data['short_code'] ?? mb_strtoupper(mb_substr($data['name'], 0, 3)),
            'initial_budget'   => $budget,
            'remaining_budget' => $budget,
            'device_token'     => Str::random(40),
            'registered_at'    => now(),
        ]);

        return back()->with('success', "Team “{$data['name']}” added.");
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($team->organization_id !== $org->id, 404);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'short_code'     => 'nullable|string|max:10',
            'owner_name'     => 'nullable|string|max:255',
            'initial_budget' => 'nullable|integer|min:0',
            'logo'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // If a fresh logo arrives, store + replace old.
        if ($request->hasFile('logo')) {
            if ($team->logo_url) $this->deleteLogo($team->logo_url);
            $disk = config('filesystems.default');
            $path = $request->file('logo')->store("orgs/{$org->id}/seasons/{$team->season_id}/teams", $disk);
            $data['logo_url'] = config("filesystems.disks.{$disk}.driver") === 'local'
                ? '/storage/' . ltrim($path, '/')
                : Storage::disk($disk)->url($path);
        }

        // Budget edit cascades to remaining only when the team has spent nothing yet —
        // otherwise budgets would go inconsistent vs. existing player buys.
        if (array_key_exists('initial_budget', $data) && $team->spent() === 0) {
            $data['remaining_budget'] = $data['initial_budget'];
        } else {
            unset($data['initial_budget']);
        }

        $team->update(collect($data)->except(['logo'])->all());

        return back()->with('success', "Team “{$team->name}” updated.");
    }

    public function approve(Request $request, Team $team): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($team->organization_id !== $org->id, 404);

        // Plan limit gate — count only approved teams against the cap so a wave
        // of pending registrations can't push the org past their plan.
        $limits  = $org->limits();
        $approved = $team->season?->teams()->where('registration_status', 'approved')->count() ?? 0;
        if ($approved >= $limits['teams']) {
            return back()->with('error', "Your {$org->plan} plan allows {$limits['teams']} teams per season. Upgrade or delete a team first.");
        }

        $team->update(['registration_status' => 'approved']);
        return back()->with('success', "Team “{$team->name}” approved.");
    }

    public function reject(Request $request, Team $team): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($team->organization_id !== $org->id, 404);

        if ($team->logo_url) $this->deleteLogo($team->logo_url);
        $name = $team->name;
        $team->delete();

        return back()->with('success', "Registration of “{$name}” rejected.");
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($team->organization_id !== $org->id, 404);

        if ($team->logo_url) $this->deleteLogo($team->logo_url);
        $name = $team->name;
        $team->delete();

        return back()->with('success', "Team “{$name}” deleted.");
    }

    /** Toggle the team-registration link for the active season + mint a token on first open. */
    public function toggleRegistration(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        if (! $season) return back()->with('error', 'No active season.');

        $data = $request->validate([
            'open'                           => 'required|boolean',
            'team_registration_fee'          => 'nullable|integer|min:0',
            'team_registration_instructions' => 'nullable|string|max:2000',
        ]);

        $update = [
            'team_registration_open'         => (bool) $data['open'],
            'team_registration_fee'          => $data['team_registration_fee'] ?? $season->team_registration_fee,
            'team_registration_instructions' => $data['team_registration_instructions'] ?? $season->team_registration_instructions,
        ];

        if ($data['open'] && ! $season->team_registration_token) {
            $update['team_registration_token'] = Str::random(20);
        }
        $season->update($update);

        return back()->with('success', $data['open'] ? 'Public team registration is open.' : 'Public team registration closed.');
    }

    public function regenerateRegistrationToken(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        if (! $season) return back();

        $season->update(['team_registration_token' => Str::random(20)]);
        return back()->with('success', 'New team registration link generated. Old link is now dead.');
    }

    private function deleteLogo(string $url): void
    {
        $disk = config('filesystems.default');
        if (str_starts_with($url, '/storage/')) {
            Storage::disk($disk)->delete(substr($url, strlen('/storage/')));
            return;
        }
        $base = rtrim((string) config("filesystems.disks.{$disk}.url", ''), '/');
        if ($base && str_starts_with($url, $base)) {
            Storage::disk($disk)->delete(ltrim(substr($url, strlen($base)), '/'));
        }
    }
}
