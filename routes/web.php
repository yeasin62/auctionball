<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrgPagesController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamDeviceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public sitemap — referenced from public/robots.txt; pings crawlers' indexers.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/', function () {
    $plans = \App\Models\PlanPricing::orderBy('sort_order')->get(['slug','price_bdt','seasons_limit','players_limit','teams_limit'])
        ->map(fn ($p) => [
            'slug'          => $p->slug,
            'price_bdt'     => (int) $p->price_bdt,
            'seasons_limit' => (int) $p->seasons_limit,
            'players_limit' => (int) $p->players_limit,
            'teams_limit'   => (int) $p->teams_limit,
        ]);
    return Inertia::render('Landing', [
        'plans'     => $plans,
        'unlimited' => \App\Models\PlanPricing::UNLIMITED,
    ]);
})->name('home');

// Language switcher (works for guests via cookie, signed-in users via DB)
Route::post('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Public help / user guide — markdown-driven, locale-aware, indexable.
Route::get('/help',         [HelpController::class, 'index'])->name('help.index');
Route::get('/help/{slug}',  [HelpController::class, 'show']) ->name('help.show');

// Public team-device bidding link (token-based — no login). Bid POSTs are
// rate-limited per IP — env var drives the threshold so prod can tighten /
// loosen without a deploy. Default 60/min is generous for a real captain.
$bidThrottle = 'throttle:' . (int) env('THROTTLE_BIDS_PER_MINUTE', 60) . ',1';

Route::get ('/join/{token}',     [TeamDeviceController::class, 'show'])->name('team-device.show');
Route::post('/join/{token}/bid', [TeamDeviceController::class, 'bid'])
    ->middleware($bidThrottle)
    ->name('team-device.bid');

// Logged-in bidding — captains who have an AuctionBall account use this
Route::middleware(['auth', 'org'])->group(function () use ($bidThrottle) {
    Route::get ('/bid', [TeamDeviceController::class, 'forCurrentUser'])->name('bid.show');
    Route::post('/bid', [TeamDeviceController::class, 'bidAsUser'])
        ->middleware($bidThrottle)
        ->name('bid.place');
});

// Public invite acceptance
Route::get ('/invite/{token}', [InvitationController::class, 'show'])  ->name('invite.show');
Route::post('/invite/{token}', [InvitationController::class, 'accept'])->name('invite.accept');

// Public player registration link — throttled to prevent abuse (mass fake
// registrations + 5 MB photo uploads filling disk and exhausting the org's
// player cap). 10 submissions / minute per IP is generous for real humans
// filling a form yet kills automated spam.
Route::get ('/r/{token}', [PublicRegistrationController::class, 'show']) ->name('public-register.show');
Route::post('/r/{token}', [PublicRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('public-register.store');

// Public team registration — separate token namespace from player registration.
Route::get ('/tr/{token}', [\App\Http\Controllers\PublicTeamRegistrationController::class, 'show']) ->name('public-team-register.show');
Route::post('/tr/{token}', [\App\Http\Controllers\PublicTeamRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('public-team-register.store');

// Billing return URLs (user-facing, may or may not be authenticated when provider redirects)
Route::get('/billing/callback/{provider}', [BillingController::class, 'callback'])->name('billing.callback');

// Billing webhooks (server-to-server, no auth, no CSRF)
Route::post('/billing/webhook/{provider}', [BillingController::class, 'webhook'])
    ->name('billing.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth', 'org'])->prefix('dashboard')->name('dashboard.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('home');

    // Seasons
    Route::get   ('/seasons',                  [SeasonController::class, 'index'])   ->name('seasons.index');
    Route::post  ('/seasons',                  [SeasonController::class, 'store'])   ->name('seasons.store');
    Route::patch ('/seasons/{season}',         [SeasonController::class, 'update'])  ->name('seasons.update');
    Route::post  ('/seasons/{season}/activate',[SeasonController::class, 'activate'])->name('seasons.activate');
    Route::post  ('/seasons/{season}/registration', [SeasonController::class, 'toggleRegistration'])->name('seasons.registration');
    Route::post  ('/seasons/{season}/registration/regenerate', [SeasonController::class, 'regenerateRegistrationToken'])->name('seasons.registration.regenerate');
    Route::post  ('/seasons/{season}/registration/form',       [SeasonController::class, 'updateRegistrationForm'])    ->name('seasons.registration.form');
    Route::get   ('/seasons/{season}/export.pdf', [ExportController::class, 'seasonSummaryPdf'])->name('seasons.export.pdf');

    // Players
    Route::get   ('/players',                  [PlayerController::class, 'index'])    ->name('players.index');
    Route::post  ('/players',                  [PlayerController::class, 'store'])    ->name('players.store');
    Route::post  ('/players/{player}',         [PlayerController::class, 'update'])   ->name('players.update');
    Route::delete('/players/{player}',         [PlayerController::class, 'destroy'])  ->name('players.destroy');
    Route::post  ('/players/{player}/approve', [PlayerController::class, 'approve'])  ->name('players.approve');
    Route::delete('/players/{player}/reject',  [PlayerController::class, 'reject'])   ->name('players.reject');
    Route::post  ('/players/approve-all',      [PlayerController::class, 'approveAll'])->name('players.approve-all');

    // Player CSV import
    Route::get ('/players/import/template', [PlayerImportController::class, 'template'])->name('players.import.template');
    Route::post('/players/import',          [PlayerImportController::class, 'preview']) ->name('players.import.preview');
    Route::post('/players/import/confirm',  [PlayerImportController::class, 'confirm']) ->name('players.import.confirm');

    // Player exports
    Route::get('/players/export.csv', [ExportController::class, 'playersCsv'])->name('players.export.csv');
    Route::get('/players/export.pdf', [ExportController::class, 'playersPdf'])->name('players.export.pdf');

    // Teams
    Route::get   ('/teams',                            [TeamController::class, 'index'])     ->name('teams.index');
    Route::post  ('/teams',                            [TeamController::class, 'store'])     ->name('teams.store');
    Route::post  ('/teams/{team}',                     [TeamController::class, 'update'])    ->name('teams.update');
    Route::delete('/teams/{team}',                     [TeamController::class, 'destroy'])   ->name('teams.destroy');
    Route::post  ('/teams/{team}/approve',             [TeamController::class, 'approve'])   ->name('teams.approve');
    Route::delete('/teams/{team}/reject',              [TeamController::class, 'reject'])    ->name('teams.reject');
    Route::post  ('/teams/registration',               [TeamController::class, 'toggleRegistration'])      ->name('teams.registration');
    Route::post  ('/teams/registration/regenerate',    [TeamController::class, 'regenerateRegistrationToken'])->name('teams.registration.regenerate');

    Route::get('/teams/export.csv', [ExportController::class, 'teamsCsv'])->name('teams.export.csv');
    Route::get('/teams/export.pdf', [ExportController::class, 'teamsPdf'])->name('teams.export.pdf');

    // Auction control
    Route::get ('/auction',    [AuctionController::class, 'control'])  ->name('auction.control');
    Route::get ('/bigscreen',  [AuctionController::class, 'bigscreen'])->name('auction.bigscreen');
    Route::get ('/rosters',    [AuctionController::class, 'rosters'])  ->name('auction.rosters');

    Route::post('/auction/player',  [AuctionController::class, 'setPlayer'])->name('auction.set-player');
    Route::post('/auction/start',   [AuctionController::class, 'start'])    ->name('auction.start');
    Route::post('/auction/pause',   [AuctionController::class, 'pause'])    ->name('auction.pause');
    Route::post('/auction/resume', [AuctionController::class, 'resume'])    ->name('auction.resume');
    Route::post('/auction/sold',    [AuctionController::class, 'sold'])     ->name('auction.sold');
    Route::post('/auction/unsold',  [AuctionController::class, 'unsold'])   ->name('auction.unsold');
    Route::post('/auction/reset',   [AuctionController::class, 'reset'])    ->name('auction.reset');
    Route::post('/auction/extend',  [AuctionController::class, 'extendTimer'])->name('auction.extend');
    Route::post('/auction/bid',     [AuctionController::class, 'placeBid']) ->name('auction.bid');
    Route::post('/auction/finalize', [AuctionController::class, 'autoFinalize'])      ->name('auction.finalize');
    Route::post('/auction/auto-finalize', [AuctionController::class, 'setAutoFinalize'])->name('auction.set-auto-finalize');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Audit log
    Route::get('/audit',     [AuditLogController::class, 'index'])->name('audit.index');

    // Org pages
    Route::get ('/users',             [OrgPagesController::class, 'users'])         ->name('users.index');
    Route::get ('/settings',                [OrgPagesController::class, 'settings'])             ->name('settings.index');
    Route::post('/settings/currency',       [OrgPagesController::class, 'updateCurrency'])       ->name('settings.currency');
    Route::post('/settings/domain',         [OrgPagesController::class, 'updateCustomDomain'])   ->name('settings.domain');
    Route::post('/settings/domain/verify',  [OrgPagesController::class, 'verifyCustomDomain'])   ->name('settings.domain.verify');

    // Billing (admin)
    Route::get ('/billing',                  [BillingController::class, 'index'])         ->name('billing.index');
    Route::post('/billing/checkout',         [BillingController::class, 'checkout'])      ->name('billing.checkout');
    // bKash manual submit — throttled. The unique-TrxID check (BillingController.php)
    // means a malicious user could otherwise burn legitimate TrxIDs by spamming.
    Route::post('/billing/bkash/manual',     [BillingController::class, 'bkashManualSubmit'])
        ->middleware('throttle:10,1')
        ->name('billing.bkash-manual');
    Route::post('/billing/auto-renew',       [BillingController::class, 'toggleAutoRenew'])->name('billing.auto-renew');
    Route::post('/billing/cancel',           [BillingController::class, 'cancel'])        ->name('billing.cancel');
    Route::post('/billing/renew-now',        [BillingController::class, 'renewNow'])      ->name('billing.renew-now');

    // Invitations (admin)
    // Throttle store at 20 invites per minute — even legitimate batch invites
    // shouldn't need more, and it caps any spam vector if an admin account is
    // compromised.
    Route::post  ('/invitations',                [InvitationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('invitations.store');
    Route::delete('/invitations/{invitation}',   [InvitationController::class, 'destroy'])->name('invitations.destroy');
});

// Super-admin
Route::middleware(['auth', 'super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get ('/',                                [SuperAdminController::class, 'index'])      ->name('index');
    Route::get ('/orgs',                            [SuperAdminController::class, 'orgs'])       ->name('orgs.index');
    Route::delete('/orgs/{organization}',           [SuperAdminController::class, 'deleteOrg'])  ->name('orgs.delete');
    Route::get ('/users',                           [SuperAdminController::class, 'users'])      ->name('users.index');
    Route::get ('/subscriptions',                   [SuperAdminController::class, 'subscriptions'])->name('subscriptions.index');
    Route::get ('/audit',                           [SuperAdminController::class, 'audit'])      ->name('audit.index');
    Route::get ('/plans',                           [SuperAdminController::class, 'plans'])      ->name('plans.index');
    Route::patch('/plans/{plan}',                   [SuperAdminController::class, 'updatePlan'])->name('plans.update');

    Route::get ('/payments',                        [SuperAdminController::class, 'payments'])         ->name('payments.index');
    Route::post('/payments/{txn}/approve',          [SuperAdminController::class, 'approvePayment'])   ->name('payments.approve');
    Route::post('/payments/{txn}/reject',           [SuperAdminController::class, 'rejectPayment'])    ->name('payments.reject');
    Route::patch('/platform-settings',              [SuperAdminController::class, 'updatePlatformSettings'])->name('platform-settings.update');
    Route::post  ('/platform-settings/logo',         [SuperAdminController::class, 'uploadPlatformLogo'])    ->name('platform-settings.logo.upload');
    Route::delete('/platform-settings/logo',         [SuperAdminController::class, 'deletePlatformLogo'])    ->name('platform-settings.logo.delete');

    Route::post('/orgs/{organization}/plan',         [SuperAdminController::class, 'setPlan'])    ->name('orgs.set-plan');
    Route::post('/orgs/{organization}/extend-sub',   [SuperAdminController::class, 'extendSubscription'])->name('orgs.extend-sub');
    Route::get ('/analytics',                        [SuperAdminController::class, 'analytics'])  ->name('analytics');
    Route::post('/orgs/{organization}/impersonate',  [SuperAdminController::class, 'impersonate'])->name('orgs.impersonate');

    Route::post  ('/users',                          [SuperAdminController::class, 'storeUser'])->name('users.store');
    Route::post  ('/users/{user}/super-admin',       [SuperAdminController::class, 'toggleSuperAdmin'])->name('users.toggle-super-admin');
    Route::post  ('/users/{user}/reset-password',    [SuperAdminController::class, 'resetUserPassword'])->name('users.reset-password');
    Route::delete('/users/{user}',                   [SuperAdminController::class, 'deleteUser'])->name('users.delete');

    Route::post('/subs/{subscription}/force-renew',  [SuperAdminController::class, 'forceRenew'])->name('subs.force-renew');
    Route::post('/subs/{subscription}/cancel',       [SuperAdminController::class, 'cancelSubscription'])->name('subs.cancel');
});

// Stop impersonating — must be available even when "logged in as" target user
Route::middleware('auth')->post('/admin/stop-impersonating',
    [SuperAdminController::class, 'stopImpersonating'])->name('admin.stop-impersonating');

Route::get('/dashboard-redirect', fn () => redirect()->route('dashboard.home'))->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get   ('/profile', [ProfileController::class, 'edit'])   ->name('profile.edit');
    Route::patch ('/profile', [ProfileController::class, 'update']) ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
