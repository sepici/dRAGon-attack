<x-app-layout>
    <x-slot name="title">Edit {{ $client->legal_name }}</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Client') }} — {{ $client->legal_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @include('clients._form', ['client' => $client, 'isEdit' => true])
                    </form>
                </div>
            </div>

            @can('delete', $client)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Danger zone') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Deleting a client also removes all of its contact persons. Projects must be removed first.
                        </p>
                        <form method="POST" action="{{ route('clients.destroy', $client) }}"
                              class="mt-4"
                              onsubmit="return confirm('Delete {{ $client->legal_name }}? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Delete client') }}</x-danger-button>
                        </form>
                        @error('delete')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
