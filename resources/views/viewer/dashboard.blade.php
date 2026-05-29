@php use App\Support\TimeUnits; @endphp
<x-app-layout>
    <x-slot name="title">Viewer dashboard</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Viewer Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @php
                $employers = auth()->user()
                    ->grantedEmployers()
                    ->with(['owner:id,name'])
                    ->withCount(['clients'])
                    ->get();
            @endphp

            @if ($employers->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        You haven't been granted access to any employers yet.
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Ask the person who invited you to share access from their tracker.
                    </p>
                </div>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    You have read-only access to the following employer{{ $employers->count() === 1 ? '' : 's' }}.
                </p>

                @foreach ($employers as $employer)
                    @php
                        $clients = \App\Models\Client::query()
                            ->where('employer_id', $employer->id)
                            ->with(['projects.deliverables'])
                            ->orderBy('legal_name')
                            ->get();
                        $deliverableCount = $clients->sum(fn ($c) => $c->projects->sum(fn ($p) => $p->deliverables->count()));
                        $hoursTotal = (float) \App\Models\TimeLog::query()
                            ->where('employer_id', $employer->id)
                            ->sum('hours');
                    @endphp
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $employer->name }}
                                    @if ($employer->is_self)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">Self</span>
                                    @endif
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Shared by {{ $employer->owner->name }}
                                </p>
                            </div>
                            <div class="text-right text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                <div>{{ $employer->clients_count }} client{{ $employer->clients_count === 1 ? '' : 's' }}</div>
                                <div>{{ $deliverableCount }} deliverable{{ $deliverableCount === 1 ? '' : 's' }}</div>
                                <div>{{ TimeUnits::formatHoursWithDays($hoursTotal) }} logged</div>
                            </div>
                        </div>

                        @if ($clients->isEmpty())
                            <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                                No clients under this employer yet.
                            </div>
                        @else
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($clients as $client)
                                    <div class="px-6 py-3">
                                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $client->legal_name }}</div>
                                        @if ($client->projects->isNotEmpty())
                                            <ul class="mt-1 text-xs text-gray-600 dark:text-gray-400 space-y-0.5">
                                                @foreach ($client->projects as $project)
                                                    <li>
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $project->name }}</span>
                                                        — {{ $project->deliverables->count() }} deliverable{{ $project->deliverables->count() === 1 ? '' : 's' }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
