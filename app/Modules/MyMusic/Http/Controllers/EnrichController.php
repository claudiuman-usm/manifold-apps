<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Support\EnrichmentRunner;
use Illuminate\Http\Request;

/**
 * Browser-driven enrichment: each POST processes a small chunk (~8 lookups,
 * ≈9s at the 1 req/s MusicBrainz limit — safely inside request timeouts);
 * the frontend loops while `pending` is non-zero. The cron command does the
 * same work unattended; both paths share EnrichmentRunner.
 */
class EnrichController extends Controller
{
    public function run(Request $request, EnrichmentRunner $runner)
    {
        $runner->parseMissing();
        $stats = $runner->enrich(min(8, (int) $request->input('lookups', 8)));

        return response()->json($stats + ['progress' => $runner->progress()]);
    }

    public function progress(EnrichmentRunner $runner)
    {
        return response()->json($runner->progress());
    }
}
