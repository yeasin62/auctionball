<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\PlatformSettings;
use App\Support\Locales;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user        = $request->user();
        $currentOrg  = null;
        $orgList     = [];
        $role        = null;

        if ($user) {
            $orgs = $user->organizations()->orderBy('name')->get();
            $orgList = $orgs->map(fn ($o) => [
                'id'   => $o->id,
                'name' => $o->name,
                'slug' => $o->slug,
                'plan' => $o->plan,
                'role' => $o->pivot->role,
            ])->values();

            $currentId = session('current_organization_id');
            $currentOrg = $orgs->firstWhere('id', $currentId) ?? $orgs->first();

            if ($currentOrg) {
                session(['current_organization_id' => $currentOrg->id]);
                $role = $currentOrg->pivot->role;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'is_super_admin' => $user->isSuperAdmin(),
                ] : null,
                'role'          => $role,
                'impersonating' => (bool) $request->session()->get('impersonating_from'),
            ],
            'currentOrg' => $currentOrg ? [
                'id'               => $currentOrg->id,
                'name'             => $currentOrg->name,
                'slug'             => $currentOrg->slug,
                'plan'             => $currentOrg->plan,
                'limits'           => $currentOrg->limits(),
                'logo_url'         => $currentOrg->logo_url,
                // useFmt() reads these — without them every page falls back
                // to BDT regardless of saved setting.
                'display_currency' => $currentOrg->display_currency,
                'bdt_per_usd'      => (int) $currentOrg->bdt_per_usd,
                'is_white_label'   => $currentOrg->isWhiteLabel(),
            ] : null,
            'organizations' => $orgList,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'locale'    => app()->getLocale(),
            'locales'   => Locales::forFrontend(),
            'appDomain'             => PlatformSettings::current()->app_domain,
            'appLogo'               => PlatformSettings::current()->app_logo_url,
            'landingPaymentMethods' => PlatformSettings::current()->enabledLandingPaymentMethods(),
        ];
    }
}
