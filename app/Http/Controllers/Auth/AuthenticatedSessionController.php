<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Send role-appropriate users to their natural home:
        //   · team_owner → straight to the bidding page (their primary tool)
        //   · everyone else (org_admin, auctioneer, viewer, super_admin) → dashboard
        $user = Auth::user();
        $org  = $user->organizations()->orderBy('name')->first();

        if ($org) {
            $request->session()->put('current_organization_id', $org->id);
            if ($org->pivot->role === 'team_owner') {
                return redirect()->intended(route('bid.show', absolute: false));
            }
        }

        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('admin.index', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
