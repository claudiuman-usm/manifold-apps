<?php

return [
    // Claude model used by the Plan screen's "Describe" step (description -> placements).
    'model' => env('PODPLAN_MODEL', 'claude-opus-5-5'),

    // Anthropic API key (shared with Receipts and My Music). When empty the
    // Describe step is hidden and plans are built by hand only.
    'api_key' => env('ANTHROPIC_API_KEY'),
];
