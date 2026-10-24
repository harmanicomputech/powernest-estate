<?php

/*
 * PowerNest Estate — browser installer for shared hosting (no SSH / PHP CLI needed).
 *
 * 1. Checks the server, 2. tests the MySQL connection, 3. writes powernest/.env with a fresh
 * APP_KEY and secrets, 4. creates the database tables, 5. creates the administrator and the
 * first estate, 6. locks itself (storage/installed.lock) and deletes this folder.
 */

declare(strict_types=1);
use App\Enums\EstateRole;
use App\Models\Estate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Estate\OccupancyService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

error_reporting(E_ALL);
ini_set('display_errors', '0');
@set_time_limit(300);
session_start();

const PNE_MIN_PHP = '8.3.0';

function pne_app_path(): ?string
{
    $dir = dirname(__DIR__);
    for ($i = 0; $i < 5; $i++) {
        $dir = dirname($dir);
        if (is_file($dir.'/powernest/bootstrap/app.php')) {
            return $dir.'/powernest';
        }
    }

    return null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function env_value(string $value): string
{
    if ($value === '') {
        return '';
    }

    return '"'.str_replace(['\\', '"', '$', "\n", "\r"], ['\\\\', '\\"', '\\$', '', ''], $value).'"';
}

function detected_url(): string
{
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

    return ($https ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').$base;
}

/**
 * @return list<array{0: string, 1: bool, 2: string}>
 */
function requirements(?string $app): array
{
    $checks = [
        ['PHP '.PNE_MIN_PHP.' or newer', version_compare(PHP_VERSION, PNE_MIN_PHP, '>='), 'You have PHP '.PHP_VERSION.'. In DirectAdmin open “Select PHP version” / “PHP Version Manager” and choose 8.3 or 8.4 for this domain.'],
    ];

    foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'dom', 'ctype', 'json', 'fileinfo', 'curl', 'gd', 'filter', 'session'] as $ext) {
        $checks[] = ["PHP extension: {$ext}", extension_loaded($ext), "Enable “{$ext}” in your hosting PHP extensions settings."];
    }

    $checks[] = ['Application folder “powernest” found next to public_html', $app !== null, 'Upload and extract the zip in your domain folder so that “powernest” and “public_html” sit side by side.'];

    if ($app) {
        foreach (['', '/storage', '/storage/framework', '/storage/framework/cache', '/storage/framework/sessions', '/storage/framework/views', '/storage/logs', '/storage/app', '/bootstrap/cache'] as $dir) {
            $checks[] = ['Writable: powernest'.($dir ?: '/'), is_writable($app.$dir), 'Set folder permissions to 755 (or 775) in the File Manager.'];
        }
    }

    return $checks;
}

$app = pne_app_path();

if ($app && is_file($app.'/storage/installed.lock')) {
    http_response_code(404);
    exit('Already installed. Delete the public_html/install folder if it still exists.');
}

