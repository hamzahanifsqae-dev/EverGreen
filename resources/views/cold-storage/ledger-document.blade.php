@php
    $ur = fn (string $urdu) => '<span class="ur" dir="rtl" lang="ur">'.$urdu.'</span>';
    $money = fn ($amount): string => $currency.' '.number_format((float) $amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer ledger — {{ $customer->name }}</title>
    @include('cold-storage._print-head')
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print statement</button>
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
                    <h1>Customer ledger {!! $ur('کسٹمر کھاتہ') !!}</h1>
                    <div class="number">{{ $customer->name }}@if ($customer->account_code) · {{ $customer->account_code }} @endif</div>
                </div>
            </header>

            <div class="party-grid">
                <div class="card">
                    <span class="label">Customer {!! $ur('کسٹمر') !!}</span>
                    <strong>{{ $customer->name }}</strong>
                    @if ($customer->phone)<p>{{ $customer->phone }}</p>@endif
                </div>
                <div class="card">
                    <span class="label">Balances {!! $ur('بیلنس') !!}</span>
                    <div class="meta-list">
                        <div><span class="k">Opening</span><span class="v">{{ $money($statement['opening_balance']) }}</span></div>
                        <div><span class="k">Closing {!! $ur('باقی رقم') !!}</span><span class="v">{{ $money($statement['closing_balance']) }}</span></div>
                        <div><span class="k">Unallocated advance</span><span class="v">{{ $money($statement['unallocated_advance']) }}</span></div>
                    </div>
                </div>
            </div>

            <div class="section-title">Statement {!! $ur('کھاتہ کی تفصیل') !!}</div>
            <table>
                <thead>
                    <tr>
                        <th>Date {!! $ur('تاریخ') !!}</th>
                        <th>Description {!! $ur('تفصیل') !!}</th>
                        <th>Reference</th>
                        <th class="num">Debit {!! $ur('ادھار') !!}</th>
                        <th class="num">Credit {!! $ur('جمع') !!}</th>
                        <th class="num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statement['rows'] as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="muted">{{ $row['reference'] ?: '—' }}</td>
                            <td class="num">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                            <td class="num">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                            <td class="num">{{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No ledger activity.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="notes">A positive closing balance is owed by the customer. A negative balance is credit held for the customer.</div>

            <footer class="footer">
                <span>{{ $merchant?->name }} · Customer ledger</span>
                <span>Generated {{ now()->format('d/m/Y H:i') }}</span>
            </footer>
        </div>
    </article>
</body>
</html>
