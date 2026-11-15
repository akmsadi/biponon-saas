<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Shared hosting can't keep a queue worker running, so cron runs `schedule:run` every minute and
 * each run drains the queue (invitation emails) and exits.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Nightly database backup into storage/app/private/<BACKUP_NAME>, old copies pruned by the
 * retention rules in config/backup.php. Failures are emailed to BACKUP_NOTIFICATION_EMAIL.
 */
Schedule::command('backup:clean')->dailyAt('01:30')->timezone('Asia/Dhaka');
Schedule::command('backup:run --only-db')->dailyAt('02:00')->timezone('Asia/Dhaka');
Schedule::command('backup:monitor')->dailyAt('08:00')->timezone('Asia/Dhaka');
