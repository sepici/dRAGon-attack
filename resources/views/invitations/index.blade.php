<x-app-layout>
    <x-slot name="title">Viewer invitations</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Viewer invitations') }}
            </h2>
            <a href="{{ route('invitations.create') }}">
                <x-primary-button>{{ __('New invitation') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                @if ($invitations->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        You haven't invited any viewers yet. From an employer's page click
                        <em>Invite this employer to view your work</em>, or use the button above.
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Employers</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Magic link</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($invitations as $inv)
                                <tr>
                                    <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $inv->email }}
                                        @if ($inv->name)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $inv->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @php
                                            $names = \App\Models\Employer::whereIn('id', (array) $inv->employer_ids)->pluck('name')->all();
                                        @endphp
                                        {{ implode(', ', $names) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm whitespace-nowrap">
                                        @if ($inv->isAccepted())
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                Accepted {{ $inv->accepted_at->diffForHumans() }}
                                            </span>
                                        @elseif ($inv->isExpired())
                                            <span class="inline-flex items-center rounded-full bg-gray-200 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                Expired
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">
                                                Pending @if ($inv->expires_at) (expires {{ $inv->expires_at->diffForHumans() }})@endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs">
                                        @if ($inv->isPending())
                                            <div class="flex items-center gap-2" x-data="{ copied: false }">
                                                <input readonly value="{{ url('/viewer-invitations/' . $inv->token) }}"
                                                       @click="$el.select()"
                                                       class="flex-1 max-w-md font-mono text-xs bg-gray-50 dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-md py-1 px-2 text-gray-900 dark:text-gray-100">
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText('{{ url('/viewer-invitations/' . $inv->token) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                                        class="text-xs whitespace-nowrap rounded-md bg-indigo-600 text-white px-2 py-1 hover:bg-indigo-700 transition">
                                                    <span x-show="!copied">Copy</span>
                                                    <span x-show="copied" x-cloak>Copied!</span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right">
                                        @if (! $inv->isAccepted())
                                            <form method="POST" action="{{ route('invitations.destroy', $inv) }}" class="inline"
                                                  onsubmit="return confirm('Revoke this invitation?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200">Revoke</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Tip — when your install has SMTP configured the invite is also emailed; until then, copy the
                magic link from this page and send it to the recipient yourself.
            </p>
        </div>
    </div>
    <style>[x-cloak]{display:none!important}</style>
</x-app-layout>
