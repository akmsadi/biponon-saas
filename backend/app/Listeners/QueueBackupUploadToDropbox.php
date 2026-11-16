<?php

namespace App\Listeners;

use App\Jobs\UploadBackupToDropbox;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * After each successful backup, queue an off-site copy of the new zip to Dropbox, when Dropbox
 * is configured.
 */
class QueueBackupUploadToDropbox
{
    public function handle(BackupWasSuccessful $event): void
    {
        if (! self::dropboxConfigured()) {
            return;
        }

        $newest = BackupDestination::create($event->diskName, $event->backupName)->newestBackup();

        if ($newest === null) {
            return;
        }

        UploadBackupToDropbox::dispatch($event->diskName, $newest->path());
    }

    public static function dropboxConfigured(): bool
    {
        return filled(config('services.dropbox.client_id'))
            && filled(config('services.dropbox.client_secret'))
            && filled(config('services.dropbox.refresh_token'));
    }
}
