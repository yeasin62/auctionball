<?php

namespace Database\Seeders;

use App\Models\AuctionState;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Wipe any prior demo data so seeding is idempotent.
            $existing = Organization::where('slug', 'demo')->first();
            if ($existing) {
                $existing->delete();
            }
            User::whereIn('email', ['admin@auctionball.test', 'captain@auctionball.test'])->delete();

            // Users — admin doubles as platform super-admin so /admin is reachable in dev
            $admin = new User([
                'name'     => 'Demo Admin',
                'email'    => 'admin@auctionball.test',
                'password' => Hash::make('password'),
            ]);
            $admin->is_super_admin = true;   // guarded; set explicitly
            $admin->save();

            $captain = User::create([
                'name'     => 'Karim Captain',
                'email'    => 'captain@auctionball.test',
                'password' => Hash::make('password'),
            ]);

            // Organization
            $org = Organization::create([
                'name'     => 'Demo Cricket Cup',
                'slug'     => 'demo',
                'plan'     => 'pro',
                'timezone' => 'Asia/Dhaka',
            ]);
            $org->users()->attach($admin->id,   ['role' => 'org_admin',  'last_active_at' => now()]);
            $org->users()->attach($captain->id, ['role' => 'team_owner', 'last_active_at' => now()]);

            // Active season
            $season = $org->seasons()->create([
                'name'            => 'BPL 2026',
                'year'            => 2026,
                'status'          => 'active',
                'budget_per_team' => 500000,
                'is_active'       => true,
                'start_date'      => now()->startOfMonth(),
                'end_date'        => now()->addMonths(2),
            ]);

            // Teams
            $teamSpecs = [
                ['Dhaka Dynamites',  'DHK'],
                ['Chittagong Kings', 'CTG'],
                ['Sylhet Strikers',  'SYL'],
                ['Khulna Tigers',    'KHL'],
            ];
            $teams = [];
            foreach ($teamSpecs as [$name, $short]) {
                $teams[$short] = Team::create([
                    'organization_id'  => $org->id,
                    'season_id'        => $season->id,
                    'name'             => $name,
                    'short_code'       => $short,
                    'initial_budget'   => 500000,
                    'remaining_budget' => 500000,
                    'device_token'     => Str::random(40),
                    'registered_at'    => now(),
                ]);
            }

            // Link captain to Dhaka
            DB::table('organization_user')
                ->where('organization_id', $org->id)
                ->where('user_id', $captain->id)
                ->update(['team_id' => $teams['DHK']->id]);
            $teams['DHK']->update(['owner_user_id' => $captain->id]);

            // Players — 12 players: 4 sold (one per team), rest in queue
            $playerSpecs = [
                // [name, category, type, base_price, jersey, batting, bowling, profession]
                ['Shakib Rahman',    'Elite',   'Old', 75000,  '7',  'Left-hand bat',  'Slow left-arm orthodox', 'Banker'],
                ['Tanveer Islam',    'Elite',   'Old', 60000,  '11', 'Right-hand bat', 'Right-arm fast',         'Engineer'],
                ['Mehedi Khan',      'Regular', 'Old', 40000,  '34', 'Right-hand bat', 'Right-arm off-spin',     'Doctor'],
                ['Rakib Hasan',      'Regular', 'New', 30000,  '21', 'Left-hand bat',  'Left-arm medium',        'Student'],
                ['Imran Siddique',   'Elite',   'Old', 65000,  '4',  'Right-hand bat', 'Right-arm leg-spin',     'Journalist'],
                ['Naim Sheikh',      'Regular', 'New', 25000,  '88', 'Left-hand bat',  'Right-arm medium',       'Trader'],
                ['Faisal Ahmed',     'Regular', 'Old', 35000,  '17', 'Right-hand bat', 'Right-arm fast-medium',  'Lawyer'],
                ['Sabbir Khan',      'New',     'New', 20000,  '99', 'Right-hand bat', '—',                      'Cricket coach'],
                ['Mahmud Ullah',     'Elite',   'Old', 70000,  '30', 'Right-hand bat', 'Right-arm off-spin',     'Architect'],
                ['Liton Das',        'Regular', 'Old', 45000,  '16', 'Right-hand bat', '—',                      'Designer'],
                ['Mosaddek Hossain', 'New',     'New', 22000,  '12', 'Right-hand bat', 'Right-arm off-spin',     'Student'],
                ['Afif Hossain',     'Regular', 'New', 28000,  '5',  'Left-hand bat',  'Right-arm off-break',    'Software engineer'],
            ];

            $players = [];
            foreach ($playerSpecs as [$name, $category, $type, $base, $jersey, $batting, $bowling, $profession]) {
                $players[] = Player::create([
                    'organization_id' => $org->id,
                    'season_id'       => $season->id,
                    'name'            => $name,
                    'category'        => $category,
                    'player_type'     => $type,
                    'base_price'      => $base,
                    'is_old_player'   => $type === 'Old',
                    'auction_status'  => 'queue',
                    'jersey_no'       => $jersey,
                    'batting_style'   => $batting,
                    'bowling_style'   => $bowling,
                    'profession'      => $profession,
                    'registered_at'   => now(),
                ]);
            }

            // Sell first 4 players to the 4 teams so dashboard shows budget movement
            $sales = [
                [0, 'DHK', 125000],   // Shakib → Dhaka
                [1, 'CTG', 110000],   // Tanveer → Chittagong
                [2, 'SYL', 60000],    // Mehedi → Sylhet
                [4, 'KHL', 90000],    // Imran → Khulna
            ];
            foreach ($sales as [$pIdx, $teamShort, $price]) {
                $p = $players[$pIdx];
                $t = $teams[$teamShort];
                $p->update([
                    'auction_status' => 'sold',
                    'sold_price'     => $price,
                    'team_id'        => $t->id,
                ]);
                $t->decrement('remaining_budget', $price);

                // Record a few bid trail rows leading up to the sale
                $steps = [$price - 20000, $price - 10000, $price];
                $bidders = [array_keys($teams)[($pIdx + 1) % 4], array_keys($teams)[($pIdx + 2) % 4], $teamShort];
                foreach ($steps as $i => $amount) {
                    $org->bids()->create([
                        'season_id' => $season->id,
                        'player_id' => $p->id,
                        'team_id'   => $teams[$bidders[$i]]->id,
                        'amount'    => $amount,
                        'placed_at' => now()->subMinutes(60 - $pIdx * 10 - $i),
                    ]);
                }
            }

            // Mark one as unsold for variety
            $players[7]->update(['auction_status' => 'unsold']);

            // Idle auction state (next player ready)
            AuctionState::updateOrCreate(
                ['organization_id' => $org->id, 'season_id' => $season->id],
                [
                    'current_player_id'      => $players[3]->id,  // Rakib up next
                    'highest_bid'            => 0,
                    'status'                 => 'idle',
                    'timer_duration_seconds' => 60,
                ]
            );

            $this->command?->info("Demo seeded → admin@auctionball.test / password   |   captain@auctionball.test / password");
        });
    }
}
