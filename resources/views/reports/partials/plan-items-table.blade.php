{{-- Re-used by the three plan sections of the weekly PDF report. --}}
@php use App\Support\TimeUnits; @endphp
<table>
    <thead>
        <tr>
            <th>Deliverable</th>
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
        @foreach ($items as $item)
            @php $d = $item->deliverable; @endphp
            <tr>
                <td>{{ $d->name }}</td>
                <td>{{ $d->project->name }}<br><span style="color:#888; font-size:8pt;">{{ $d->project->client->legal_name }}</span></td>
                <td class="num">{{ TimeUnits::formatHoursWithDays($d->target_hours) }}</td>
                <td class="num">{{ TimeUnits::formatHoursWithDays($item->allocated_hours) }}</td>
                <td class="num">{{ TimeUnits::formatHoursWithDays($d->hours_spent) }}</td>
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
    </tbody>
</table>
