<?php

/**
 * Flower module manifest — read by the hub's ModuleServiceProvider to auto-wire
 * routes/migrations/views, and by the dashboard to render this tool's card.
 */
return [
    'key' => 'flower',
    'order' => 10,
    'icon' => '🌊',
    'color' => 'teal',
    'route' => 'flower.index',
    'name' => [
        'ro' => 'Flow-er',
        'en' => 'Flow-er',
    ],
    'description' => [
        'ro' => 'Liste de verificare cronometrate care te țin în ritm la montaj.',
        'en' => 'Timed checklists that keep you in flow while editing.',
    ],
    'shortcuts' => [
        [
            'label' => ['ro' => 'Șablon nou', 'en' => 'New template'],
            'route' => 'flower.templates.create',
        ],
    ],
];
