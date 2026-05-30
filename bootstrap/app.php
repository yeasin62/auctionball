<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\RedirectToCanonicalHost::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ResolveOrganizationByDomain::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Visitor tracker runs LAST so it sees the final response Content-Type
            // and can skip non-HTML (JSON, downloads, Inertia partial reloads).
            \App\Http\Middleware\RecordVisitor::class,
        ]);

        $middleware->alias([
            'org'         => \App\Http\Middleware\EnsureHasOrganization::class,
            'org-role'    => \App\Http\Middleware\EnsureOrgRole::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()) {
                return $response;
            }

            if ($status === 404 || (! app()->environment(['local', 'testing']) && in_array($status, [403, 500, 503], true))) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
