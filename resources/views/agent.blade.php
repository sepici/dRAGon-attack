<x-app-layout>
    <x-slot name="title">Connect your AI</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Connect your AI') }}
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Plug Claude, ChatGPT, n8n, or your own script into dRAGonattack Tracker.
        </p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Quick start --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Quick start</h3>
                <ol class="mt-3 space-y-2 text-sm text-gray-700 dark:text-gray-300 list-decimal list-inside">
                    <li>
                        Create an API token at
                        <a href="{{ route('profile.edit') }}" class="text-indigo-600 dark:text-indigo-400 underline">your profile</a>.
                        Pick the abilities you want the agent to have (e.g. <code>time-logs:write</code> for "log my hours").
                    </li>
                    <li>
                        Pick your tool below, paste the OpenAPI URL and the token into its config.
                    </li>
                    <li>
                        Ask the agent things like "what did I work on this week?" or "log 2 hours on Clonallon today."
                    </li>
                </ol>
            </div>

            {{-- The two URLs the user will need repeatedly --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">URLs you'll need</h3>

                <div x-data="{ copied: false }">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">OpenAPI spec</div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">For tools that auto-import an API (ChatGPT Custom GPTs, Postman, OpenAI Assistants).</p>
                    <div class="mt-1 flex items-center gap-2">
                        <input readonly value="{{ $openApiUrl }}" @click="$el.select()"
                               class="flex-1 font-mono text-xs bg-gray-50 dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-md py-1.5 px-2 text-gray-900 dark:text-gray-100">
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $openApiUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="text-xs whitespace-nowrap inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700 transition">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>
                </div>

                <div x-data="{ copied: false }">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">API base URL</div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">For raw HTTP clients and scripts.</p>
                    <div class="mt-1 flex items-center gap-2">
                        <input readonly value="{{ $apiBaseUrl }}" @click="$el.select()"
                               class="flex-1 font-mono text-xs bg-gray-50 dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-md py-1.5 px-2 text-gray-900 dark:text-gray-100">
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $apiBaseUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="text-xs whitespace-nowrap inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700 transition">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ChatGPT Custom GPT --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">ChatGPT (Custom GPT)</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    ChatGPT Plus / Team / Enterprise. Recommended for casual use — Custom GPTs eat OpenAPI directly.
                </p>
                <ol class="mt-3 space-y-2 text-sm text-gray-700 dark:text-gray-300 list-decimal list-inside">
                    <li>In ChatGPT, click <strong>Create a GPT</strong> → <strong>Configure</strong> → <strong>Actions</strong>.</li>
                    <li>Click <strong>Import from URL</strong> and paste the OpenAPI URL above.</li>
                    <li>Under Authentication, choose <strong>API Key</strong> → <strong>Bearer</strong>. Paste your API token from <a href="{{ route('profile.edit') }}" class="text-indigo-600 dark:text-indigo-400 underline">your profile</a>.</li>
                    <li>Set a name / description / instructions on the Configure tab. Save.</li>
                </ol>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Try: <em>"Log 2 hours on Clonallon Proposal today."</em> ChatGPT will resolve the deliverable name and POST to <code>/time-logs</code>.
                </p>
            </div>

            {{-- Claude Desktop with MCP --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Claude Desktop (MCP)</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Native to Claude. Plugs the API in as a set of named tools Claude can call directly.
                </p>
                <ol class="mt-3 space-y-2 text-sm text-gray-700 dark:text-gray-300 list-decimal list-inside">
                    <li>Clone the repo and build the reference server:
<pre class="mt-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md p-2 overflow-x-auto font-mono text-xs"><code>cd path/to/rag-tracker/mcp
npm install
npm run build</code></pre>
                    </li>
                    <li>Open <code>~/Library/Application Support/Claude/claude_desktop_config.json</code> (macOS) or <code>%APPDATA%\Claude\claude_desktop_config.json</code> (Windows) and add:
<pre class="mt-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md p-2 overflow-x-auto font-mono text-xs"><code>{
  "mcpServers": {
    "dragonattack": {
      "command": "node",
      "args": ["/ABSOLUTE/PATH/TO/rag-tracker/mcp/dist/index.js"],
      "env": {
        "DRAGONATTACK_API_URL": "{{ $apiBaseUrl }}",
        "DRAGONATTACK_API_TOKEN": "&lt;your-token-here&gt;"
      }
    }
  }
}</code></pre>
                    </li>
                    <li>Quit and relaunch Claude. The hammer icon in the chat input should list 21 <strong>dragonattack</strong> tools.</li>
                </ol>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Try: <em>"Log 1.5 hours on Clonallon Proposal today."</em> Claude calls <code>log_time</code> with the fuzzy name — the API resolves to the right deliverable.
                </p>
            </div>

            {{-- curl / generic --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">curl / generic HTTP</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    For scripts, n8n / Zapier HTTP nodes, or anything that speaks Bearer auth.
                </p>

                <div class="mt-3 space-y-3 text-xs">
                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">Who am I?</div>
<pre class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md p-3 overflow-x-auto font-mono"><code>curl -H "Authorization: Bearer $TOKEN" \
  {{ $apiBaseUrl }}/me</code></pre>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">Log 2 hours on a deliverable today (by name)</div>
<pre class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md p-3 overflow-x-auto font-mono"><code>curl -X POST {{ $apiBaseUrl }}/time-logs \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"hours": 2, "deliverable_name": "Clonallon Proposal"}'</code></pre>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">What did I work on yesterday?</div>
<pre class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md p-3 overflow-x-auto font-mono"><code>curl -H "Authorization: Bearer $TOKEN" \
  '{{ $apiBaseUrl }}/time-logs?date=yesterday'</code></pre>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">This week's plan</div>
<pre class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md p-3 overflow-x-auto font-mono"><code>curl -H "Authorization: Bearer $TOKEN" \
  {{ $apiBaseUrl }}/plans/weekly</code></pre>
                    </div>
                </div>
            </div>

            {{-- Reference --}}
            <div class="bg-gray-50 dark:bg-gray-900/50 overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Reference</h3>
                <ul class="mt-2 text-sm text-gray-700 dark:text-gray-300 space-y-1 list-disc list-inside">
                    <li><a href="{{ $openApiUrl }}" class="text-indigo-600 dark:text-indigo-400 underline">OpenAPI spec (JSON)</a> — describes every endpoint, request, and response.</li>
                    <li><a href="{{ route('profile.edit') }}" class="text-indigo-600 dark:text-indigo-400 underline">API tokens</a> — create / revoke tokens, set abilities.</li>
                </ul>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Hours are the storage unit (1 day = 8h). Responses include both <code>*_hours</code> and derived <code>*_days</code>.
                    Date inputs accept <code>today</code>, <code>yesterday</code>, ISO dates, and natural-language phrases.
                </p>
            </div>
        </div>
    </div>

    <style>[x-cloak]{display:none!important}</style>
</x-app-layout>
