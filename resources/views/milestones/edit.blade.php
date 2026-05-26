<x-app-layout>
    <x-slot name="title">Edit milestone</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit milestone') }} — {{ $milestone->name }}
            </h2>
            <form method="POST" action="{{ route('milestones.destroy', $milestone) }}"
                  onsubmit="return confirm('Delete this milestone? Its deliverables stay but become un-grouped.');">
                @csrf
                @method('DELETE')
                <button class="text-sm text-red-600 dark:text-red-400 hover:underline">
                    Delete milestone
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('milestones.update', $milestone) }}">
                    @csrf
                    @method('PUT')
                    @include('milestones._form', ['isEdit' => true])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
