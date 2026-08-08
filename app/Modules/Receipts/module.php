<?php

/**
 * Receipts module manifest — auto-discovered by the hub's ModuleServiceProvider.
 */
return [
    'key' => 'receipts',
    'order' => 20,
    'icon' => '',
    'color' => 'amber',
    'route' => 'receipts.index',
    'name' => [
        'ro' => 'Bonuri',
        'en' => 'Receipts',
    ],
    'description' => [
        'ro' => 'Fotografiază bonuri; AI completează datele. Cheltuieli pe categorii.',
        'en' => 'Snap receipts; AI fills in the details. Expenses by category.',
    ],
    'shortcuts' => [
        [
            'label' => ['ro' => 'Adaugă bon', 'en' => 'Add receipt'],
            'route' => 'receipts.create',
        ],
    ],
];
