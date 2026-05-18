<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        $filters = $request->only(['q', 'category', 'type', 'status']);

        $playersQuery = $season
            ? $season->players()->with('team:id,name')
            : Player::query()->whereRaw('1 = 0');

        if ($q = $filters['q'] ?? null)        $playersQuery->where('name', 'like', "%{$q}%");
        if ($c = $filters['category'] ?? null) $playersQuery->where('category', $c);
        if ($t = $filters['type'] ?? null)     $playersQuery->where('player_type', $t);
        if ($s = $filters['status'] ?? null)   $playersQuery->where('auction_status', $s);

        $players = $playersQuery->orderBy('name')->paginate(25)->withQueryString();

        $pendingCount = $season ? $season->players()->where('auction_status', 'pending')->count() : 0;
        $sport        = $season?->sport ?? 'cricket';

        return Inertia::render('Dashboard/Players/Index', [
            'season'        => $season ? [
                'id'                => $season->id,
                'name'              => $season->name,
                'sport'             => $sport,
                'player_categories' => $season->categoryList(),
            ] : null,
            'players'       => $players,
            'filters'       => $filters,
            'limits'        => $org->limits(),
            'used'          => $season ? $season->players()->count() : 0,
            'pending_count' => $pendingCount,
            'positions'     => Player::positionsFor($sport),
            // Org-defined custom fields from the form-builder. Admin's manual
            // create/edit player form needs the same schema so they can fill
            // these in (and edit existing responses) without going through the
            // public registration page.
            'custom_fields' => $season?->registration_form_schema ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        if (! $season) {
            return back()->with('error', 'Create and activate a season before adding players.');
        }

        $limits = $org->limits();
        if ($season->players()->count() >= $limits['players']) {
            return back()->with('error', "Your {$org->plan} plan allows {$limits['players']} players per season.");
        }

        $rules = [
            'name'          => 'required|string|max:255',
            'category'      => ['required', Rule::in($season->categoryNames())],
            'player_type'   => ['required', Rule::in(Player::TYPES)],
            'position'      => ['nullable', Rule::in(Player::positionsFor($season->sport ?? 'cricket'))],
            'base_price'    => 'required|integer|min:0',
            'jersey_no'     => 'nullable|string|max:10',
            'batting_style' => 'nullable|string|max:50',
            'bowling_style' => 'nullable|string|max:50',
            'profession'    => 'nullable|string|max:100',
            'photo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];

        $customFields = $season->registration_form_schema ?? [];
        $rules = $this->mergeCustomRules($rules, $customFields, $request->input('custom', []));

        $data = $request->validate($rules);

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $photoUrl = $this->storePhoto($request->file('photo'), $org->id, $season->id);
        }

        $season->players()->create([
            ...collect($data)->except(['photo', 'custom'])->all(),
            'photo_url'         => $photoUrl,
            'organization_id'   => $org->id,
            'auction_status'    => 'queue',
            'is_old_player'     => $data['player_type'] === 'Old',
            'registered_at'     => now(),
            'registration_data' => $this->buildRegistrationData($customFields, $request, $org->id, $season->id) ?: null,
        ]);

        return back()->with('success', "Player “{$data['name']}” added to queue.");
    }

    public function update(Request $request, Player $player): RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        abort_if($player->organization_id !== $org->id, 404);

        $season = $org->activeSeason();
        $sport  = $season?->sport ?? 'cricket';

        $rules = [
            'name'          => 'required|string|max:255',
            'category'      => ['required', Rule::in($season ? $season->categoryNames() : array_column(\App\Models\Season::DEFAULT_PLAYER_CATEGORIES, 'name'))],
            'player_type'   => ['required', Rule::in(Player::TYPES)],
            'position'      => ['nullable', Rule::in(Player::positionsFor($sport))],
            'base_price'    => 'required|integer|min:0',
            'jersey_no'     => 'nullable|string|max:10',
            'batting_style' => 'nullable|string|max:50',
            'bowling_style' => 'nullable|string|max:50',
            'profession'    => 'nullable|string|max:100',
            'photo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];

        $customFields = $season?->registration_form_schema ?? [];
        $rules = $this->mergeCustomRules($rules, $customFields, $request->input('custom', []));

        $data = $request->validate($rules);

        // If a new photo arrives, replace + delete old.
        if ($request->hasFile('photo')) {
            if ($player->photo_url) $this->deletePhoto($player->photo_url);
            $data['photo_url'] = $this->storePhoto($request->file('photo'), $org->id, $player->season_id);
        }

        $update = collect($data)->except(['photo', 'custom'])->all();
        $update['is_old_player'] = $data['player_type'] === 'Old';

        // Merge incoming custom-field values into existing registration_data.
        // Image fields without a fresh upload keep the old URL — admins can
        // edit just the text fields without re-uploading every photo.
        $existing = $player->registration_data ?? [];
        $rebuilt  = $this->buildRegistrationData($customFields, $request, $org->id, $player->season_id, $existing);
        $update['registration_data'] = $rebuilt ?: null;

        $player->update($update);

        return back()->with('success', "Player “{$player->name}” updated.");
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($player->organization_id !== $org->id, 404);

        if ($player->photo_url) {
            $this->deletePhoto($player->photo_url);
        }

        $name = $player->name;
        $player->delete();

        return back()->with('success', "Player “{$name}” deleted.");
    }

    /** Approve a publicly-registered player → moves them into auction queue. */
    public function approve(Request $request, Player $player): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($player->organization_id !== $org->id, 404);

        $player->update(['auction_status' => 'queue']);
        return back()->with('success', "“{$player->name}” approved.");
    }

    /** Bulk approve all pending players. */
    public function approveAll(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        if (! $season) return back();

        $count = $season->players()->where('auction_status', 'pending')->update(['auction_status' => 'queue']);
        return back()->with('success', "{$count} player(s) approved.");
    }

    public function reject(Request $request, Player $player): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        abort_if($player->organization_id !== $org->id, 404);

        if ($player->photo_url) $this->deletePhoto($player->photo_url);
        $name = $player->name;
        $player->delete();

        return back()->with('success', "Registration of “{$name}” rejected.");
    }

    /**
     * Store a player photo on the configured filesystem disk.
     *
     * For dev: FILESYSTEM_DISK=public → stored under storage/app/public, served via /storage symlink.
     * For prod: FILESYSTEM_DISK=s3   → stored on S3/R2 with no code change.
     */
    private function storePhoto($file, int $orgId, int $seasonId): string
    {
        $disk = config('filesystems.default');
        $path = $file->store("orgs/{$orgId}/seasons/{$seasonId}/players", $disk);
        return Storage::disk($disk)->url($path);
    }

    private function deletePhoto(string $url): void
    {
        $disk = config('filesystems.default');
        $base = rtrim(config("filesystems.disks.{$disk}.url", '') ?? '', '/');
        if ($base && str_starts_with($url, $base)) {
            $path = ltrim(substr($url, strlen($base)), '/');
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Append per-type validation rules for org-defined custom fields. Mirrors
     * PublicRegistrationController::store so the admin form accepts the same
     * data shape as the public-facing form.
     */
    private function mergeCustomRules(array $rules, array $customFields, array $custom): array
    {
        foreach ($customFields as $f) {
            $key = "custom.{$f['id']}";
            $req = ($f['required'] ?? false) ? 'required' : 'nullable';
            $rules[$key] = match ($f['type']) {
                'heading'  => 'sometimes',
                'text'     => "{$req}|string|max:255",
                'textarea' => "{$req}|string|max:2000",
                'number'   => "{$req}|numeric",
                'email'    => "{$req}|email|max:160",
                'phone'    => "{$req}|string|max:32|regex:/^[0-9+\-\s()]+$/",
                'url'      => "{$req}|url|max:500",
                'date'     => "{$req}|date",
                'time'     => "{$req}|date_format:H:i",
                'select'   => [$req, Rule::in($f['options'] ?? [])],
                'radio'    => [$req, Rule::in($f['options'] ?? [])],
                'multi'    => "{$req}|array",
                'checkbox' => $req === 'required' ? 'accepted' : 'sometimes|in:0,1,true,false,on,off,yes,no',
                // Image — on edit the field may be sent as a URL string (kept
                // value); on create it must be a fresh file upload.
                'image'    => "{$req}|file|image|mimes:jpg,jpeg,png,webp|max:5120",
                'payment'  => "{$req}|string|max:100",
                default    => "{$req}|string|max:255",
            };
            if ($f['type'] === 'multi') {
                $rules["custom.{$f['id']}.*"] = [Rule::in($f['options'] ?? [])];
            }
        }
        return $rules;
    }

    /**
     * Build the registration_data JSON from the request's `custom.*` payload.
     * `$existing` (used by update) preserves image URLs when the admin doesn't
     * upload a fresh file — the field is just absent from the request.
     */
    private function buildRegistrationData(array $customFields, Request $request, int $orgId, int $seasonId, array $existing = []): array
    {
        $custom = $request->input('custom', []);
        $disk   = config('filesystems.default');
        $out    = [];

        foreach ($customFields as $f) {
            if ($f['type'] === 'heading') continue;

            // Image fields: prefer fresh upload; else keep existing URL.
            if ($f['type'] === 'image') {
                $file = $request->file("custom.{$f['id']}");
                if ($file) {
                    $path = $file->store("orgs/{$orgId}/seasons/{$seasonId}/registration", $disk);
                    $out[$f['id']] = ['label' => $f['label'], 'type' => 'image', 'value' => Storage::disk($disk)->url($path)];
                } elseif (! empty($existing[$f['id']])) {
                    $out[$f['id']] = $existing[$f['id']];
                }
                continue;
            }

            $val = $custom[$f['id']] ?? null;
            if ($val === null || $val === '' || (is_array($val) && empty($val))) continue;

            if ($f['type'] === 'checkbox') {
                if (! filter_var($val, FILTER_VALIDATE_BOOLEAN)) continue;
                $out[$f['id']] = ['label' => $f['label'], 'value' => 'Yes'];
                continue;
            }
            if ($f['type'] === 'multi' && is_array($val)) {
                $out[$f['id']] = [
                    'label'  => $f['label'],
                    'value'  => implode(', ', $val),
                    'values' => array_values($val),
                ];
                continue;
            }
            $out[$f['id']] = ['label' => $f['label'], 'value' => $val];
        }

        return $out;
    }
}
