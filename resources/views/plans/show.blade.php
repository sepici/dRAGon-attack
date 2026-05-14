@php
    // Display-friendly date range for the period header.
    $rangeText = $period->starts_on->format('d M Y') . ' → ' . $period->ends_on->format('d M Y');
    $overUnderColor = $overUnder > 0
        ? 'text-red-600 dark:text-red-400'
        : 'text-emerald-600 dark:text-emerald-400';
    $overUnderLabel = $overUnder > 0 ? 'Over by' : 'Under by';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $kind->label() }} Plan
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $rangeText }}
                </p>
            </div>
            {{-- Add-deliverable button lands in M3c --}}
            <span class="text-xs text-gray-500 dark:text-gray-400">Add to plan: coming in M3c</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
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
                            Sum across {{ $deliverables->count() }} deliverable(s) on this plan.
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

            {{-- Planned items table (uses shared component with allocated column) --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Planned deliverables</h3>
                </div>
                <x-plan-table
                    :deliverables="$deliverables"
                    :show-allocation="true"
                    :allocations="$allocations"
                    empty-message="No deliverables on this plan yet. (Add-to-plan UI lands in M3c.)" />
            </div>
        </div>
    </div>
</x-app-layout>
