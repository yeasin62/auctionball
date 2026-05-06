# AuctionBall

Real-time player auction platform for cricket and football tournaments. Like the BPL or IPL auction, but for any tournament — small or large — with live bidding from phones, a cinematic big-screen display, and full multi-tenant SaaS controls.

Built for Bangladeshi tournament organizers, with first-class support for Bengali (বাংলা) and bKash payments.

## What you get

- **Live auction control panel** — auctioneer drives the event from a laptop
- **Phone-based bidding** for team owners with anti-snipe timer extension
- **Cinematic big-screen view** for the venue (TV / projector)
- **Drag-and-drop registration form builder** — collect any player info you need
- **Multi-currency** (BDT / USD with custom rate)
- **bKash, Nagad, Rocket, bank transfer, PayPal** for fees and subscriptions
- **Bengali + English** UI with locale-aware number formatting
- **Multi-tenant** — many organizations on one install, isolated data per org
- **Super-admin platform panel** — orgs, users, payments, plans, audit log, analytics with real-time visitors + traffic sources
- **Markdown-driven help docs** at `/help` (locale-aware)

## Tech stack

- **PHP 8.3** + **Laravel 11**
- **Inertia.js** + **Vue 3** (Composition API, `<script setup>`)
- **Tailwind CSS** + custom design system (glass surfaces, gradient brand)
- **Laravel Reverb** WebSocket server for real-time auction updates
- **MySQL 8.0** / **MariaDB 10.6+** in production (SQLite for local dev only)
- **Redis** for cache, session, queue
- **dompdf** for PDF exports (season summary, player list, team list)
- **vue-i18n** for English ↔ Bengali

## Quick start (local development)

```bash
# 1. Clone + install
git clone https://github.com/<your-org>/auctionball.git
cd auctionball
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (SQLite is fine for local dev)
touch database/database.sqlite
php artisan migrate --seed

# 4. Build assets + run
npm run build               # or `npm run dev` for hot reload
php artisan serve           # http://127.0.0.1:8000
php artisan reverb:start    # http://127.0.0.1:8080 — needs to be running for live auction
php artisan queue:work      # for emails, subscription renewals
```

Login with the seeder's demo admin (see `database/seeders/DemoSeeder.php`).

## Production deployment

See [`deploy/README.md`](deploy/README.md) for the complete production guide:

- MySQL setup ([`deploy/mysql/setup.sql`](deploy/mysql/setup.sql))
- OPcache + JIT tuning ([`deploy/php/opcache.ini`](deploy/php/opcache.ini))
- Supervisor configs for queue workers, Reverb, scheduler ([`deploy/supervisor/`](deploy/supervisor/))
- Nginx server blocks (main app + WebSocket subdomain)
- Backup strategy (mysqldump cron + S3 sync + restore drill)
- Production checklist

The codebase boots with a `RuntimeException` if production runs on SQLite — defence-in-depth so misconfigured deploys fail fast, not on auction night.

## Project layout

```
app/
  Http/Controllers/    Auth, Auction, Billing, Player, Team, SuperAdmin, Help, Sitemap, ...
  Http/Middleware/     ResolveOrganizationByDomain, EnsureHasOrganization, RecordVisitor, ...
  Models/              Organization, Season, Team, Player, Bid, Subscription, VisitorEvent, ...
  Services/            AuctionService, Billing/{BkashProvider, PayPalProvider}, ...
  Events/              AuctionStateUpdated (broadcast on Reverb)
resources/
  js/Pages/            All Inertia pages (Vue 3 SFCs)
  js/Components/       Field, TextField, Toggle, ConfirmDialog, ImageCropper, ...
  js/composables/      useAuctionChannel, useFmt, useConfirm/Alert/Prompt, useHaptics
  js/locales/          en.json, bn.json (vue-i18n)
  docs/en/             Markdown user guide (rendered at /help)
  views/exports/       Blade templates for PDF exports
routes/
  web.php              Public + auth + dashboard + super-admin routes
  channels.php         Reverb private channel auth
deploy/                Production ops: supervisor, mysql, php, README
```

## Key features in depth

- **Server-authoritative auction** — `AuctionService` validates every bid (rejects below floor + step, anti-snipe extension, budget enforcement). Bids broadcast on Reverb via `ShouldBroadcastNow` for sub-second latency.
- **Org isolation** — every query is scoped to `current_organization`. Routes enforce via `org` middleware.
- **Plan gating** — `Organization::PLAN_LIMITS` drives feature flags (PDF export, team count, season count, watermark).
- **Audit log** — every important action (bid, sold, plan change, impersonation) is logged with actor, target, before/after.
- **Visitor analytics** — middleware records each page visit (debounced session+path) with referrer + UTM tags. Super-admin dashboard shows real-time + 30-day metrics.

## Contributing

This is a private codebase (currently). Internal contribution guidelines apply.

## License

Proprietary — all rights reserved. Contact [hello@auctionball.com](mailto:hello@auctionball.com) for licensing inquiries.
