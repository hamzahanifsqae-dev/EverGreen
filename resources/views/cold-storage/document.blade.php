@php
    $documentTitle = match ($type) {
        'receipt' => 'Goods receipt',
        'dispatch' => 'Gate pass',
        default => 'Storage invoice',
    };
    $documentNumber = $record->receipt_no ?? $record->dispatch_no ?? $record->bill_no;
    $merchant = $record->merchant;
    $logoPath = $merchant?->logo?->photo_url;
    $money = fn ($amount): string => $currency.' '.number_format((float) $amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} {{ $documentNumber }}</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --accent: #0f766e;
            --accent-dark: #115e59;
            --bg: #eef2f6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 28px 16px 48px;
            background: var(--bg);
            color: var(--ink);
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.45;
        }

        .toolbar {
            max-width: 860px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .toolbar button {
            appearance: none;
            border: 0;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 16px;
            cursor: pointer;
        }

        .toolbar button:hover { background: var(--accent-dark); }

        .sheet {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.1);
            overflow: hidden;
        }

        .accent-bar {
            height: 6px;
            background: linear-gradient(90deg, var(--accent), #14b8a6);
        }

        .sheet-body {
            padding: 36px 40px 42px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 28px;
            align-items: flex-start;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            max-width: 58%;
        }

        .brand img {
            height: 52px;
            width: auto;
            object-fit: contain;
        }

        .brand-mark {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }

        .brand-copy strong {
            display: block;
            font-size: 16px;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .brand-copy span,
        .brand-copy div {
            color: var(--muted);
            font-size: 12px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: var(--accent-dark);
            text-transform: uppercase;
        }

        .doc-title .number {
            margin-top: 6px;
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
        }

        .party-grid {
            margin-top: 28px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
        }

        .card {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px 16px;
        }

        .card .label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .card strong {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .card p {
            margin: 0;
            color: var(--muted);
            font-size: 12.5px;
        }

        .meta-list {
            display: grid;
            gap: 8px;
        }

        .meta-list div {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 8px;
            font-size: 12.5px;
        }

        .meta-list .k { color: var(--muted); }
        .meta-list .v { color: var(--ink); font-weight: 600; }

        .section-title {
            margin: 28px 0 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: var(--accent-dark);
            color: #fff;
            text-align: left;
            font-size: 11px;
            font-weight: 650;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 11px 12px;
        }

        thead th:first-child { border-radius: 8px 0 0 0; }
        thead th:last-child { border-radius: 0 8px 0 0; }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
            font-size: 13px;
        }

        tbody tr:nth-child(even) td { background: #fafbfc; }

        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .muted { color: var(--muted); font-size: 12px; }

        .totals {
            width: 300px;
            margin: 22px 0 0 auto;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 9px 14px;
            font-size: 13px;
            color: var(--muted);
            background: #fff;
        }

        .totals .row + .row { border-top: 1px solid var(--line); }
        .totals .row span:last-child { color: var(--ink); font-weight: 600; font-variant-numeric: tabular-nums; }

        .totals .grand {
            background: var(--accent-dark);
            color: #fff;
            font-weight: 700;
        }

        .totals .grand span:last-child { color: #fff; }

        .totals .due {
            background: #ecfdf5;
            color: var(--accent-dark);
        }

        .totals .due span:last-child { color: var(--accent-dark); }

        .notes {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px dashed var(--line);
            color: var(--muted);
            font-size: 12px;
        }

        .footer {
            margin-top: 28px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            color: var(--muted);
            font-size: 11px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar { display: none !important; }

            .sheet {
                box-shadow: none;
                border-radius: 0;
                max-width: none;
            }

            .sheet-body { padding: 18mm 16mm; }

            tbody tr:nth-child(even) td { background: transparent; }

            thead th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .totals .grand,
            .totals .due,
            .accent-bar {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 720px) {
            .sheet-body { padding: 24px 18px 28px; }
            .header, .party-grid { display: block; }
            .brand, .doc-title { max-width: none; }
            .doc-title { text-align: left; margin-top: 18px; }
            .totals { width: 100%; }
            .meta-list div { grid-template-columns: 96px 1fr; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">
            {{ $type === 'bill' ? 'Print invoice' : 'Print document' }}
        </button>
    </div>

    <article class="sheet">
        <div class="accent-bar"></div>
        <div class="sheet-body">
            <header class="header">
                <div class="brand">
                    @if ($logoPath)
                        <img src="{{ asset('storage/'.$logoPath) }}" alt="{{ $merchant?->name }}">
                    @else
                        <div class="brand-mark">{{ strtoupper(substr((string) ($merchant?->name ?: 'CS'), 0, 2)) }}</div>
                    @endif
                    <div class="brand-copy">
                        <strong>{{ $merchant?->name ?: 'Cold Storage' }}</strong>
                        <div>
                            @if ($record->business?->name){{ $record->business->name }}@endif
                            @if ($record->branch?->name)
                                @if ($record->business?->name) · @endif{{ $record->branch->name }}
                            @endif
                        </div>
                        @if ($merchant?->address_line_1 || $merchant?->city)
                            <div>
                                {{ collect([$merchant?->address_line_1, $merchant?->city])->filter()->implode(', ') }}
                            </div>
                        @endif
                        @if ($merchant?->phone || $merchant?->email)
                            <div>
                                {{ collect([$merchant?->phone, $merchant?->email])->filter()->implode(' · ') }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="doc-title">
                    <h1>{{ $type === 'bill' ? 'Invoice' : $documentTitle }}</h1>
                    <div class="number"># {{ $documentNumber }}</div>
                </div>
            </header>

            <div class="party-grid">
                <div class="card">
                    <span class="label">{{ $type === 'dispatch' ? 'Released to' : 'Bill to' }}</span>
                    <strong>{{ $record->customer?->name ?: '—' }}</strong>
                    @if ($record->customer?->email)
                        <p>{{ $record->customer->email }}</p>
                    @endif
                    @if ($record->customer?->phone)
                        <p>{{ $record->customer->phone }}</p>
                    @endif
                    @if ($type === 'dispatch' && $record->recipient_name)
                        <p>Recipient: {{ $record->recipient_name }}</p>
                    @endif
                </div>

                <div class="card">
                    <span class="label">Document details</span>
                    <div class="meta-list">
                        @if ($type === 'receipt')
                            <div><span class="k">Received on</span><span class="v">{{ $record->received_on?->format('d/m/Y') ?: '—' }}</span></div>
                            <div><span class="k">Vehicle</span><span class="v">{{ $record->vehicle_number ?: '—' }}</span></div>
                            <div><span class="k">Branch</span><span class="v">{{ $record->branch?->name ?: '—' }}</span></div>
                        @elseif ($type === 'dispatch')
                            <div><span class="k">Dispatched on</span><span class="v">{{ $record->dispatched_on?->format('d/m/Y') ?: '—' }}</span></div>
                            <div><span class="k">Vehicle</span><span class="v">{{ $record->vehicle_number ?: '—' }}</span></div>
                            <div><span class="k">Branch</span><span class="v">{{ $record->branch?->name ?: '—' }}</span></div>
                        @else
                            <div><span class="k">Period</span><span class="v">{{ $record->period_start?->format('d/m/Y') }} – {{ $record->period_end?->format('d/m/Y') }}</span></div>
                            <div><span class="k">Charge</span><span class="v">{{ ucfirst((string) $record->charge_basis) }} / {{ ucfirst((string) $record->charge_period) }}</span></div>
                            <div><span class="k">Branch</span><span class="v">{{ $record->branch?->name ?: '—' }}</span></div>
                            <div><span class="k">Status</span><span class="v">{{ ucfirst((string) $record->status) }}</span></div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($type === 'receipt')
                <div class="section-title">Received lots</div>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Lot</th>
                            <th class="num">Packages</th>
                            <th class="num">Weight</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record->items as $item)
                            <tr>
                                <td>{{ $item->product?->name ?: '—' }}</td>
                                <td>{{ $item->lot_number }}</td>
                                <td class="num">{{ number_format((float) $item->package_count, 3) }}</td>
                                <td class="num">{{ number_format((float) $item->net_weight, 3) }} {{ $item->weight_unit }}</td>
                                <td>
                                    @forelse ($item->allocations as $allocation)
                                        <div>{{ $allocation->chamber?->name }} {{ $allocation->location?->name }}</div>
                                    @empty
                                        <span class="muted">—</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No receipt lines.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="notes">These goods remain the property of the customer. This receipt is not a purchase.</div>
            @elseif ($type === 'dispatch')
                <div class="section-title">Released lots</div>
                <table>
                    <thead>
                        <tr>
                            <th>Lot</th>
                            <th>Product</th>
                            <th class="num">Packages</th>
                            <th class="num">Weight</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record->lines as $line)
                            <tr>
                                <td>{{ $line->receiptItem?->lot_number }}</td>
                                <td>{{ $line->receiptItem?->product?->name }}</td>
                                <td class="num">{{ number_format((float) $line->package_count, 3) }}</td>
                                <td class="num">{{ number_format((float) $line->net_weight, 3) }}</td>
                                <td>{{ $line->chamber?->name }} {{ $line->location?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No dispatch lines.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="notes">This gate pass releases customer-owned goods. It is not a product sale.</div>
            @else
                <div class="section-title">Storage charges</div>
                <table>
                    <thead>
                        <tr>
                            <th>Lot</th>
                            <th>Period</th>
                            <th class="num">Qty-days</th>
                            <th class="num">Rate</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($record->lines as $line)
                            <tr>
                                <td>
                                    <strong>{{ $line->lot_number }}</strong>
                                    @if ($line->calculation_note)
                                        <div class="muted">{{ $line->calculation_note }}</div>
                                    @endif
                                </td>
                                <td>{{ $line->period_start?->format('d/m/Y') }} – {{ $line->period_end?->format('d/m/Y') }}</td>
                                <td class="num">{{ number_format((float) $line->quantity_days, 3) }}</td>
                                <td class="num">{{ number_format((float) $line->rate, 2) }}</td>
                                <td class="num">{{ $money($line->line_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No storage lines for this period.</td></tr>
                        @endforelse

                        @foreach ($record->services as $service)
                            <tr>
                                <td colspan="4">
                                    <strong>{{ $service->name }}</strong>
                                    <div class="muted">Service · {{ ucfirst((string) $service->basis) }} · Qty {{ number_format((float) $service->quantity, 3) }}</div>
                                </td>
                                <td class="num">{{ $money($service->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="totals">
                    <div class="row"><span>Storage</span><span>{{ $money($record->storage_total) }}</span></div>
                    <div class="row"><span>Services</span><span>{{ $money($record->service_total) }}</span></div>
                    <div class="row grand"><span>Total</span><span>{{ $money($record->total_amount) }}</span></div>
                    <div class="row"><span>Paid</span><span>{{ $money($record->paid_amount) }}</span></div>
                    <div class="row due"><span>Amount due</span><span>{{ $money($record->due_amount) }}</span></div>
                </div>

                @if ($record->notes)
                    <div class="notes"><strong>Notes:</strong> {{ $record->notes }}</div>
                @else
                    <div class="notes">This invoice covers cold-storage service charges for customer-owned goods held at the facility.</div>
                @endif
            @endif

            <footer class="footer">
                <span>{{ $merchant?->name }} · Cold storage document</span>
                <span>Generated {{ now()->format('d/m/Y H:i') }}</span>
            </footer>
        </div>
    </article>
</body>
</html>
