<x-app-layout>
    <x-slot name="title">Users</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Users') }}
            </h2>
            <a href="{{ route('admin.users.create') }}">
                <x-primary-button>{{ __('New user') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('delete'))
                <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    {{ $errors->first('delete') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Wk cap</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mo cap</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $user->name }}
                                        @if ($user->id === auth()->id())
                                            <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">(you)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ $user->email }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        @php
                                            $chip = match ($user->role) {
                                                \App\Enums\UserRole::Admin  => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                                \App\Enums\UserRole::User   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                                \App\Enums\UserRole::Viewer => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $chip }}">
                                            {{ $user->role->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format((float)$user->weekly_capacity_days, 1) }}d</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format((float)$user->monthly_capacity_days, 1) }}d</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No users yet. <a href="{{ route('admin.users.create') }}" class="text-indigo-600 dark:text-indigo-400">Create one</a>.
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
