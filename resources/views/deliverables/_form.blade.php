{{--
    Shared deliverable form.
    Expects:
        $deliverable — model (new on create, existing on edit)
        $projects    — Collection of the user's projects, eager-loaded with
                       client + client.contactPersons
        $isEdit      — bool
--}}

@php
    // Flatten projects → { project_id: { client_name, contacts: [{id,name}] } }
    // so Alpine can show the right contact checkboxes for the selected project.
    $projectsJs = $projects->mapWithKeys(fn ($p) => [
        $p->id => [
            'client_name' => $p->client->legal_name,
            'contacts' => $p->client->contactPersons->map(fn ($cp) => [
                'id' => $cp->id,
                'name' => $cp->full_name . ($cp->role_title ? " ({$cp->role_title})" : ''),
            ])->values(),
        ],
    ]);

    $selectedProjectId = old('project_id', $deliverable->project_id);
    $oldContactIds = old('contact_ids');
    if ($oldContactIds === null) {
        $oldContactIds = $isEdit ? $deliverable->contactPersons->pluck('id')->all() : [];
    }
@endphp

<div x-data="{
        projects: @js($projectsJs),
        projectId: '{{ $selectedProjectId }}',
        contactIds: @js(array_map('intval', (array) $oldContactIds)),
        get availableContacts() {
            return this.projectId && this.projects[this.projectId]
                ? this.projects[this.projectId].contacts
                : [];
        },
        get clientName() {
            return this.projectId && this.projects[this.projectId]
                ? this.projects[this.projectId].client_name
                : '';
        },
        onProjectChange() {
            // Drop any contact_ids that no longer belong to this project's client
            const valid = this.availableContacts.map(c => c.id);
            this.contactIds = this.contactIds.filter(id => valid.includes(id));
        },
        toggleContact(id) {
            const i = this.contactIds.indexOf(id);
            if (i === -1) this.contactIds.push(id);
            else this.contactIds.splice(i, 1);
        },
     }" class="space-y-6">

    {{-- Project --}}
    <div>
        <x-input-label for="project_id" :value="__('Project')" />
        <select id="project_id" name="project_id" required
            x-model="projectId" @change="onProjectChange()"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
            <option value="">— Select project —</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}">{{ $project->name }} — {{ $project->client->legal_name }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('project_id')" />
        @if ($projects->isEmpty())
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                You have no projects yet. <a href="{{ route('projects.create') }}" class="underline">Create one</a> first.
            </p>
        @endif
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="clientName" x-cloak>
            Client: <span class="font-medium" x-text="clientName"></span>
        </p>
    </div>

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Deliverable name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
            :value="old('name', $deliverable->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    {{-- Description --}}
    <div>
        <x-input-label for="description" :value="__('Description / outcome (optional)')" />
        <textarea id="description" name="description" rows="3"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
            placeholder="e.g. 'KYC flow signed off by client and tested in staging'">{{ old('description', $deliverable->description) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>

    {{-- Target / Spent days + Deadline --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="target_days" :value="__('Target days')" />
            <x-text-input id="target_days" name="target_days" type="number"
                step="0.5" min="0" class="mt-1 block w-full"
                :value="old('target_days', $deliverable->target_days ?? 0)" required />
            <x-input-error class="mt-2" :messages="$errors->get('target_days')" />
        </div>
        <div>
            <x-input-label for="days_spent" :value="__('Days spent so far')" />
            <x-text-input id="days_spent" name="days_spent" type="number"
                step="0.5" min="0" class="mt-1 block w-full"
                :value="old('days_spent', $deliverable->days_spent ?? 0)" />
            <x-input-error class="mt-2" :messages="$errors->get('days_spent')" />
        </div>
        <div>
            <x-input-label for="deadline" :value="__('Deadline (optional)')" />
            <x-text-input id="deadline" name="deadline" type="date" class="mt-1 block w-full"
                :value="old('deadline', optional($deliverable->deadline)->format('Y-m-d'))" />
            <x-input-error class="mt-2" :messages="$errors->get('deadline')" />
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
                        @selected(old('status', $deliverable->status?->value ?? 'R') === $s->value)>
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
                        @selected(old('moscow', $deliverable->moscow?->value) === $m->value)>
                        {{ $m->value }} — {{ $m->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('moscow')" />
        </div>
    </div>

    {{-- Responsible contacts (multi-select via checkboxes) --}}
    <div>
        <x-input-label :value="__('Responsible contacts (optional)')" />
        <div class="mt-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 min-h-[3rem]">
            <template x-if="!projectId">
                <p class="text-xs text-gray-500 dark:text-gray-400">Select a project first to see available contacts.</p>
            </template>
            <template x-if="projectId && availableContacts.length === 0">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    This project's client has no contact persons.
                    Add some on the client's page.
                </p>
            </template>
            <template x-if="projectId && availableContacts.length > 0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="c in availableContacts" :key="c.id">
                        <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                            <input type="checkbox" name="contact_ids[]"
                                :value="c.id"
                                :checked="contactIds.includes(c.id)"
                                @change="toggleContact(c.id)"
                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span x-text="c.name"></span>
                        </label>
                    </template>
                </div>
            </template>
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('contact_ids')" />
        <x-input-error class="mt-2" :messages="$errors->get('contact_ids.*')" />
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('deliverables.index') }}"
           class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
            Cancel
        </a>
        <x-primary-button :disabled="$projects->isEmpty()">
            {{ $isEdit ? __('Save changes') : __('Create deliverable') }}
        </x-primary-button>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
