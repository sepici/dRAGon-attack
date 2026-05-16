<x-app-layout>
    <x-slot name="title">Edit {{ $user->name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit User') }} — {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @include('admin.users._form', ['user' => $user, 'isEdit' => true])
                    </form>
                </div>
            </div>

            {{-- Danger zone: delete --}}
            @if ($user->id !== auth()->id())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Danger zone') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Deleting a user removes their account permanently. Their tracker data
                            (clients, projects, deliverables) will be removed in cascade once those
                            tables exist.
                        </p>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                              class="mt-4"
                              onsubmit="return confirm('Delete {{ $user->email }}? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Delete user') }}</x-danger-button>
                        </form>
                        @error('delete')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
