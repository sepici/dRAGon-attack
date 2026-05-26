/**
 * Builds an MCP Server instance for a given API config (i.e. a given user's
 * token). Called once per session — stdio mode builds one for the process
 * lifetime; HTTP mode builds one per authenticated connection.
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

import type { ApiConfig } from "./api.js";
import { buildTools, runTool } from "./tools.js";

export function buildServer(config: ApiConfig): Server {
  const tools = buildTools(config);

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

  return server;
}
