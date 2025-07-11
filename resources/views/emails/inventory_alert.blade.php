    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Alert</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
<h2 style="color: #e63946;">Inventory Alert</h2>
<p style="font-size: 16px;">Dear Team,</p>
<p style="font-size: 14px; color: #555;">
    We have observed that the number of keys used in the last 14 days is approaching a critical threshold compared to the total keys in stock. Please review the details below:
</p>

@foreach ($sections as $section => $color)
    <h3 style="color: #{{ $color }};">{{ ucfirst($section) }} Keys</h3>
    <table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse; margin: 20px 0;">
        <tr style="background-color: #f4f4f4;">
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Metric</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Value</th>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Total Keys Used</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['keys_used'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Total Keys in Stock</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['keys_in_stock'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Keys Used (Last 14 Days)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['used_last_14_days'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Keys Used (Last 30 Days)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['used_last_30_days'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Run Rate (Last 14 Days)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ number_format($analyticsData[$section]['run_rate_14_days'] ?? 0.00, 2) }} keys/day</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Run Rate (Last 30 Days)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ number_format($analyticsData[$section]['run_rate_30_days'] ?? 0.00, 2) }} keys/day</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Keys needed to RMA</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['keys_rma_needed'] ?? 0 }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Days Left of Inventory(14 days)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['days_left_inventory_14_days'] ?? 'Keys not used in last 14 days' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd;">Days Left of Inventory(30 days)</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $analyticsData[$section]['days_left_inventory_30_days'] ?? 'Keys not used in last 30 days' }}</td>
        </tr>
    </table>
@endforeach

<p style="font-size: 14px; color: #555;">
    Please take necessary actions to ensure there is no disruption in inventory. Thank you for your attention.
</p>
<p style="font-size: 14px;">Best Regards,<br>Your Inventory Management Team</p>
</body>
</html>
