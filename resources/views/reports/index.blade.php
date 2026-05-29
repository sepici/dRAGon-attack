@php
    $employers = auth()->user()->employers()
        ->orderByDesc('is_self')->orderBy('sort_order')->orderBy('name')->get();
@endphp
<x-app-layout>
    <x-slot name="title">Reports</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Generate form --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Generate this week's report</h3>
                <form method="POST" action="{{ route('reports.generate') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label :value="__('Employers')" />
                        <div class="mt-1 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-2 space-y-1">
                            @foreach ($employers as $emp)
                                <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                                    <input type="checkbox" name="employer_ids[]" value="{{ $emp->id }}" checked
                                        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    {{ $emp->name }}@if ($emp->is_self) <span class="text-xs text-gray-500 dark:text-gray-400">(Self)</span>@endif
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Defaults to all; uncheck to scope the PDF to a subset.</p>
                    </div>
                    <div class="flex items-center justify-end">
                        <x-primary-button>{{ __('Generate report') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Previously generated</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Each report captures: review of the week, plan for next week, current month, current quarter.
                        Re-generating creates a new file so you keep a history.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Week of</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Generated</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($reports as $report)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $report->week_starts_on->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $report->generated_at->format('d M Y, H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        @if ($report->fileExists())
                                            <a href="{{ route('reports.download', $report) }}"
                                               class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200">
                                                Download PDF
                                            </a>
                                        @else
                                            <span class="text-gray-400">file missing</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No reports yet. Click <strong>Generate this week's report</strong> above to create one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
