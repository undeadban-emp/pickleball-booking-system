<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Court Schedule {{ $from->toDateString() }} to {{ $to->toDateString() }}</title>
    <style>
        @page {
            margin: 20px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Helvetica", "Arial", sans-serif;
            font-size: 15.5px;
            color: #1c1917;
        }

        .doc-header {
            width: 100%;
            border-bottom: 2px solid #1c1917;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .doc-header td {
            vertical-align: middle;
        }

        .doc-header .logo {
            height: 52px;
            width: auto;
        }

        .doc-header .brand {
            font-size: 15px;
            font-weight: bold;
        }

        .doc-header .range {
            text-align: right;
            font-size: 11px;
            color: #57534e;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 2px;
        }

        .legend {
            margin-bottom: 14px;
        }

        .legend span {
            display: inline-block;
            margin-right: 16px;
            font-size: 10px;
        }

        .legend i {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-right: 4px;
            border: 1px solid #d6d3d1;
            vertical-align: middle;
        }

        .swatch-vacant { background-color: #d1fae5; }
        .swatch-occupied { background-color: #fecdca; }
        .swatch-blocked { background-color: #e7e5e4; }

        .court-section {
            margin-bottom: 18px;
        }

        .court-section.page-break {
            page-break-before: always;
        }

        .court-heading {
            background-color: #1c1917;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .court-heading .date {
            float: right;
            font-weight: normal;
            color: #d6d3d1;
        }

        table.schedule {
            width: 100%;
            border-collapse: collapse;
        }

        table.schedule th {
            text-align: left;
            background-color: #f5f5f4;
            border: 1px solid #d6d3d1;
            padding: 15px 16px;
            font-size: 13.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #57534e;
        }

        table.schedule td {
            border: 1px solid #d6d3d1;
            padding: 15px 16px;
            vertical-align: top;
        }

        tr.vacant td { background-color: #d1fae5; }
        tr.occupied td { background-color: #fecdca; }
        tr.blocked td { background-color: #e7e5e4; color: #78716c; }

        td.time { white-space: nowrap; font-weight: bold; width: 90px; }
        td.name { width: 28%; }
        td.phone { width: 18%; }

        .empty-note {
            padding: 8px 2px;
            color: #78716c;
            font-style: italic;
        }

        .doc-footer {
            margin-top: 6px;
            font-size: 9px;
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
                <p class="doc-title">Court Schedule</p>
            </td>
            <td class="range">
                {{ $from->toDateString() === $to->toDateString() ? $from->format('F j, Y') : $from->format('M j, Y').' – '.$to->format('M j, Y') }}<br>
                {{ $courts->count() === 1 ? $courts->first()->name : 'All courts' }}<br>
                Printed {{ now()->format('M j, Y g:ia') }}
            </td>
        </tr>
    </table>

    <div class="legend">
        <span><i class="swatch-vacant"></i>Vacant</span>
        <span><i class="swatch-occupied"></i>Occupied</span>
        <span><i class="swatch-blocked"></i>Blocked / maintenance</span>
    </div>

    @php $__first = true; @endphp
    @for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay())
        @foreach ($courts as $court)
            @php
                $__daySlots = $slots->get($date->toDateString(), collect())->get($court->id, collect());
            @endphp

            <div class="court-section {{ $__first ? '' : 'page-break' }}">
                @php $__first = false; @endphp
                <div class="court-heading">
                    {{ $court->name }}
                    <span class="date">{{ $date->format('l, F j, Y') }}</span>
                </div>

                @if ($__daySlots->isEmpty())
                    <p class="empty-note">No slots generated for this date.</p>
                @else
                    <table class="schedule">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Time</th>
                                <th>Name</th>
                                <th>Cellphone</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($__daySlots as $slot)
                                @php
                                    $__booking = $slot->status === 'booked' ? $slot->bookings->first() : null;
                                    $__rowClass = $slot->status === 'blocked' ? 'blocked' : ($__booking ? 'occupied' : 'vacant');
                                @endphp
                                <tr class="{{ $__rowClass }}">
                                    <td class="time">{{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:ia') }}&ndash;{{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:ia') }}</td>
                                    <td class="name">{{ $__booking ? $__booking->contactName() : ($slot->status === 'blocked' ? 'Blocked' : '—') }}</td>
                                    <td class="phone">{{ $__booking ? ($__booking->contactPhone() ?? '—') : '—' }}</td>
                                    <td>{{ $__booking ? ($__booking->contactEmail() ?? '—') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    @endfor

    <p class="doc-footer">Generated by the admin booking reports panel.</p>
</body>
</html>
