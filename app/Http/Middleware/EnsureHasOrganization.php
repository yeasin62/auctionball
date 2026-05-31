<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $org = $user->currentOrganization();

        if (! $org) {
            // Super admins often have no org of their own — bounce them to the
            // platform panel instead of forcing them through /register.
            if ($user->is_super_admin) {
                return redirect()->route('admin.index');
            }
            return redirect()->route('register')
                ->with('error', 'You need to create an organization first.');
        }

        $request->attributes->set('current_organization', $org);

        return $next($request);
    }
}
