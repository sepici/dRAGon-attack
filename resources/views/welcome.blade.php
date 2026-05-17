<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Honest weekly RAG status tracking for client deliverables — without the wishful thinking.">

    <title>{{ config('app.name', 'dRAGonattack Tracker') }}</title>

    {{-- Favicons: PNG for modern browsers, .ico fallback for older ones. --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Use the compiled Tailwind CSS (same as the rest of the app) so we
         pick up the brand palette and dark-mode classes. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 selection:bg-indigo-500 selection:text-white">

    {{-- Top bar with auth CTA --}}
    <header class="absolute top-0 left-0 right-0 z-10 p-4 sm:p-6">
        <div class="max-w-6xl mx-auto flex items-center justify-end">
            @if (Route::has('login'))
                @auth
                    <a href="{{ route(auth()->user()->role->landingRoute()) }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition">
                        Continue →
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition">
                        Log in →
                    </a>
                @endauth
            @endif
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative pt-28 pb-16 sm:pt-32 sm:pb-24">
        <div class="max-w-4xl mx-auto px-6 lg:px-8 text-center">
            <a href="{{ url('/') }}" class="inline-block">
                <x-application-logo class="h-24 sm:h-32 w-auto mx-auto fill-current text-gray-800 dark:text-gray-100" />
            </a>

            <h1 class="mt-10 text-3xl sm:text-5xl font-bold tracking-tight text-gray-900 dark:text-white">
                Honest weekly status,
                <span class="text-indigo-500">without the wishful thinking.</span>
            </h1>

            <p class="mt-6 text-lg leading-relaxed text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                Plan, track, review, and report on client deliverables with RAG that actually means something. Red is the default for anything not signed off — because pretending otherwise is how teams ship surprises instead of work.
            </p>

            <div class="mt-10 flex items-center justify-center gap-3">
                @auth
                    <a href="{{ route(auth()->user()->role->landingRoute()) }}"
                       class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-base font-semibold rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition">
                        Continue to the tracker →
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-base font-semibold rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition">
                        Log in →
                    </a>
                    <a href="#what-it-does"
                       class="inline-flex items-center px-6 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-indigo-500 dark:hover:text-indigo-400 transition">
                        See what it does
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- What it does — 4 feature cards --}}
    <section id="what-it-does" class="py-16 sm:py-24 bg-white dark:bg-gray-800/40 border-y border-gray-200 dark:border-gray-700">
        <div class="max-w-6xl mx-auto px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto">
                <p class="text-sm font-semibold text-indigo-500 uppercase tracking-wider">The workflow</p>
                <h2 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">Plan it, track it, review it, ship it.</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-400">
                    A four-stage loop the spreadsheet couldn't enforce. The app does.
                </p>
            </div>

            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">

                {{-- Plan --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 ring-1 ring-gray-200 dark:ring-gray-700 p-6">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0V11.25m18 7.5V11.25m-18 0h18"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Plan</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        Weekly, monthly, quarterly. Allocate days to deliverables in 0.5-day increments. Capacity-vs-scope flags over-commitment in red before the week even starts.
                    </p>
                </div>

                {{-- Track --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 ring-1 ring-gray-200 dark:ring-gray-700 p-6">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Track</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        Clients, projects, deliverables — with RAG that derives upward (a project's status is the worst of its deliverables, not whatever you felt like ticking).
                    </p>
                </div>

                {{-- Review --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 ring-1 ring-gray-200 dark:ring-gray-700 p-6">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Review</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        End-of-week ritual: tick what's <em>delivered, tested, signed off</em>, log days actually spent, capture ad-hoc work (server emergencies), and roll incomplete items into next week.
                    </p>
                </div>

                {{-- Report --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 ring-1 ring-gray-200 dark:ring-gray-700 p-6">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Report</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        One-click weekly PDF: review of the week, plan for next, current month, current quarter. Hand it to leadership; no PowerPoint required.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- RAG philosophy + status legend --}}
    <section class="py-16 sm:py-24">
        <div class="max-w-5xl mx-auto px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-5 gap-10 items-start">

            <div class="lg:col-span-3">
                <p class="text-sm font-semibold text-indigo-500 uppercase tracking-wider">Why this exists</p>
                <h2 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    A RAG that doesn't lie.
                </h2>
                <div class="mt-5 space-y-4 text-gray-600 dark:text-gray-400 leading-relaxed">
                    <p>
                        Most weekly status reports are wishful thinking. Items default to <strong class="text-indigo-500">Green</strong> because nobody wants to say "this isn't going to ship". By the time it's obviously Red, the deadline has passed.
                    </p>
                    <p>
                        This tracker inverts that. <strong class="text-gray-900 dark:text-white">Red is the default.</strong> An item only earns Green when it's been delivered, tested, and signed off — not when someone is "working on it". A project's status is automatically the worst of its deliverables', so it can't drift away from the truth. Capacity is explicit and overcommitment is flagged before the week starts.
                    </p>
                    <p>
                        The result is a status report your stakeholders can actually trust — and use to make decisions while there's still time.
                    </p>
                </div>
            </div>

            {{-- Status code legend --}}
            <div class="lg:col-span-2 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-6 shadow-sm">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status codes</p>
                <dl class="mt-4 space-y-3">
                    <div class="flex gap-3">
                        <dt class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-500 text-white text-sm font-bold">R</dt>
                        <dd class="text-sm">
                            <p class="font-semibold text-gray-900 dark:text-white">Red — won't deliver on the current plan</p>
                            <p class="text-gray-600 dark:text-gray-400">Default for anything not done. Action needed: drop scope, add resource, or push the date.</p>
                        </dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-500 text-white text-sm font-bold">A</dt>
                        <dd class="text-sm">
                            <p class="font-semibold text-gray-900 dark:text-white">Amber — at risk; needs attention</p>
                            <p class="text-gray-600 dark:text-gray-400">On the plan but slipping. Look at it this week or it becomes Red.</p>
                        </dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white text-sm font-bold">G</dt>
                        <dd class="text-sm">
                            <p class="font-semibold text-gray-900 dark:text-white">Green — delivered &amp; signed off</p>
                            <p class="text-gray-600 dark:text-gray-400">Tested, accepted, done. Not "in progress" — done.</p>
                        </dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-600 text-white text-sm font-bold">B</dt>
                        <dd class="text-sm">
                            <p class="font-semibold text-gray-900 dark:text-white">Blocked — waiting on input</p>
                            <p class="text-gray-600 dark:text-gray-400">Stalled on someone else (client reply, sign-off, access). Visible so it can't drift unnoticed.</p>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    {{-- Bottom CTA --}}
    <section class="pb-20">
        <div class="max-w-3xl mx-auto px-6 lg:px-8 text-center">
            <div class="rounded-2xl bg-indigo-600 px-8 py-10 shadow-lg">
                <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to stop pretending it's all green?</h2>
                <p class="mt-3 text-indigo-100">
                    Internal access only. Talk to an admin to get an account.
                </p>
                <div class="mt-6">
                    @auth
                        <a href="{{ route(auth()->user()->role->landingRoute()) }}"
                           class="inline-flex items-center px-6 py-3 bg-white text-indigo-700 hover:bg-indigo-50 text-base font-semibold rounded-md shadow-sm transition">
                            Continue to the tracker →
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center px-6 py-3 bg-white text-indigo-700 hover:bg-indigo-50 text-base font-semibold rounded-md shadow-sm transition">
                            Log in →
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-gray-200 dark:border-gray-700 py-6">
        <div class="max-w-6xl mx-auto px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
            <p>© {{ now()->year }} dRAGonattack Tracker. Built for internal use.</p>
            <p>Laravel {{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }}</p>
        </div>
    </footer>
</body>
</html>
