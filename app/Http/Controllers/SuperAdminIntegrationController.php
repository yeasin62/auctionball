<?php

namespace App\Http\Controllers;

use App\Models\PlatformSettings;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminIntegrationController extends Controller
{
    public function index(): Response
    {
        $settings = PlatformSettings::current();

        return Inertia::render('SuperAdmin/Integrations', [
            'integrations' => [
                'openai' => [
                    'model' => $this->normalizeModel('openai', $settings->openai_model ?: config('services.openai.model', 'gpt-5.5')),
                    'has_database_key' => filled($settings->openai_api_key),
                    'has_env_key' => filled(config('services.openai.key')),
                    'masked_key' => $this->maskedKey($settings->openai_api_key, config('services.openai.key')),
                ],
                'anthropic' => [
                    'model' => $this->normalizeModel('anthropic', $settings->anthropic_model ?: config('services.anthropic.model', 'claude-opus-4-1-20250805')),
                    'has_database_key' => filled($settings->anthropic_api_key),
                    'has_env_key' => filled(config('services.anthropic.key')),
                    'masked_key' => $this->maskedKey($settings->anthropic_api_key, config('services.anthropic.key')),
                ],
                'ai_provider' => $settings->ai_provider ?: 'auto',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'openai_model' => ['required', 'string', 'max:80'],
            'clear_openai_api_key' => ['boolean'],
            'anthropic_api_key' => ['nullable', 'string', 'max:500'],
            'anthropic_model' => ['required', 'string', 'max:100'],
            'clear_anthropic_api_key' => ['boolean'],
            'ai_provider' => ['required', 'in:auto,openai,anthropic'],
        ]);

        $settings = PlatformSettings::current();
        $settings->ai_provider = $data['ai_provider'];
        $settings->openai_model = $data['openai_model'];
        $settings->anthropic_model = $data['anthropic_model'];

        if ($request->boolean('clear_openai_api_key')) {
            $settings->openai_api_key = null;
        } elseif (filled($data['openai_api_key'] ?? null)) {
            $settings->openai_api_key = $data['openai_api_key'];
        }

        if ($request->boolean('clear_anthropic_api_key')) {
            $settings->anthropic_api_key = null;
        } elseif (filled($data['anthropic_api_key'] ?? null)) {
            $settings->anthropic_api_key = $data['anthropic_api_key'];
        }

        $settings->save();

        Audit::log('integrations.updated', 'Integration settings updated', [
            'ai_provider' => $settings->ai_provider,
            'openai_model' => $settings->openai_model,
            'openai_key_saved' => filled($settings->openai_api_key),
            'anthropic_model' => $settings->anthropic_model,
            'anthropic_key_saved' => filled($settings->anthropic_api_key),
        ]);

        return back()->with('success', 'Integration settings updated.');
    }

    public function models(string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['openai', 'anthropic'], true), 404);

        $settings = PlatformSettings::current();
        $key = $provider === 'openai'
            ? ($settings->openai_api_key ?: config('services.openai.key'))
            : ($settings->anthropic_api_key ?: config('services.anthropic.key'));

        if (! filled($key)) {
            return response()->json([
                'models' => [],
                'error' => 'API key is not configured.',
            ], 422);
        }

        try {
            $models = $provider === 'openai'
                ? $this->openAiModels($key)
                : $this->anthropicModels($key);

            return response()->json(['models' => $models]);
        } catch (\Throwable $e) {
            Log::warning('AI model list fetch failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'models' => [],
                'error' => 'Could not fetch model list.',
            ], 502);
        }
    }

    private function normalizeModel(string $provider, ?string $model): string
    {
        $model = trim((string) $model);
        $bad = [
            'anthropic' => ['claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5', 'claude-haiku-4-5-20251001'],
        ];

        if ($provider === 'openai') {
            return $model === '' ? 'gpt-5.5' : $model;
        }

        return $model === '' || in_array($model, $bad['anthropic'], true) ? 'claude-opus-4-1-20250805' : $model;
    }

    private function openAiModels(string $key): array
    {
        return collect(Http::withToken($key)
            ->acceptJson()
            ->timeout(12)
            ->get('https://api.openai.com/v1/models')
            ->throw()
            ->json('data', []))
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && $this->isOpenAiTextModel($id))
            ->unique()
            ->values()
            ->map(fn (string $id) => [
                'value' => $id,
                'label' => $this->modelLabel($id),
            ])
            ->all();
    }

    private function anthropicModels(string $key): array
    {
        return collect(Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->timeout(12)
            ->get('https://api.anthropic.com/v1/models', ['limit' => 1000])
            ->throw()
            ->json('data', []))
            ->map(fn (array $model) => [
                'value' => $model['id'] ?? null,
                'label' => $model['display_name'] ?? $this->modelLabel((string) ($model['id'] ?? '')),
            ])
            ->filter(fn (array $model) => filled($model['value']))
            ->unique('value')
            ->values()
            ->all();
    }

    private function modelLabel(string $id): string
    {
        return str($id)
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }

    private function isOpenAiTextModel(string $id): bool
    {
        return (bool) preg_match('/^(gpt-|chatgpt-|o[1-9](?:-|$))/', $id);
    }

    private function maskedKey(?string $databaseKey, ?string $envKey): ?string
    {
        $key = $databaseKey ?: $envKey;
        if (! filled($key)) {
            return null;
        }

        $key = trim($key);
        $prefix = str($key)->before('-')->toString();
        $suffix = str($key)->substr(-4)->toString();

        return $prefix !== ''
            ? "{$prefix}-..." . $suffix
            : '...' . $suffix;
    }
}
