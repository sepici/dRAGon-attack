@php
    use App\Support\TimeUnits;

    $rangeText = $period->starts_on->format('d M Y') . ' → ' . $period->ends_on->format('d M Y');

    // Group review rows by milestone (same shape as plans.show). A milestone
    // envelope row appears as a "Milestone" entry the user can also tick
    // done. Deliverable rows under a milestone are nested below its header.
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

<x-app-layout>
    <x-slot name="title">Weekly review</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Weekly Review') }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $rangeText }}</p>
            </div>
            {{-- Roll-forward button (separate form) --}}
            @if ($items->whereNull('completed_at')->isNotEmpty())
                <form method="POST" action="{{ route('review.roll-forward') }}"
                      onsubmit="return confirm('Copy incomplete items into next week\'s plan?');">
                    @csrf
                    <x-secondary-button>{{ __('Roll incomplete forward') }}</x-secondary-button>
                </form>
            @endif
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

            {{-- Banner: hours come from the journal now --}}
            <div class="rounded-md bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 px-4 py-3 text-sm text-indigo-800 dark:text-indigo-200 flex items-center justify-between">
                <div>
                    Hours are now logged day-by-day in the
                    <a href="{{ route('journal.today') }}" class="font-medium underline">Daily Journal</a>.
                    This page is for retrospective sign-off only — tick what's done, add a closing note.
                </div>
                <a href="{{ route('journal.today') }}"
                   class="ml-4 text-xs whitespace-nowrap inline-flex items-center rounded-md px-3 py-1.5 bg-indigo-600 text-white hover:bg-indigo-700 transition">
                    Open journal →
                </a>
            </div>

            <form method="POST" action="{{ route('review.store') }}" class="space-y-6">
                @csrf

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Planned for this week</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Tick what's <em>delivered + tested + signed off</em> (not just "in progress"). Hours come from your journal.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Done</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverable</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="days (hours)">Allocated</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="hours (days) — derived from journal">Spent</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @if ($items->isEmpty())
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Nothing was planned for this week.
                                            <a href="{{ route('plans.weekly') }}" class="text-indigo-600 dark:text-indigo-400 underline">Plan the week</a> first.
                                        </td>
                                    </tr>
                                @endif

                                @foreach ($groups as $key => $group)
                                    @php
                                        $m = $group['milestone'];
                                        $header = $group['header'];
                                        $rows = $group['rows'];
                                        $isNoMilestoneGroup = ($key === '_none');
                                    @endphp

                                    {{-- Group header --}}
                                    <tr class="bg-indigo-50/40 dark:bg-indigo-900/20">
                                        <td colspan="6" class="px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                            @if ($isNoMilestoneGroup)
                                                <span class="text-gray-500 dark:text-gray-400">(no milestone)</span>
                                            @else
                                                <a href="{{ route('milestones.show', $m) }}" class="hover:underline text-gray-900 dark:text-gray-100">{{ $m->name }}</a>
                                                <span class="text-gray-500 dark:text-gray-400 font-normal">— {{ $m->project->name }} / {{ $m->project->client->legal_name }}</span>
                                                @if ($m->isScopeAmbiguous())
                                                    <span class="ml-2 text-xs text-amber-600 dark:text-amber-400" title="All current deliverables are Green, but scope isn't confirmed.">scope?</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Milestone envelope row (if any) --}}
                                    @if ($header)
                                        <tr class="bg-white dark:bg-gray-800 {{ $header->completed_at ? 'opacity-60' : '' }}">
                                            <td class="px-3 py-3 text-center">
                                                <input type="checkbox"
                                                    name="items[{{ $header->id }}][completed]"
                                                    value="1"
                                                    @checked(old("items.{$header->id}.completed", $header->completed_at))
                                                    class="rounded border-gray-300 dark:border-gray-700 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            </td>
                                            <td class="px-3 py-3 text-sm font-medium text-gray-800 dark:text-gray-200 italic">
                                                Milestone envelope
                                                <div class="text-xs text-gray-500 dark:text-gray-400 not-italic">forward-planning allocation</div>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $m->project->client->legal_name }}</td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatDaysWithHours($header->allocated_hours) }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatHoursWithDays($header->hours_spent) }}
                                            </td>
                                            <td class="px-3 py-3 text-sm">
                                                <input type="text"
                                                    name="items[{{ $header->id }}][notes]"
                                                    value="{{ old("items.{$header->id}.notes", $header->notes) }}"
                                                    placeholder="Phase outcome / next slice…"
                                                    class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                            </td>
                                        </tr>
                                    @endif

                                    @foreach ($rows as $item)
                                        @php $d = $item->deliverable; @endphp
                                        <tr class="{{ $item->completed_at ? 'opacity-60' : '' }}">
                                            <td class="px-3 py-3 text-center">
                                                <input type="checkbox"
                                                    name="items[{{ $item->id }}][completed]"
                                                    value="1"
                                                    @checked(old("items.{$item->id}.completed", $item->completed_at))
                                                    class="rounded border-gray-300 dark:border-gray-700 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            </td>
                                            <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('deliverables.show', $d) }}" class="hover:underline">{{ $d->name }}</a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $d->project->name }}</div>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $d->project->client->legal_name }}</td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatDaysWithHours($item->allocated_hours) }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatHoursWithDays($item->hours_spent) }}
                                            </td>
                                            <td class="px-3 py-3 text-sm">
                                                <input type="text"
                                                    name="items[{{ $item->id }}][notes]"
                                                    value="{{ old("items.{$item->id}.notes", $item->notes) }}"
                                                    placeholder="Outcome / blocker / next step…"
                                                    class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <x-primary-button>{{ __('Save review') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
