<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrgPagesController extends Controller
{
    public function users(Request $request): Response
    {
        $org = $request->attributes->get('current_organization');

        $users = $org->users()->orderBy('name')->get()
            ->map(fn ($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'role'           => $u->pivot->role,
                'team_id'        => $u->pivot->team_id,
                'last_active_at' => $u->pivot->last_active_at,
                'joined_at'      => $u->pivot->created_at,
            ]);

        $invitations = Invitation::where('organization_id', $org->id)
            ->whereNull('accepted_at')
            ->orderByDesc('id')
            ->with('team:id,name')
            ->get()
            ->map(fn ($i) => [
                'id'         => $i->id,
                'email'      => $i->email,
                'name'       => $i->name,
                'role'       => $i->role,
                'team'       => $i->team?->name,
                'expires_at' => $i->expires_at?->format('Y-m-d'),
                'expired'    => $i->isExpired(),
                'link'       => route('invite.show', $i->token),
            ]);

        $teams = $org->activeSeason()
            ? $org->activeSeason()->teams()->orderBy('name')->get(['id', 'name'])
            : collect();

        return Inertia::render('Dashboard/Users/Index', [
            'users'       => $users,
            'invitations' => $invitations,
            'teams'       => $teams,
        ]);
    }

    public function settings(Request $request): Response
    {
        return Inertia::render('Dashboard/Settings/Index', [
            'org' => $request->attributes->get('current_organization')
                ->only(['name', 'slug', 'plan', 'timezone', 'rules', 'logo_url', 'display_currency', 'bdt_per_usd']),
        ]);
    }

    /** Org admin updates display currency + conversion rate (used by Money helper everywhere). */
    public function updateCurrency(Request $request): RedirectResponse
    {
        $org  = $request->attributes->get('current_organization');
        $data = $request->validate([
            'display_currency' => ['required', Rule::in(['BDT', 'USD'])],
            'bdt_per_usd'      => 'required|integer|min:1|max:1000',
        ]);

        $org->update($data);

        return back()->with('success', "Display currency set to {$data['display_currency']}.");
    }

    public function billing(Request $request): Response
    {
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        return Inertia::render('Dashboard/Billing/Index', [
            'org' => ['name' => $org->name, 'plan' => $org->plan, 'limits' => $org->limits()],
            'usage' => [
                'seasons' => $org->seasons()->count(),
                'players' => $season ? $season->players()->count() : 0,
                'teams'   => $season ? $season->teams()->count()   : 0,
            ],
        ]);
    }
}
