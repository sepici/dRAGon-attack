<x-app-layout>
    <x-slot name="title">New viewer invitation</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Invite to view your work') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('invitations.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="email" :value="__('Recipient email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                            :value="old('email')" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('Recipient name (optional)')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name')" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label :value="__('Grant access to')" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">Pick one or more employers. The viewer will see only data linked to the selected employers.</p>
                        <div class="mt-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 space-y-1">
                            @foreach ($employers as $emp)
                                @php
                                    $checked = collect(old('employer_ids', $preselected ? [$preselected] : []))
                                        ->map(fn ($v) => (int) $v)
                                        ->contains($emp->id);
                                @endphp
                                <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                                    <input type="checkbox" name="employer_ids[]" value="{{ $emp->id }}"
                                        @checked($checked)
                                        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    {{ $emp->name }}@if ($emp->is_self) <span class="text-xs text-gray-500 dark:text-gray-400">(Self)</span>@endif
                                </label>
                            @endforeach
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('employer_ids')" />
                        <x-input-error class="mt-2" :messages="$errors->get('employer_ids.*')" />
                    </div>

                    <div>
                        <x-input-label for="message" :value="__('Optional message')" />
                        <textarea id="message" name="message" rows="3"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            placeholder="Shown to the recipient on the accept page (e.g. 'For weekly status reports').">{{ old('message') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('message')" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('invitations.index') }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            Cancel
                        </a>
                        <x-primary-button>{{ __('Create invitation') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
