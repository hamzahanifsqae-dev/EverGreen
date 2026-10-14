<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Cold storage action alerts</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #0f172a; line-height: 1.5; margin: 0; padding: 24px; background: #f8fafc;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;">
        <tr>
            <td style="padding: 24px;">
                <h1 style="margin: 0 0 8px; font-size: 20px;">Cold storage action alerts</h1>
                <p style="margin: 0 0 20px; color: #64748b; font-size: 14px;">
                    {{ $merchant->name }} · {{ number_format($digest['open_count'] ?? 0) }} open
                </p>

                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 24px;">
                    <tr>
                        <td style="padding: 12px; background: #fffbeb; border-radius: 8px; width: 50%;">
                            <div style="font-size: 12px; color: #92400e;">Bills due</div>
                            <div style="font-size: 22px; font-weight: bold;">{{ number_format($digest['bill_count'] ?? 0) }}</div>
                        </td>
                        <td style="width: 8px;"></td>
                        <td style="padding: 12px; background: #f0f9ff; border-radius: 8px; width: 50%;">
                            <div style="font-size: 12px; color: #075985;">Reservations</div>
                            <div style="font-size: 22px; font-weight: bold;">{{ number_format($digest['reservation_count'] ?? 0) }}</div>
                        </td>
                    </tr>
                </table>

                @if (! empty($digest['bills']))
                    <h2 style="font-size: 16px; margin: 0 0 8px;">Outstanding storage bills</h2>
                    <table width="100%" cellpadding="8" cellspacing="0" role="presentation" style="border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: #f1f5f9; text-align: left;">
                                <th>Bill</th>
                                <th>Customer</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($digest['bills'] as $row)
                                <tr style="border-top: 1px solid #e2e8f0;">
                                    <td>{{ $row['bill_no'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>{{ $digest['currency'] }} {{ number_format($row['due_amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if (! empty($digest['reservations']))
                    <h2 style="font-size: 16px; margin: 0 0 8px;">Reservations starting soon</h2>
                    <table width="100%" cellpadding="8" cellspacing="0" role="presentation" style="border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: #f1f5f9; text-align: left;">
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>From</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($digest['reservations'] as $row)
                                <tr style="border-top: 1px solid #e2e8f0;">
                                    <td>{{ $row['reservation_no'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>{{ $row['reserved_from'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if (! empty($digest['panel_url']))
                    <p style="margin: 0;">
                        <a href="{{ $digest['panel_url'] }}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 14px;">
                            Open action alerts
                        </a>
                    </p>
                @endif

                <p style="margin: 24px 0 0; font-size: 12px; color: #94a3b8;">
                    Recipients are managed under Cold Storage → Alert emails. This is separate from notification templates.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
