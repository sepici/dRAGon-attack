@php use App\Support\TimeUnits; @endphp
<x-app-layout>
    <x-slot name="title">{{ $milestone->name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $milestone->name }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    <a href="{{ route('projects.show', $milestone->project) }}" class="hover:underline">
                        {{ $milestone->project->name }}
                    </a>
                    — {{ $milestone->project->client->legal_name }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $milestone)
                    <a href="{{ route('milestones.edit', $milestone) }}">
                        <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                    </a>
                @endcan
                <a href="{{ route('milestones.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    ← All milestones
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Summary --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ TimeUnits::formatDaysWithHours($milestone->effective_target_hours) }}
                        @if (is_null($milestone->target_hours))
                            <span class="text-xs text-gray-500 dark:text-gray-400">(sum of children)</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Spent</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ TimeUnits::formatHoursWithDays($milestone->hours_spent) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ $milestone->deadline ? $milestone->deadline->format('d M Y') : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</dt>
                    <dd class="mt-1">
                        @if ($milestone->moscow)
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $milestone->moscow->chipClasses() }}">
                                {{ $milestone->moscow->value }} — {{ $milestone->moscow->label() }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $milestone->status->chipClasses() }}">
                            {{ $milestone->status->value }} — {{ $milestone->status->label() }}
                        </span>
                        @if ($milestone->isScopeAmbiguous())
                            <span class="text-xs text-amber-600 dark:text-amber-400" title="All current deliverables are Green, but scope isn't confirmed.">
                                scope not confirmed
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Scope complete</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">
                        {{ $milestone->scope_complete ? 'Yes' : 'No' }}
                    </dd>
                </div>

                @if ($milestone->description)
                    <div class="sm:col-span-3">
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $milestone->description }}</dd>
                    </div>
                @endif
            </div>

            {{-- Child deliverables --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Deliverables ({{ $milestone->deliverables->count() }})
                    </h3>
                    <a href="{{ route('deliverables.create', ['project' => $milestone->project_id, 'milestone' => $milestone->id]) }}"
                       class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                        + Add deliverable
                    </a>
                </div>

                @if ($milestone->deliverables->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No deliverables in this milestone yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deliverable</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="days (hours)">Target</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-3">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($milestone->deliverables as $d)
                                    <tr>
                                        <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            <a href="{{ route('deliverables.show', $d) }}" class="hover:underline">{{ $d->name }}</a>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">
                                            {{ TimeUnits::formatDaysWithHours($d->target_hours) }}
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
                                            <a href="{{ route('deliverables.edit', $d) }}"
                                               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
