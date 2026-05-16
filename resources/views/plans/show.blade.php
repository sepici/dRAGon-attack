@php
    $rangeText = $period->starts_on->format('d M Y') . ' → ' . $period->ends_on->format('d M Y');
    $overUnderColor = $overUnder > 0
        ? 'text-red-600 dark:text-red-400'
        : 'text-emerald-600 dark:text-emerald-400';
    $overUnderLabel = $overUnder > 0 ? 'Over by' : 'Under by';
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
                            {{ number_format($capacity, 1) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">days</span>
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
                            {{ number_format($totalAllocated, 1) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">days</span>
                        </dd>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Sum across {{ $items->count() }} deliverable(s) on this plan.
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
                                {{ $overUnder > 0 ? '+' : '' }}{{ number_format($overUnder, 1) }}
                                <span class="text-sm font-medium">days</span>
                            @endif
                        </dd>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            @if ($overUnder > 0)
                                {{ $overUnderLabel }} {{ number_format(abs($overUnder), 1) }} days — push items to backlog or extend the deadline.
                            @elseif ($overUnder < 0)
                                You have {{ number_format(abs($overUnder), 1) }} days of headroom.
                            @else
                                Allocated exactly to capacity.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Planned items: editable table --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Planned deliverables</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverable</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Project</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target&nbsp;(d)</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Allocated&nbsp;(d)</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Spent&nbsp;(d)</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($items as $item)
                                @php $d = $item->deliverable; @endphp
                                <tr>
                                    <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('deliverables.show', $d) }}" class="hover:underline">{{ $d->name }}</a>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <a href="{{ route('projects.show', $d->project) }}" class="hover:underline">{{ $d->project->name }}</a>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $d->project->client->legal_name }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format((float) $d->target_days, 1) }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                        <form method="POST" action="{{ route('plan-items.update', $item) }}" class="flex items-center justify-end gap-1">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" name="allocated_days" step="0.5" min="0"
                                                value="{{ number_format((float) $item->allocated_days, 1) }}"
                                                class="w-20 text-right text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                            <button class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                Save
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format((float) $d->days_spent, 1) }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $d->deadline ? $d->deadline->format('d M') : '—' }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-center">
                                        @if ($d->moscow)
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->moscow->chipClasses() }}">{{ $d->moscow->value }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
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
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Nothing planned for this period yet. Add a deliverable below.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if ($items->isNotEmpty())
                            <tfoot class="bg-gray-50 dark:bg-gray-900/30 font-medium">
                                <tr>
                                    <td class="px-3 py-2 text-right text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400" colspan="4">Totals</td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ number_format($totalAllocated, 1) }}</td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ number_format((float) $items->sum(fn ($it) => $it->deliverable->days_spent), 1) }}</td>
                                    <td colspan="4">&nbsp;</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Add deliverable to plan --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Add deliverable to {{ strtolower($kind->label()) }} plan</h3>
                </div>
                <div class="p-6">
                    @if ($availableDeliverables->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if ($items->isEmpty())
                                You have no deliverables yet.
                                <a href="{{ route('deliverables.create') }}" class="underline text-indigo-600 dark:text-indigo-400">Create one</a> first.
                            @else
                                All your deliverables are already on this plan.
                            @endif
                        </p>
                    @else
                        <form method="POST" action="{{ route('plan-items.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                            @csrf
                            <input type="hidden" name="plan_period_id" value="{{ $period->id }}">
                            <div class="sm:col-span-2">
                                <x-input-label for="deliverable_id" :value="__('Deliverable')" />
                                <select id="deliverable_id" name="deliverable_id" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">— Pick a deliverable —</option>
                                    @foreach ($availableDeliverables as $d)
                                        <option value="{{ $d->id }}">
                                            {{ $d->name }} — {{ $d->project->name }} / {{ $d->project->client->legal_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('deliverable_id')" />
                            </div>
                            <div>
                                <x-input-label for="allocated_days" :value="__('Days')" />
                                <div class="flex items-center gap-2 mt-1">
                                    <x-text-input id="allocated_days" name="allocated_days" type="number"
                                        step="0.5" min="0" class="w-24 text-right"
                                        value="{{ old('allocated_days', 1.0) }}" required />
                                    <x-primary-button>{{ __('Add') }}</x-primary-button>
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('allocated_days')" />
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
