<x-app-layout>
    <x-slot name="title">Deliverables</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Deliverables') }}
            </h2>
            @can('create', \App\Models\Deliverable::class)
                <a href="{{ route('deliverables.create') }}">
                    <x-primary-button>{{ __('New deliverable') }}</x-primary-button>
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Filter bar (M15b). Cascading Employer → Client → Project. --}}
            @if (! empty($picker['employers']))
                @php $hasActiveFilter = $filters['employer_id'] || $filters['client_id'] || $filters['project_id']; @endphp
                <form method="GET" action="{{ route('deliverables.index') }}"
                      class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4"
                      x-data="{
                        clientsByEmployer: @js((object) $picker['clientsByEmployer']),
                        projectsByClient: @js((object) $picker['projectsByClient']),
                        employerId: @js((string) ($filters['employer_id'] ?? '')),
                        clientId: @js((string) ($filters['client_id'] ?? '')),
                        projectId: @js((string) ($filters['project_id'] ?? '')),
                        clientOptions() { return this.employerId ? (this.clientsByEmployer[this.employerId] ?? []) : []; },
                        projectOptions() { return this.clientId ? (this.projectsByClient[this.clientId] ?? []) : []; },
                      }">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Employer</label>
                            <select name="employer_id" x-model="employerId" @change="clientId = ''; projectId = ''"
                                    class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                                <option value="">All employers</option>
                                @foreach ($picker['employers'] as $e)
                                    <option value="{{ $e['id'] }}">{{ $e['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Client</label>
                            <select name="client_id" x-model="clientId" @change="projectId = ''"
                                    :disabled="! employerId"
                                    class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2 disabled:opacity-50">
                                <option value="">All clients</option>
                                <template x-for="c in clientOptions()" :key="c.id">
                                    <option :value="c.id" :selected="String(c.id) === clientId" x-text="c.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Project</label>
                            <select name="project_id" x-model="projectId"
                                    :disabled="! clientId"
                                    class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2 disabled:opacity-50">
                                <option value="">All projects</option>
                                <template x-for="p in projectOptions()" :key="p.id">
                                    <option :value="p.id" :selected="String(p.id) === projectId" x-text="p.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <x-primary-button class="!py-1.5">Apply</x-primary-button>
                            @if ($hasActiveFilter)
                                <a href="{{ route('deliverables.index') }}"
                                   class="text-sm text-gray-600 dark:text-gray-400 hover:underline self-center">Clear</a>
                            @endif
                        </div>
                    </div>
                    @if ($hasActiveFilter)
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            Showing {{ $deliverables->count() }} deliverable{{ $deliverables->count() === 1 ? '' : 's' }} matching the active filter.
                        </p>
                    @endif
                </form>
            @endif

            <x-plan-table
                :deliverables="$deliverables"
                :show-milestone="true"
                empty-message="No deliverables yet. Start by creating one." />
        </div>
    </div>
</x-app-layout>
