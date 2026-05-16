{{--
    DomPDF-rendered weekly report. Keep CSS simple — no flexbox, no grid,
    no modern features. Tables for layout, inline styles where useful.

    Expects: $user, $generatedAt, $thisWeek, $completedItems, $adHocItems,
    $incompleteItems, $weekCapacity, $weekTotalSpent, $nextWeek,
    $nextWeekItems, $thisMonth, $monthItems, $monthCapacity, $thisQuarter,
    $quarterItems, $quarterCapacity.
--}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Weekly Report</title>
<style>
    @page { margin: 36px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.4; }
    h1 { font-size: 18pt; margin: 0 0 4px 0; color: #1E2761; }
    h2 { font-size: 13pt; margin: 18px 0 6px 0; padding: 4px 8px; background: #1E2761; color: #fff; }
    p.meta { color: #555; margin: 0 0 12px 0; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { padding: 5px 6px; border: 1px solid #d4d4d4; vertical-align: top; }
    th { background: #f1f1f1; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; color: #444; }
    td.num { text-align: right; }
    td.center { text-align: center; }
    .chip { display: inline-block; padding: 1px 6px; border-radius: 9px; font-size: 8pt; font-weight: bold; color: #fff; }
    .chip-R { background: #E74C3C; }
    .chip-A { background: #F39C12; }
    .chip-G { background: #27AE60; }
    .chip-B { background: #6F42C1; }
    .chip-M { background: #1E2761; }
    .chip-S { background: #059669; }
    .chip-C { background: #F59E0B; }
    .chip-W { background: #6B7280; }
    .summary { margin-top: 4px; font-size: 9pt; }
    .summary strong { color: #1E2761; }
    .empty { color: #888; font-style: italic; padding: 6px 0; }
    .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #d4d4d4; font-size: 8pt; color: #888; }
    .over { color: #E74C3C; font-weight: bold; }
    .under { color: #27AE60; font-weight: bold; }
</style>
</head>
<body>

<h1>Weekly Status Report</h1>
<p class="meta">
    {{ $user->name }} &mdash;
    Week of {{ $thisWeek->starts_on->format('d M Y') }}
    to {{ $thisWeek->ends_on->format('d M Y') }} &mdash;
    Generated {{ $generatedAt->format('d M Y H:i') }}
</p>

{{-- ============================================================== --}}
{{-- 1. Review of completed week                                      --}}
{{-- ============================================================== --}}
<h2>1. Review of completed week</h2>

@php
    use App\Support\TimeUnits;
    $over = $weekTotalSpent - $weekCapacity;
@endphp
<p class="summary">
    <strong>{{ TimeUnits::formatHoursWithDays($weekTotalSpent) }}</strong> spent
    against a capacity of <strong>{{ TimeUnits::formatHoursWithDays($weekCapacity) }}</strong>
    @if ($over > 0)
        — <span class="over">{{ TimeUnits::formatHoursWithDays($over) }} over</span>.
    @elseif ($over < 0)
        — <span class="under">{{ TimeUnits::formatHoursWithDays(abs($over)) }} under</span>.
    @else
        — exactly to capacity.
    @endif
</p>

@if ($completedItems->isEmpty())
    <p class="empty">Nothing completed this week.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Deliverable</th>
                <th>Client</th>
                <th class="num">Allocated</th>
                <th class="num">Spent</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($completedItems as $item)
                <tr>
                    <td>{{ $item->deliverable->name }}<br><span style="color:#888; font-size:8pt;">{{ $item->deliverable->project->name }}</span></td>
                    <td>{{ $item->deliverable->project->client->legal_name }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($item->allocated_hours) }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($item->hours_spent) }}</td>
                    <td>{{ $item->notes ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($adHocItems->isNotEmpty())
    <h2 style="margin-top:14px; background:#6B7280;">Unplanned work this week</h2>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Spent</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($adHocItems as $a)
                <tr>
                    <td>{{ $a->ad_hoc_name }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($a->hours_spent) }}</td>
                    <td>{{ $a->ad_hoc_notes ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($incompleteItems->isNotEmpty())
    <h2 style="margin-top:14px; background:#E74C3C;">Carried over (incomplete)</h2>
    <table>
        <thead>
            <tr>
                <th>Deliverable</th>
                <th>Client</th>
                <th class="num">Allocated</th>
                <th class="num">Spent</th>
                <th class="center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($incompleteItems as $item)
                <tr>
                    <td>{{ $item->deliverable->name }}</td>
                    <td>{{ $item->deliverable->project->client->legal_name }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($item->allocated_hours) }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($item->hours_spent) }}</td>
                    <td class="center"><span class="chip chip-{{ $item->status->value }}">{{ $item->status->value }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ============================================================== --}}
{{-- 2. Plan for the new week                                         --}}
{{-- ============================================================== --}}
<h2>2. Plan for the new week</h2>
@if (! $nextWeek || $nextWeekItems->isEmpty())
    <p class="empty">Next week's plan is empty — add items on /plans/weekly to populate.</p>
@else
    <p class="summary">
        {{ $nextWeek->starts_on->format('d M Y') }}
        to {{ $nextWeek->ends_on->format('d M Y') }}
        &mdash; {{ $nextWeekItems->count() }} item(s),
        {{ TimeUnits::formatHoursWithDays($nextWeekItems->sum(fn ($i) => (float) $i->allocated_hours)) }} allocated.
    </p>
    @include('reports.partials.plan-items-table', ['items' => $nextWeekItems])
@endif

{{-- ============================================================== --}}
{{-- 3. Updated 1-month plan                                           --}}
{{-- ============================================================== --}}
<h2>3. 1-Month plan</h2>
@php
    $monthTotal = (float) $monthItems->sum(fn ($i) => (float) $i->allocated_hours);
    $monthOver = $monthTotal - $monthCapacity;
@endphp
<p class="summary">
    {{ $thisMonth->starts_on->format('d M Y') }}
    to {{ $thisMonth->ends_on->format('d M Y') }}
    &mdash; <strong>{{ TimeUnits::formatHoursWithDays($monthTotal) }}</strong> planned of
    <strong>{{ TimeUnits::formatHoursWithDays($monthCapacity) }}</strong> capacity
    @if ($monthOver > 0)
        (<span class="over">{{ TimeUnits::formatHoursWithDays($monthOver) }} over</span>).
    @elseif ($monthOver < 0)
        (<span class="under">{{ TimeUnits::formatHoursWithDays(abs($monthOver)) }} under</span>).
    @else
        (exactly to capacity).
    @endif
</p>
@if ($monthItems->isEmpty())
    <p class="empty">No items on the monthly plan.</p>
@else
    @include('reports.partials.plan-items-table', ['items' => $monthItems])
@endif

{{-- ============================================================== --}}
{{-- 4. 3-Month plan                                                   --}}
{{-- ============================================================== --}}
<h2>4. 3-Month plan</h2>
@php
    $quarterTotal = (float) $quarterItems->sum(fn ($i) => (float) $i->allocated_hours);
    $quarterOver = $quarterTotal - $quarterCapacity;
@endphp
<p class="summary">
    {{ $thisQuarter->starts_on->format('d M Y') }}
    to {{ $thisQuarter->ends_on->format('d M Y') }}
    &mdash; <strong>{{ TimeUnits::formatHoursWithDays($quarterTotal) }}</strong> planned of
    <strong>{{ TimeUnits::formatHoursWithDays($quarterCapacity) }}</strong> capacity
    @if ($quarterOver > 0)
        (<span class="over">{{ TimeUnits::formatHoursWithDays($quarterOver) }} over</span>).
    @elseif ($quarterOver < 0)
        (<span class="under">{{ TimeUnits::formatHoursWithDays(abs($quarterOver)) }} under</span>).
    @else
        (exactly to capacity).
    @endif
</p>
@if ($quarterItems->isEmpty())
    <p class="empty">No items on the quarterly plan.</p>
@else
    @include('reports.partials.plan-items-table', ['items' => $quarterItems])
@endif

<p class="footer">
    Generated by dRAGonattack Tracker on {{ $generatedAt->format('d M Y H:i') }} for {{ $user->email }}.
</p>

</body>
</html>
