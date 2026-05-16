<x-app-layout>
    <x-slot name="title">Viewer dashboard</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Viewer Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="mb-2 font-medium">Viewer landing page.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Read-only views across all users' tracker data land in M2+.
                        For now, this confirms viewer routing works.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
