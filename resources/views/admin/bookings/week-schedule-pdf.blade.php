<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Week Schedule {{ $weekStart->toDateString() }}</title>
    <style>
        @page {
            margin: 130px 24px 24px 24px;
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
            font-size: 8.5px;
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
            font-size: 12px;
            font-weight: bold;
            margin-top: -15px;
        }

        .doc-header .range {
            text-align: right;
            font-size: 9px;
            color: #57534e;
        }

        .doc-title {
            font-size: 12px;
            font-weight: bold;
            margin: 2px 0;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }

        table.grid th {
            text-align: left;
            background-color: #f5f5f4;
            border: 1px solid #d6d3d1;
            padding: 3px 4px;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #57534e;
        }

        table.grid td {
            border: 1px solid #d6d3d1;
            padding: 2px 3px;
            vertical-align: top;
            font-size: 7px;
            line-height: 1.25;
        }

        table.grid td.time {
            white-space: nowrap;
            font-weight: bold;
            background-color: #fafaf9;
        }

        .cell-empty {
            color: #d6d3d1;
            text-align: center;
        }

        .cell-open {
            color: #a8a29e;
            text-align: center;
        }

        .cell-booking {
            display: block;
            width: 100%;
            border-radius: 3px;
            padding: 1px 3px;
        }

        .cell-booking .name {
            display: block;
            font-weight: bold;
        }

        .cell-booking .label {
            display: block;
        }

        .status-confirmed { background-color: #d1fae5; color: #065f46; }
        .status-awaiting { background-color: #e0e7ff; color: #3730a3; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-completed { background-color: #e0f2fe; color: #075985; }
        .status-other { background-color: #e7e5e4; color: #44403c; }

        .doc-footer {
            margin-top: 8px;
            font-size: 7px;
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
                    <p class="doc-title">Week Schedule — {{ $courtName }}</p>
                </td>
                <td class="range">
                    {{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}<br>
                    Printed {{ now()->format('M j, Y g:ia') }}
                </td>
            </tr>
        </table>
    </div>

    @if ($times->isEmpty())
        <p>No time slots generated for this court in this week.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 9%;">Time</th>
                    @foreach ($days as $day)
                        <th>{{ $day->format('D, M j') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($times as $time)
                    @php
                        $__rowEndTime = $grid[$time]->first()?->end_time;
                    @endphp
                    <tr>
                        <td class="time">
                            {{ \Illuminate\Support\Carbon::parse($time)->format('g:i A') }}@if ($__rowEndTime) - {{ \Illuminate\Support\Carbon::parse($__rowEndTime)->format('g:i A') }}@endif
                        </td>
                        @foreach ($days as $day)
                            @php
                                $__dateStr = $day->toDateString();
                                $__slot = $grid[$time][$__dateStr] ?? null;
                                $__booking = $__slot?->bookings->first();
                                $__statusClass = 'status-other';
                                $__label = null;
                                if ($__booking) {
                                    $__label = $__booking->status === 'pending_payment' && $__booking->hasSubmittedPayment()
                                        ? 'Awaiting Approval'
                                        : str($__booking->status)->replace('_', ' ')->headline();
                                    $__statusClass = match (true) {
                                        $__booking->status === 'pending_payment' && $__booking->hasSubmittedPayment() => 'status-awaiting',
                                        $__booking->status === 'confirmed' => 'status-confirmed',
                                        $__booking->status === 'pending_payment' => 'status-pending',
                                        $__booking->status === 'completed' => 'status-completed',
                                        default => 'status-other',
                                    };
                                }
                            @endphp
                            <td>
                                @if (! $__slot)
                                    <span class="cell-empty">—</span>
                                @elseif ($__booking)
                                    <span class="cell-booking {{ $__statusClass }}">
                                        <span class="name">{{ $__booking->contactName() }}</span>
                                        <span class="label">{{ $__label }}</span>
                                    </span>
                                @else
                                    <span class="cell-open">Open</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="doc-footer">Generated by the admin bookings panel.</p>
</body>
</html>
