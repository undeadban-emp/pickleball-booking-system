<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Report {{ $from->toDateString() }} to {{ $to->toDateString() }}</title>
    <style>
        @page {
            margin: 22px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", "Helvetica", "Arial", sans-serif;
            font-size: 11.5px;
            color: #1c1917;
        }

        .doc-header {
            width: 100%;
            border-bottom: 2px solid #1c1917;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }

        .doc-header td {
            vertical-align: middle;
        }

        .doc-header .logo {
            height: 46px;
            width: auto;
        }

        .doc-header .brand {
            font-size: 14px;
            font-weight: bold;
        }

        .doc-header .range {
            text-align: right;
            font-size: 10.5px;
            color: #57534e;
        }

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            margin: 2px 0;
        }

        .kpi-table {
            width: 100%;
            margin-bottom: 18px;
        }

        .kpi-box {
            width: 25%;
            border: 1px solid #d6d3d1;
            padding: 10px 12px;
        }

        .kpi-box + .kpi-box {
            border-left: none;
        }

        .kpi-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #78716c;
        }

        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            margin-top: 3px;
        }

        .kpi-sub {
            font-size: 9px;
            color: #a8a29e;
            margin-top: 2px;
        }

        .section {
            margin-bottom: 16px;
        }

        .section-title {
            background-color: #1c1917;
            color: #ffffff;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th {
            text-align: left;
            background-color: #f5f5f4;
            border: 1px solid #d6d3d1;
            padding: 6px 8px;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #57534e;
        }

        table.data td {
            border: 1px solid #d6d3d1;
            padding: 6px 8px;
        }

        table.data td.num, table.data th.num {
            text-align: right;
        }

        table.data td.rank {
            color: #a8a29e;
            width: 28px;
        }

        table.data tbody tr:nth-child(-n+3) td.rank {
            font-weight: bold;
            color: #b45309;
        }

        table.data tfoot td {
            font-weight: bold;
            background-color: #f5f5f4;
        }

        .empty-note {
            padding: 6px 2px;
            color: #78716c;
            font-style: italic;
            font-size: 10.5px;
        }

        .doc-footer {
            margin-top: 14px;
            font-size: 8.5px;
            color: #a8a29e;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="doc-header">
        <tr>
            <td style="width: 60%;">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" class="logo">
                @endif
                @if ($brand->show_brand_text && $brand->brand_text)
                    <div class="brand">{{ $brand->brand_text }}</div>
                @endif
                <p class="doc-title">Client Report</p>
            </td>
            <td class="range">
                {{ $from->toDateString() === $to->toDateString() ? $from->format('F j, Y') : $from->format('M j, Y').' – '.$to->format('M j, Y') }}<br>
                Printed {{ now()->format('M j, Y g:ia') }}
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <p class="kpi-label">New Customers</p>
                <p class="kpi-value">{{ number_format($newVsReturning['new']) }}</p>
                <p class="kpi-sub">First sale in range</p>
            </td>
            <td class="kpi-box">
                <p class="kpi-label">Returning Customers</p>
                <p class="kpi-value">{{ number_format($newVsReturning['returning']) }}</p>
                <p class="kpi-sub">Had an earlier sale</p>
            </td>
            <td class="kpi-box">
                <p class="kpi-label">Registered Bookings</p>
                <p class="kpi-value">{{ number_format($guestVsRegistered['registered']['count']) }}</p>
                <p class="kpi-sub">₱{{ number_format($guestVsRegistered['registered']['total'], 2) }}</p>
            </td>
            <td class="kpi-box">
                <p class="kpi-label">Guest Bookings</p>
                <p class="kpi-value">{{ number_format($guestVsRegistered['guest']['count']) }}</p>
                <p class="kpi-sub">₱{{ number_format($guestVsRegistered['guest']['total'], 2) }}</p>
            </td>
        </tr>
    </table>

    <div class="section">
        <p class="section-title">Top Customers by Spend</p>
        @if ($topCustomers->isEmpty())
            <p class="empty-note">No registered-customer sales in this range.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th class="rank">#</th>
                        <th>Customer</th>
                        <th class="num">Bookings</th>
                        <th class="num">Total Spend</th>
                        <th class="num">Avg / Booking</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topCustomers as $i => $row)
                        <tr>
                            <td class="rank">{{ $i + 1 }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="num">{{ $row->count }}</td>
                            <td class="num">₱{{ number_format($row->total, 2) }}</td>
                            <td class="num">₱{{ number_format($row->count > 0 ? $row->total / $row->count : 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total ({{ $topCustomers->count() }} customer{{ $topCustomers->count() === 1 ? '' : 's' }})</td>
                        <td class="num">{{ $topCustomers->sum('count') }}</td>
                        <td class="num">₱{{ number_format($topCustomers->sum('total'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    <p class="doc-footer">Generated by the admin client reports panel. Registered-customer sales only — guest bookings have no stable identity to roll up by customer.</p>
</body>
</html>
