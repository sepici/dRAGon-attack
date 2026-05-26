@php
    use App\Support\TimeUnits;

    $rangeText = $period->starts_on->format('d M Y') . ' → ' . $period->ends_on->format('d M Y');
    $overUnderColor = $overUnder > 0
        ? 'text-red-600 dark:text-red-400'
        : 'text-emerald-600 dark:text-emerald-400';
    $overUnderLabel = $overUnder > 0 ? 'Over by' : 'Under by';

    // Group items by milestone for rendering. Each "group" holds the optional
    // header allocation (PlanItem with milestone_id) plus child rows (PlanItems
    // with deliverable_id whose deliverable belongs to this milestone).
    //
    // A deliverable allocation whose deliverable has no milestone goes to the
    // "(no milestone)" tail group.
    $groups = []; // [milestoneId|'_none' => ['milestone' => Milestone|null, 'header' => PlanItem|null, 'rows' => [PlanItem]]]
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
            $milestone = $item->deliverable->milestone ?? null;
            $key = $milestone ? $milestone->id : '_none';
            if (! isset($groups[$key])) {
                $groups[$key] = ['milestone' => $milestone, 'header' => null, 'rows' => []];
            }
            $groups[$key]['rows'][] = $item;
        }
    }
    // Order groups: real milestones (by name) first, "(no milestone)" tail at the end.
    uksort($groups, function ($a, $b) use ($groups) {
        if ($a === '_none') return 1;
        if ($b === '_none') return -1;
        return strcasecmp(
            $groups[$a]['milestone']->name ?? '',
            $groups[$b]['milestone']->name ?? '',
        );
    });
@endphp

