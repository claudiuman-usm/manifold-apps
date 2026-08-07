@php
    // Favicon mirrors the topbar logo mark, color-coded per context so each
    // tool gets its own. Add a case here when a new tool defines its palette.
    $ctx = $ctx ?? 'ctx-hub';
    $stops = match ($ctx) {
        'ctx-flower' => ['#5eead4', '#10b6a4', '#67e8f9'],
        default => ['#c4b5fd', '#7b6cf6', '#f9a8d4'],
    };
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        .'<stop offset="0" stop-color="'.$stops[0].'"/>'
        .'<stop offset=".55" stop-color="'.$stops[1].'"/>'
        .'<stop offset="1" stop-color="'.$stops[2].'"/>'
        .'</linearGradient></defs>'
        .'<rect x="3" y="3" width="26" height="26" rx="8" fill="url(#g)"/></svg>';
@endphp
<link rel="icon" href="data:image/svg+xml;base64,{{ base64_encode($svg) }}">
