{{--
    Shared form fields for create + edit.
    Expects:
        $user           — the User model (new on create, existing on edit)
        $isEdit         — bool: true = update form, false = create form
--}}

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
        :value="old('name', $user->name)" required autofocus autocomplete="name" />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
        :value="old('email', $user->email)" required autocomplete="email" />
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

<div>
    <x-input-label for="role" :value="__('Role')" />
    <select id="role" name="role" required
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
        @foreach (\App\Enums\UserRole::cases() as $role)
            <option value="{{ $role->value }}"
                @selected(old('role', $user->role?->value) === $role->value)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get('role')" />
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Admin manages users only. User tracks their own work. Viewer is read-only across all users.
    </p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="weekly_capacity_days" :value="__('Weekly capacity (days)')" />
        <x-text-input id="weekly_capacity_days" name="weekly_capacity_days" type="number"
            step="0.5" min="0" max="7" class="mt-1 block w-full"
            :value="old('weekly_capacity_days', $user->weekly_capacity_days ?? 5.0)" required />
        <x-input-error class="mt-2" :messages="$errors->get('weekly_capacity_days')" />
    </div>
    <div>
        <x-input-label for="monthly_capacity_days" :value="__('Monthly capacity (days)')" />
        <x-text-input id="monthly_capacity_days" name="monthly_capacity_days" type="number"
            step="0.5" min="0" max="31" class="mt-1 block w-full"
            :value="old('monthly_capacity_days', $user->monthly_capacity_days ?? 20.0)" required />
        <x-input-error class="mt-2" :messages="$errors->get('monthly_capacity_days')" />
    </div>
</div>

<div>
    <x-input-label for="password" :value="$isEdit ? __('New password') : __('Password')" />
    <x-text-input id="password" name="password" type="password"
        class="mt-1 block w-full"
        :required="!$isEdit"
        autocomplete="new-password" />
    <x-input-error class="mt-2" :messages="$errors->get('password')" />
    @if ($isEdit)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Leave blank to keep the current password.
        </p>
    @endif
</div>

<div>
    <x-input-label for="password_confirmation" :value="__('Confirm password')" />
    <x-text-input id="password_confirmation" name="password_confirmation" type="password"
        class="mt-1 block w-full"
        :required="!$isEdit"
        autocomplete="new-password" />
</div>

<div class="flex items-center justify-end gap-3">
    <a href="{{ route('admin.users.index') }}"
       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
        Cancel
    </a>
    <x-primary-button>
        {{ $isEdit ? __('Save changes') : __('Create user') }}
    </x-primary-button>
</div>
