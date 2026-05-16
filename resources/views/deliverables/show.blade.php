@php use App\Support\TimeUnits; @endphp
<x-app-layout>
    <x-slot name="title">{{ $deliverable->name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $deliverable->name }}
            </h2>
            <div class="flex items-center gap-2">
                @can('update', $deliverable)
                    <a href="{{ route('deliverables.edit', $deliverable) }}">
                        <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                    </a>
                @endcan
                <a href="{{ route('deliverables.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    ← Back to deliverables
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Project</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            <a href="{{ route('projects.show', $deliverable->project) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $deliverable->project->name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            <a href="{{ route('clients.show', $deliverable->project->client) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $deliverable->project->client->legal_name }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ $deliverable->deadline ? $deliverable->deadline->format('d M Y') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ TimeUnits::formatDaysWithHours($deliverable->target_hours) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Spent</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ TimeUnits::formatHoursWithDays($deliverable->hours_spent) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Remaining</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ TimeUnits::formatDaysWithHours($deliverable->remaining_hours) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">MoSCoW</dt>
                        <dd class="mt-1">
                            @if ($deliverable->moscow)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliverable->moscow->chipClasses() }}">
                                    {{ $deliverable->moscow->value }} — {{ $deliverable->moscow->label() }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliverable->status->chipClasses() }}">
                                {{ $deliverable->status->value }} — {{ $deliverable->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $deliverable->created_at->format('d M Y') }}</dd>
                    </div>

                    @if ($deliverable->description)
                        <div class="sm:col-span-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description / outcome</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $deliverable->description }}</dd>
                        </div>
                    @endif

                    <div class="sm:col-span-3">
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Responsible contacts</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            @forelse ($deliverable->contactPersons as $contact)
                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-200 mr-1 mb-1">
                                    {{ $contact->full_name }}@if ($contact->role_title)<span class="ml-1 text-gray-500 dark:text-gray-400">({{ $contact->role_title }})</span>@endif
                                </span>
                            @empty
                                <span class="text-gray-400">— none —</span>
                            @endforelse
                        </dd>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
