#!/usr/bin/env node
/**
 * Entry point. Dispatches to either stdio (default — for Claude Desktop
 * launching us as a subprocess) or HTTP (for hosting remotely and letting
 * Claude Desktop / other MCP clients connect via URL).
 *
 *   MCP_TRANSPORT=stdio   stdio mode (default)
 *   MCP_TRANSPORT=http    streamable-HTTP server
 *
 * In stdio mode, token is read from DRAGONATTACK_API_TOKEN env.
 * In HTTP mode, each connecting client sends their own Bearer token.
 *
 * See README for hosting + Claude Desktop config snippets.
 */

// Make this file an ES module so top-level `await` (in the switch below)
// is allowed. Without this, TS sees no imports/exports and treats it as
// a plain script.
export {};

const mode = process.env.MCP_TRANSPORT ?? "stdio";

switch (mode) {
  case "stdio": {
    const { runStdio } = await import("./stdio.js");
    await runStdio();
    break;
  }
  case "http": {
    const { runHttp } = await import("./http.js");
    await runHttp();
    break;
  }
  default:
    console.error(
      `Unknown MCP_TRANSPORT '${mode}'. Set to 'stdio' or 'http'.`,
    );
    process.exit(1);
}
