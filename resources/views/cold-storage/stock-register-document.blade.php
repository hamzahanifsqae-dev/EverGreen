@php
    $ur = fn (string $urdu) => '<span class="ur" dir="rtl" lang="ur">'.$urdu.'</span>';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock register</title>
    @include('cold-storage._print-head')
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print register</button>
    </div>

    <article class="sheet">
        <div class="accent-bar"></div>
        <div class="sheet-body">
            <header class="header">
                <div class="brand">
                    @if ($merchant?->logo?->photo_url)
                        <img src="{{ asset('storage/'.$merchant->logo->photo_url) }}" alt="{{ $merchant?->name }}">
                    @else
                        <div class="brand-mark">{{ strtoupper(substr((string) ($merchant?->name ?: 'CS'), 0, 2)) }}</div>
                    @endif
                    <div class="brand-copy">
                        <strong>{{ $merchant?->name ?: 'Cold Storage' }}</strong>
                    </div>
                </div>
                <div class="doc-title">
                    <h1>Stock register {!! $ur('اسٹاک رجسٹر') !!}</h1>
                </div>
            </header>

            @forelse ($groups as $group)
                <div class="section-title">
                    {{ $group['customer'] }} — Lot {{ $group['lot_number'] }} ({{ $group['product'] }})
                    · Opening {{ number_format($group['opening'], 2) }} → Closing {{ number_format($group['closing'], 2) }}
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date {!! $ur('تاریخ') !!}</th>
                            <th class="num">Inward {!! $ur('آمد') !!}</th>
                            <th class="num">Outward {!! $ur('روانگی') !!}</th>
                            <th class="num">Adjustment</th>
                            <th class="num">Closing {!! $ur('بقایا') !!}</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="num">{{ $row['inward'] > 0 ? number_format($row['inward'], 2) : 'NIL' }}</td>
                                <td class="num">{{ $row['outward'] > 0 ? number_format($row['outward'], 2) : 'NIL' }}</td>
                                <td class="num">{{ $row['adjustment'] != 0 ? number_format($row['adjustment'], 2) : 'NIL' }}</td>
                                <td class="num">{{ number_format($row['closing'], 2) }}</td>
                                <td class="muted">{{ implode(', ', $row['references']) ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty
                <p class="muted">No stock movements match these filters.</p>
            @endforelse

            <footer class="footer">
                <span>{{ $merchant?->name }} · Stock register</span>
                <span>Generated {{ now()->format('d/m/Y H:i') }}</span>
            </footer>
        </div>
    </article>
</body>
</html>
