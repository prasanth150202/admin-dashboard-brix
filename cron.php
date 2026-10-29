<?php

// Entry point for hPanel's cron form, which takes a PHP file path rather
// than a shell command. Equivalent to `php artisan schedule:run`. Not
// reachable over HTTP: the root .htaccess only forwards into public/.

use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$status = $app->handleCommand(new ArgvInput(['artisan', 'schedule:run']));

exit($status);
