<?php

/**
 * PodPlan module manifest — auto-discovered by the hub's ModuleServiceProvider.
 */
return [
    'key' => 'podplan',
    // PodPlan's own version, shown in the module's navigation. Bump it with every
    // change to the module (and note the change in the hub's CHANGELOG.md).
    'version' => '1.2.0',
    'order' => 40,
    'icon' => '',
    'color' => 'sky',
    'route' => 'podplan.index',
    'name' => [
        'ro' => 'PodPlan',
        'en' => 'PodPlan',
    ],
    'description' => [
        'ro' => 'Planifică o filmare pe o schiță văzută de sus și primești lista de împachetat din echipamentul tău.',
        'en' => 'Plan a shoot on a top-down diagram and get the packing checklist from your own gear.',
    ],
    'shortcuts' => [
        [
            'label' => ['ro' => 'Proiect nou', 'en' => 'New project'],
            'route' => 'podplan.projects.create',
        ],
        [
            'label' => ['ro' => 'Inventar', 'en' => 'Inventory'],
            'route' => 'podplan.inventory.index',
        ],
    ],
];
