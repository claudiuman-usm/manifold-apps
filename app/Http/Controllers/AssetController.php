<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves static assets through the app so they always come from the repo
 * (updated by a plain `git pull` / cPanel "Update from Remote"). This avoids
 * copying files into the LiteSpeed shim's served folder on every deploy —
 * the request falls through the shim to index.php, and we stream the file
 * from the app's own public/ directory. Cache-busted via ?v=filemtime.
 */
class AssetController extends Controller
{
    public function css(): Response
    {
        $path = public_path('css/app.css');

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
