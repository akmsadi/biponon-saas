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
            'services.dropbox.keep_backups' => 0,
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

    public function test_after_uploading_only_the_newest_zips_are_kept(): void
    {
        config(['services.dropbox.keep_backups' => 2]);
        Storage::disk('local')->put('biponon/2026-11-17-02-00-00.zip', 'zip bytes');

        $zip = fn (string $name, string $uploadedAt) => [
            '.tag' => 'file', 'name' => $name, 'path_lower' => "/saas_database/{$name}", 'server_modified' => $uploadedAt,
        ];

        Http::fake([
            'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'short-lived']),
            'content.dropboxapi.com/2/files/upload' => Http::response(['name' => '2026-11-17-02-00-00.zip']),
            'api.dropboxapi.com/2/files/list_folder' => Http::response([
                'entries' => [
                    $zip('2026-11-15-02-00-00.zip', '2026-11-15T02:01:00Z'),
                    $zip('2026-11-17-02-00-00.zip', '2026-11-17T02:01:00Z'),
                    $zip('2026-11-14-02-00-00.zip', '2026-11-14T02:01:00Z'),
                    $zip('2026-11-16-02-00-00.zip', '2026-11-16T02:01:00Z'),
                    ['.tag' => 'file', 'name' => 'notes.txt', 'path_lower' => '/saas_database/notes.txt', 'server_modified' => '2026-01-01T00:00:00Z'],
                    ['.tag' => 'folder', 'name' => 'old', 'path_lower' => '/saas_database/old'],
                ],
                'cursor' => 'cursor',
                'has_more' => false,
            ]),
            'api.dropboxapi.com/2/files/delete_v2' => Http::response(['metadata' => []]),
        ]);

        (new UploadBackupToDropbox('local', 'biponon/2026-11-17-02-00-00.zip'))->handle();

        $deleted = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/files/delete_v2'))
            ->map(fn (array $pair) => $pair[0]['path'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['/saas_database/2026-11-14-02-00-00.zip', '/saas_database/2026-11-15-02-00-00.zip'], $deleted);
    }

    public function test_a_failed_clean_up_does_not_fail_the_upload(): void
    {
        config(['services.dropbox.keep_backups' => 7]);
        Storage::disk('local')->put('biponon/2026-11-17-02-00-00.zip', 'zip bytes');
        Http::fake([
            'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'short-lived']),
            'content.dropboxapi.com/2/files/upload' => Http::response(['name' => '2026-11-17-02-00-00.zip']),
            'api.dropboxapi.com/2/files/list_folder' => Http::response('Service unavailable', 503),
        ]);

        (new UploadBackupToDropbox('local', 'biponon/2026-11-17-02-00-00.zip'))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://content.dropboxapi.com/2/files/upload');
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/files/delete_v2'));
    }

    public function test_a_backup_pruned_before_the_upload_runs_is_skipped(): void
    {
        (new UploadBackupToDropbox('local', 'biponon/gone.zip'))->handle();

        Http::assertNothingSent();
    }
}
