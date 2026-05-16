<x-app-layout>
    <x-slot name="title">Edit {{ $project->name }}</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Project') }} — {{ $project->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('projects.update', $project) }}">
                        @csrf
                        @method('PUT')
                        @include('projects._form', ['project' => $project, 'clients' => $clients, 'isEdit' => true])
                    </form>
                </div>
            </div>

            @can('delete', $project)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Danger zone') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Deleting a project also removes all of its deliverables.
                        </p>
                        <form method="POST" action="{{ route('projects.destroy', $project) }}"
                              class="mt-4"
                              onsubmit="return confirm('Delete project {{ $project->name }}? Its deliverables will be removed too.');">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Delete project') }}</x-danger-button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
