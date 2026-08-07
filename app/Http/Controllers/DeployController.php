<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Token-guarded deploy actions for a no-SSH host. Lets us run migrations
 * (and optionally the seeder) by hitting a URL after a git pull, instead of
 * hand-writing ALTER TABLEs. Only migrate/seed are ever run — never arbitrary
 * commands. Requires DEPLOY_TOKEN in .env; returns 404 otherwise so the
 * endpoint is invisible without the secret.
 */
class DeployController extends Controller
{
    public function migrate(Request $request)
    {
        $expected = (string) config('app.deploy_token');
        $given = (string) $request->query('token', '');

        abort_if($expected === '' || ! hash_equals($expected, $given), 404);

        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        if ($request->boolean('seed')) {
            Artisan::call('db:seed', ['--force' => true]);
            $output .= "\n".Artisan::output();
        }

        return response('<pre style="font:13px ui-monospace,monospace;padding:16px;">'
            .e(trim($output) ?: 'Done.').'</pre>');
    }
}
