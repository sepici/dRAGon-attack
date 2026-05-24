#!/usr/bin/env node
/**
 * MCP server for the dRAGonattack Tracker.
 *
 * Spoken-with by Claude Desktop (or any MCP client) over stdio. The client
 * launches us as a subprocess with config-supplied env vars; we register
 * tools and forward each call to the tracker's /api/v1 surface.
 *
 * Required env:
 *   DRAGONATTACK_API_URL    e.g. https://dragonattack.tr/api/v1
 *   DRAGONATTACK_API_TOKEN  a Sanctum personal-access token
 *
 * See README.md for the claude_desktop_config.json snippet.
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

import { buildTools, runTool } from "./tools.js";

const baseUrl = process.env.DRAGONATTACK_API_URL;
const token = process.env.DRAGONATTACK_API_TOKEN;

if (!baseUrl || !token) {
  console.error(
    "Missing config. Set both DRAGONATTACK_API_URL and DRAGONATTACK_API_TOKEN.",
  );
  process.exit(1);
}

const tools = buildTools({ baseUrl, token });

const server = new Server(
  { name: "dragonattack", version: "1.0.0" },
  { capabilities: { tools: {} } },
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: tools.map((t) => t.definition),
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args = {} } = request.params;
  const result = await runTool(tools, name, args as Record<string, unknown>);

  if (result.ok) {
    return {
      content: [
        { type: "text", text: JSON.stringify(result.data, null, 2) },
      ],
    };
  }

  return {
    isError: true,
    content: [
      { type: "text", text: JSON.stringify(result.error, null, 2) },
    ],
  };
});

const transport = new StdioServerTransport();
await server.connect(transport);
