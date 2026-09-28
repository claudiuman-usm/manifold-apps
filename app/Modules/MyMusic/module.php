<?php

/**
 * My Music module manifest — auto-discovered by the hub's ModuleServiceProvider.
 */
return [
    'key' => 'music',
    'order' => 30,
    'icon' => '',
    'color' => 'pink',
    'route' => 'music.index',
    'name' => [
        'ro' => 'Muzica mea',
        'en' => 'My Music',
    ],
    'description' => [
        'ro' => 'Muzica din Liked pe YouTube: organizată, filtrată, transformată în playlisturi.',
        'en' => 'Your liked YouTube music: organized, filtered, turned into playlists.',
    ],
    'shortcuts' => [
        [
            'label' => ['ro' => 'Playlisturi', 'en' => 'Playlists'],
            'route' => 'music.playlists.index',
        ],
    ],
];
