{{--
    Shared form fields for contact-person create + edit.
    Expects:
        $client  — parent Client (for the back/cancel links)
        $contact — the ContactPerson model
        $isEdit  — bool
--}}

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="first_name" :value="__('First name')" />
        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
            :value="old('first_name', $contact->first_name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
    </div>
    <div>
        <x-input-label for="last_name" :value="__('Last name')" />
        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full"
            :value="old('last_name', $contact->last_name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="email" :value="__('Email (optional)')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
            :value="old('email', $contact->email)" autocomplete="email" />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>
    <div>
        <x-input-label for="role_title" :value="__('Role / Title (optional)')" />
        <x-text-input id="role_title" name="role_title" type="text" class="mt-1 block w-full"
            :value="old('role_title', $contact->role_title)" />
        <x-input-error class="mt-2" :messages="$errors->get('role_title')" />
    </div>
</div>

<div class="flex items-center justify-end gap-3">
    <a href="{{ route('clients.show', $client) }}"
       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
        Cancel
    </a>
    <x-primary-button>
        {{ $isEdit ? __('Save changes') : __('Add contact') }}
    </x-primary-button>
</div>
