<x-app-layout>
    <x-slot name="title">New client</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('New Client') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('clients.store') }}" class="space-y-6">
                        @csrf
                        @include('clients._form', ['client' => $client, 'isEdit' => false])
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
