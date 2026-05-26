/**
 * Stdio transport — Claude Desktop launches us as a subprocess and talks over
 * stdin/stdout. Token comes from process env, set once at launch.
 */

import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";

import { buildServer } from "./server-factory.js";

export async function runStdio(): Promise<void> {
  const baseUrl = process.env.DRAGONATTACK_API_URL;
  const token = process.env.DRAGONATTACK_API_TOKEN;

  if (!baseUrl || !token) {
    console.error(
      "Missing config. Set both DRAGONATTACK_API_URL and DRAGONATTACK_API_TOKEN.",
    );
    process.exit(1);
  }

  const server = buildServer({ baseUrl, token });
  const transport = new StdioServerTransport();
  await server.connect(transport);
}
