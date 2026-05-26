<x-app-layout>
    <x-slot name="title">{{ $project->name }}</x-slot>

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

            {{-- Milestones (optional grouping) --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Milestones</h3>
                    @can('update', $project)
                        <a href="{{ route('milestones.create', ['project' => $project->id]) }}">
                            <x-secondary-button>{{ __('Add milestone') }}</x-secondary-button>
                        </a>
                    @endcan
                </div>
                @if ($project->milestones->isEmpty())
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 text-sm text-gray-500 dark:text-gray-400">
                        No milestones — small projects don't need them.
                        Add some to group this project's deliverables into phases.
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($project->milestones as $m)
                            <div class="px-6 py-3 flex items-center justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('milestones.show', $m) }}"
                                       class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline">
                                        {{ $m->name }}
                                    </a>
                                    @if ($m->isScopeAmbiguous())
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300" title="All current deliverables are Green, but scope isn't confirmed.">scope?</span>
                                    @endif
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $m->deliverables->count() }} deliverable(s)
                                        @if ($m->deadline)
                                            · due {{ $m->deadline->format('d M Y') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if ($m->moscow)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->moscow->chipClasses() }}">{{ $m->moscow->value }}</span>
                                    @endif
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->status->chipClasses() }}">{{ $m->status->value }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Deliverables --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Deliverables</h3>
                    @can('update', $project)
                        <a href="{{ route('deliverables.create', ['project' => $project->id]) }}">
                            <x-secondary-button>{{ __('Add deliverable') }}</x-secondary-button>
                        </a>
                    @endcan
                </div>
                <x-plan-table
                    :deliverables="$project->deliverables"
                    :show-project="false"
                    :show-client="false"
                    empty-message="No deliverables yet for this project." />
            </div>
        </div>
    </div>
</x-app-layout>
