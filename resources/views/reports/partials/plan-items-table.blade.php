{{-- Re-used by the three plan sections of the weekly PDF report. Rows are
     grouped by milestone (deliverable's milestone, or milestone_id for envelope
     rows). A "(no milestone)" tail group catches deliverables without one. --}}
@php
    use App\Support\TimeUnits;

    $groups = [];
    foreach ($items as $item) {
        if ($item->milestone_id) {
            $key = $item->milestone_id;
            if (! isset($groups[$key])) {
                $groups[$key] = ['milestone' => $item->milestone, 'header' => null, 'rows' => []];
            }
            $groups[$key]['header'] = $item;
            if (! $groups[$key]['milestone']) {
                $groups[$key]['milestone'] = $item->milestone;
            }
        } elseif ($item->deliverable_id) {
            $m = $item->deliverable->milestone ?? null;
            $key = $m ? $m->id : '_none';
            if (! isset($groups[$key])) {
                $groups[$key] = ['milestone' => $m, 'header' => null, 'rows' => []];
            }
            $groups[$key]['rows'][] = $item;
        }
    }
    uksort($groups, function ($a, $b) use ($groups) {
        if ($a === '_none') return 1;
        if ($b === '_none') return -1;
        return strcasecmp($groups[$a]['milestone']->name ?? '', $groups[$b]['milestone']->name ?? '');
    });
@endphp
<table>
    <thead>
        <tr>
            <th>Item</th>
            <th>Project / Client</th>
            <th class="num">Target</th>
            <th class="num">Allocated</th>
            <th class="num">Spent</th>
            <th>Deadline</th>
            <th class="center">MoSCoW</th>
            <th class="center">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groups as $key => $group)
            @php
                $m = $group['milestone'];
                $header = $group['header'];
                $rows = $group['rows'];
                $isNoMilestoneGroup = ($key === '_none');
            @endphp

            {{-- Group header row --}}
            <tr style="background:#eef2ff;">
                <td colspan="8" style="font-weight:bold; font-size:8pt;">
                    @if ($isNoMilestoneGroup)
                        (no milestone)
                    @else
                        {{ $m->name }}
                        <span style="color:#666; font-weight:normal;">
                            — {{ $m->project->name }} / {{ $m->project->client->legal_name }}
                            @php $emp = $m->project->client->employer ?? null; @endphp
                            @if ($emp)
                                <span style="background:#e0e7ff; color:#3730a3; padding:0 4px; border-radius:6px; font-size:7pt; margin-left:4px;">{{ $emp->name }}</span>
                            @endif
                        </span>
                        @if ($m->isScopeAmbiguous())
                            <span style="color:#b45309; font-size:7pt;">scope?</span>
                        @endif
                    @endif
                </td>
            </tr>

            {{-- Milestone envelope row (if any) --}}
            @if ($header)
                <tr>
                    <td><em>Milestone envelope</em></td>
                    <td><span style="color:#888; font-size:8pt;">forward-planning allocation</span></td>
                    <td class="num">{{ TimeUnits::formatDaysWithHours($m->effective_target_hours) }}</td>
                    <td class="num">{{ TimeUnits::formatDaysWithHours($header->allocated_hours) }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($header->hours_spent) }}</td>
                    <td>{{ $m->deadline ? $m->deadline->format('d M') : '—' }}</td>
                    <td class="center">—</td>
                    <td class="center">
                        <span class="chip chip-{{ $m->status->value }}">{{ $m->status->value }}</span>
                    </td>
                </tr>
            @endif

            {{-- Deliverable rows in this group --}}
            @foreach ($rows as $item)
                @php
                    $d = $item->deliverable;
                    $rowEmp = $d->project->client->employer ?? null;
                @endphp
                <tr>
                    <td>{{ $d->name }}</td>
                    <td>
                        {{ $d->project->name }}<br>
                        <span style="color:#888; font-size:8pt;">{{ $d->project->client->legal_name }}</span>
                        @if ($rowEmp)
                            <span style="background:#e0e7ff; color:#3730a3; padding:0 4px; border-radius:6px; font-size:7pt; margin-left:2px;">{{ $rowEmp->name }}</span>
                        @endif
                    </td>
                    <td class="num">{{ TimeUnits::formatDaysWithHours($d->target_hours) }}</td>
                    <td class="num">{{ TimeUnits::formatDaysWithHours($item->allocated_hours) }}</td>
                    <td class="num">{{ TimeUnits::formatHoursWithDays($item->hours_spent) }}</td>
                    <td>{{ $d->deadline ? $d->deadline->format('d M') : '—' }}</td>
                    <td class="center">
                        @if ($d->moscow)
                            <span class="chip chip-{{ $d->moscow->value }}">{{ $d->moscow->value }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="center">
                        <span class="chip chip-{{ $d->status->value }}">{{ $d->status->value }}</span>
                    </td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
