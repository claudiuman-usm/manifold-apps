<?php

namespace App\Modules\MyMusic\Console;

use App\Modules\MyMusic\Support\EnrichmentRunner;
use Illuminate\Console\Command;

/**
 * Parse + enrich in chunks. In prod a cPanel cron runs this every minute
 * (default ~40 MusicBrainz lookups ≈ 45s at 1.1s/req); locally use --all
 * to churn through the whole backlog in one go.
 */
class EnrichCommand extends Command
{
    protected $signature = 'music:enrich {--lookups=40 : Max MusicBrainz requests this run} {--all : Keep going until nothing is pending}';

    protected $description = 'Parse synced videos into tracks and enrich them via MusicBrainz';

    public function handle(EnrichmentRunner $runner): int
    {
        $created = $runner->parseMissing();
        $this->info("Parsed {$created} new track(s).");

        do {
            $stats = $runner->enrich((int) $this->option('lookups'));
            $progress = $runner->progress();

            $this->info(sprintf(
                'Enriched %d (%d ok, %d not found, %d lookups) — %d still pending.',
                $stats['processed'], $stats['ok'], $stats['not_found'], $stats['lookups'], $progress['pending'],
            ));
        } while ($this->option('all') && $stats['processed'] > 0 && $progress['pending'] > 0);

        return self::SUCCESS;
    }
}
