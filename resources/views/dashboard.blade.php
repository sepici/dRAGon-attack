@php
    use App\Support\TimeUnits;

    $totalDeliverables = array_sum($statusCounts);

    $capacityCards = [
        ['period' => $weekly,    'label' => 'This week',    'route' => route('plans.weekly')],
        ['period' => $monthly,   'label' => 'This month',   'route' => route('plans.monthly')],
        ['period' => $quarterly, 'label' => 'This quarter', 'route' => route('plans.quarterly')],
    ];

    $statusCards = [
        ['key' => 'R', 'label' => 'Red',     'count' => $statusCounts['R'], 'classes' => 'bg-red-500 text-white',     'desc' => "Won't deliver on plan"],
        ['key' => 'A', 'label' => 'Amber',   'count' => $statusCounts['A'], 'classes' => 'bg-amber-500 text-white',   'desc' => "At risk — needs attention"],
        ['key' => 'G', 'label' => 'Green',   'count' => $statusCounts['G'], 'classes' => 'bg-green-600 text-white',   'desc' => "Delivered &amp; signed off"],
        ['key' => 'B', 'label' => 'Blocked', 'count' => $statusCounts['B'], 'classes' => 'bg-purple-600 text-white',  'desc' => "Waiting on someone else"],
    ];

    $totalMilestones = array_sum($milestoneCounts);
    $milestoneCards = [
        ['count' => $milestoneCounts['R'], 'label' => 'Red',     'classes' => 'bg-red-500 text-white'],
        ['count' => $milestoneCounts['A'], 'label' => 'Amber',   'classes' => 'bg-amber-500 text-white'],
        ['count' => $milestoneCounts['G'], 'label' => 'Green',   'classes' => 'bg-green-600 text-white'],
        ['count' => $milestoneCounts['B'], 'label' => 'Blocked', 'classes' => 'bg-purple-600 text-white'],
    ];
@endphp

<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ now()->format('l, d M Y') }} &mdash; welcome back, {{ auth()->user()->name }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('review.show') }}"><x-secondary-button>{{ __('Run weekly review') }}</x-secondary-button></a>
                <form method="POST" action="{{ route('reports.generate') }}">@csrf
                    <x-primary-button>{{ __('Generate PDF') }}</x-primary-button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Capacity vs scope, three cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ($capacityCards as $card)
                    @php
                        $p = $card['period'];
                        $allocated = $p->totalAllocated();
                        $cap = $p->capacity();
                        $over = $allocated - $cap;
                        $overClass = $over > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400';
                    @endphp
                    <a href="{{ $card['route'] }}"
                       class="block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:ring-2 hover:ring-indigo-500/30 transition">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ TimeUnits::formatDaysWithHours($allocated) }}<span class="text-base font-medium text-gray-500 dark:text-gray-400"> / {{ TimeUnits::formatDaysWithHours($cap) }} planned</span>
                        </p>
                        <p class="mt-1 text-sm {{ $overClass }} font-medium">
                            @if ($over > 0)
                                +{{ TimeUnits::formatDaysWithHours($over) }} over capacity
                            @elseif ($over < 0)
                                {{ TimeUnits::formatDaysWithHours(abs($over)) }} headroom
                            @else
                                Exactly to capacity
                            @endif
                        </p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $p->starts_on->format('d M') }} → {{ $p->ends_on->format('d M Y') }}
                        </p>
                    </a>
                @endforeach
            </div>

            {{-- Deliverables by status --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Deliverables by status</h3>
                    <a href="{{ route('deliverables.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View all ({{ $totalDeliverables }})</a>
                </div>
                @if ($totalDeliverables === 0)
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No deliverables yet. <a href="{{ route('deliverables.create') }}" class="text-indigo-600 dark:text-indigo-400 underline">Create your first one</a>.
                    </div>
                @else
                    <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ($statusCards as $sc)
                            <div class="text-center">
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full {{ $sc['classes'] }} text-2xl font-bold">
                                    {{ $sc['count'] }}
                                </div>
                                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $sc['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{!! $sc['desc'] !!}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Milestones by status --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Milestones by status</h3>
                    <a href="{{ route('milestones.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View all ({{ $totalMilestones }})</a>
                </div>
                @if ($totalMilestones === 0)
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No milestones yet. Small projects don't need them — but on larger projects, group deliverables into phases via
                        <a href="{{ route('milestones.create') }}" class="text-indigo-600 dark:text-indigo-400 underline">Milestones</a>.
                    </div>
                @else
                    <div class="p-6">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ($milestoneCards as $mc)
                                <div class="text-center">
                                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full {{ $mc['classes'] }} text-2xl font-bold">
                                        {{ $mc['count'] }}
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $mc['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        @if ($scopeNotConfirmedCount > 0)
                            <p class="mt-4 text-xs text-amber-700 dark:text-amber-400">
                                {{ $scopeNotConfirmedCount }} milestone{{ $scopeNotConfirmedCount === 1 ? '' : 's' }}
                                with all-Green children but <em>scope not confirmed</em> — open them and tick "Scope is complete" once you're sure no deliverables are missing.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Two columns: due soon + recently completed --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Due in next 7 days --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Due in the next 7 days</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Open deliverables with deadlines coming up.</p>
                    </div>
                    @if ($upcoming->isEmpty())
                        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                            Nothing due in the next week. Either you're on top of things or items need deadlines —
                            <a href="{{ route('deliverables.index') }}" class="text-indigo-600 dark:text-indigo-400 underline">review the list</a>.
                        </div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($upcoming as $d)
                                <li class="px-6 py-3 flex items-center justify-between text-sm">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('deliverables.show', $d) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:underline truncate block">{{ $d->name }}</a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $d->project->client->legal_name }} &mdash; {{ $d->project->name }}</p>
                                    </div>
                                    <div class="flex items-center gap-3 ml-4">
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ $d->deadline->format('D, d M') }}</span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->status->chipClasses() }}">{{ $d->status->value }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Recently completed --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Recently completed</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Last few deliverables marked done in review.</p>
                    </div>
                    @if ($recentlyCompleted->isEmpty())
                        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                            Nothing completed yet. Mark items done from the <a href="{{ route('review.show') }}" class="text-indigo-600 dark:text-indigo-400 underline">weekly review</a>.
                        </div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($recentlyCompleted as $d)
                                <li class="px-6 py-3 flex items-center justify-between text-sm">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('deliverables.show', $d) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:underline truncate block">{{ $d->name }}</a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $d->project->client->legal_name }} &mdash; {{ $d->project->name }}</p>
                                    </div>
                                    <div class="flex items-center gap-3 ml-4">
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ $d->completed_at->diffForHumans(['short' => true]) }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ TimeUnits::formatHoursWithDays($d->hours_spent) }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Quick links footer --}}
            <div class="text-center text-xs text-gray-500 dark:text-gray-400">
                Quick links:
                <a href="{{ route('clients.index') }}" class="underline">Clients</a> &middot;
                <a href="{{ route('projects.index') }}" class="underline">Projects</a> &middot;
                <a href="{{ route('deliverables.index') }}" class="underline">Deliverables</a> &middot;
                <a href="{{ route('plans.weekly') }}" class="underline">Weekly plan</a> &middot;
                <a href="{{ route('review.show') }}" class="underline">Review</a> &middot;
                <a href="{{ route('reports.index') }}" class="underline">Reports</a>
            </div>
        </div>
    </div>
</x-app-layout>
