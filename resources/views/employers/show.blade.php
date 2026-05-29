<x-app-layout>
    <x-slot name="title">{{ $employer->name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $employer->name }}
                    @if ($employer->is_self)
                        <span class="ml-2 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                            Self
                        </span>
                    @endif
                </h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('invitations.create', ['employer' => $employer->id]) }}">
                    <x-secondary-button>{{ __('Invite to view') }}</x-secondary-button>
                </a>
                @can('update', $employer)
                    <a href="{{ route('employers.edit', $employer) }}">
                        <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                    </a>
                @endcan
                <a href="{{ route('employers.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    ← All employers
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

            @if ($errors->any())
                <div class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Clients under this employer --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Clients ({{ $employer->clients->count() }})
                    </h3>
                    <a href="{{ route('clients.create') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                        + Add client
                    </a>
                </div>
                @if ($employer->clients->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No clients under this employer yet.
                    </div>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($employer->clients as $client)
                            <li class="px-6 py-3 flex items-center justify-between text-sm">
                                <a href="{{ route('clients.show', $client) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:underline">
                                    {{ $client->legal_name }}
                                </a>
                                <a href="{{ route('clients.show', $client) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Open →
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Delete (only non-Self + no clients) --}}
            @if (! $employer->is_self)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Delete this employer</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Removes the employer permanently. Only allowed when there are no clients attached —
                        move or delete the clients first.
                    </p>
                    <form method="POST" action="{{ route('employers.destroy', $employer) }}" class="mt-3"
                          onsubmit="return confirm('Delete employer {{ $employer->name }}? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button :disabled="$employer->clients->isNotEmpty()">
                            {{ __('Delete employer') }}
                        </x-danger-button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
