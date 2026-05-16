@php
    use App\Support\TimeUnits;

    // Build the initial Alpine state for the ad-hoc widget from existing rows.
    $adHocSeed = $adHocLogs->map(fn ($l) => [
        'id' => $l->id,
        'name' => $l->ad_hoc_name,
        'hours' => (float) $l->hours,
        'notes' => $l->notes,
    ])->all();

    $headroom = $dailyTarget - $totalHours;
    $totalColor = match (true) {
        $totalHours > $dailyTarget + 0.01 => 'text-red-600 dark:text-red-400',
        $totalHours > 0                   => 'text-emerald-600 dark:text-emerald-400',
        default                           => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<x-app-layout>
    <x-slot name="title">Journal · {{ $date->format('d M') }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Daily Journal') }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $date->format('l, d M Y') }}
                    @if ($isToday)
                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">today</span>
                    @endif
                </p>
            </div>

            {{-- Date navigator --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('journal.show', ['date' => $prevDate]) }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    ← {{ \Carbon\Carbon::parse($prevDate)->format('d M') }}
                </a>
                <form method="GET" action="{{ url('/journal') }}"
                      onchange="if (this.date.value) { window.location.href = '/journal/' + this.date.value; }">
                    <input type="date" name="date" value="{{ $date->toDateString() }}"
                           class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                </form>
                @unless ($isToday)
                    <a href="{{ route('journal.today') }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Today</a>
                @endunless
                <a href="{{ route('journal.show', ['date' => $nextDate]) }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    {{ \Carbon\Carbon::parse($nextDate)->format('d M') }} →
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Day total vs daily target --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total logged today</p>
                    <p class="mt-1 text-2xl font-bold {{ $totalColor }}">
                        {{ TimeUnits::formatHoursWithDays($totalHours) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Daily target</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ TimeUnits::formatHoursWithDays($dailyTarget) }}
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">(one workday)</span>
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('journal.store', ['date' => $date->toDateString()]) }}" class="space-y-6">
                @csrf

                {{-- Planned items for the week containing this date --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Planned this week
                            @if ($period)
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">
                                    ({{ $period->starts_on->format('d M') }} → {{ $period->ends_on->format('d M') }})
                                </span>
                            @endif
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Hours you put in on each deliverable today. Leave at 0 to remove the day's log for that item.
                        </p>
                    </div>

                    @if ($planItems->isEmpty() && $extraDeliverables->isEmpty())
                        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                            @if (! $period)
                                You haven't planned this week yet —
                                <a href="{{ route('plans.weekly') }}" class="text-indigo-600 dark:text-indigo-400 underline">plan the week</a> first
                                or use the unplanned-work section below.
                            @else
                                Nothing planned for this week. Add items on the
                                <a href="{{ route('plans.weekly') }}" class="text-indigo-600 dark:text-indigo-400 underline">weekly plan</a>,
                                or log unplanned work below.
                            @endif
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverable</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hours</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($planItems as $item)
                                        @php
                                            $d = $item->deliverable;
                                            $log = $deliverableLogs->get($d->id);
                                        @endphp
                                        <tr class="{{ $item->completed_at ? 'opacity-60' : '' }}">
                                            <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('deliverables.show', $d) }}" class="hover:underline">{{ $d->name }}</a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $d->project->name }}</div>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                {{ $d->project->client->legal_name }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                <input type="number"
                                                    name="items[{{ $d->id }}][hours]"
                                                    step="0.5" min="0" max="24"
                                                    value="{{ old("items.{$d->id}.hours", $log ? number_format((float) $log->hours, 1) : '') }}"
                                                    placeholder="0"
                                                    class="w-20 text-right text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                            </td>
                                            <td class="px-3 py-3 text-sm">
                                                <input type="text"
                                                    name="items[{{ $d->id }}][notes]"
                                                    value="{{ old("items.{$d->id}.notes", $log?->notes) }}"
                                                    placeholder="What you did, blockers, etc."
                                                    class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                            </td>
                                        </tr>
                                    @endforeach

                                    {{-- Deliverables with a log on this date but not on this week's plan. --}}
                                    @if ($extraDeliverables->isNotEmpty())
                                        <tr class="bg-amber-50/40 dark:bg-amber-900/10">
                                            <td colspan="4" class="px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400">
                                                Also logged on this date (not on this week's plan):
                                            </td>
                                        </tr>
                                        @foreach ($extraDeliverables as $d)
                                            @php $log = $deliverableLogs->get($d->id); @endphp
                                            <tr>
                                                <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <a href="{{ route('deliverables.show', $d) }}" class="hover:underline">{{ $d->name }}</a>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $d->project->name }}</div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $d->project->client->legal_name }}
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                    <input type="number"
                                                        name="items[{{ $d->id }}][hours]"
                                                        step="0.5" min="0" max="24"
                                                        value="{{ old("items.{$d->id}.hours", number_format((float) $log->hours, 1)) }}"
                                                        class="w-20 text-right text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                                </td>
                                                <td class="px-3 py-3 text-sm">
                                                    <input type="text"
                                                        name="items[{{ $d->id }}][notes]"
                                                        value="{{ old("items.{$d->id}.notes", $log?->notes) }}"
                                                        class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Ad-hoc / unplanned work --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg"
                     x-data="{
                        rows: @js(count($adHocSeed) > 0 ? $adHocSeed : [['id' => null, 'name' => '', 'hours' => '', 'notes' => '']]),
                        add() { this.rows.push({ id: null, name: '', hours: '', notes: '' }); },
                        remove(i) { this.rows.splice(i, 1); if (this.rows.length === 0) this.add(); },
                     }">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Unplanned work today</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Hours on things that aren't tied to a tracked deliverable — emergency support, a one-off call, internal admin.
                            </p>
                        </div>
                        <button type="button" @click="add()" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            + Add row
                        </button>
                    </div>

                    <div class="p-6 space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <input type="hidden" :name="`ad_hoc[${i}][id]`" :value="row.id ?? ''">
                                <input type="text" :name="`ad_hoc[${i}][name]`" x-model="row.name"
                                    placeholder="e.g. emergency server intervention"
                                    class="col-span-5 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                <input type="number" :name="`ad_hoc[${i}][hours]`" x-model="row.hours"
                                    step="0.5" min="0" max="24" placeholder="Hours"
                                    class="col-span-2 text-sm text-right border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                <input type="text" :name="`ad_hoc[${i}][notes]`" x-model="row.notes"
                                    placeholder="Notes (optional)"
                                    class="col-span-4 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                <button type="button" @click="remove(i)"
                                    class="col-span-1 text-xs text-red-600 dark:text-red-400 hover:underline">
                                    Remove
                                </button>
                            </div>
                        </template>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Rows with a blank name are ignored on save. Removed rows are deleted on save.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <x-primary-button>{{ __('Save journal') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
