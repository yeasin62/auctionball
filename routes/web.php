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
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminContentController;
use App\Http\Controllers\SuperAdminIntegrationController;
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

foreach (PublicPageController::PAGE_SLUGS as $slug) {
    Route::get("/{$slug}", [PublicPageController::class, 'show'])
        ->defaults('slug', $slug)
        ->name("public.{$slug}");
}
Route::post('/contact', [PublicPageController::class, 'submitContact'])
    ->middleware('throttle:6,1')
    ->name('public.contact.submit');
Route::get('/blog', [PublicPageController::class, 'blog'])->name('public.blog');
Route::get('/blog/{post:slug}', [PublicPageController::class, 'blogPost'])->name('public.blog.show');

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
    Route::get   ('/seasons',                  [SeasonController::class, 'index'])   ->middleware('org-role:org_admin,auctioneer,viewer')->name('seasons.index');
    Route::post  ('/seasons',                  [SeasonController::class, 'store'])   ->middleware('org-role:org_admin')->name('seasons.store');
    Route::patch ('/seasons/{season}',         [SeasonController::class, 'update'])  ->middleware('org-role:org_admin')->name('seasons.update');
    Route::delete('/seasons/{season}',         [SeasonController::class, 'destroy']) ->middleware('org-role:org_admin')->name('seasons.destroy');
    Route::post  ('/seasons/{season}/activate',  [SeasonController::class, 'activate'])  ->middleware('org-role:org_admin')->name('seasons.activate');
    Route::post  ('/seasons/{season}/deactivate',[SeasonController::class, 'deactivate'])->middleware('org-role:org_admin')->name('seasons.deactivate');
    Route::post  ('/seasons/{season}/registration', [SeasonController::class, 'toggleRegistration'])->middleware('org-role:org_admin')->name('seasons.registration');
    Route::post  ('/seasons/{season}/registration/regenerate', [SeasonController::class, 'regenerateRegistrationToken'])->middleware('org-role:org_admin')->name('seasons.registration.regenerate');
    Route::post  ('/seasons/{season}/registration/form',       [SeasonController::class, 'updateRegistrationForm'])    ->middleware('org-role:org_admin')->name('seasons.registration.form');
    Route::post  ('/seasons/{season}/categories',              [SeasonController::class, 'updatePlayerCategories'])    ->middleware('org-role:org_admin')->name('seasons.categories');
    Route::get   ('/seasons/{season}/export.pdf', [ExportController::class, 'seasonSummaryPdf'])->middleware('org-role:org_admin,auctioneer,viewer')->name('seasons.export.pdf');

    // Players
    Route::get   ('/players',                  [PlayerController::class, 'index'])    ->middleware('org-role:org_admin,auctioneer,viewer')->name('players.index');
    Route::post  ('/players',                  [PlayerController::class, 'store'])    ->middleware('org-role:org_admin,auctioneer')->name('players.store');

    // Player CSV import
    Route::get ('/players/import/template', [PlayerImportController::class, 'template'])->middleware('org-role:org_admin,auctioneer')->name('players.import.template');
    Route::post('/players/import',          [PlayerImportController::class, 'preview']) ->middleware('org-role:org_admin,auctioneer')->name('players.import.preview');
    Route::post('/players/import/confirm',  [PlayerImportController::class, 'confirm']) ->middleware('org-role:org_admin,auctioneer')->name('players.import.confirm');

    // Player exports
    Route::get('/players/export.csv', [ExportController::class, 'playersCsv'])->middleware('org-role:org_admin,auctioneer,viewer')->name('players.export.csv');
    Route::get('/players/export.pdf', [ExportController::class, 'playersPdf'])->middleware('org-role:org_admin,auctioneer,viewer')->name('players.export.pdf');
    Route::post('/players/approve-all', [PlayerController::class, 'approveAll'])->middleware('org-role:org_admin,auctioneer')->name('players.approve-all');

    Route::post  ('/players/{player}',         [PlayerController::class, 'update'])   ->middleware('org-role:org_admin,auctioneer')->name('players.update');
    Route::delete('/players/{player}',         [PlayerController::class, 'destroy'])  ->middleware('org-role:org_admin,auctioneer')->name('players.destroy');
    Route::post  ('/players/{player}/approve', [PlayerController::class, 'approve'])  ->middleware('org-role:org_admin,auctioneer')->name('players.approve');
    Route::delete('/players/{player}/reject',  [PlayerController::class, 'reject'])   ->middleware('org-role:org_admin,auctioneer')->name('players.reject');

    // Teams
    Route::get   ('/teams',                            [TeamController::class, 'index'])     ->middleware('org-role:org_admin,auctioneer,viewer')->name('teams.index');
    Route::post  ('/teams',                            [TeamController::class, 'store'])     ->middleware('org-role:org_admin,auctioneer')->name('teams.store');
    Route::post  ('/teams/registration',               [TeamController::class, 'toggleRegistration'])      ->middleware('org-role:org_admin')->name('teams.registration');
    Route::post  ('/teams/registration/regenerate',    [TeamController::class, 'regenerateRegistrationToken'])->middleware('org-role:org_admin')->name('teams.registration.regenerate');

    Route::get('/teams/export.csv', [ExportController::class, 'teamsCsv'])->middleware('org-role:org_admin,auctioneer,viewer')->name('teams.export.csv');
    Route::get('/teams/export.pdf', [ExportController::class, 'teamsPdf'])->middleware('org-role:org_admin,auctioneer,viewer')->name('teams.export.pdf');
    Route::post  ('/teams/{team}',                     [TeamController::class, 'update'])    ->middleware('org-role:org_admin,auctioneer')->name('teams.update');
    Route::delete('/teams/{team}',                     [TeamController::class, 'destroy'])   ->middleware('org-role:org_admin,auctioneer')->name('teams.destroy');
    Route::post  ('/teams/{team}/approve',             [TeamController::class, 'approve'])   ->middleware('org-role:org_admin,auctioneer')->name('teams.approve');
    Route::delete('/teams/{team}/reject',              [TeamController::class, 'reject'])    ->middleware('org-role:org_admin,auctioneer')->name('teams.reject');

    // Auction control
    Route::get ('/auction',    [AuctionController::class, 'control'])  ->middleware('org-role:org_admin,auctioneer')->name('auction.control');
    Route::get ('/bigscreen',  [AuctionController::class, 'bigscreen'])->middleware('org-role:org_admin,auctioneer,viewer')->name('auction.bigscreen');
    Route::get ('/rosters',    [AuctionController::class, 'rosters'])  ->middleware('org-role:org_admin,auctioneer,viewer')->name('auction.rosters');

    Route::post('/auction/player',  [AuctionController::class, 'setPlayer'])->middleware('org-role:org_admin,auctioneer')->name('auction.set-player');
    Route::post('/auction/start',   [AuctionController::class, 'start'])    ->middleware('org-role:org_admin,auctioneer')->name('auction.start');
    Route::post('/auction/pause',   [AuctionController::class, 'pause'])    ->middleware('org-role:org_admin,auctioneer')->name('auction.pause');
    Route::post('/auction/resume', [AuctionController::class, 'resume'])    ->middleware('org-role:org_admin,auctioneer')->name('auction.resume');
    Route::post('/auction/sold',    [AuctionController::class, 'sold'])     ->middleware('org-role:org_admin,auctioneer')->name('auction.sold');
    Route::post('/auction/unsold',  [AuctionController::class, 'unsold'])   ->middleware('org-role:org_admin,auctioneer')->name('auction.unsold');
    Route::post('/auction/reset',   [AuctionController::class, 'reset'])    ->middleware('org-role:org_admin,auctioneer')->name('auction.reset');
    Route::post('/auction/extend',  [AuctionController::class, 'extendTimer'])->middleware('org-role:org_admin,auctioneer')->name('auction.extend');
    Route::post('/auction/bid',     [AuctionController::class, 'placeBid']) ->middleware('org-role:org_admin,auctioneer')->name('auction.bid');
    Route::post('/auction/finalize', [AuctionController::class, 'autoFinalize'])      ->middleware('org-role:org_admin,auctioneer')->name('auction.finalize');
    Route::post('/auction/auto-finalize', [AuctionController::class, 'setAutoFinalize'])->middleware('org-role:org_admin,auctioneer')->name('auction.set-auto-finalize');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->middleware('org-role:org_admin,auctioneer,viewer')->name('analytics.index');

    // Audit log
    Route::get('/audit',     [AuditLogController::class, 'index'])->middleware('org-role:org_admin,auctioneer,viewer')->name('audit.index');

    // Org pages
    Route::get ('/users',             [OrgPagesController::class, 'users'])         ->middleware('org-role:org_admin')->name('users.index');
    Route::delete('/users/{user}',    [OrgPagesController::class, 'removeUser'])    ->middleware('org-role:org_admin')->name('users.remove');
    Route::get ('/settings',                [OrgPagesController::class, 'settings'])             ->middleware('org-role:org_admin')->name('settings.index');
    Route::post('/settings/currency',       [OrgPagesController::class, 'updateCurrency'])       ->middleware('org-role:org_admin')->name('settings.currency');
    Route::post('/settings/domain',         [OrgPagesController::class, 'updateCustomDomain'])   ->middleware('org-role:org_admin')->name('settings.domain');
    Route::post('/settings/domain/verify',  [OrgPagesController::class, 'verifyCustomDomain'])   ->middleware('org-role:org_admin')->name('settings.domain.verify');

    // Billing (admin)
    Route::get ('/billing',                  [BillingController::class, 'index'])         ->middleware('org-role:org_admin')->name('billing.index');
    Route::post('/billing/checkout',         [BillingController::class, 'checkout'])      ->middleware('org-role:org_admin')->name('billing.checkout');
    // bKash manual submit — throttled. The unique-TrxID check (BillingController.php)
    // means a malicious user could otherwise burn legitimate TrxIDs by spamming.
    Route::post('/billing/bkash/manual',     [BillingController::class, 'bkashManualSubmit'])
        ->middleware(['org-role:org_admin', 'throttle:10,1'])
        ->name('billing.bkash-manual');
    Route::post('/billing/auto-renew',       [BillingController::class, 'toggleAutoRenew'])->middleware('org-role:org_admin')->name('billing.auto-renew');
    Route::post('/billing/cancel',           [BillingController::class, 'cancel'])        ->middleware('org-role:org_admin')->name('billing.cancel');
    Route::post('/billing/renew-now',        [BillingController::class, 'renewNow'])      ->middleware('org-role:org_admin')->name('billing.renew-now');

    // Invitations (admin)
    // Throttle store at 20 invites per minute — even legitimate batch invites
    // shouldn't need more, and it caps any spam vector if an admin account is
    // compromised.
    Route::post  ('/invitations',                [InvitationController::class, 'store'])
        ->middleware(['org-role:org_admin', 'throttle:20,1'])
        ->name('invitations.store');
    Route::delete('/invitations/{invitation}',   [InvitationController::class, 'destroy'])->middleware('org-role:org_admin')->name('invitations.destroy');
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

    Route::get   ('/content',                        [SuperAdminContentController::class, 'index'])         ->name('content.index');
    Route::get   ('/content/blog-posts',             [SuperAdminContentController::class, 'blogPosts'])     ->name('content.blog-posts.index');
    Route::get   ('/advanced',                       [SuperAdminContentController::class, 'advanced'])      ->name('advanced.index');
    Route::get   ('/integrations',                   [SuperAdminIntegrationController::class, 'index'])     ->name('integrations.index');
    Route::patch ('/integrations',                   [SuperAdminIntegrationController::class, 'update'])    ->name('integrations.update');
    Route::post  ('/content/categories',             [SuperAdminContentController::class, 'storeCategory']) ->name('content.categories.store');
    Route::delete('/content/categories/{category}',   [SuperAdminContentController::class, 'deleteCategory'])->name('content.categories.delete');
    Route::post  ('/content/blog/generate',          [SuperAdminContentController::class, 'generatePost'])  ->name('content.blog.generate');
    Route::post  ('/content/blog',                   [SuperAdminContentController::class, 'storePost'])     ->name('content.blog.store');
    Route::patch ('/content/blog/{post:slug}',       [SuperAdminContentController::class, 'updatePost'])    ->name('content.blog.update');
    Route::delete('/content/blog/{post:slug}',       [SuperAdminContentController::class, 'deletePost'])    ->name('content.blog.delete');
    Route::post  ('/content/images',                 [SuperAdminContentController::class, 'uploadImage'])   ->name('content.images.upload');
    Route::patch ('/advanced/scripts',               [SuperAdminContentController::class, 'updateScripts']) ->name('advanced.scripts.update');

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
    Route::post  ('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
