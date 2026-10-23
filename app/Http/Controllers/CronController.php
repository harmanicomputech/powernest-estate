<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Web-triggered cron for shared hosting without shell access. Call it every minute
 * (DirectAdmin cron with curl/wget, or an external cron service):
 *   https://your-domain/cron/{CRON_TOKEN}
 * It applies pending migrations after an upload of a new release, runs the scheduler and,
 * when a database queue is used, works through queued jobs for up to ~40 seconds.
 */
class CronController extends Controller
{
    public function __invoke(string $token): Response
    {
        $expected = (string) config('powernest.cron_token');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);

        $lock = Cache::lock('powernest:web-cron', 120);

        if (! $lock->get()) {
            return response('busy', 200)->header('Cache-Control', 'no-store');
        }

        try {
            @set_time_limit(110);
            $this->applyNewRelease();
            Artisan::call('schedule:run');

            if (config('queue.default') === 'database') {
                Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 40, '--tries' => 3]);
            }
        } finally {
            $lock->release();
        }

        return response('ok', 200)->header('Cache-Control', 'no-store');
    }

    /**
     * Release packages ship a RELEASE file; when it differs from the last applied one,
     * run migrations and clear compiled views so an uploaded update needs no shell.
     */
    private function applyNewRelease(): void
    {
        $releaseFile = base_path('RELEASE');

        if (! is_file($releaseFile)) {
            return;
        }

        $release = trim((string) file_get_contents($releaseFile));
        $appliedFile = storage_path('app/release-applied');

        if ($release === '' || (is_file($appliedFile) && trim((string) file_get_contents($appliedFile)) === $release)) {
            return;
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('view:clear');
        file_put_contents($appliedFile, $release);
    }
}
