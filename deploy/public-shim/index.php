<?php

/**
 * Loader shim — ONLY needed if the host's vhost won't serve directly from
 * /home/manifold/repositories/manifold-apps/public (the LiteSpeed docroot
 * gotcha). Copy this file to the folder the server actually serves, i.e.
 *   /home/manifold/apps.manifold.ro/public/index.php
 * together with the .htaccess in this folder, plus a copy of the app's
 * public/css/ (and any other static assets). It boots the real app that lives
 * in /home/manifold/repositories/manifold-apps.
 *
 * If a plain test.txt in repositories/manifold-apps/public IS reachable, you
 * don't need this shim — just point the docroot there instead.
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The Laravel app lives two levels up from this public/ folder, under repositories/.
$appBase = dirname(__DIR__, 2).'/repositories/manifold-apps';

if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBase.'/vendor/autoload.php';

(require_once $appBase.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
