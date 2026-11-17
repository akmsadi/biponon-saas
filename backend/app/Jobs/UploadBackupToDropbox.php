<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Copies one finished backup zip to Dropbox, then deletes the folder's older zips beyond the configured
 * number to keep. Queued, so a Dropbox outage is retried by the
 * every-minute queue run instead of failing the nightly backup, which is already safe on disk.
 */
class UploadBackupToDropbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Single-request uploads to Dropbox are capped at 150 MB. */
    public const MAX_BYTES = 150 * 1024 * 1024;

    public function __construct(public string $diskName, public string $path) {}

    /**
     * Wait 1 minute, then 5, before trying again.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $disk = Storage::disk($this->diskName);

        if (! $disk->exists($this->path)) {
            // Pruned before the upload ran; nothing left to copy.
            return;
        }

        if ($disk->size($this->path) > self::MAX_BYTES) {
            throw new RuntimeException("Backup {$this->path} is larger than Dropbox's 150 MB single-upload limit.");
        }

        $accessToken = $this->accessToken();

        Http::withToken($accessToken)
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path' => $this->dropboxPath(),
                    'mode' => 'add',
                    'autorename' => true,
                    'mute' => true,
                ], JSON_UNESCAPED_SLASHES),
            ])
            ->withBody($disk->get($this->path), 'application/octet-stream')
            ->timeout(300)
            ->post('https://content.dropboxapi.com/2/files/upload')
            ->throw();

        try {
            $this->pruneOldBackups($accessToken);
        } catch (Throwable $exception) {
            // The upload succeeded; a failed clean-up must not retry the job and upload the zip again.
            report($exception);
        }
    }

    /**
     * Where the file lands in Dropbox: the configured folder plus the backup's own file name.
     */
    public function dropboxPath(): string
    {
        return $this->dropboxFolder().'/'.basename($this->path);
    }

    private function dropboxFolder(): string
    {
        return '/'.trim((string) config('services.dropbox.backup_folder'), '/');
    }

    /**
     * Delete all but the newest `keep_backups` zips in the folder, newest by Dropbox upload time.
     * Other files in the folder are left alone.
     */
    private function pruneOldBackups(string $accessToken): void
    {
        $keep = (int) config('services.dropbox.keep_backups');

        if ($keep < 1) {
            return;
        }

        $dropbox = Http::withToken($accessToken)->baseUrl('https://api.dropboxapi.com/2');

        $page = $dropbox->post('/files/list_folder', ['path' => $this->dropboxFolder()])->throw()->json();
        $entries = $page['entries'];

        while ($page['has_more']) {
            $page = $dropbox->post('/files/list_folder/continue', ['cursor' => $page['cursor']])->throw()->json();
            $entries = [...$entries, ...$page['entries']];
        }

        collect($entries)
            ->filter(fn (array $entry) => $entry['.tag'] === 'file' && str_ends_with(strtolower($entry['name']), '.zip'))
            ->sortByDesc('server_modified')
            ->slice($keep)
            ->each(fn (array $entry) => $dropbox->post('/files/delete_v2', ['path' => $entry['path_lower']])->throw());
    }

    /**
     * Swap the long-lived refresh token for an access token, which Dropbox expires after 4 hours.
     */
    private function accessToken(): string
    {
        return Http::asForm()
            ->post('https://api.dropboxapi.com/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => config('services.dropbox.refresh_token'),
                'client_id' => config('services.dropbox.client_id'),
                'client_secret' => config('services.dropbox.client_secret'),
            ])
            ->throw()
            ->json('access_token');
    }
}
