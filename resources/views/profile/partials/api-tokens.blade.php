@php
    use App\Support\ApiAbility;

    /** @var \Illuminate\Database\Eloquent\Collection $tokens */
    $tokens = auth()->user()->tokens()->orderByDesc('created_at')->get();

    // After token creation the controller flashes { plain, id, name } so we
    // can render the plaintext value ONCE for the user to copy.
    $justCreated = session('api_token_created');
@endphp

<header>
    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
        {{ __('API tokens') }}
    </h2>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Issue a personal-access token so an AI agent (Claude, ChatGPT, n8n, your own script) can
        read and write your tracker on your behalf. You can revoke any token at any time.
    </p>
</header>

@if ($justCreated)
    <div class="mt-4 rounded-md border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/30 p-4">
        <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
            New token "{{ $justCreated['name'] }}" created.
        </p>
        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
            Copy it now — for security, it won't be shown again. If you lose it, revoke the
            token below and create a new one.
        </p>
        <div class="mt-3 flex items-center gap-2"
             x-data="{ copied: false }">
            <input type="text"
                   readonly
                   value="{{ $justCreated['plain'] }}"
                   @click="$el.select()"
                   class="flex-1 font-mono text-xs bg-white dark:bg-gray-900 border-emerald-300 dark:border-emerald-700 rounded-md shadow-sm py-1.5 px-2 text-gray-900 dark:text-gray-100 focus:ring-emerald-500 focus:border-emerald-500">
            <button type="button"
                    @click="navigator.clipboard.writeText('{{ $justCreated['plain'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    class="text-xs whitespace-nowrap inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700 transition">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak>Copied!</span>
            </button>
        </div>
    </div>
@endif

{{-- Create-token form --}}
<form method="POST" action="{{ route('profile.api-tokens.store') }}" class="mt-6 space-y-4">
    @csrf

    <div>
        <x-input-label for="api_token_name" :value="__('Token name')" />
        <x-text-input id="api_token_name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name')" placeholder="e.g. Claude Desktop, ChatGPT GPT" required maxlength="80" />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            A friendly label so you remember which tool this token is for. Only used in the list below.
        </p>
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Abilities') }}</legend>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Pick the smallest set the agent needs. You can always revoke and re-issue with broader scope.
        </p>
        <div class="mt-2 space-y-2">
            @foreach (ApiAbility::all() as $ability)
                <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                    <input type="checkbox" name="abilities[]" value="{{ $ability['value'] }}"
                           @checked(in_array($ability['value'], old('abilities', []), true))
                           class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span>
                        <span class="font-medium">{{ $ability['label'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">— {{ $ability['description'] }}</span>
                        <code class="ml-1 text-xs text-gray-400 dark:text-gray-500">{{ $ability['value'] }}</code>
                    </span>
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('abilities')" />
        <x-input-error class="mt-2" :messages="$errors->get('abilities.0')" />
    </fieldset>

    <div class="flex items-center justify-end">
        <x-primary-button>{{ __('Create token') }}</x-primary-button>
    </div>
</form>

{{-- Active tokens list --}}
<div class="mt-8">
    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Active tokens') }}</h3>

    @if ($tokens->isEmpty())
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No tokens yet.</p>
    @else
        <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-md">
            @foreach ($tokens as $token)
                <li class="flex items-start justify-between gap-4 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            {{ $token->name }}
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach (($token->abilities ?? []) as $ability)
                                <span class="inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                                    {{ $ability }}
                                </span>
                            @endforeach
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Created {{ $token->created_at->diffForHumans() }}
                            @if ($token->last_used_at)
                                · Last used {{ $token->last_used_at->diffForHumans() }}
                            @else
                                · <span class="italic">never used</span>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('profile.api-tokens.destroy', $token->id) }}"
                          onsubmit="return confirm('Revoke this token? Any agent using it will immediately stop working.');">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs text-red-600 dark:text-red-400 hover:underline">
                            Revoke
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<style>[x-cloak]{display:none!important}</style>
