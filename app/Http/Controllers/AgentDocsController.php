<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Renders the "Connect your AI" page — the user-facing landing for plugging
 * a third-party agent (Claude, ChatGPT, n8n, custom scripts) into the API.
 *
 * Just static-ish content: the live OpenAPI URL, a token reminder, and
 * copy-paste snippets for the common integrations. The actual API surface
 * lives at /api/v1/* (see App\Http\Controllers\Api\V1).
 */
class AgentDocsController extends Controller
{
    public function show(): View
    {
        return view('agent', [
            'openApiUrl' => url('/api/v1/openapi.json'),
            'apiBaseUrl' => url('/api/v1'),
            // The Streamable-HTTP MCP server lives wherever you've hosted it
            // (see mcp/README.md). Set MCP_PUBLIC_URL in .env to display
            // copy-paste Claude Desktop config on this page; leave it unset
            // to hide the "remote MCP" section.
            'mcpPublicUrl' => env('MCP_PUBLIC_URL'),
        ]);
    }
}
