<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
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
                'can_remove'     => $u->id !== $request->user()->id,
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

    public function removeUser(Request $request, User $user): RedirectResponse
    {
        $org = $request->attributes->get('current_organization');
        $member = $org->users()->where('users.id', $user->id)->first();

        if (! $member) {
            return back()->with('error', 'That user is not a member of this organization.');
        }

        if ($request->user()->id === $user->id) {
            return back()->with('error', 'You cannot remove your own admin access.');
        }

        if ($member->pivot->role === 'org_admin') {
            $adminCount = $org->users()->wherePivot('role', 'org_admin')->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Add another organization admin before removing this one.');
            }
        }

        $teamIds = Team::query()
            ->where('organization_id', $org->id)
            ->where('owner_user_id', $user->id)
            ->pluck('id')
            ->all();

        Team::query()
            ->where('organization_id', $org->id)
            ->where('owner_user_id', $user->id)
            ->update(['owner_user_id' => null]);

        $org->users()->detach($user->id);

        Audit::log('org_user.removed', "Removed {$user->name} ({$user->email}) from organization", [
            'target_user_id' => $user->id,
            'role' => $member->pivot->role,
            'team_id' => $member->pivot->team_id,
            'cleared_team_owner_ids' => $teamIds,
        ], $user, $org->id);

        return back()->with('success', 'User removed from this organization.');
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
