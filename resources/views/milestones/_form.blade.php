{{--
    Shared milestone form. Used by create + edit.
    Expects:
        $milestone — model (new on create, existing on edit)
        $projects  — Collection of the auth user's projects with their clients
        $isEdit    — bool (create vs update form)
--}}
@php
    $targetDaysOld = old('target_days', $milestone->target_hours !== null
        ? \App\Support\TimeUnits::daysFromHours($milestone->target_hours)
        : '');
@endphp

<div class="space-y-6">

    {{-- Project --}}
    <div>
        <x-input-label for="project_id" :value="__('Project')" />
        <select id="project_id" name="project_id" required
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">— Select project —</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}"
                    @selected((int) old('project_id', $milestone->project_id) === (int) $project->id)>
                    {{ $project->name }} — {{ $project->client->legal_name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('project_id')" />
        @if ($projects->isEmpty())
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                You have no projects yet.
                <a href="{{ route('projects.create') }}" class="underline">Create one</a> first.
            </p>
        @endif
    </div>

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Milestone name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
            :value="old('name', $milestone->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            e.g. "Phase 1: Backend holding", "Discovery", "Hand-off".
        </p>
    </div>

    {{-- Description --}}
    <div>
        <x-input-label for="description" :value="__('Description (optional)')" />
        <textarea id="description" name="description" rows="3"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            placeholder="What's the outcome of this milestone? When do you sign it off?">{{ old('description', $milestone->description) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>

    {{-- Target days + deadline + MoSCoW --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="target_days" :value="__('Target days (optional)')" />
            <x-text-input id="target_days" name="target_days" type="number"
                step="0.5" min="0" class="mt-1 block w-full"
                :value="$targetDaysOld" />
            <x-input-error class="mt-2" :messages="$errors->get('target_days')" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Leave blank to derive from the sum of child deliverables.
            </p>
        </div>
        <div>
            <x-input-label for="deadline" :value="__('Deadline (optional)')" />
            <x-text-input id="deadline" name="deadline" type="date" class="mt-1 block w-full"
                :value="old('deadline', optional($milestone->deadline)->format('Y-m-d'))" />
            <x-input-error class="mt-2" :messages="$errors->get('deadline')" />
        </div>
        <div>
            <x-input-label for="moscow" :value="__('MoSCoW priority (optional)')" />
            <select id="moscow" name="moscow"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">— Not set —</option>
                @foreach (\App\Enums\Moscow::cases() as $m)
                    <option value="{{ $m->value }}"
                        @selected(old('moscow', $milestone->moscow?->value) === $m->value)>
                        {{ $m->value }} — {{ $m->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('moscow')" />
        </div>
    </div>

    {{-- Scope complete --}}
    <div class="rounded-md bg-indigo-50/40 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 p-4">
        <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
            <input type="checkbox" name="scope_complete" value="1"
                @checked(old('scope_complete', $milestone->scope_complete))
                class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="font-medium">Scope is complete</span>
                <span class="block mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                    Tick when you've added every deliverable that needs to be in this milestone.
                    Until ticked, the milestone status stays Amber even if all current children are Green —
                    a safeguard against the "auto-complete with missing scope" failure mode.
                </span>
            </span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('milestones.index') }}"
           class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
            Cancel
        </a>
        <x-primary-button :disabled="$projects->isEmpty()">
            {{ $isEdit ? __('Save changes') : __('Create milestone') }}
        </x-primary-button>
    </div>
</div>
