<?php

return [
    // Google OAuth web client (see README — YouTube Data API v3 must be enabled).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    // Special playlist ID for the authorized user's Liked videos.
    'liked_playlist' => env('MUSIC_LIKED_PLAYLIST', 'LL'),

    // YouTube Data API daily quota budget (units). Default project quota is 10,000.
    'daily_quota' => (int) env('MUSIC_DAILY_QUOTA', 10000),

    // Pages of 50 fetched per sync HTTP request (keeps requests short on shared hosting).
    'sync_pages_per_request' => 5,

    // MusicBrainz requires a descriptive User-Agent with contact info; 1 request/sec max.
    'musicbrainz_agent' => env('MUSIC_MB_AGENT', 'ManifoldApps-MyMusic/1.0 (claudiu.man@gmail.com)'),
];
