<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $project->name }}
            </h2>
            <div class="flex items-center gap-2">
                @can('update', $project)
                    <a href="{{ route('projects.edit', $project) }}">
                        <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                    </a>
                @endcan
                <a href="{{ route('projects.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    ← Back to projects
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

            {{-- Project details --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            <a href="{{ route('clients.show', $project->client) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $project->client->legal_name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ $project->deadline ? $project->deadline->format('d M Y') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Responsible contact</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            @if ($project->responsibleContact)
                                {{ $project->responsibleContact->full_name }}
                                @if ($project->responsibleContact->role_title)
                                    <span class="text-gray-500 dark:text-gray-400">({{ $project->responsibleContact->role_title }})</span>
                                @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</dt>
                        <dd class="mt-1">
                            @if ($project->moscow)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $project->moscow->chipClasses() }}">
                                    {{ $project->moscow->value }} — {{ $project->moscow->label() }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $project->status->chipClasses() }}">
                                {{ $project->status->value }} — {{ $project->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $project->created_at->format('d M Y') }}</dd>
                    </div>

                    @if ($project->description)
                        <div class="sm:col-span-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $project->description }}</dd>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Deliverables --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 flex items-center justify-between border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Deliverables</h3>
                    @can('update', $project)
                        <a href="{{ route('deliverables.create', ['project' => $project->id]) }}">
                            <x-secondary-button>{{ __('Add deliverable') }}</x-secondary-button>
                        </a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target&nbsp;(d)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Spent&nbsp;(d)</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($project->deliverables as $deliverable)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('deliverables.show', $deliverable) }}" class="hover:underline">
                                            {{ $deliverable->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format((float) $deliverable->target_days, 1) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format((float) $deliverable->days_spent, 1) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $deliverable->deadline ? $deliverable->deadline->format('d M') : '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        @if ($deliverable->moscow)
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliverable->moscow->chipClasses() }}">{{ $deliverable->moscow->value }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliverable->status->chipClasses() }}">{{ $deliverable->status->value }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        <a href="{{ route('deliverables.show', $deliverable) }}"
                                           class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No deliverables yet.
                                        @can('update', $project)
                                            <a href="{{ route('deliverables.create', ['project' => $project->id]) }}" class="text-indigo-600 dark:text-indigo-400">Add one</a>.
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
