<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicRegistrationController extends Controller
{
    public function show(string $token): Response
    {
        $season = Season::where('registration_token', $token)
            ->where('registration_open', true)
            ->firstOrFail();

        $org = $season->organization;

        return Inertia::render('Public/PlayerRegister', [
            'org'    => [
                'name'             => $org->name,
                'slug'             => $org->slug,
                'logo_url'         => $org->logo_url,
                'display_currency' => $org->display_currency,
                'bdt_per_usd'      => (int) $org->bdt_per_usd,
            ],
            'season' => [
                'name'                      => $season->name,
                'year'                      => $season->year,
                'sport'                     => $season->sport ?? 'cricket',
                'registration_fee'          => (int) $season->registration_fee,
                'registration_instructions' => $season->registration_instructions,
                'token'                     => $season->registration_token,
                'custom_fields'             => $season->registration_form_schema ?? [],
                'player_categories'         => $season->categoryList(),
            ],
            'positions' => Player::positionsFor($season->sport ?? 'cricket'),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $season = Season::where('registration_token', $token)
            ->where('registration_open', true)
            ->firstOrFail();

        $org = $season->organization;

        // If the org has added a custom payment field, the legacy TrxID block
        // is hidden on the form — relax its requirement so submission works.
        $customSchema    = $season->registration_form_schema ?? [];
        $hasPaymentField = collect($customSchema)->contains(fn ($f) => ($f['type'] ?? null) === 'payment');

        // Built-in field rules.
        $rules = [
            'name'                => 'required|string|max:255',
            'category'            => ['required', Rule::in($season->categoryNames())],
            'position'            => ['nullable', Rule::in(Player::positionsFor($season->sport ?? 'cricket'))],
            'jersey_no'           => 'nullable|string|max:10',
            'batting_style'       => 'nullable|string|max:50',
            'bowling_style'       => 'nullable|string|max:50',
            'profession'          => 'nullable|string|max:100',
            'photo'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'registration_txn_id' => ($season->registration_fee > 0 && ! $hasPaymentField)
                ? 'required|string|max:100'
                : 'nullable|string|max:100',
        ];

        // Dynamic rules from the org's custom-fields schema.
        $customFields = $season->registration_form_schema ?? [];
        $custom       = $request->input('custom', []);

        foreach ($customFields as $f) {
            // Conditional fields: if the condition is not satisfied, the field
            // is hidden on the client and any submitted value is ignored. Treat
            // as nullable (never required) to avoid blocking submission.
            $conditionMet = $this->isConditionMet($f, $customFields, $custom);
            $key  = "custom.{$f['id']}";
            $base = $conditionMet && ($f['required'] ?? false) ? 'required' : 'nullable';

            $req = $base === 'required' ? 'required' : 'nullable';
            $rules[$key] = match ($f['type']) {
                'heading'  => 'sometimes',                                              // visual only — no input collected
                'text'     => "{$base}|string|max:255",
                'textarea' => "{$base}|string|max:2000",
                'number'   => "{$base}|numeric",
                'email'    => "{$base}|email|max:160",
                'phone'    => "{$base}|string|max:32|regex:/^[0-9+\-\s()]+$/",         // allow + - space ( ) and digits
                'url'      => "{$base}|url|max:500",
                'date'     => "{$base}|date",
                'time'     => "{$base}|date_format:H:i",
                'select'   => [$req, Rule::in($f['options'] ?? [])],
                'radio'    => [$req, Rule::in($f['options'] ?? [])],
                'multi'    => "{$base}|array",                                          // each option validated below
                // Required checkbox must actually be ticked → `accepted` rejects
                // unchecked. Otherwise just gate against junk values.
                'checkbox' => $req === 'required' ? 'accepted' : 'sometimes|in:0,1,true,false,on,off,yes,no',
                'image'    => "{$base}|image|mimes:jpg,jpeg,png,webp|max:5120",
                // Payment field — value submitted is the customer's TrxID /
                // bank reference. The list of methods displayed is server-side
                // schema-only (no client trust needed).
                'payment'  => "{$base}|string|max:100",
                default    => "{$base}|string|max:255",
            };
            // For multi-select, also constrain each chosen value to the configured options.
            if ($f['type'] === 'multi') {
                $rules["custom.{$f['id']}.*"] = [Rule::in($f['options'] ?? [])];
            }
        }

        $data = $request->validate($rules);

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $disk = config('filesystems.default');
            $path = $request->file('photo')->store("orgs/{$org->id}/seasons/{$season->id}/players", $disk);
            $photoUrl = Storage::disk($disk)->url($path);
        }

        // Pluck custom-field responses keyed by field id, label-paired for display.
        // For `image` fields the actual file lives in $request->file('custom.{id}'),
        // so we upload it and store the resulting URL as the value.
        $disk             = config('filesystems.default');
        $registrationData = [];
        foreach ($customFields as $f) {
            // Skip fields whose condition isn't met — they're hidden on the
            // client; recording their value would surface them in the player
            // detail card with no context.
            if (! $this->isConditionMet($f, $customFields, $custom)) continue;

            // Section headers carry no value.
            if ($f['type'] === 'heading') continue;

            if ($f['type'] === 'image') {
                $file = $request->file("custom.{$f['id']}");
                if (! $file) continue;
                $path = $file->store("orgs/{$org->id}/seasons/{$season->id}/registration", $disk);
                $registrationData[$f['id']] = [
                    'label' => $f['label'],
                    'type'  => 'image',
                    'value' => Storage::disk($disk)->url($path),
                ];
                continue;
            }

            $val = $data['custom'][$f['id']] ?? null;
            if ($val === null || $val === '' || (is_array($val) && empty($val))) continue;

            // Yes/No checkbox: only record when actually ticked, and store a
            // human-friendly "Yes" so player detail cards don't show "false".
            if ($f['type'] === 'checkbox') {
                if (! filter_var($val, FILTER_VALIDATE_BOOLEAN)) continue;
                $registrationData[$f['id']] = ['label' => $f['label'], 'value' => 'Yes'];
                continue;
            }

            // Multi-select: comma-join for display; raw array also kept under `values`.
            if ($f['type'] === 'multi' && is_array($val)) {
                $registrationData[$f['id']] = [
                    'label'  => $f['label'],
                    'value'  => implode(', ', $val),
                    'values' => array_values($val),
                ];
                continue;
            }

            $registrationData[$f['id']] = ['label' => $f['label'], 'value' => $val];
        }

        $season->players()->create([
            'organization_id'     => $org->id,
            'name'                => $data['name'],
            'photo_url'           => $photoUrl,
            'category'            => $data['category'],
            'player_type'         => 'New',
            'position'            => $data['position'] ?? null,
            'base_price'          => $season->basePriceForCategory($data['category']) ?? 10000,
            'is_old_player'       => false,
            'auction_status'      => 'pending',
            'jersey_no'           => $data['jersey_no']     ?? null,
            'batting_style'       => $data['batting_style'] ?? null,
            'bowling_style'       => $data['bowling_style'] ?? null,
            'profession'          => $data['profession']    ?? null,
            'registration_txn_id' => $data['registration_txn_id'] ?? null,
            'registration_data'   => $registrationData ?: null,
            'registered_at'       => now(),
        ]);

        return redirect()
            ->route('public-register.show', $token)
            ->with('success', 'Registration submitted! The organizer will review and approve your entry.');
    }

    /**
     * Server-side mirror of the client's conditional-visibility evaluator.
     * Returns true when a field should be active (rendered + validated).
     * Unknown source / operator → defaults to true (fail-open) so a malformed
     * schema never blocks a real submission.
     */
    private function isConditionMet(array $field, array $allFields, array $values): bool
    {
        if (empty($field['conditional'])) return true;

        $cond = $field['conditional'];
        $sourceVal = $values[$cond['field'] ?? ''] ?? null;

        return match ($cond['operator'] ?? null) {
            'equals'     => (string) $sourceVal === (string) ($cond['value'] ?? ''),
            'not_equals' => (string) $sourceVal !== (string) ($cond['value'] ?? ''),
            'is_set'     => $sourceVal !== null && $sourceVal !== '' && $sourceVal !== false,
            'is_empty'   => $sourceVal === null || $sourceVal === '' || $sourceVal === false,
            default      => true,
        };
    }
}
