@php
    use Carbon\CarbonImmutable;
    $thisMonth = CarbonImmutable::now()->format('Y-m');
@endphp
<x-app-layout>
    <x-slot name="title">Timesheets</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Timesheets') }}
            </h2>
            <form method="POST" action="{{ route('timesheets.generate') }}" class="flex items-center gap-2">
                @csrf
                <input type="month" name="month" value="{{ $thisMonth }}"
                       class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1 px-2">
                <x-primary-button>{{ __('Generate timesheet') }}</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Previously generated</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        One row per project (or ad-hoc item) worked on that month, with a column per day of the month.
                        Sourced from your <a href="{{ route('journal.today') }}" class="underline text-indigo-600 dark:text-indigo-400">daily journal</a>.
                        Re-generating creates a new file so you keep a history.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Month</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Generated</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($timesheets as $timesheet)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $timesheet->month_starts_on->format('F Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $timesheet->generated_at->format('d M Y, H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                        @if ($timesheet->fileExists())
                                            <a href="{{ route('timesheets.download', $timesheet) }}"
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
                                        No timesheets yet. Pick a month and click <strong>Generate timesheet</strong> above.
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
