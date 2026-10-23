<?php

/*
 * PowerNest Estate front controller for shared hosting (DirectAdmin / cPanel).
 *
 * The application lives in a "powernest" folder OUTSIDE the web root, next to public_html:
 *   /home/USER/domains/example.com/powernest     <- application (not web-accessible)
 *   /home/USER/domains/example.com/public_html   <- this file
 * This file looks for that folder in its parent directories, so subdomain folders work too.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appPath = (static function (): ?string {
    $dir = __DIR__;
    for ($i = 0; $i < 5; $i++) {
        $dir = dirname($dir);
        if (is_file($dir.'/powernest/bootstrap/app.php')) {
            return $dir.'/powernest';
        }
    }

    return null;
})();

if ($appPath === null) {
    http_response_code(500);
    exit('PowerNest Estate: the "powernest" application folder was not found next to public_html. See README-INSTALL.txt.');
}

// Not installed yet: send the browser to the installer.
if (! is_file($appPath.'/storage/installed.lock')) {
    if (is_file(__DIR__.'/install/index.php')) {
        header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/install/', true, 302);
        exit;
    }
    http_response_code(503);
    exit('PowerNest Estate is not installed.');
}

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
