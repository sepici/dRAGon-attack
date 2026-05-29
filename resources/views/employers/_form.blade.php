{{--
    Shared form for employer create + edit.
    Expects:
        $employer — the Employer model (new on create, existing on edit)
        $isEdit   — bool
--}}

<div class="space-y-6">

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Employer name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
            :value="old('name', $employer->name)"
            :readonly="$isEdit && $employer->is_self"
            required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
        @if ($isEdit && $employer->is_self)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                The Self employer's name is fixed and cannot be changed.
            </p>
        @else
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                e.g. the agency that hires you, or "Acme Co." for direct-client work.
            </p>
        @endif
    </div>

    {{-- Sort order --}}
    <div>
        <x-input-label for="sort_order" :value="__('Sort order (optional)')" />
        <x-text-input id="sort_order" name="sort_order" type="number" class="mt-1 block w-32"
            :value="old('sort_order', $employer->sort_order)" />
        <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Lower numbers appear first. Self is always pinned to the top regardless of this field.
        </p>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('employers.index') }}"
           class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
            Cancel
        </a>
        <x-primary-button>
            {{ $isEdit ? __('Save changes') : __('Add employer') }}
        </x-primary-button>
    </div>
</div>
