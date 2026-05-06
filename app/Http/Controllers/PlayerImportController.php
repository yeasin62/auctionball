<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PlayerImportController extends Controller
{
    private const HEADERS = [
        'name', 'category', 'player_type', 'position', 'base_price',
        'jersey_no', 'batting_style', 'bowling_style', 'profession',
    ];

    /** Download a sample CSV template. */
    public function template(): Response
    {
        $rows = [
            self::HEADERS,
            ['Shakib Rahman', 'Elite',   'Old', 'All-rounder', '75000', '7',  'Left-hand bat',  'Slow left-arm orthodox', 'Banker'],
            ['Tanveer Islam', 'Regular', 'New', 'Batter',      '30000', '11', 'Right-hand bat', 'Right-arm fast',         'Engineer'],
        ];

        $csv = '';
        foreach ($rows as $r) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $r)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="auctionball-players-template.csv"',
        ]);
    }

    /** Step 1 — parse + validate, return preview rows. Does NOT insert. */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        if (! $season) {
            return back()->with('error', 'Activate a season before importing players.');
        }

        $rows = $this->readCsv($request->file('file')->getRealPath());
        if (empty($rows)) {
            return back()->with('error', 'CSV is empty.');
        }

        $headerRow = array_map(fn ($v) => strtolower(trim($v)), $rows[0]);
        $missing = array_diff(self::HEADERS, $headerRow);
        if (! empty($missing)) {
            return back()->with('error', 'Missing columns: ' . implode(', ', $missing));
        }

        $col = array_flip($headerRow);
        $body = array_slice($rows, 1);

        $valid   = [];
        $invalid = [];

        $allowedPositions = implode(',', Player::positionsFor($season->sport ?? 'cricket'));

        foreach ($body as $i => $r) {
            $row = [
                'name'          => trim($r[$col['name']]          ?? ''),
                'category'      => trim($r[$col['category']]      ?? 'Regular'),
                'player_type'   => trim($r[$col['player_type']]   ?? 'New'),
                'position'      => trim($r[$col['position']]      ?? ''),
                'base_price'    => (int) trim($r[$col['base_price']]    ?? 0),
                'jersey_no'     => trim($r[$col['jersey_no']]     ?? ''),
                'batting_style' => trim($r[$col['batting_style']] ?? ''),
                'bowling_style' => trim($r[$col['bowling_style']] ?? ''),
                'profession'    => trim($r[$col['profession']]    ?? ''),
            ];

            $v = Validator::make($row, [
                'name'          => 'required|string|max:255',
                'category'      => 'required|in:Elite,Regular,New',
                'player_type'   => 'required|in:Old,New',
                'position'      => "nullable|in:{$allowedPositions}",
                'base_price'    => 'required|integer|min:0',
                'jersey_no'     => 'nullable|string|max:10',
                'batting_style' => 'nullable|string|max:50',
                'bowling_style' => 'nullable|string|max:50',
                'profession'    => 'nullable|string|max:100',
            ]);

            if ($v->fails()) {
                $invalid[] = ['row' => $i + 2, 'data' => $row, 'errors' => $v->errors()->all()];
            } else {
                $valid[] = $row;
            }
        }

        // Plan limit check
        $limits = $org->limits();
        $usedPlayers = $season->players()->count();
        $headroom = max(0, $limits['players'] - $usedPlayers);
        $willImport = min(count($valid), $headroom);
        $skippedForLimit = count($valid) - $willImport;

        // Stash valid rows in session for confirm step
        $token = bin2hex(random_bytes(16));
        cache()->put("player_import:{$org->id}:{$token}", $valid, now()->addMinutes(15));

        return Inertia::render('Dashboard/Players/Import', [
            'preview' => [
                'valid_count'      => count($valid),
                'invalid_count'    => count($invalid),
                'will_import'      => $willImport,
                'skipped_for_limit'=> $skippedForLimit,
                'headroom'         => $headroom,
                'token'            => $token,
                'valid'            => array_slice($valid, 0, 50),
                'invalid'          => array_slice($invalid, 0, 25),
            ],
            'limits' => $limits,
            'used'   => $usedPlayers,
            'season' => ['id' => $season->id, 'name' => $season->name],
        ]);
    }

    /** Step 2 — confirm + chunked insert. */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string|size:32']);
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        abort_unless($season, 422);

        $key = "player_import:{$org->id}:{$data['token']}";
        $rows = cache()->pull($key);
        if (! $rows) {
            return back()->with('error', 'Import session expired — re-upload the CSV.');
        }

        $limits   = $org->limits();
        $headroom = max(0, $limits['players'] - $season->players()->count());
        $rows     = array_slice($rows, 0, $headroom);

        $now = now();
        $insert = array_map(fn ($r) => [
            'organization_id' => $org->id,
            'season_id'       => $season->id,
            'name'            => $r['name'],
            'category'        => $r['category'],
            'player_type'     => $r['player_type'],
            'position'        => $r['position'] ?: null,
            'base_price'      => $r['base_price'],
            'is_old_player'   => $r['player_type'] === 'Old',
            'auction_status'  => 'queue',
            'jersey_no'       => $r['jersey_no'] ?: null,
            'batting_style'   => $r['batting_style'] ?: null,
            'bowling_style'   => $r['bowling_style'] ?: null,
            'profession'      => $r['profession'] ?: null,
            'registered_at'   => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ], $rows);

        // Chunked insert — scales to thousands of rows without memory blow-up.
        DB::transaction(function () use ($insert) {
            foreach (array_chunk($insert, 500) as $batch) {
                Player::insert($batch);
            }
        });

        return redirect()->route('dashboard.players.index')
            ->with('success', count($insert) . ' players imported.');
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $h = fopen($path, 'r');
        if (! $h) return [];
        while (($r = fgetcsv($h)) !== false) {
            $rows[] = $r;
        }
        fclose($h);
        return $rows;
    }
}
