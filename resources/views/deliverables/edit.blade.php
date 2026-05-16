<x-app-layout>
    <x-slot name="title">Edit {{ $deliverable->name }}</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Deliverable') }} — {{ $deliverable->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('deliverables.update', $deliverable) }}">
                        @csrf
                        @method('PUT')
                        @include('deliverables._form', [
                            'deliverable' => $deliverable,
                            'projects' => $projects,
                            'isEdit' => true,
                        ])
                    </form>
                </div>
            </div>

            @can('delete', $deliverable)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Danger zone') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Deletes this deliverable and detaches all responsible contacts. Plan allocations
                            referencing this deliverable will also be removed in cascade (when plans land).
                        </p>
                        <form method="POST" action="{{ route('deliverables.destroy', $deliverable) }}"
                              class="mt-4"
                              onsubmit="return confirm('Delete deliverable {{ $deliverable->name }}?');">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Delete deliverable') }}</x-danger-button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
