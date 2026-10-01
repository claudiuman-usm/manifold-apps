<?php

namespace App\Modules\MyMusic\Console;

use App\Modules\MyMusic\Support\LikedSyncer;
use App\Modules\MyMusic\Support\YouTubeApiException;
use App\Modules\MyMusic\Support\YouTubeClient;
use Illuminate\Console\Command;

/**
 * Unattended full sync of the Liked playlist — a daily cron runs this so new
 * likes land in the library without opening the app. Being a full pass, it
 * also detects unlikes (video gone from the playlist → marked non-music).
 * New videos are then parsed + enriched by the every-minute music:enrich cron.
 */
class SyncCommand extends Command
{
    protected $signature = 'music:sync';

    protected $description = 'Fetch new Liked videos from YouTube into the library';

    public function handle(LikedSyncer $syncer, YouTubeClient $yt): int
    {
        if (! $yt->isConnected()) {
            $this->warn('No Google account connected — open /music and connect first.');

            return self::SUCCESS; // not an error worth a cron alert
        }

        try {
            $stats = $syncer->sync();
        } catch (YouTubeApiException $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Scanned %d liked videos — %d new, %d refreshed, %d re-liked, %d unliked.',
            $stats['scanned'], $stats['inserted'], $stats['refreshed'],
            $stats['reliked'], $stats['unliked'],
        ));

        return self::SUCCESS;
    }
}