$_SESSION['pne_csrf'] ??= bin2hex(random_bytes(16));
$checks = requirements($app);
$ready = ! in_array(false, array_column($checks, 1), true);
$errors = [];
$log = [];
$done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (! hash_equals($_SESSION['pne_csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    $in = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $_POST);
    $url = rtrim((string) ($in['app_url'] ?? ''), '/');

    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter the full website address, e.g. https://estate.example.com';
    }
    foreach (['db_name' => 'Database name', 'db_user' => 'Database user', 'admin_name' => 'Your name', 'estate_name' => 'Estate name', 'estate_code' => 'Estate code'] as $key => $label) {
        if (($in[$key] ?? '') === '') {
            $errors[] = "{$label} is required.";
        }
    }
    if (! filter_var($in['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email.';
    }
    if (strlen((string) ($in['admin_password'] ?? '')) < 10) {
        $errors[] = 'The administrator password must be at least 10 characters.';
    }
    if (($in['admin_password'] ?? '') !== ($in['admin_password_confirmation'] ?? '')) {
        $errors[] = 'The two administrator passwords do not match.';
    }
    if (! preg_match('/^[A-Za-z0-9_-]{2,12}$/', (string) ($in['estate_code'] ?? ''))) {
        $errors[] = 'Estate code: 2–12 letters, numbers, dashes or underscores (residents type this to request access).';
    }

    if (! $errors) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', ($in['db_host'] ?? '') ?: 'localhost', (int) (($in['db_port'] ?? '') ?: 3306), $in['db_name']),
                $in['db_user'],
                (string) ($in['db_pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10],
            );
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $log[] = "Connected to MySQL/MariaDB {$version}.";
        } catch (Throwable $exception) {
            $errors[] = 'Could not connect to the database: '.$exception->getMessage().' — check the name, user and password you created in DirectAdmin → MySQL Management.';
        }
    }

    if (! $errors) {
        $secure = str_starts_with($url, 'https://');
        $mailOn = ($in['mail_host'] ?? '') !== '';
        $cronToken = bin2hex(random_bytes(20));
        $lines = [
            'APP_NAME="PowerNest Estate"',
            'APP_ENV=production',
            'APP_KEY=base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG=false',
            'APP_URL='.env_value($url),
            'APP_TIMEZONE=UTC',
            'APP_DISPLAY_TIMEZONE='.env_value(($in['timezone'] ?? '') ?: 'Africa/Lagos'),
            'APP_LOCALE=en',
            'APP_FALLBACK_LOCALE=en',
            'APP_MAINTENANCE_DRIVER=file',
            'BCRYPT_ROUNDS=12',
            '',
            'LOG_CHANNEL=stack',
            'LOG_STACK=daily',
            'LOG_LEVEL=warning',
            '',
            'DB_CONNECTION=mysql',
            'DB_HOST='.env_value(($in['db_host'] ?? '') ?: 'localhost'),
            'DB_PORT='.(int) (($in['db_port'] ?? '') ?: 3306),
            'DB_DATABASE='.env_value($in['db_name']),
            'DB_USERNAME='.env_value($in['db_user']),
            'DB_PASSWORD='.env_value((string) ($in['db_pass'] ?? '')),
            '',
            'SESSION_DRIVER=database',
            'SESSION_LIFETIME=120',
            'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE='.($secure ? 'true' : 'false'),
            'SESSION_SAME_SITE=lax',
            'CACHE_STORE=database',
            '# sync = background work (token vending, emails) runs immediately; no queue worker needed.',
            'QUEUE_CONNECTION=sync',
            'FILESYSTEM_DISK=local',
            'BROADCAST_CONNECTION=log',
            '',
            'MAIL_MAILER='.($mailOn ? 'smtp' : 'log'),
            'MAIL_HOST='.env_value($in['mail_host'] ?? ''),
            'MAIL_PORT='.(int) (($in['mail_port'] ?? '') ?: 587),
            'MAIL_USERNAME='.env_value($in['mail_user'] ?? ''),
            'MAIL_PASSWORD='.env_value($in['mail_pass'] ?? ''),
            'MAIL_SCHEME='.(($in['mail_port'] ?? '') === '465' ? 'smtps' : 'null'),
            'MAIL_FROM_ADDRESS='.env_value(($in['mail_from'] ?? '') ?: $in['admin_email']),
            'MAIL_FROM_NAME="PowerNest Estate"',
            '',
            '# Turn on only after confirming the payment and vending credentials below.',
            'INTEGRATIONS_LIVE='.(! empty($in['integrations_live']) ? 'true' : 'false'),
            'STRON_BASE_URL='.env_value($in['stron_url'] ?? ''),
            'STRON_COMPANY='.env_value($in['stron_company'] ?? ''),
            'STRON_USERNAME='.env_value($in['stron_user'] ?? ''),
            'STRON_PASSWORD='.env_value($in['stron_pass'] ?? ''),
            'STRON_TIMEOUT=25',
            'PAYSTACK_ENABLED='.(($in['paystack_secret'] ?? '') !== '' ? 'true' : 'false'),
            'PAYSTACK_PUBLIC_KEY='.env_value($in['paystack_public'] ?? ''),
            'PAYSTACK_SECRET_KEY='.env_value($in['paystack_secret'] ?? ''),
            'PAYAZA_ENABLED='.(($in['payaza_public'] ?? '') !== '' ? 'true' : 'false'),
            'PAYAZA_PUBLIC_KEY='.env_value($in['payaza_public'] ?? ''),
            'PAYAZA_SECRET_KEY='.env_value($in['payaza_secret'] ?? ''),
            'PAYAZA_ENVIRONMENT=live',
            'PAYAZA_VERIFY_URL='.env_value($in['payaza_verify_url'] ?? ''),
            'PAYAZA_WEBHOOK_SECRET='.bin2hex(random_bytes(16)),
            '',
            'CRON_TOKEN='.$cronToken,
            'RETENTION_VISITS_MONTHS=12',
            'RETENTION_PASS_SCANS_MONTHS=6',
            'RETENTION_VISITOR_PASSES_MONTHS=12',
            'RETENTION_WEBHOOK_EVENTS_MONTHS=12',
            '',
        ];

        if (@file_put_contents($app.'/.env', implode("\n", $lines), LOCK_EX) === false) {
            $errors[] = 'Could not write powernest/.env. Make the powernest folder writable (755) and try again.';
        } else {
            @chmod($app.'/.env', 0600);
            $log[] = 'Configuration file written with a new encryption key.';
        }
    }

    if (! $errors) {
        try {
            foreach (['/bootstrap/cache/config.php', '/bootstrap/cache/routes-v7.php', '/bootstrap/cache/services.php', '/bootstrap/cache/packages.php'] as $cached) {
                @unlink($app.$cached);
            }

            require $app.'/vendor/autoload.php';
            /** @var Application $laravel */
            $laravel = require $app.'/bootstrap/app.php';
            $laravel->usePublicPath(dirname(__DIR__));
            $kernel = $laravel->make(Kernel::class);
            $kernel->bootstrap();

            $kernel->call('migrate', ['--force' => true]);
            $log[] = 'Database tables created.';
            if (is_file($app.'/RELEASE')) {
                @file_put_contents($app.'/storage/app/release-applied', trim((string) file_get_contents($app.'/RELEASE')));
            }

            $user = User::firstOrNew(['email' => strtolower((string) $in['admin_email'])]);
            $user->fill(['name' => $in['admin_name'], 'password' => $in['admin_password'], 'phone' => ($in['admin_phone'] ?? '') ?: null]);
            $user->forceFill(['is_platform_admin' => true, 'status' => 'active', 'email_verified_at' => now()])->save();

            $estate = Estate::firstOrNew(['code' => strtoupper((string) $in['estate_code'])]);
            $estate->fill([
                'name' => $in['estate_name'],
                'slug' => Str::slug((string) $in['estate_name']).'-'.Str::lower(Str::random(4)),
                'address' => ($in['estate_address'] ?? '') ?: null,
                'timezone' => ($in['timezone'] ?? '') ?: 'Africa/Lagos',
                'contact_email' => strtolower((string) $in['admin_email']),
                'status' => 'active',
                'settings' => $estate->settings ?? [],
            ])->save();

            app(OccupancyService::class)->ensureMembership($estate->id, $user, EstateRole::EstateOwner);
            $user->forceFill(['current_estate_id' => $estate->id])->save();
            app(AuditLogger::class)->record('platform.installed', $estate, ['admin' => $user->email], $estate, $user);
            $log[] = "Administrator {$user->email} and estate “{$estate->name}” created.";

            // Optional data bundled from the old PowerNest power app (customers, homes, meters, history).
            $legacyFile = $app.'/storage/app/legacy-import.sql';
            if (is_file($legacyFile)) {
                if (! empty($in['import_legacy'])) {
                    $db = Illuminate\Support\Facades\DB::connection();
                    foreach (preg_split('/;\R/', (string) file_get_contents($legacyFile)) as $statement) {
                        $statement = trim((string) preg_replace('/^--.*$/m', '', $statement));
                        if ($statement !== '') {
                            $db->unprepared($statement);
                        }
                    }
                    for ($i = 0; $i < 20 && $db->table('legacy_token_imports')->exists(); $i++) {
                        $kernel->call('power:import-legacy-tokens');
                    }
                    $log[] = sprintf('Imported %d customers, %d homes, %d meters and %d electricity purchases from the old app.',
                        $db->table('users')->count() - 1, $db->table('properties')->count(), $db->table('meters')->count(), $db->table('power_purchases')->count());
                    $done['imported'] = true;
                }
                @unlink($legacyFile);
            }

            if ($secure && is_writable($htaccess = dirname(__DIR__).'/.htaccess')) {
                file_put_contents($htaccess, str_replace('#PNE-HTTPS ', '', (string) file_get_contents($htaccess)));
            }

            file_put_contents($app.'/storage/installed.lock', date(DATE_ATOM));

            $done = ['url' => $url, 'cron' => $url.'/cron/'.$cronToken, 'email' => $user->email, 'paystack' => $url.'/webhooks/paystack'] + ($done ?? []);

            // Remove the installer so it can never be run again.
            foreach (glob(__DIR__.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir(__DIR__);
        } catch (Throwable $exception) {
            $errors[] = 'Installation failed: '.$exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install · PowerNest Estate</title>
<style>
    :root { --brand: #195840; --brand-dark: #10382a; --gold: #d4a843; --ink: #1b1f1d; --muted: #6a716c; --line: #e5e2da; --canvas: #f6f5f1; --ok: #18794e; --bad: #b42318; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--canvas); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    header { background: var(--brand-dark); color: #fff; padding: 22px 20px; }
    header .wrap { display: flex; align-items: center; gap: 12px; }
    .wrap { max-width: 760px; margin: 0 auto; }
    main { padding: 24px 16px 60px; }
    h1 { margin: 0; font-size: 20px; } h2 { font-size: 16px; margin: 0 0 4px; }
    .card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px; margin-bottom: 18px; }
    .muted { color: var(--muted); font-size: 13.5px; margin: 0 0 14px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .full { grid-column: 1 / -1; }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #3b413e; }
    input, select { width: 100%; padding: 10px 12px; border: 1px solid #d3cfc4; border-radius: 10px; font: inherit; background: #fff; }
    input:focus { outline: 2px solid #2f8862; border-color: transparent; }
    .check { display: flex; gap: 10px; align-items: flex-start; padding: 7px 0; border-bottom: 1px solid #efede7; font-size: 14px; }
    .check:last-child { border: 0; }
    .ok { color: var(--ok); font-weight: 700; } .bad { color: var(--bad); font-weight: 700; }
    .hint { font-size: 12.5px; color: var(--muted); }
    button { background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: 14px 20px; font: 600 16px system-ui, sans-serif; width: 100%; cursor: pointer; }
    button:disabled { opacity: .6; }
    .alert { border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; font-size: 14px; }
    .alert-bad { background: #fdecea; border: 1px solid #f6c7c1; color: #7a1a12; }
    .alert-ok { background: #e3f4ea; border: 1px solid #bfe5cf; }
    code, .code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; background: #efede7; padding: 2px 6px; border-radius: 6px; word-break: break-all; }
    .code { display: block; padding: 10px 12px; margin: 6px 0 12px; }
    details summary { cursor: pointer; font-weight: 700; }
    a { color: var(--brand); font-weight: 600; }
    @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<header><div class="wrap">
    <svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#154634"/><path d="M7.5 15.2 16 8.5l8.5 6.7v8.3a1.5 1.5 0 0 1-1.5 1.5H9a1.5 1.5 0 0 1-1.5-1.5z" fill="none" stroke="#dfbc63" stroke-width="1.7" stroke-linejoin="round"/><path d="M17.1 13.2 13.3 18.6h3.1l-.9 4.2 4.2-5.9h-3.2z" fill="#fff"/></svg>
    <h1>PowerNest Estate · Installer</h1>
</div></header>
<main><div class="wrap">

<?php if ($done) { ?>
    <div class="alert alert-ok"><strong>Installation complete.</strong> The installer has removed itself.<?php foreach ($log as $line): ?><br><?= e($line) ?><?php endforeach ?></div>
    <?php if (! empty($done['imported'])): ?>
        <div class="alert alert-ok">Customers from the old PowerNest app can sign in with their existing email and password. Check the homes each customer was linked to under <strong>Properties</strong>.</div>
    <?php endif ?>
    <div class="card">
        <h2>1. Sign in</h2>
        <p class="muted">Use <strong><?= e($done['email']) ?></strong> and the password you chose. You are the platform administrator and the owner of the first estate.</p>
        <a href="<?= e($done['url']) ?>/login">Open <?= e($done['url']) ?>/login →</a>
    </div>
    <div class="card">
        <h2>2. Add the cron job (required)</h2>
        <p class="muted">This keeps payments reconciled, expires visitor passes and sends reminders. In DirectAdmin go to <strong>Advanced Features → Cron Jobs</strong>, set every field to <code>*</code> (every minute) and use this command:</p>
        <span class="code">curl -fsS "<?= e($done['cron']) ?>" &gt;/dev/null 2&gt;&amp;1</span>
        <p class="hint">If curl is not allowed, use <code>wget -q -O /dev/null "<?= e($done['cron']) ?>"</code>, or an external service such as cron-job.org calling that address every minute. Keep this address private.</p>
    </div>
    <div class="card">
        <h2>3. Payment webhooks</h2>
        <p class="muted">Paystack dashboard → Settings → API Keys &amp; Webhooks → Webhook URL:</p>
        <span class="code"><?= e($done['paystack']) ?></span>
        <p class="hint">The Payaza webhook address (with its secret) is in <code>powernest/.env</code> as <code>PAYAZA_WEBHOOK_SECRET</code>: use <code><?= e($done['url']) ?>/webhooks/payaza/&lt;that value&gt;</code>. Payments and vending stay switched off until <code>INTEGRATIONS_LIVE=true</code> in that file.</p>
    </div>
<?php } else { ?>

    <?php if ($errors) { ?>
        <div class="alert alert-bad"><strong>Please fix the following:</strong><ul style="margin:8px 0 0 18px; padding:0"><?php foreach ($errors as $error) { ?><li><?= e($error) ?></li><?php } ?></ul></div>
    <?php } ?>

    <div class="card">
        <h2>Server check</h2>
        <p class="muted">Everything below must be green before you can install.</p>
        <?php foreach ($checks as [$label, $ok, $help]) { ?>
            <div class="check"><span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?></span><div><?= e($label) ?><?php if (! $ok) { ?><div class="hint"><?= e($help) ?></div><?php } ?></div></div>
        <?php } ?>
    </div>

    <?php if ($ready) { ?>
    <form method="post" autocomplete="off" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Installing… this can take a minute';">
        <input type="hidden" name="_csrf" value="<?= e($_SESSION['pne_csrf']) ?>">

        <div class="card">
            <h2>Website</h2>
            <p class="muted">The address people will use. Use https:// once your SSL certificate is active.</p>
            <div class="grid">
                <div class="full"><label for="app_url">Website address</label><input id="app_url" name="app_url" value="<?= old('app_url', detected_url()) ?>" required></div>
                <div class="full"><label for="timezone">Timezone</label><select id="timezone" name="timezone"><?php foreach (['Africa/Lagos' => 'West Africa (Lagos)', 'Africa/Accra' => 'Accra', 'Africa/Nairobi' => 'Nairobi', 'UTC' => 'UTC'] as $tz => $label) { ?><option value="<?= e($tz) ?>" <?= ($_POST['timezone'] ?? 'Africa/Lagos') === $tz ? 'selected' : '' ?>><?= e($label) ?></option><?php } ?></select></div>
            </div>
        </div>

        <div class="card">
            <h2>Database</h2>
            <p class="muted">Create an empty database and user first in DirectAdmin → <strong>MySQL Management</strong>. Names usually start with your username, e.g. <code>user_powernest</code>.</p>
            <div class="grid">
                <div><label for="db_host">Host</label><input id="db_host" name="db_host" value="<?= old('db_host', 'localhost') ?>"></div>
                <div><label for="db_port">Port</label><input id="db_port" name="db_port" value="<?= old('db_port', '3306') ?>"></div>
                <div class="full"><label for="db_name">Database name</label><input id="db_name" name="db_name" value="<?= old('db_name') ?>" required></div>
                <div><label for="db_user">Database user</label><input id="db_user" name="db_user" value="<?= old('db_user') ?>" required></div>
                <div><label for="db_pass">Database password</label><input id="db_pass" name="db_pass" type="password"></div>
            </div>
        </div>

        <div class="card">
            <h2>Administrator</h2>
            <p class="muted">Your own account. It can manage the platform and the first estate.</p>
            <div class="grid">
                <div><label for="admin_name">Full name</label><input id="admin_name" name="admin_name" value="<?= old('admin_name') ?>" required></div>
                <div><label for="admin_phone">Phone (optional)</label><input id="admin_phone" name="admin_phone" value="<?= old('admin_phone') ?>"></div>
                <div class="full"><label for="admin_email">Email</label><input id="admin_email" name="admin_email" type="email" value="<?= old('admin_email') ?>" required></div>
                <div><label for="admin_password">Password (min. 10 characters)</label><input id="admin_password" name="admin_password" type="password" minlength="10" required></div>
                <div><label for="admin_password_confirmation">Confirm password</label><input id="admin_password_confirmation" name="admin_password_confirmation" type="password" minlength="10" required></div>
            </div>
        </div>

        <div class="card">
            <h2>First estate</h2>
            <div class="grid">
                <div class="full"><label for="estate_name">Estate name</label><input id="estate_name" name="estate_name" value="<?= old('estate_name') ?>" required></div>
                <div><label for="estate_code">Estate code</label><input id="estate_code" name="estate_code" value="<?= old('estate_code') ?>" maxlength="12" required><span class="hint">Short code residents use to request access, e.g. GFC01.</span></div>
                <div><label for="estate_address">Address (optional)</label><input id="estate_address" name="estate_address" value="<?= old('estate_address') ?>"></div>
            </div>
        </div>

        <div class="card">
            <details>
                <summary>Email (SMTP) — optional, can be added later</summary>
                <p class="muted" style="margin-top:10px">Needed for password resets, invitations and token emails. You can use the mailbox you created in DirectAdmin (host is usually <code>mail.yourdomain.com</code>) or Brevo.</p>
                <div class="grid">
                    <div><label for="mail_host">SMTP host</label><input id="mail_host" name="mail_host" value="<?= old('mail_host') ?>"></div>
                    <div><label for="mail_port">Port</label><input id="mail_port" name="mail_port" value="<?= old('mail_port', '587') ?>"></div>
                    <div><label for="mail_user">Username</label><input id="mail_user" name="mail_user" value="<?= old('mail_user') ?>"></div>
                    <div><label for="mail_pass">Password</label><input id="mail_pass" name="mail_pass" type="password"></div>
                    <div class="full"><label for="mail_from">From address</label><input id="mail_from" name="mail_from" value="<?= old('mail_from') ?>"></div>
                </div>
            </details>
        </div>

        <div class="card">
            <details>
                <summary>Electricity & payments — optional, can be added later</summary>
                <p class="muted" style="margin-top:10px">Use new (rotated) credentials. Nothing is charged or vended until you tick “Go live” or later set <code>INTEGRATIONS_LIVE=true</code> in <code>powernest/.env</code>.</p>
                <div class="grid">
                    <div class="full"><label for="stron_url">Stron API URL</label><input id="stron_url" name="stron_url" value="<?= old('stron_url') ?>" placeholder="http://www.server-newv.stronpower.com/api"></div>
                    <div><label for="stron_company">Stron company</label><input id="stron_company" name="stron_company" value="<?= old('stron_company') ?>"></div>
                    <div><label for="stron_user">Stron username</label><input id="stron_user" name="stron_user" value="<?= old('stron_user') ?>"></div>
                    <div class="full"><label for="stron_pass">Stron password</label><input id="stron_pass" name="stron_pass" type="password"></div>
                    <div><label for="payaza_public">Payaza public key</label><input id="payaza_public" name="payaza_public" value="<?= old('payaza_public') ?>"></div>
                    <div><label for="payaza_secret">Payaza secret key</label><input id="payaza_secret" name="payaza_secret" type="password"></div>
                    <div class="full"><label for="payaza_verify_url">Payaza status-query URL (with {reference})</label><input id="payaza_verify_url" name="payaza_verify_url" value="<?= old('payaza_verify_url') ?>"><span class="hint">Confirm with Payaza. Without it, Payaza payments need manual confirmation by finance.</span></div>
                    <div><label for="paystack_public">Paystack public key</label><input id="paystack_public" name="paystack_public" value="<?= old('paystack_public') ?>"></div>
                    <div><label for="paystack_secret">Paystack secret key</label><input id="paystack_secret" name="paystack_secret" type="password"></div>
                    <div class="full"><label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="integrations_live" value="1" style="width:auto" <?= ! empty($_POST['integrations_live']) ? 'checked' : '' ?>> Go live now (real payments and token vending)</label></div>
                </div>
            </details>
        </div>

        <?php if ($app && is_file($app.'/storage/app/legacy-import.sql')): ?>
        <div class="card">
            <h2>Data from the old PowerNest app</h2>
            <p class="muted">This package includes your customers, their homes and meters, and electricity purchase history with tokens. It is imported into the estate above, then the data file is deleted from the server.</p>
            <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="import_legacy" value="1" style="width:auto" <?= $_SERVER['REQUEST_METHOD'] !== 'POST' || ! empty($_POST['import_legacy']) ? 'checked' : '' ?>> Import this data</label>
        </div>
        <?php endif ?>

        <button type="submit">Install PowerNest Estate</button>
    </form>
    <?php } ?>

<?php } ?>
</div></main>
</body>
</html>
