@php
    $ur = fn (string $urdu) => '<span class="ur" dir="rtl" lang="ur">'.$urdu.'</span>';
    $money = fn ($amount): string => $currency.' '.number_format((float) $amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rent collection register</title>
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
                    <h1>Rent collection register {!! $ur('کرایہ وصولی رجسٹر') !!}</h1>
                </div>
            </header>

            <table>
                <thead>
                    <tr>
                        <th>Date {!! $ur('تاریخ') !!}</th>
                        <th>Customer {!! $ur('کسٹمر') !!}</th>
                        <th>Withdrawal</th>
                        <th>Bill</th>
                        <th class="num">Bags {!! $ur('بوری') !!}</th>
                        <th class="num">Rate</th>
                        <th class="num">Charged {!! $ur('کرایہ') !!}</th>
                        <th class="num">Collected {!! $ur('وصول شدہ') !!}</th>
                        <th class="num">Due</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td>{{ $row['customer'] }}</td>
                            <td class="muted">{{ $row['withdrawal_reference'] ?? '—' }}</td>
                            <td class="muted">{{ $row['bill_reference'] ?? '—' }}</td>
                            <td class="num">{{ $row['bags'] > 0 ? number_format($row['bags'], 2) : '—' }}</td>
                            <td class="num">{{ $row['rate'] !== null ? number_format($row['rate'], 2) : '—' }}</td>
                            <td class="num">{{ $row['charged'] !== null ? number_format($row['charged'], 2) : '—' }}</td>
                            <td class="num">{{ $row['collected'] !== null ? number_format($row['collected'], 2) : '—' }}</td>
                            <td class="num">{{ $row['due'] !== null ? number_format($row['due'], 2) : '—' }}</td>
                            <td class="muted">{{ $row['method'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="muted">No withdrawals or advances in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="totals">
                <div class="row"><span>Bags released</span><span>{{ number_format($totals['bags'], 2) }}</span></div>
                <div class="row"><span>Rent charged</span><span>{{ $money($totals['charged']) }}</span></div>
                <div class="row grand"><span>Collected</span><span>{{ $money($totals['collected']) }}</span></div>
                <div class="row due"><span>Outstanding</span><span>{{ $money($totals['due']) }}</span></div>
            </div>

            <footer class="footer">
                <span>{{ $merchant?->name }} · Rent collection register</span>
                <span>Generated {{ now()->format('d/m/Y H:i') }}</span>
            </footer>
        </div>
    </article>
</body>
</html>
