<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebCronTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_requires_configured_token(): void
    {
        config(['powernest.cron_token' => null]);
        $this->get('/cron/anything')->assertNotFound();

        config(['powernest.cron_token' => 'secret-token-123']);
        $this->get('/cron/wrong')->assertNotFound();
    }

    public function test_cron_runs_scheduler_with_valid_token(): void
    {
        config(['powernest.cron_token' => 'secret-token-123', 'queue.default' => 'sync']);
        Artisan::spy();

        $this->get('/cron/secret-token-123')->assertOk()->assertSeeText('ok');

        Artisan::shouldHaveReceived('call')->with('schedule:run')->once();
        Artisan::shouldNotHaveReceived('call', ['queue:work', \Mockery::any()]);
    }

    public function test_cron_migrates_once_per_uploaded_release(): void
    {
        config(['powernest.cron_token' => 'secret-token-123', 'queue.default' => 'sync']);
        $releaseFile = base_path('RELEASE');
        $appliedFile = storage_path('app/release-applied');
        file_put_contents($releaseFile, 'test-release-1');
        @unlink($appliedFile);
        Artisan::spy();

        try {
            $this->get('/cron/secret-token-123')->assertOk();
            $this->get('/cron/secret-token-123')->assertOk();

            Artisan::shouldHaveReceived('call')->with('migrate', ['--force' => true])->once();
            $this->assertSame('test-release-1', file_get_contents($appliedFile));
        } finally {
            @unlink($releaseFile);
            @unlink($appliedFile);
        }
    }
}