<x-app-layout>
    <x-slot name="title">{{ $kind->label() }} plan</x-slot>

    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $kind->label() }} Plan
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $rangeText }}
            </p>
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

            {{-- Capacity vs scope widget --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Capacity &amp; scope</h3>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Capacity ({{ $kind->thisPeriodLabel() }})
                        </dt>
                        <dd class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ TimeUnits::formatDaysWithHours($capacity) }}
                        </dd>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            @if ($kind === \App\Enums\PlanKind::Quarterly)
                                3 × your monthly capacity. Change it on your <a href="{{ route('profile.edit') }}" class="underline">profile</a>.
                            @else
                                From your <a href="{{ route('profile.edit') }}" class="underline">profile</a>.
                            @endif
                        </p>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Planned (allocated)
                        </dt>
                        <dd class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ TimeUnits::formatDaysWithHours($totalAllocated) }}
                        </dd>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Sum across {{ $items->count() }} allocation(s) on this plan.
                        </p>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Over / Under
                        </dt>
                        <dd class="mt-1 text-3xl font-bold {{ $overUnderColor }}">
                            @if ($overUnder == 0)
                                Even
                            @else
                                {{ $overUnder > 0 ? '+' : '' }}{{ TimeUnits::formatDaysWithHours($overUnder) }}
                            @endif
                        </dd>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            @if ($overUnder > 0)
                                {{ $overUnderLabel }} {{ TimeUnits::formatDaysWithHours(abs($overUnder)) }} — push items to backlog or extend the deadline.
                            @elseif ($overUnder < 0)
                                You have {{ TimeUnits::formatDaysWithHours(abs($overUnder)) }} of headroom.
                            @else
                                Allocated exactly to capacity.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Planned items: grouped by milestone --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Planned allocations</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Milestone rows are forward-planning <em>envelopes</em>; deliverable rows below them are specific draws against the envelope.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    @if ($items->isEmpty())
                        <p class="px-6 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Nothing planned for this period yet. Add an allocation below.
                        </p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Project / Client</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="days (hours)">Target</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="days">Allocated&nbsp;(d)</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="hours (days)">Spent</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($groups as $key => $group)
                                    @php
                                        $m = $group['milestone'];
                                        $header = $group['header'];
                                        $rows = $group['rows'];
                                        $isNoMilestoneGroup = ($key === '_none');
                                    @endphp

                                    {{-- Group header --}}
                                    <tr class="bg-indigo-50/40 dark:bg-indigo-900/20">
                                        <td colspan="8" class="px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                            @if ($isNoMilestoneGroup)
                                                <span class="text-gray-500 dark:text-gray-400">(no milestone)</span>
                                            @else
                                                <a href="{{ route('milestones.show', $m) }}" class="hover:underline text-gray-900 dark:text-gray-100">
                                                    {{ $m->name }}
                                                </a>
                                                <span class="text-gray-500 dark:text-gray-400 font-normal">— {{ $m->project->name }} / {{ $m->project->client->legal_name }}</span>
                                                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->status->chipClasses() }}">{{ $m->status->value }}</span>
                                                @if ($m->isScopeAmbiguous())
                                                    <span class="ml-1 text-xs text-amber-600 dark:text-amber-400" title="All current deliverables are Green, but scope isn't confirmed.">scope?</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Milestone-envelope row (optional) --}}
                                    @if ($header)
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200 italic">
                                                Milestone envelope
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="text-xs">forward-planning allocation</span>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatDaysWithHours($m->effective_target_hours) }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                <form method="POST" action="{{ route('plan-items.update', $header) }}" class="flex items-center justify-end gap-1">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="number" name="allocated_days" step="0.5" min="0"
                                                        value="{{ rtrim(rtrim(number_format(TimeUnits::daysFromHours($header->allocated_hours), 1), '0'), '.') }}"
                                                        class="w-20 text-right text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">d</span>
                                                    <button class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">Save</button>
                                                </form>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatHoursWithDays($header->hours_spent) }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                {{ $m->deadline ? $m->deadline->format('d M') : '—' }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-center">—</td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                <form method="POST" action="{{ route('plan-items.destroy', $header) }}" class="inline"
                                                      onsubmit="return confirm('Remove this milestone envelope from the plan?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif

                                    {{-- Deliverable rows in this group --}}
                                    @foreach ($rows as $item)
                                        @php $d = $item->deliverable; @endphp
                                        <tr>
                                            <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('deliverables.show', $d) }}" class="hover:underline">{{ $d->name }}</a>
                                                @if ($d->moscow)
                                                    <span class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $d->moscow->chipClasses() }}">{{ $d->moscow->value }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                <a href="{{ route('projects.show', $d->project) }}" class="hover:underline">{{ $d->project->name }}</a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $d->project->client->legal_name }}</div>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatDaysWithHours($d->target_hours) }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                <form method="POST" action="{{ route('plan-items.update', $item) }}" class="flex items-center justify-end gap-1">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="number" name="allocated_days" step="0.5" min="0"
                                                        value="{{ rtrim(rtrim(number_format(TimeUnits::daysFromHours($item->allocated_hours), 1), '0'), '.') }}"
                                                        class="w-20 text-right text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">d</span>
                                                    <button class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">Save</button>
                                                </form>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatHoursWithDays($item->hours_spent) }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                {{ $d->deadline ? $d->deadline->format('d M') : '—' }}
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-center">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->status->chipClasses() }}">{{ $d->status->value }}</span>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                <form method="POST" action="{{ route('plan-items.destroy', $item) }}" class="inline"
                                                      onsubmit="return confirm('Remove {{ $d->name }} from this plan?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>

                            <tfoot class="bg-gray-50 dark:bg-gray-900/30 font-medium">
                                <tr>
                                    <td class="px-3 py-2 text-right text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400" colspan="3">Totals</td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ TimeUnits::formatDaysWithHours($totalAllocated) }}</td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ TimeUnits::formatHoursWithDays($items->sum(fn ($it) => (float) $it->hours_spent)) }}</td>
                                    <td colspan="3">&nbsp;</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Add to plan: deliverable OR milestone toggle --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Add to {{ strtolower($kind->label()) }} plan</h3>
                </div>
                <div class="p-6">
                    @php
                        $weeklyCapDays = (float) auth()->user()->weekly_capacity_hours / 8.0;
                        $remainingByDeliverable = $availableDeliverables->mapWithKeys(fn ($d) => [
                            $d->id => max(0.0, ($d->target_hours - $d->hours_spent) / 8.0),
                        ]);
                        $defaultDays = min(1.0, $weeklyCapDays);
                        $oldKind = old('milestone_id') ? 'milestone' : 'deliverable';
                    @endphp

                    @if ($availableDeliverables->isEmpty() && $availableMilestones->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if ($items->isEmpty())
                                You have no deliverables or milestones yet.
                                <a href="{{ route('deliverables.create') }}" class="underline text-indigo-600 dark:text-indigo-400">Create one</a> first.
                            @else
                                Everything you own is already on this plan.
                            @endif
                        </p>
                    @else
                        <form method="POST" action="{{ route('plan-items.store') }}"
                              x-data="{
                                  kind: '{{ $oldKind }}',
                                  deliverableId: '{{ old('deliverable_id') }}',
                                  milestoneId: '{{ old('milestone_id') }}',
                                  weeklyCapDays: {{ $weeklyCapDays }},
                                  remaining: @js($remainingByDeliverable),
                                  allocatedDays: '{{ old('allocated_days', $defaultDays) }}',
                                  onDeliverableChange() {
                                      if (!this.deliverableId) return;
                                      const rem = this.remaining[this.deliverableId] ?? 0;
                                      const suggest = Math.min(rem, this.weeklyCapDays);
                                      this.allocatedDays = (Math.round(suggest * 2) / 2).toFixed(1);
                                  }
                              }"
                              class="space-y-4">
                            @csrf
                            <input type="hidden" name="plan_period_id" value="{{ $period->id }}">

                            {{-- Kind toggle --}}
                            <div class="flex items-center gap-6 text-sm">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="_kind" value="deliverable" x-model="kind"
                                        class="border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                                    Deliverable
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="_kind" value="milestone" x-model="kind"
                                        class="border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                                    Milestone <span class="text-xs text-gray-500 dark:text-gray-400">(envelope)</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                                {{-- Deliverable picker --}}
                                <div class="sm:col-span-2" x-show="kind === 'deliverable'" x-cloak>
                                    <x-input-label for="deliverable_id" :value="__('Deliverable')" />
                                    <select id="deliverable_id" name="deliverable_id"
                                        x-model="deliverableId" @change="onDeliverableChange()"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="">— Pick a deliverable —</option>
                                        @foreach ($availableDeliverables as $d)
                                            <option value="{{ $d->id }}">
                                                {{ $d->name }} — {{ $d->project->name }} / {{ $d->project->client->legal_name }}@if ($d->milestone) [{{ $d->milestone->name }}]@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('deliverable_id')" />
                                    @if ($availableDeliverables->isEmpty())
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No deliverables left to add — they're all on this plan.</p>
                                    @endif
                                </div>

                                {{-- Milestone picker --}}
                                <div class="sm:col-span-2" x-show="kind === 'milestone'" x-cloak>
                                    <x-input-label for="milestone_id" :value="__('Milestone (envelope)')" />
                                    <select id="milestone_id" name="milestone_id"
                                        x-model="milestoneId"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="">— Pick a milestone —</option>
                                        @foreach ($availableMilestones as $m)
                                            <option value="{{ $m->id }}">
                                                {{ $m->name }} — {{ $m->project->name }} / {{ $m->project->client->legal_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('milestone_id')" />
                                    @if ($availableMilestones->isEmpty())
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No milestones left to add — they're all on this plan.</p>
                                    @endif
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Forward-planning envelope for a milestone — useful before the deliverables are scoped.</p>
                                </div>

                                {{-- Days + submit --}}
                                <div>
                                    <x-input-label for="allocated_days" :value="__('Days')" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input id="allocated_days" name="allocated_days" type="number"
                                            step="0.5" min="0" class="w-24 text-right"
                                            x-model="allocatedDays" required />
                                        <x-primary-button>{{ __('Add') }}</x-primary-button>
                                    </div>
                                    <x-input-error class="mt-2" :messages="$errors->get('allocated_days')" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">1 day = 8 hours.</p>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>[x-cloak]{display:none!important}</style>
</x-app-layout>
