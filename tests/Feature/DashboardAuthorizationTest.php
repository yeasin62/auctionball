<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_mutate_players(): void
    {
        [$user, $org] = $this->userInOrg('viewer');
        $season = $this->activeSeason($org);

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->post('/dashboard/players', [
                'name' => 'Blocked Player',
                'category' => 'Elite',
                'player_type' => 'New',
                'position' => 'Batter',
                'base_price' => 10000,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('players', [
            'season_id' => $season->id,
            'name' => 'Blocked Player',
        ]);
    }

    public function test_team_registration_route_is_not_captured_by_team_update_route(): void
    {
        [$user, $org] = $this->userInOrg('org_admin');
        $season = $this->activeSeason($org);

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->post('/dashboard/teams/registration', [
                'open' => true,
                'team_registration_fee' => 0,
                'team_registration_instructions' => 'Register your team.',
            ])
            ->assertRedirect();

        $this->assertTrue($season->fresh()->team_registration_open);
    }

    public function test_approving_pending_players_respects_plan_limit(): void
    {
        [$user, $org] = $this->userInOrg('org_admin');
        $season = $this->activeSeason($org);

        for ($i = 1; $i <= 44; $i++) {
            $this->player($season, ['name' => "Queued Player {$i}", 'auction_status' => 'queue']);
        }
        $pending = $this->player($season, ['name' => 'Pending Player', 'auction_status' => 'pending']);

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->post("/dashboard/players/{$pending->id}/approve")
            ->assertSessionHas('error');

        $this->assertSame('pending', $pending->fresh()->auction_status);
    }

    private function userInOrg(string $role): array
    {
        $user = User::factory()->create();

        $org = new Organization([
            'name' => 'Audit Org',
            'slug' => 'audit-org-' . strtolower($role),
        ]);
        $org->plan = 'free';
        $org->save();

        $org->users()->attach($user->id, [
            'role' => $role,
            'last_active_at' => now(),
        ]);

        return [$user, $org];
    }

    private function activeSeason(Organization $org): Season
    {
        return Season::create([
            'organization_id' => $org->id,
            'name' => 'Audit Season',
            'year' => 2026,
            'sport' => 'cricket',
            'status' => 'active',
            'budget_per_team' => 500000,
            'is_active' => true,
        ]);
    }

    private function player(Season $season, array $overrides = []): Player
    {
        return Player::create([
            'organization_id' => $season->organization_id,
            'season_id' => $season->id,
            'name' => 'Audit Player',
            'category' => 'Elite',
            'player_type' => 'New',
            'position' => 'Batter',
            'base_price' => 10000,
            'is_old_player' => false,
            'auction_status' => 'queue',
            'registered_at' => now(),
            ...$overrides,
        ]);
    }
}
