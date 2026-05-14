{{--
    Shared project form.
    Expects:
        $project  — Project model (new on create, existing on edit)
        $clients  — Collection of the user's clients, eager-loaded with contactPersons
        $isEdit   — bool
--}}

@php
    // Flatten clients → contacts for the Alpine state. We pre-emit JSON so
    // the page doesn't need an API endpoint just to filter a dropdown.
    $clientsJs = $clients->mapWithKeys(fn ($c) => [
        $c->id => $c->contactPersons->map(fn ($cp) => [
            'id' => $cp->id,
            'name' => $cp->full_name . ($cp->role_title ? " ({$cp->role_title})" : ''),
        ])->values(),
    ]);
    $selectedClientId = old('client_id', $project->client_id);
    $selectedContactId = old('responsible_contact_id', $project->responsible_contact_id);
@endphp

<div x-data="{
        clients: @js($clientsJs),
        clientId: '{{ $selectedClientId }}',
        contactId: '{{ $selectedContactId }}',
        get contacts() {
            return this.clientId ? (this.clients[this.clientId] || []) : [];
        },
        onClientChange() {
            // Clear the contact if it no longer belongs to the newly chosen client
            const ids = this.contacts.map(c => String(c.id));
            if (this.contactId && !ids.includes(String(this.contactId))) {
                this.contactId = '';
            }
        },
     }" class="space-y-6">

    {{-- Client --}}
    <div>
        <x-input-label for="client_id" :value="__('Client')" />
        <select id="client_id" name="client_id" required
            x-model="clientId" @change="onClientChange()"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
            <option value="">— Select client —</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}">{{ $client->legal_name }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
        @if ($clients->isEmpty())
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                You have no clients yet. <a href="{{ route('clients.create') }}" class="underline">Create one</a> first.
            </p>
        @endif
    </div>

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Project name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
            :value="old('name', $project->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    {{-- Description --}}
    <div>
        <x-input-label for="description" :value="__('Description (optional)')" />
        <textarea id="description" name="description" rows="4"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('description', $project->description) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>

    {{-- Deadline + responsible contact --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="deadline" :value="__('Deadline (optional)')" />
            <x-text-input id="deadline" name="deadline" type="date" class="mt-1 block w-full"
                :value="old('deadline', optional($project->deadline)->format('Y-m-d'))" />
            <x-input-error class="mt-2" :messages="$errors->get('deadline')" />
        </div>
        <div>
            <x-input-label for="responsible_contact_id" :value="__('Responsible contact (optional)')" />
            <select id="responsible_contact_id" name="responsible_contact_id"
                x-model="contactId"
                :disabled="!clientId"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <option value="">— None —</option>
                <template x-for="c in contacts" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('responsible_contact_id')" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Pick from the selected client's contacts. Manage contacts on the client page.
            </p>
        </div>
    </div>

    {{-- Status + MoSCoW --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="status" :value="__('Status')" />
            <select id="status" name="status" required
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                @foreach (\App\Enums\Status::cases() as $s)
                    <option value="{{ $s->value }}"
                        @selected(old('status', $project->status?->value ?? 'R') === $s->value)>
                        {{ $s->value }} — {{ $s->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('status')" />
        </div>
        <div>
            <x-input-label for="moscow" :value="__('MoSCoW priority (optional)')" />
            <select id="moscow" name="moscow"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                <option value="">— Not set —</option>
                @foreach (\App\Enums\Moscow::cases() as $m)
                    <option value="{{ $m->value }}"
                        @selected(old('moscow', $project->moscow?->value) === $m->value)>
                        {{ $m->value }} — {{ $m->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('moscow')" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('projects.index') }}"
           class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
            Cancel
        </a>
        <x-primary-button :disabled="$clients->isEmpty()">
            {{ $isEdit ? __('Save changes') : __('Create project') }}
        </x-primary-button>
    </div>
</div>
