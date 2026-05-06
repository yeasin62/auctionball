<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| Subscription renewal scheduler
|--------------------------------------------------------------------------
| Runs every hour. The command itself is fast (it only queries + dispatches
| jobs), so an hourly run picks up subscriptions whose dunning cooldown just
| expired without a long lag.
|
| In production set up cron once:
|   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
| In dev, run `php artisan schedule:work` in another terminal.
*/
Schedule::command('subscriptions:run-renewals')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Pre-renewal reminders (T-7 / T-3 / T-1 days)
|--------------------------------------------------------------------------
| Runs once a day. Sends a reminder per subscription per threshold —
| `last_reminder_days_before` on subscriptions ensures no duplicates.
*/
Schedule::command('subscriptions:send-renewal-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Dhaka')
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Daily database + storage backup
|--------------------------------------------------------------------------
| spatie/laravel-backup writes a zip of the DB dump + critical files to
| BACKUP_DISK (default: local; set to 's3' or 'backblaze' in prod).
| Retention is BACKUP_KEEP_DAILY days (default 30). The clean run prunes
| anything older than that.
|
| Production cron only needs the standard `php artisan schedule:run` entry —
| the two commands below are dispatched from there.
*/
Schedule::command('backup:clean')
    ->dailyAt('01:00')
    ->timezone('Asia/Dhaka')
    ->onOneServer();

Schedule::command('backup:run --only-db')
    ->dailyAt('01:30')
    ->timezone('Asia/Dhaka')
    ->onOneServer()
    ->runInBackground();

// Weekly full backup (DB + uploaded files) on Sundays
Schedule::command('backup:run')
    ->weeklyOn(0, '02:00')
    ->timezone('Asia/Dhaka')
    ->onOneServer()
    ->runInBackground();
