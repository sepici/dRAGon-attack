@props([
    // Iterable of Deliverable models (with project + project.client eager-loaded
    // when showProject is true).
    'deliverables' => collect(),

    // Show the "Project" column. False when the surrounding context already
    // makes the project obvious (e.g. on a single project's show page).
    'showProject' => true,

    // Show the "Client" column. Same idea — hide on a client-scoped page.
    'showClient' => true,

    // Show an extra "Allocated (d)" column. Used by Weekly/Monthly/Quarterly
    // plan views; not used on the master Deliverables table.
    'showAllocation' => false,

    // Map of deliverable_id => allocated_days (decimal). Only consulted when
    // showAllocation is true.
    'allocations' => [],

    // Friendly empty state text.
    'emptyMessage' => 'No deliverables yet.',

    // Route name for the "Open" link (and the deliverable name link). Useful
    // for plan views to link back to the plan-item edit screen instead.
    'openRoute' => 'deliverables.show',
])

{{-- Total target / spent / allocated rows for the footer summary --}}
@php
    $totalTarget = $deliverables->sum(fn ($d) => (float) $d->target_days);
    $totalSpent = $deliverables->sum(fn ($d) => (float) $d->days_spent);
    $totalAllocated = $showAllocation
        ? collect($allocations)->sum()
        : null;
@endphp

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverable</th>
                    @if ($showProject)
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Project</th>
                    @endif
                    @if ($showClient)
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                    @endif
                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target&nbsp;(d)</th>
                    @if ($showAllocation)
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Allocated&nbsp;(d)</th>
                    @endif
                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Spent&nbsp;(d)</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($deliverables as $deliverable)
                    <tr>
                        <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                            <a href="{{ route($openRoute, $deliverable) }}" class="hover:underline">
                                {{ $deliverable->name }}
                            </a>
                        </td>
                        @if ($showProject)
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <a href="{{ route('projects.show', $deliverable->project) }}" class="hover:underline">
                                    {{ $deliverable->project->name }}
                                </a>
                            </td>
                        @endif
                        @if ($showClient)
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                {{ $deliverable->project->client->legal_name }}
                            </td>
                        @endif
                        <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                            {{ number_format((float) $deliverable->target_days, 1) }}
                        </td>
                        @if ($showAllocation)
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                {{ number_format((float) ($allocations[$deliverable->id] ?? 0), 1) }}
                            </td>
                        @endif
                        <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                            {{ number_format((float) $deliverable->days_spent, 1) }}
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            {{ $deliverable->deadline ? $deliverable->deadline->format('d M') : '—' }}
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-sm text-center">
                            @if ($deliverable->moscow)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliverable->moscow->chipClasses() }}">
                                    {{ $deliverable->moscow->value }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliverable->status->chipClasses() }}">
                                {{ $deliverable->status->value }}
                            </span>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                            <a href="{{ route($openRoute, $deliverable) }}"
                               class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    @php
                        // colspan accounts for optional columns
                        $cols = 6 + ($showProject ? 1 : 0) + ($showClient ? 1 : 0) + ($showAllocation ? 1 : 0);
                    @endphp
                    <tr>
                        <td colspan="{{ $cols }}" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>

            {{-- Totals footer (only when there are rows) --}}
            @if ($deliverables->isNotEmpty())
                <tfoot class="bg-gray-50 dark:bg-gray-900/30 font-medium">
                    <tr>
                        <td class="px-3 py-2 text-right text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            colspan="{{ 1 + ($showProject ? 1 : 0) + ($showClient ? 1 : 0) }}">
                            Totals
                        </td>
                        <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">
                            {{ number_format($totalTarget, 1) }}
                        </td>
                        @if ($showAllocation)
                            <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">
                                {{ number_format($totalAllocated, 1) }}
                            </td>
                        @endif
                        <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">
                            {{ number_format($totalSpent, 1) }}
                        </td>
                        <td colspan="4">&nbsp;</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
