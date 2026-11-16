<?php

namespace Tests\Feature\Backup;

use App\Jobs\UploadBackupToDropbox;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupWasSuccessful;
use Tests\TestCase;

class UploadBackupToDropboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();

        config([
            'services.dropbox.client_id' => 'app-key',
            'services.dropbox.client_secret' => 'app-secret',
            'services.dropbox.refresh_token' => 'refresh-token',
            'services.dropbox.backup_folder' => '/saas_database',
        ]);
    }

    public function test_a_successful_backup_queues_the_newest_zip_for_upload(): void
    {
        Queue::fake();
        Storage::disk('local')->put('biponon/2026-11-16-02-00-00.zip', 'older');
        Storage::disk('local')->put('biponon/2026-11-17-02-00-00.zip', 'newest');
        touch(Storage::disk('local')->path('biponon/2026-11-16-02-00-00.zip'), now()->subDay()->getTimestamp());

        event(new BackupWasSuccessful('local', 'biponon'));

        Queue::assertPushed(
            UploadBackupToDropbox::class,
            fn (UploadBackupToDropbox $job) => $job->diskName === 'local' && $job->path === 'biponon/2026-11-17-02-00-00.zip',
        );
    }

    public function test_nothing_is_queued_when_dropbox_is_not_configured(): void
    {
        Queue::fake();
        config(['services.dropbox.refresh_token' => null]);
        Storage::disk('local')->put('biponon/2026-11-17-02-00-00.zip', 'zip');

        event(new BackupWasSuccessful('local', 'biponon'));

        Queue::assertNothingPushed();
    }

    public function test_the_job_refreshes_the_token_and_uploads_the_zip_into_the_folder(): void
    {
        Storage::disk('local')->put('biponon/2026-11-17-02-00-00.zip', 'zip bytes');
        Http::fake([
            'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'short-lived', 'expires_in' => 14400]),
            'content.dropboxapi.com/2/files/upload' => Http::response(['name' => '2026-11-17-02-00-00.zip']),
        ]);

        (new UploadBackupToDropbox('local', 'biponon/2026-11-17-02-00-00.zip'))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.dropboxapi.com/oauth2/token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-token'
            && $request['client_id'] === 'app-key');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://content.dropboxapi.com/2/files/upload'
            && $request->hasHeader('Authorization', 'Bearer short-lived')
            && json_decode($request->header('Dropbox-API-Arg')[0], true)['path'] === '/saas_database/2026-11-17-02-00-00.zip'
            && $request->body() === 'zip bytes');
    }

    public function test_a_dropbox_error_fails_the_attempt_so_the_queue_retries_it(): void
    {
        Storage::disk('local')->put('biponon/2026-11-17-02-00-00.zip', 'zip bytes');
        Http::fake([
            'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'short-lived']),
            'content.dropboxapi.com/2/files/upload' => Http::response('Service unavailable', 503),
        ]);

        $this->expectException(RequestException::class);

        (new UploadBackupToDropbox('local', 'biponon/2026-11-17-02-00-00.zip'))->handle();
    }

    public function test_a_backup_pruned_before_the_upload_runs_is_skipped(): void
    {
        (new UploadBackupToDropbox('local', 'biponon/gone.zip'))->handle();

        Http::assertNothingSent();
    }
}
