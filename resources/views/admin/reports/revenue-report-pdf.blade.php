<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Revenue Report {{ $from->toDateString() }} to {{ $to->toDateString() }}</title>
    <style>
        @page {
            margin: 130px 30px 24px 30px;
        }

        .page-header {
            position: fixed;
            top: -110px;
            left: 0;
            right: 0;
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
        }

        .doc-header td {
            vertical-align: middle;
        }

        .doc-header .logo {
            height: 62px;
            width: auto;
        }

        .doc-header .brand {
            font-size: 14px;
            font-weight: bold;
            margin-top: -15px;
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
            width: 33.33%;
            border: 1px solid #d6d3d1;
            padding: 10px 14px;
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
            font-size: 19px;
            font-weight: bold;
            margin-top: 3px;
        }

        .kpi-sub {
            font-size: 9px;
            color: #a8a29e;
            margin-top: 2px;
        }

        .kpi-value.positive { color: #047857; }
        .kpi-value.negative { color: #be123c; }

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
            padding: 5px 8px;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #57534e;
        }

        table.data td {
            border: 1px solid #d6d3d1;
            padding: 5px 8px;
        }

        table.data td.num, table.data th.num {
            text-align: right;
        }

        table.data tfoot td {
            font-weight: bold;
            background-color: #f5f5f4;
        }

        .two-col {
            width: 100%;
        }

        .two-col td {
            vertical-align: top;
            width: 50%;
        }

        .two-col td:first-child {
            padding-right: 8px;
        }

        .two-col td:last-child {
            padding-left: 8px;
        }

        .empty-note {
            padding: 6px 2px;
            color: #78716c;
            font-style: italic;
            font-size: 10.5px;
        }

        .lost-box {
            width: 100%;
            margin-bottom: 8px;
        }

        .lost-cell {
            width: 50%;
            border: 1px solid #d6d3d1;
            padding: 8px 12px;
        }

        .lost-cell + .lost-cell {
            border-left: none;
        }

        .lost-cell .kpi-label { color: #78716c; }
        .lost-cell .kpi-value { font-size: 15px; color: #be123c; }

        .signoff {
            margin-top: 34px;
            width: 100%;
        }

        .signoff td {
            width: 50%;
            padding-top: 30px;
            font-size: 10px;
            color: #57534e;
        }

        .signoff .line {
            border-top: 1px solid #78716c;
            padding-top: 4px;
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
    <div class="page-header">
        <table class="doc-header">
            <tr>
                <td style="width: 60%;">
                    @if ($logoPath)
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="{{ $logoPath }}" class="logo">
                    @endif
                    @if ($brand->show_brand_text && $brand->brand_text)
                        <div class="brand">{{ $brand->brand_text }}</div>
                    @endif
                    <p class="doc-title">Revenue &amp; Finance Report</p>
                </td>
                <td class="range">
                    {{ $from->toDateString() === $to->toDateString() ? $from->format('F j, Y') : $from->format('M j, Y').' – '.$to->format('M j, Y') }}<br>
                    Printed {{ now()->format('M j, Y g:ia') }}
                </td>
            </tr>
        </table>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <p class="kpi-label">Total Revenue</p>
                <p class="kpi-value positive">₱{{ number_format($totalRevenue, 2) }}</p>
                <p class="kpi-sub">Confirmed &amp; completed bookings only</p>
            </td>
            <td class="kpi-box">
                <p class="kpi-label">Paid Bookings</p>
                <p class="kpi-value">{{ number_format($totalBookings) }}</p>
                <p class="kpi-sub">In selected range</p>
            </td>
            <td class="kpi-box">
                <p class="kpi-label">Avg. per Booking</p>
                <p class="kpi-value">₱{{ number_format($avgPerBooking, 2) }}</p>
                <p class="kpi-sub">Revenue ÷ paid bookings</p>
            </td>
        </tr>
    </table>

    <div class="section">
        <p class="section-title">Daily Revenue</p>
        @if ($trend->isEmpty())
            <p class="empty-note">No confirmed sales in this range.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="num">Bookings</th>
                        <th class="num">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trend as $day)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($day->d)->format('D, M j, Y') }}</td>
                            <td class="num">{{ $day->count }}</td>
                            <td class="num">₱{{ number_format($day->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num">{{ $totalBookings }}</td>
                        <td class="num">₱{{ number_format($totalRevenue, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    <table class="two-col">
        <tr>
            <td>
                <div class="section-title">Revenue by Court</div>
                @if ($byCourt->isEmpty())
                    <p class="empty-note">No data.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>Court</th><th class="num">Bkg</th><th class="num">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($byCourt as $row)
                                <tr>
                                    <td>{{ $row->court_name }}</td>
                                    <td class="num">{{ $row->count }}</td>
                                    <td class="num">₱{{ number_format($row->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td>
                <div class="section-title">Revenue by Payment Method</div>
                @if ($byPaymentMethod->isEmpty())
                    <p class="empty-note">No data.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>Method</th><th class="num">Bkg</th><th class="num">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($byPaymentMethod as $row)
                                <tr>
                                    <td>{{ $row->method_name }}</td>
                                    <td class="num">{{ $row->count }}</td>
                                    <td class="num">₱{{ number_format($row->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="section-title">Revenue by Source</div>
                @if (empty($bySource))
                    <p class="empty-note">No data.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>Source</th><th class="num">Bkg</th><th class="num">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($bySource as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="num">{{ $row['count'] }}</td>
                                    <td class="num">₱{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td>
                <div class="section-title">Outstanding Payments (as of today)</div>
                <table class="data">
                    <thead>
                        <tr><th>Age</th><th class="num">Bkg</th><th class="num">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingAging as $bucket => $data)
                            <tr>
                                <td>{{ $bucket }}</td>
                                <td class="num">{{ $data['count'] }}</td>
                                <td class="num">₱{{ number_format($data['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <p class="section-title">Hold Revenue (HOLD)</p>
        <table class="lost-box">
            <tr>
                <td class="lost-cell">
                    <p class="kpi-label">Currently on hold (as of today)</p>
                    <p class="kpi-value">₱{{ number_format($holdRevenue['total'], 2) }}</p>
                    <p class="kpi-sub">{{ $holdRevenue['count'] }} booking{{ $holdRevenue['count'] === 1 ? '' : 's' }}</p>
                </td>
            </tr>
        </table>

        @if ($holdRevenue['byReason']->isNotEmpty())
            <table class="data">
                <thead>
                    <tr><th>Reason</th><th class="num">Bkg</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                    @foreach ($holdRevenue['byReason'] as $reason => $data)
                        <tr>
                            <td>{{ $reason }}</td>
                            <td class="num">{{ $data['count'] }}</td>
                            <td class="num">₱{{ number_format($data['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <p class="section-title">Lost Revenue (Rejected &amp; Cancelled)</p>
        <table class="lost-box">
            <tr>
                <td class="lost-cell">
                    <p class="kpi-label">Rejected</p>
                    <p class="kpi-value">₱{{ number_format($lost['rejectedTotal'], 2) }}</p>
                    <p class="kpi-sub">{{ $lost['rejectedCount'] }} booking{{ $lost['rejectedCount'] === 1 ? '' : 's' }}</p>
                </td>
                <td class="lost-cell">
                    <p class="kpi-label">Cancelled (not rebooked)</p>
                    <p class="kpi-value">₱{{ number_format($lost['cancelledTotal'], 2) }}</p>
                    <p class="kpi-sub">{{ $lost['cancelledCount'] }} booking{{ $lost['cancelledCount'] === 1 ? '' : 's' }}</p>
                </td>
            </tr>
        </table>

        @if ($lost['byReason']->isNotEmpty())
            <table class="data">
                <thead>
                    <tr><th>Reason</th><th class="num">Bkg</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                    @foreach ($lost['byReason'] as $reason => $data)
                        <tr>
                            <td>{{ $reason }}</td>
                            <td class="num">{{ $data['count'] }}</td>
                            <td class="num">₱{{ number_format($data['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <table class="signoff">
        <tr>
            <td><span class="line">Prepared by &nbsp;&nbsp;&nbsp;&nbsp; Date: ____________</span></td>
            <td><span class="line">Reviewed by / Approved &nbsp;&nbsp;&nbsp;&nbsp; Date: ____________</span></td>
        </tr>
    </table>

    <p class="doc-footer">Generated by the admin revenue &amp; finance reports panel. Figures reflect confirmed/completed bookings only.</p>
</body>
</html>
