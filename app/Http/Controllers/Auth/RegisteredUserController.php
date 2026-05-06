<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'plans' => array_keys(Organization::PLAN_LIMITS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
            'org_name'    => 'required|string|max:255',
            'org_slug'    => [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/',
                Rule::unique('organizations', 'slug'),
            ],
            // 'plan' is intentionally accepted here only as a hint for the post-
            // registration redirect (deep-link preselection on /dashboard/billing).
            // The org is ALWAYS created on `free`. Paid plans must go through the
            // billing flow (PayPal callback or super-admin-approved bKash) so we
            // never grant an unpaid Pro/Enterprise. Bypassing this was a CRITICAL
            // self-upgrade vulnerability in earlier versions.
            'plan'        => ['nullable', Rule::in(array_keys(Organization::PLAN_LIMITS))],
        ], [
            'org_slug.regex' => 'Slug must be lowercase letters, numbers and dashes only (no leading/trailing dash).',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $org = new Organization([
                'name' => $data['org_name'],
                'slug' => $data['org_slug'],
            ]);
            $org->plan = 'free';   // explicit, bypasses $fillable change of `plan`
            $org->save();

            $org->users()->attach($user->id, [
                'role'           => 'org_admin',
                'last_active_at' => now(),
            ]);

            session(['current_organization_id' => $org->id]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
