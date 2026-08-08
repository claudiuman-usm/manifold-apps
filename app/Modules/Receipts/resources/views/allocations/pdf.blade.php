<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php($fmt = fn ($n) => number_format((float) $n, 2))
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #1a1a1a; font-size: 12px; margin: 0; }
        .head { border-bottom: 2px solid #b45309; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { color: #b45309; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 20px; margin: 6px 0 2px; }
        .meta { color: #555; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #777;
             border-bottom: 1px solid #ccc; padding: 6px 8px; }
        td { padding: 7px 8px; border-bottom: 1px solid #eee; }
        .r { text-align: right; }
        .total-row td { border-top: 2px solid #333; border-bottom: none; font-weight: bold; font-size: 14px; padding-top: 10px; }
        .notes { margin-top: 20px; color: #555; font-size: 11px; white-space: pre-line; }
        .foot { margin-top: 28px; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">{{ $appName }} · {{ __('receipts::messages.allocations.heading') }}</div>
        <h1>{{ $allocation->title }}</h1>
        <div class="meta">
            {{ $allocation->client?->name }}@if ($allocation->client?->email) · {{ $allocation->client->email }}@endif<br>
            @if ($allocation->period_month){{ $allocation->period_month->translatedFormat('F Y') }} · @endif{{ now()->format('d M Y') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('receipts::messages.show.date') }}</th>
                <th>{{ __('receipts::messages.show.merchant') }}</th>
                <th>{{ __('receipts::messages.show.category') }}</th>
                <th class="r">{{ __('receipts::messages.show.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allocation->receipts as $r)
                <tr>
                    <td>{{ optional($r->purchased_at)->format('Y-m-d') ?: '—' }}</td>
                    <td>{{ $r->merchant ?: '—' }}</td>
                    <td>{{ $r->category?->name ?: '—' }}</td>
                    <td class="r">{{ $fmt($r->amount) }} {{ $r->currency }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">{{ __('receipts::messages.allocations.total') }}</td>
                <td class="r">{{ $fmt($total) }} {{ $baseCurrency }}</td>
            </tr>
        </tbody>
    </table>

    @if ($allocation->notes)
        <div class="notes">{{ $allocation->notes }}</div>
    @endif

    <div class="foot">{{ $appName }} — {{ now()->format('Y-m-d H:i') }}</div>
</body>
</html>
