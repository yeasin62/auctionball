<?php

use App\Models\Team;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Live auction channel — used by control panel, big-screen, and team-device.
 *
 * Two ways to authorize:
 *   1) Authenticated org member (admin / auctioneer / viewer)
 *   2) Team-device session — anyone holding a valid /join/{token} session
 *      that resolves to a team in this org+season.
 */
/**
 * Platform-wide super-admin channel. Used today for the pending-payments
 * badge in the sidebar — add other admin-only broadcasts (new signup,
 * incident, etc.) on the same channel later if needed.
 */
Broadcast::channel('super-admin', function ($user) {
    return $user && $user->isSuperAdmin();
});

Broadcast::channel('auction.{orgId}.{seasonId}', function ($user, $orgId, $seasonId) {
    // Public big-screen visitors are scoped by the /live/{org}/{season?} route.
    if ((int) session('public_bigscreen_org_id') === (int) $orgId
        && (int) session('public_bigscreen_season_id') === (int) $seasonId) {
        return ['id' => 'public-bigscreen', 'name' => 'Public big screen', 'kind' => 'public'];
    }

    // Path A: logged-in org member
    if ($user) {
        $belongs = $user->organizations()->where('organizations.id', $orgId)->exists();
        if ($belongs) {
            return ['id' => $user->id, 'name' => $user->name, 'kind' => 'user'];
        }
    }

    // Path B: team-device session (set by JoinController when token valid)
    $teamId = session('team_device_team_id');
    if ($teamId) {
        $team = Team::find($teamId);
        if ($team
            && (int) $team->organization_id === (int) $orgId
            && (int) $team->season_id === (int) $seasonId) {
            return ['id' => 'team-' . $team->id, 'name' => $team->name, 'kind' => 'team'];
        }
    }

    return false;
});
