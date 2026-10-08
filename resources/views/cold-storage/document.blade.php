<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $record->receipt_no ?? $record->dispatch_no ?? $record->bill_no }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #0f172a; margin: 2rem; }
        h1 { margin-bottom: 0.25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border-bottom: 1px solid #e2e8f0; text-align: left; padding: 0.5rem; vertical-align: top; }
        .meta { color: #475569; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>
        @if ($type === 'receipt') Goods receipt @elseif ($type === 'dispatch') Gate pass @else Storage bill @endif
    </h1>
    <p class="meta">
        {{ $record->merchant?->name }} · {{ $record->business?->name }} · {{ $record->branch?->name }}<br>
        Customer: {{ $record->customer?->name }}
    </p>

    @if ($type === 'receipt')
        <p>Receipt {{ $record->receipt_no }} · {{ $record->received_on?->format('d/m/Y') }} · Vehicle {{ $record->vehicle_number ?: '—' }}</p>
        <table>
            <thead><tr><th>Product</th><th>Lot</th><th>Packages</th><th>Weight</th><th>Location</th></tr></thead>
            <tbody>
            @foreach ($record->items as $item)
                <tr>
                    <td>{{ $item->product?->name }}</td>
                    <td>{{ $item->lot_number }}</td>
                    <td>{{ $item->package_count }}</td>
                    <td>{{ $item->net_weight }} {{ $item->weight_unit }}</td>
                    <td>
                        @foreach ($item->allocations as $allocation)
                            <div>{{ $allocation->chamber?->name }} {{ $allocation->location?->name }}</div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p>These goods remain the property of the customer. This receipt is not a purchase.</p>
    @elseif ($type === 'dispatch')
        <p>Gate pass {{ $record->dispatch_no }} · {{ $record->dispatched_on?->format('d/m/Y') }}</p>
        <p>Recipient: {{ $record->recipient_name }} · Vehicle {{ $record->vehicle_number ?: '—' }}</p>
        <table>
            <thead><tr><th>Lot</th><th>Product</th><th>Packages</th><th>Weight</th><th>Location</th></tr></thead>
            <tbody>
            @foreach ($record->lines as $line)
                <tr>
                    <td>{{ $line->receiptItem?->lot_number }}</td>
                    <td>{{ $line->receiptItem?->product?->name }}</td>
                    <td>{{ $line->package_count }}</td>
                    <td>{{ $line->net_weight }}</td>
                    <td>{{ $line->chamber?->name }} {{ $line->location?->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p>This gate pass releases customer-owned goods. It is not a product sale.</p>
    @else
        <p>Bill {{ $record->bill_no }} · {{ $record->period_start?->format('d/m/Y') }} to {{ $record->period_end?->format('d/m/Y') }}</p>
        <table>
            <thead><tr><th>Lot</th><th>Period</th><th>Quantity-days</th><th>Rate</th><th>Total</th></tr></thead>
            <tbody>
            @foreach ($record->lines as $line)
                <tr>
                    <td>{{ $line->lot_number }}</td>
                    <td>{{ $line->period_start?->format('d/m/Y') }} – {{ $line->period_end?->format('d/m/Y') }}</td>
                    <td>{{ $line->quantity_days }}</td>
                    <td>{{ number_format((float) $line->rate, 2) }}</td>
                    <td>{{ $currency }} {{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @endforeach
            @foreach ($record->services as $service)
                <tr>
                    <td colspan="4">{{ $service->name }}</td>
                    <td>{{ $currency }} {{ number_format((float) $service->line_total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p>Total {{ $currency }} {{ number_format((float) $record->total_amount, 2) }} · Due {{ $currency }} {{ number_format((float) $record->due_amount, 2) }}</p>
    @endif
</body>
</html>
