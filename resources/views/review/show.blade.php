@php
    $rangeText = $period->starts_on->format('d M Y') . ' → ' . $period->ends_on->format('d M Y');
    [$plannedItems, $adHocExisting] = $items->partition(fn ($i) => ! is_null($i->deliverable_id));
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
            @if ($plannedItems->whereNull('completed_at')->isNotEmpty())
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

            <form method="POST" action="{{ route('review.store') }}" class="space-y-6">
                @csrf

                {{-- Planned items --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Planned for this week</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Tick what's <em>delivered + tested + signed off</em> (not just "in progress"). Enter actual days spent.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Done</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverable</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Allocated (d)</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Spent (d)</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($plannedItems as $item)
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
                                            {{ number_format((float) $item->allocated_days, 1) }}
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                            <input type="number"
                                                name="items[{{ $item->id }}][days_spent]"
                                                step="0.5" min="0"
                                                value="{{ old("items.{$item->id}.days_spent", number_format((float) $item->days_spent, 1)) }}"
                                                class="w-20 text-right text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                        </td>
                                        <td class="px-3 py-3 text-sm">
                                            <input type="text"
                                                name="items[{{ $item->id }}][notes]"
                                                value="{{ old("items.{$item->id}.notes", $item->notes) }}"
                                                placeholder="Outcome / blocker / next step…"
                                                class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Nothing was planned for this week.
                                            <a href="{{ route('plans.weekly') }}" class="text-indigo-600 dark:text-indigo-400 underline">Plan the week</a> first.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Ad-hoc / unplanned work --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg"
                     x-data="{
                        rows: [{ name: '', days_spent: '', notes: '' }],
                        add() { this.rows.push({ name: '', days_spent: '', notes: '' }); },
                        remove(i) { this.rows.splice(i, 1); if (this.rows.length === 0) this.add(); },
                     }">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Unplanned work this week</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Anything you spent time on that wasn't on the plan — e.g. emergency server intervention, ad-hoc support, an urgent client call.
                            </p>
                        </div>
                        <button type="button" @click="add()" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            + Add row
                        </button>
                    </div>

                    {{-- Already-saved ad-hoc items from a previous review submission (read-only). --}}
                    @if ($adHocExisting->isNotEmpty())
                        <div class="overflow-x-auto border-b border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Days</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($adHocExisting as $adHoc)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $adHoc->ad_hoc_name }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format((float) $adHoc->days_spent, 1) }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $adHoc->ad_hoc_notes ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="p-6 space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <input type="text" :name="`ad_hoc[${i}][name]`" x-model="row.name"
                                    placeholder="e.g. emergency server intervention"
                                    class="col-span-5 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                <input type="number" :name="`ad_hoc[${i}][days_spent]`" x-model="row.days_spent"
                                    step="0.5" min="0" placeholder="Days"
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
                            Rows with a blank name are ignored on save.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <x-primary-button>{{ __('Save review') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
