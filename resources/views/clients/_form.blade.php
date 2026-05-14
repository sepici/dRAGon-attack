{{--
    Shared form fields for client create + edit.
    Expects:
        $client  — the Client model (new on create, existing on edit)
        $isEdit  — bool
--}}

<div>
    <x-input-label for="legal_name" :value="__('Legal name')" />
    <x-text-input id="legal_name" name="legal_name" type="text" class="mt-1 block w-full"
        :value="old('legal_name', $client->legal_name)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('legal_name')" />
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="email" :value="__('Email (optional)')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
            :value="old('email', $client->email)" autocomplete="email" />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>
    <div>
        <x-input-label for="phone" :value="__('Phone (optional)')" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
            :value="old('phone', $client->phone)" autocomplete="tel" />
        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
    </div>
</div>

<div>
    <x-input-label for="notes" :value="__('Notes (optional)')" />
    <textarea id="notes" name="notes" rows="4"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('notes', $client->notes) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
</div>

<div class="flex items-center justify-end gap-3">
    <a href="{{ route('clients.index') }}"
       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
        Cancel
    </a>
    <x-primary-button>
        {{ $isEdit ? __('Save changes') : __('Create client') }}
    </x-primary-button>
</div>
