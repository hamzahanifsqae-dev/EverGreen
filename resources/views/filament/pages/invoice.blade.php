<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $isSale = $type === 'sale';

        $invoiceNo   = $isSale ? $record->sale_no       : $record->purchase_no;
        $invoiceDate = $isSale ? $record->sale_date     : $record->purchase_date;
        $paidAmount = (float) ($record->paid_amount ?? 0);
        $remainingAmount = (float) ($record->due_amount ?? 0);
        $paymentHistory = ($record->payments ?? collect())
            ->sortBy([
                ['payment_date', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values();

        // Customer for Sale | Vendor for Purchase
        $party = $isSale ? $record->customer : $record->vendor;
        $formatPercent = function (float $value): string {
            $formatted = number_format($value, 2, '.', '');
            $formatted = rtrim(rtrim($formatted, '0'), '.');

            return ($formatted === '' ? '0' : $formatted) . '%';
        };
        $totalDiscount = 0;
        $totalTax = 0;
        $totalDiscountPercent = 0;
        $totalTaxPercent = 0;

        foreach ($record->items as $item) {
            $lineTotal = (float) ($item->line_total ?? 0);
            $discountRate = (float) ($item->discount ?? 0);
            $taxRate = (float) ($item->tax ?? 0);

            $discountAmount = $lineTotal * ($discountRate / 100);
            $taxableAmount = $lineTotal - $discountAmount;
            $taxAmount = $taxableAmount * ($taxRate / 100);

            $totalDiscount += $discountAmount;
            $totalTax += $taxAmount;
            $totalDiscountPercent += $discountRate;
            $totalTaxPercent += $taxRate;
        }

        $headerGroupOptions = $headerGroupOptions ?? ['__default' => 'Default (Current Header)'];
        $footerGroupOptions = $footerGroupOptions ?? ['__default' => 'Default (Current Footer)'];
        $selectedHeaderGroupId = (string) ($selectedHeaderGroupId ?? array_key_first($headerGroupOptions));
        $selectedFooterGroupId = (string) ($selectedFooterGroupId ?? array_key_first($footerGroupOptions));
        $dynamicGroups = $dynamicGroups ?? ['header' => [], 'footer' => []];
        $headerGroups = $dynamicGroups['header'] ?? [];
        $footerGroups = $dynamicGroups['footer'] ?? [];
        $showDefaultHeader = $selectedHeaderGroupId === '__default';
        $showDefaultFooter = $selectedFooterGroupId === '__default';

        $configuredHeaderLogo = null;
        foreach ($headerGroups as $group) {
            foreach (($group['fields'] ?? []) as $field) {
                if (($field['value_type'] ?? null) === 'business_logo' && filled($field['value'] ?? null)) {
                    $configuredHeaderLogo = (string) $field['value'];
                    break 2;
                }
            }
        }
    @endphp

    <title>Invoice {{ $invoiceNo }}</title>

    <style>
        :root {
            --ink: #1f2937;
            --muted: #6b7280;
            --line: #e5e7eb;
            --accent: #111827;
            --bg: #f3f4f6;
            --invoice-frame-width: min(900px, calc(100vw - 24px));
            --invoice-arrow-gap: 52px;
        }

        body {
            font-family: "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 30px 0;
            color: var(--ink);
        }

        .invoice {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 48px 54px 60px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
            box-sizing: border-box;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: start;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 45%;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        .brand img {
            height: 44px;
            width: auto;
        }

        .invoice-title {
            text-align: right;
            max-width: 45%;
        }

        .invoice-title h1 {
            margin: 0 0 8px;
            font-size: 26px;
            letter-spacing: 2px;
            font-weight: 600;
            color: var(--accent);
        }

        .invoice-title .subtitle {
            font-size: 12px;
            color: var(--muted);
        }


        .party-row {
            margin-top: 26px;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            font-size: 12.5px;
            color: var(--muted);
        }

        .party-row strong {
            color: var(--accent);
        }

        .invoice-meta {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 28px;
            font-size: 12.5px;
            color: var(--muted);
        }

        .invoice-meta div {
            display: grid;
            grid-template-columns: 96px minmax(0, 1fr);
            align-items: baseline;
            gap: 10px;
        }

        .invoice-meta span.label {
            color: var(--muted);
        }

        .divider {
            margin: 26px 0 14px;
            border-top: 1px solid var(--line);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            color: #fff;
            background: #111827;
            padding: 10px 8px;
            font-weight: 600;
        }

        tbody td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
            vertical-align: top;
        }

        .item-meta {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        .summary {
            width: 320px;
            margin-left: auto;
            margin-top: auto;
            font-size: 12.5px;
            color: var(--muted);
            background: #fff;
            padding: 0;
            border-radius: 0;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .summary div {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
        }

        .summary .grand {
            margin-top: 6px;
            background: #111827;
            padding: 10px 12px;
            border-radius: 6px;
            font-weight: 600;
            color: #fff;
        }


        .notes {
            margin-top: 32px;
            font-size: 12px;
            color: var(--muted);
        }

        .payment-history {
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
        }

        .payment-history table {
            margin-top: 8px;
        }

        .payment-history th,
        .payment-history td {
            text-align: left;
            padding: 8px 6px;
            border-bottom: 1px solid var(--line);
        }

        .contact-us {
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
        }

        .contact-us strong {
            color: var(--accent);
        }

        .footer-gap {
            flex: 1 1 auto;
        }

        .actions {
            position: fixed;
            top: 24px;
            left: 24px;
            right: 24px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            z-index: 10;
            align-items: flex-start;
            pointer-events: none;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            pointer-events: auto;
        }

        .btn {
            border: none;
            background: #111827;
            color: #fff;
            padding: 6px 12px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
            line-height: 18px;
        }

        .btn.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .combo-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            pointer-events: auto;
            min-width: 320px;
        }

        .combo-label {
            font-size: 12px;
            color: #374151;
            font-weight: 600;
            line-height: 1.2;
        }

        .combo-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .combo-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .combo-select {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            font-size: 12px;
            border-radius: 8px;
            padding: 6px 8px;
            width: 100%;
        }

        .side-arrow {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            line-height: 1;
            padding: 0;
            z-index: 12;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
        }

        .side-arrow.left {
            left: max(12px, calc(50% - (var(--invoice-frame-width) / 2) - var(--invoice-arrow-gap)));
        }

        .side-arrow.right {
            right: max(12px, calc(50% - (var(--invoice-frame-width) / 2) - var(--invoice-arrow-gap)));
        }

        .side-arrow:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .invoice-nav-arrow {
            text-decoration: none;
        }

        @media (max-width: 900px) {
            .actions {
                position: static;
                margin: 12px;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .combo-controls {
                width: 100%;
                min-width: 0;
            }

            .combo-fields {
                grid-template-columns: 1fr;
            }

            .side-arrow {
                display: none;
            }
        }

        @media (max-width: 560px) {
            .invoice-items-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .invoice-items-scroll table {
                width: auto;
                min-width: 640px;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .invoice {
                box-shadow: none;
                border-radius: 0;
                padding: 12mm;
                min-height: 100vh;
                height: 100vh;
            }
            .actions {
                display: none;
            }
            .side-arrow {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="actions">
    <form
        id="invoice-template-form"
        class="combo-controls"
        method="GET"
        action="{{ route('invoices.show', ['type' => $type, 'id' => $record->id]) }}"
    >
        <span class="combo-label">Invoice Template</span>

        <div class="combo-fields">
            <div class="combo-field">
                <label class="combo-label" for="invoice-header-select">Header</label>
                <select id="invoice-header-select" name="header" class="combo-select">
                    @foreach($headerGroupOptions as $optionId => $optionLabel)
                        <option value="{{ $optionId }}" @selected((string) $optionId === $selectedHeaderGroupId)>
                            {{ $optionLabel }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="combo-field">
                <label class="combo-label" for="invoice-footer-select">Footer</label>
                <select id="invoice-footer-select" name="footer" class="combo-select">
                    @foreach($footerGroupOptions as $optionId => $optionLabel)
                        <option value="{{ $optionId }}" @selected((string) $optionId === $selectedFooterGroupId)>
                            {{ $optionLabel }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <div class="action-buttons">
        <button class="btn" type="button" onclick="window.print()">Print</button>
        <button
            class="btn secondary"
            type="button"
            aria-label="Close"
            onclick="window.location.href = @js($closeUrl ?? url('/'))"
        >
            Close
        </button>
    </div>
</div>

<button
    type="button"
    id="invoice-prev-arrow"
    class="side-arrow left invoice-nav-arrow"
    aria-label="Previous Invoice"
    data-href="{{ $previousInvoiceUrl ?? '' }}"
    @if(empty($previousInvoiceUrl)) disabled @endif
>&lt;</button>

<button
    type="button"
    id="invoice-next-arrow"
    class="side-arrow right invoice-nav-arrow"
    aria-label="Next Invoice"
    data-href="{{ $nextInvoiceUrl ?? '' }}"
    @if(empty($nextInvoiceUrl)) disabled @endif
>&gt;</button>

<div>
    <section>
        <div class="invoice">
                <div class="header">
                    <div class="brand">
                        @if($configuredHeaderLogo)
                            <img src="{{ asset('storage/'.$configuredHeaderLogo) }}">
                        @elseif($record->merchant?->logo)
                            <img src="{{ asset('storage/'.$record->merchant->logo->photo_url) }}">
                        @else
                            <strong>{{ $record->merchant?->name }}</strong>
                        @endif

                        @if($showDefaultHeader)
                            <div>
                                {{ $record->merchant?->name }}<br>
                                @if($record->merchant?->address)
                                    {{ $record->merchant->address }}<br>
                                @endif
                                @if($record->merchant?->city || $record->merchant?->country)
                                    {{ $record->merchant->city }} {{ $record->merchant->country }}<br>
                                @endif
                                @if($record->merchant?->phone)
                                    {{ $record->merchant->phone }}<br>
                                @endif
                                @if($record->merchant?->email)
                                    {{ $record->merchant->email }}<br>
                                @endif
                                @if($record->merchant?->vat_number)
                                    VAT: {{ $record->merchant->vat_number }}
                                @endif
                            </div>
                        @endif

                        @foreach($headerGroups as $group)
                            <div>
                                <strong>{{ $group['group_name'] }}</strong><br>
                                @foreach($group['fields'] as $field)
                                    @continue(($field['value_type'] ?? null) === 'business_logo')
                                    {{ $field['label'] }}: {{ $field['value'] }}<br>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="invoice-title">
                        <h1>INVOICE</h1>
                        <div class="subtitle">Invoice# {{ $invoiceNo }}</div>
                    </div>
                </div>

                <div class="party-row">
                    <div>
                        <strong>{{ $isSale ? 'Bill To' : 'Bill From' }}</strong><br>
                        {{ $party?->name }}<br>
                        @if($party?->email)
                            {{ $party->email }}<br>
                        @endif
                        @if($party?->phone)
                            {{ $party->phone }}<br>
                        @endif
                        {{ $party?->address ?? '—' }}
                    </div>
                    <div>
                        <div class="invoice-meta">
                            <div><span class="label">Invoice Date</span><span>{{ $invoiceDate?->format('d/m/Y') }}</span></div>
                            <div><span class="label">Invoice #</span><span>{{ $invoiceNo }}</span></div>
                            <div><span class="label">Due Date</span><span>{{ $invoiceDate?->format('d/m/Y') }}</span></div>
                            <div aria-hidden="true"></div>
                            <div><span class="label">Created By</span><span>{{ $record->createdBy?->name ?: ($record->merchant?->name ?: '—') }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="invoice-items-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Item & Description</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th>Discount (%)</th>
                        <th>Tax (%)</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($record->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                {{ $item->product?->name }}
                                @if($item->variants->first())
                                    <div class="item-meta">
                                        {{ $item->variants->first()->variant?->name }}
                                    </div>
                                @endif
                            </td>
                            <td>Rs{{ number_format($item->unit_price, 2) }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>Rs{{ number_format($item->line_total, 2) }}</td>
                            <td>{{ $formatPercent((float) ($item->discount ?? 0)) }}</td>
                            <td>{{ $formatPercent((float) ($item->tax ?? 0)) }}</td>
                            @php
                                $lineTotal = (float) ($item->line_total ?? 0);
                                $discountRate = (float) ($item->discount ?? 0);
                                $taxRate = (float) ($item->tax ?? 0);

                                $discountAmount = $lineTotal * ($discountRate / 100);
                                $taxableAmount = $lineTotal - $discountAmount;
                                $taxAmount = $taxableAmount * ($taxRate / 100);

                                $lineGrandTotal = $taxableAmount + $taxAmount;
                            @endphp
                            <td>Rs{{ number_format($lineGrandTotal, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>

                <div class="footer-gap"></div>

                <div class="summary" style="margin-bottom: 24px;">
                    <div>
                        <span>Sub Total</span>
                        <span>Rs{{ number_format($record->subtotal, 2) }}</span>
                    </div>

                    <div>
                        <span>Total Discount</span>
                        <span>Rs{{ number_format($totalDiscount, 2) }}</span>
                    </div>

                    <div>
                        <span>Total Tax</span>
                        <span>Rs{{ number_format($totalTax, 2) }}</span>
                    </div>

                    <div>
                        <span>Paid Amount</span>
                        <span>Rs{{ number_format($paidAmount, 2) }}</span>
                    </div>

                    @if($remainingAmount > 0)
                        <div>
                            <span>Remaining Amount</span>
                            <span>Rs{{ number_format($remainingAmount, 2) }}</span>
                        </div>
                    @endif

                    <div class="grand">
                        <span>Grand Total</span>
                        <span>Rs{{ number_format($record->total_amount, 2) }}</span>
                    </div>

                </div>

                @if($record->notes)
                    <div class="notes">
                        <strong>Notes</strong><br>
                        {{ $record->notes }}
                    </div>
                @endif

                @if($paymentHistory->isNotEmpty())
                    <div class="payment-history">
                        <strong>Payment History</strong>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($paymentHistory as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                        <td>{{ ucfirst((string) ($payment->entry_type ?? 'payment')) }}</td>
                                        <td>Rs{{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($showDefaultFooter && ($record->merchant?->email || $record->merchant?->phone || $record->merchant?->whatsapp_number))
                    <div class="contact-us">
                        <strong>Contact Us</strong><br>
                        @if($record->merchant?->email)
                            Email: {{ $record->merchant->email }}<br>
                        @endif
                        @if($record->merchant?->phone)
                            Phone: {{ $record->merchant->phone }}<br>
                        @endif
                        @if($record->merchant?->whatsapp_number)
                            WhatsApp: {{ $record->merchant->whatsapp_number }}
                        @endif
                    </div>
                @endif

                @if(! empty($footerGroups))
                    <div class="contact-us">
                        @foreach($footerGroups as $group)
                            <strong>{{ $group['group_name'] }}</strong><br>
                            @foreach($group['fields'] as $field)
                                @continue(($field['value_type'] ?? null) === 'business_logo')
                                {{ $field['label'] }}: {{ $field['value'] }}<br>
                            @endforeach
                            @if(! $loop->last)
                                <br>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
</div>

<script>
    (function () {
        const templateForm = document.getElementById('invoice-template-form');
        const headerSelect = document.getElementById('invoice-header-select');
        const footerSelect = document.getElementById('invoice-footer-select');
        const prevInvoiceArrow = document.getElementById('invoice-prev-arrow');
        const nextInvoiceArrow = document.getElementById('invoice-next-arrow');

        const submitTemplateForm = () => {
            if (templateForm) {
                templateForm.submit();
            }
        };

        headerSelect?.addEventListener('change', submitTemplateForm);
        footerSelect?.addEventListener('change', submitTemplateForm);

        prevInvoiceArrow?.addEventListener('click', () => {
            const href = prevInvoiceArrow.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });

        nextInvoiceArrow?.addEventListener('click', () => {
            const href = nextInvoiceArrow.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });

        document.addEventListener('keydown', (event) => {
            const target = event.target;
            const tagName = target?.tagName?.toLowerCase();
            const isTypingContext = target?.isContentEditable || ['input', 'textarea', 'select'].includes(tagName);

            if (isTypingContext) {
                return;
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                const href = prevInvoiceArrow?.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                const href = nextInvoiceArrow?.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            }
        });
    })();
</script>

</body>
</html>
