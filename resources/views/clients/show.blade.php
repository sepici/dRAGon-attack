<x-app-layout>
    <x-slot name="title">{{ $client->legal_name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $client->legal_name }}
            </h2>
            <div class="flex items-center gap-2">
                @can('update', $client)
                    <a href="{{ route('clients.edit', $client) }}">
                        <x-secondary-button>{{ __('Edit') }}</x-secondary-button>
                    </a>
                @endcan
                <a href="{{ route('clients.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    ← Back to clients
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

            {{-- Client details --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Employer</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                            <a href="{{ route('employers.show', $client->employer) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $client->employer->name }}
                            </a>
                            @if ($client->employer->is_self)
                                <span class="ml-1 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">Self</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $client->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $client->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</dt>
                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $client->created_at->format('d M Y') }}</dd>
                    </div>
                    @if ($client->notes)
                        <div class="sm:col-span-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $client->notes }}</dd>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Contact persons --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 flex items-center justify-between border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Contact persons</h3>
                    @can('update', $client)
                        <a href="{{ route('clients.contacts.create', $client) }}">
                            <x-secondary-button>{{ __('Add contact') }}</x-secondary-button>
                        </a>
                    @endcan
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role / Title</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($client->contactPersons as $contact)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $contact->full_name }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $contact->role_title ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $contact->email ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right space-x-3">
                                        @can('update', $contact)
                                            <a href="{{ route('clients.contacts.edit', [$client, $contact]) }}"
                                               class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                Edit
                                            </a>
                                        @endcan
                                        @can('delete', $contact)
                                            <form method="POST" action="{{ route('clients.contacts.destroy', [$client, $contact]) }}" class="inline"
                                                  onsubmit="return confirm('Remove {{ $contact->full_name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No contact persons yet.
                                        @can('update', $client)
                                            <a href="{{ route('clients.contacts.create', $client) }}" class="text-indigo-600 dark:text-indigo-400">Add one</a>.
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
