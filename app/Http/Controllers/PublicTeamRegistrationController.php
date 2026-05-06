<?php

namespace App\Http\Controllers;

use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public team self-registration. Mirrors the player-registration flow:
 * org admin toggles team_registration_open on a season (mints a token URL),
 * captains/owners self-submit at /tr/{token}, admin approves on /dashboard/teams.
 *
 * Submitted teams have `registration_status = 'pending'` until approved — they
 * don't receive a device_token and aren't billed against the plan's team-cap
 * yet (only counted on approval).
 */
class PublicTeamRegistrationController extends Controller
{
    public function show(string $token): Response
    {
        $season = Season::where('team_registration_token', $token)
            ->where('team_registration_open', true)
            ->firstOrFail();

        $org = $season->organization;

        return Inertia::render('Public/TeamRegister', [
            'org'    => [
                'name'             => $org->name,
                'slug'             => $org->slug,
                'logo_url'         => $org->logo_url,
                'display_currency' => $org->display_currency,
                'bdt_per_usd'      => (int) $org->bdt_per_usd,
            ],
            'season' => [
                'name'                           => $season->name,
                'year'                           => $season->year,
                'sport'                          => $season->sport ?? 'cricket',
                'team_registration_fee'          => (int) $season->team_registration_fee,
                'team_registration_instructions' => $season->team_registration_instructions,
                'token'                          => $season->team_registration_token,
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $season = Season::where('team_registration_token', $token)
            ->where('team_registration_open', true)
            ->firstOrFail();

        $org = $season->organization;

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'short_code'          => 'nullable|string|max:10',
            'owner_name'          => 'required|string|max:255',
            'logo'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'registration_txn_id' => $season->team_registration_fee > 0
                ? 'required|string|max:100'
                : 'nullable|string|max:100',
        ]);

        // Reject duplicate TrxIDs across teams to prevent same payment claiming
        // two slots. Admin can still approve manually if it's a legitimate edge case.
        if (! empty($data['registration_txn_id'])) {
            $exists = $season->teams()
                ->where('registration_txn_id', $data['registration_txn_id'])
                ->exists();
            if ($exists) {
                return back()->with('error', 'This transaction ID has already been used for a team registration.');
            }
        }

        $logoUrl = null;
        if ($request->hasFile('logo')) {
            $disk = config('filesystems.default');
            $path = $request->file('logo')->store("orgs/{$org->id}/seasons/{$season->id}/teams", $disk);
            // Use relative URL on local disk so APP_URL changes don't break the path.
            $logoUrl = config("filesystems.disks.{$disk}.driver") === 'local'
                ? '/storage/' . ltrim($path, '/')
                : Storage::disk($disk)->url($path);
        }

        $season->teams()->create([
            'organization_id'      => $org->id,
            'name'                 => $data['name'],
            'short_code'           => $data['short_code'] ?? mb_strtoupper(mb_substr($data['name'], 0, 3)),
            'owner_name'           => $data['owner_name'],
            'logo_url'             => $logoUrl,
            'initial_budget'       => $season->budget_per_team,
            'remaining_budget'     => $season->budget_per_team,
            'device_token'         => Str::random(40),
            'registration_status'  => 'pending',
            'registration_txn_id'  => $data['registration_txn_id'] ?? null,
            'registered_at'        => now(),
        ]);

        return redirect()
            ->route('public-team-register.show', $token)
            ->with('success', 'Team registration submitted! The organizer will review your entry and approve it before the auction starts.');
    }
}
