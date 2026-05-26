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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <x-plan-table
                :deliverables="$deliverables"
                :show-milestone="true"
                empty-message="No deliverables yet. Start by creating one." />
        </div>
    </div>
</x-app-layout>
