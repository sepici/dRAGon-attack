@php
    use App\Support\TimeUnits;

    $byProject = $milestones->groupBy('project_id');
@endphp
<x-app-layout>
    <x-slot name="title">Milestones</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Milestones') }}
            </h2>
            <a href="{{ route('milestones.create') }}">
                <x-primary-button>{{ __('New milestone') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($byProject->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400">
                    No milestones yet. Milestones are optional — small projects don't need them.
                    Big projects (10+ deliverables) benefit from grouping into phases.
                    <a href="{{ route('milestones.create') }}" class="text-indigo-600 dark:text-indigo-400 underline">Create your first one</a>.
                </div>
            @else
                @foreach ($byProject as $projectId => $group)
                    @php $project = $group->first()->project; @endphp
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $project->name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $project->client->legal_name }}</p>
                            </div>
                            <a href="{{ route('projects.show', $project) }}"
                               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                Open project →
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Milestone</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="days (hours)">Target</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="hours (days)">Spent</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverables</th>
                                        <th class="px-4 py-2">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($group as $m)
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('milestones.show', $m) }}" class="hover:underline">{{ $m->name }}</a>
                                                @if ($m->isScopeAmbiguous())
                                                    <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300" title="All current deliverables are Green, but you haven't confirmed scope is complete.">
                                                        scope?
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatDaysWithHours($m->effective_target_hours) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                                {{ TimeUnits::formatHoursWithDays($m->hours_spent) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                {{ $m->deadline ? $m->deadline->format('d M Y') : '—' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                @if ($m->moscow)
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->moscow->chipClasses() }}">{{ $m->moscow->value }}</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->status->chipClasses() }}">{{ $m->status->value }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-700 dark:text-gray-300">
                                                {{ $m->deliverables->count() }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                                <a href="{{ route('milestones.edit', $m) }}"
                                                   class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
