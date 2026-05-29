<x-guest-layout>
    <x-slot name="title">Accept invitation</x-slot>

    <div class="w-full">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            You've been invited to view {{ $invitation->inviter->name }}'s work
        </h1>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            You'll be able to see the following on a read-only basis:
        </p>
        <ul class="mt-2 list-disc list-inside text-sm text-gray-700 dark:text-gray-300">
            @foreach ($employers as $emp)
                <li>{{ $emp->name }}@if ($emp->is_self) <span class="text-xs text-gray-500 dark:text-gray-400">(Self)</span>@endif</li>
            @endforeach
        </ul>

        @if ($invitation->message)
            <div class="mt-4 rounded-md bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 p-3 text-sm text-indigo-900 dark:text-indigo-200 whitespace-pre-line">
                {{ $invitation->message }}
            </div>
        @endif

        <form method="POST" action="{{ route('viewer-invitations.accept', $invitation->token) }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" type="email" class="mt-1 block w-full bg-gray-100 dark:bg-gray-700"
                    :value="$invitation->email" readonly />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Your viewer account will be created (or reused if you already have a viewer login) under this email.
                </p>
            </div>

            <div>
                <x-input-label for="name" :value="__('Your name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                    :value="old('name', $invitation->name)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Set a password')" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                    required autocomplete="new-password" />
                <x-input-error class="mt-2" :messages="$errors->get('password')" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                    class="mt-1 block w-full" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end">
                <x-primary-button>{{ __('Accept and create account') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
