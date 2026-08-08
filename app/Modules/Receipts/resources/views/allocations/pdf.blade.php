<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php($fmt = fn ($n) => number_format((float) $n, 2))
    @php($catRo = [
        'Groceries' => 'Alimente',
        'Meals' => 'Mese',
        'Transport' => 'Transport',
        'Office' => 'Birou',
        'Software' => 'Software',
        'Travel' => 'Deplasări',
        'Utilities' => 'Utilități',
        'Other' => 'Altele',
    ])
    @php($catName = fn ($c) => $c ? ($catRo[$c->name] ?? $c->name) : '—')
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #1a1a1a; font-size: 12px; margin: 0; }
        .head { border-bottom: 3px solid #d9531e; padding-bottom: 14px; margin-bottom: 20px; }
        .head td { vertical-align: top; }
        .company { font-size: 20px; font-weight: bold; }
        .company-meta { color: #333; font-size: 12px; line-height: 1.5; }
        .date { color: #333; font-size: 12px; text-align: right; }
        .intro { font-size: 13px; line-height: 1.5; margin: 4px 0 22px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            background: #d9531e; color: #fff; text-align: left;
            padding: 9px 10px; font-size: 11px;
        }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 9px 10px; }
        table.items tbody tr:nth-child(odd) { background: #fdf3ec; }
        .total td { background: #d9531e; color: #fff; font-weight: bold; font-size: 13px; padding: 10px; }
        .foot { margin-top: 30px; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <table style="width:100%; border-collapse:collapse;" class="head">
        <tr>
            <td>
                <div class="company">{{ $company['name'] }}</div>
                <div class="company-meta">
                    CUI: {{ $company['cui'] }}<br>
                    {{ $company['address'] }}
                </div>
            </td>
            <td class="date">Data: {{ now()->format('d.m.Y') }}</td>
        </tr>
    </table>

    <p class="intro">
        Documentele fiscale din tabelul alăturat sunt alocate ca și cheltuieli facturii fiscale
        <strong>{{ $invoiceNumber }}</strong>@if ($client) pentru Clientul <strong>{{ $client->name }}</strong>@endif.
    </p>

    <table class="items">
        <thead>
            <tr>
                <th style="width:34px;">#</th>
                <th>Furnizor</th>
                <th style="width:80px;">Data</th>
                <th style="width:120px;">Categorie</th>
                <th class="r" style="width:90px;">Sumă</th>
                <th style="width:60px;">Monedă</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipts as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->merchant ?: '—' }}</td>
                    <td>{{ optional($r->purchased_at)->format('d.m.y') ?: '—' }}</td>
                    <td>{{ $catName($r->category) }}</td>
                    <td class="r">{{ $fmt($r->amount) }}</td>
                    <td>{{ $r->currency }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3"></td>
                <td>TOTAL</td>
                <td class="r">{{ $fmt($total) }}</td>
                <td>{{ $baseCurrency }}</td>
            </tr>
        </tbody>
    </table>

    <div class="foot">{{ $company['name'] }} — {{ now()->format('Y-m-d H:i') }}</div>
</body>
</html>
