<?php

return [
    // Single base currency for the Receipts module (Phase 1). Change to EUR etc.
    'base_currency' => env('RECEIPTS_BASE_CURRENCY', 'RON'),

    // Claude model used for receipt field extraction (vision).
    'model' => env('RECEIPTS_MODEL', 'claude-opus-4-8'),

    // Anthropic API key. When empty, uploads still work — extraction is skipped
    // and the receipt lands in "review" for manual entry.
    'api_key' => env('ANTHROPIC_API_KEY'),

    // Issuing company shown on the allocation PDF that goes to the accountant.
    'company' => [
        'name' => env('RECEIPTS_COMPANY_NAME', 'Usmile Media SRL'),
        'cui' => env('RECEIPTS_COMPANY_CUI', 'RO36448669'),
        'address' => env('RECEIPTS_COMPANY_ADDRESS', 'Graurului 7/34, Brașov, Romania'),
    ],
];
