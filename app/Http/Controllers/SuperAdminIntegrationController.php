<?php

namespace App\Http\Controllers;

use App\Models\PlatformSettings;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                ],
                'anthropic' => [
                    'model' => $this->normalizeModel('anthropic', $settings->anthropic_model ?: config('services.anthropic.model', 'claude-opus-4-1-20250805')),
                    'has_database_key' => filled($settings->anthropic_api_key),
                    'has_env_key' => filled(config('services.anthropic.key')),
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
}
