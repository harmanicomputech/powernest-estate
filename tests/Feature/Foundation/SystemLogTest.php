<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_see_recent_log_entries(): void
    {
        $log = storage_path('logs/zz-system-log-test.log');
        file_put_contents($log, "[2026-10-24 09:00:00] production.ERROR: Dompdf ran out of memory {\"exception\":\"x\"}\n#0 /app/PdfController.php(10)\n");
        touch($log, time() + 60);

        try {
            $admin = User::factory()->create();
            $admin->forceFill(['is_platform_admin' => true])->save();

            $this->actingAs(User::factory()->create())->get(route('platform.logs'))->assertForbidden();
            $this->actingAs($admin)->get(route('platform.logs'))->assertOk()->assertSee('Dompdf ran out of memory')->assertSee('PdfController.php');
        } finally {
            @unlink($log);
        }
    }
}
