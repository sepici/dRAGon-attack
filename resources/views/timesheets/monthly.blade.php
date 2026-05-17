{{--
    Monthly timesheet PDF. Landscape A4. Matches the Onur layout:
    rows = projects + ad-hoc names, cols = days 1..N, right col = row total,
    bottom row = per-day totals, footer = "X Hours" / "Y Day(s) worked".

    Inputs:
      $user, $monthStart, $monthEnd, $daysInMonth, $generatedAt,
      $rows (array of [label, days[1..N], total]),
      $dayTotals (array [1..N] => float),
      $totalHours (float),
      $daysWorked (int)
--}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Timesheet — {{ $monthStart->format('F Y') }}</title>
<style>
    /* Sizing trimmed to ~90% of the original to leave breathing room
       horizontally — 30+ day columns × landscape A4 was a tight fit. */
    @page { margin: 18px 18px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #1a1a1a; }

    /* Top-left identity block */
    table.id { border-collapse: collapse; margin-bottom: 12px; }
    table.id td { border: 1px solid #999; padding: 2px 6px; font-size: 8pt; }
    table.id td.label { background: #f1f1f1; font-weight: bold; width: 54px; }
    table.id td.value { min-width: 180px; }

    /* The grid */
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td {
        border: 1px solid #b8b8b8;
        padding: 2px 3px;
        vertical-align: middle;
    }
    table.grid th {
        background: #efefef;
        font-size: 7pt;
        font-weight: bold;
        text-align: center;
    }
    table.grid th.sr      { width: 20px; }
    table.grid th.task    { width: 150px; text-align: left; padding-left: 5px; }
    table.grid th.day     { width: 16px; font-size: 6.5pt; }
    table.grid th.total   { width: 26px; background: #e4e4e4; }

    table.grid td.sr      { text-align: center; color: #555; font-size: 7pt; }
    table.grid td.task    { text-align: left; padding-left: 5px; font-size: 7pt; }
    table.grid td.cell    { text-align: center; font-size: 6.5pt; }
    table.grid td.empty   { background: #fafafa; }
    table.grid td.total   {
        text-align: right;
        background: #f4f4f4;
        font-weight: bold;
        font-size: 7pt;
        padding-right: 5px;
    }

    /* Day-totals strip below the grid */
    table.grid tr.day-totals td {
        border-top: 2px solid #999;
        background: #efefef;
        font-weight: bold;
        font-size: 6.5pt;
        text-align: center;
    }
    table.grid tr.day-totals td.label {
        text-align: right;
        padding-right: 5px;
        background: #fff;
        border: none;
        font-weight: normal;
        font-size: 7pt;
        color: #555;
    }

    /* Summary */
    p.summary { margin-top: 10px; font-size: 8pt; }
    p.summary strong { color: #1E2761; }

    /* Footnote */
    p.note { margin-top: 16px; font-size: 7pt; color: #666; font-style: italic; line-height: 1.5; }

    .empty-state { color: #888; font-style: italic; font-size: 8pt; padding: 12px 0; }
</style>
</head>
<body>

{{-- Identity --}}
<table class="id">
    <tr><td class="label">Name</td><td class="value">{{ $user->name }}</td></tr>
    <tr><td class="label">Month</td><td class="value" style="text-align:right;">{{ $monthStart->format('M-y') }}</td></tr>
</table>

@if (empty($rows))
    <p class="empty-state">No time logged in {{ $monthStart->format('F Y') }}.
        Use the <strong>Daily Journal</strong> to log hours.</p>
@else
    <table class="grid">
        <thead>
            <tr>
                <th class="sr">Sr.<br>No.</th>
                <th class="task">Task</th>
                @for ($d = 1; $d <= $daysInMonth; $d++)
                    <th class="day">{{ $d }}</th>
                @endfor
                <th class="total">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td class="sr">{{ $i + 1 }}</td>
                    <td class="task">{{ $row['label'] }}</td>
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php $v = $row['days'][$d] ?? 0; @endphp
                        @if ($v > 0)
                            <td class="cell">{{ rtrim(rtrim(number_format($v, 1), '0'), '.') }}</td>
                        @else
                            <td class="cell empty">&nbsp;</td>
                        @endif
                    @endfor
                    <td class="total">{{ rtrim(rtrim(number_format($row['total'], 1), '0'), '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="day-totals">
                <td class="label" colspan="2">Daily totals</td>
                @for ($d = 1; $d <= $daysInMonth; $d++)
                    @php $v = $dayTotals[$d] ?? 0; @endphp
                    <td>{{ $v > 0 ? rtrim(rtrim(number_format($v, 1), '0'), '.') : 0 }}</td>
                @endfor
                <td class="total">{{ rtrim(rtrim(number_format($totalHours, 1), '0'), '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="summary">
        <strong>{{ rtrim(rtrim(number_format($totalHours, 1), '0'), '.') }}</strong> Hours
        &nbsp;·&nbsp;
        <strong>{{ $daysWorked }}</strong> Day{{ $daysWorked === 1 ? '' : '(s)' }} worked
    </p>
@endif

<p class="note">
    Generated by dRAGonattack Tracker on {{ $generatedAt->format('d M Y H:i') }}.
    Hours are sourced from daily journal entries between
    {{ $monthStart->format('d M') }} and {{ $monthEnd->format('d M Y') }}.
</p>

</body>
</html>
