/**
 * Streamable-HTTP transport — for hosting the MCP server remotely.
 *
 *   GET    /mcp        open or resume an SSE session (returns events)
 *   POST   /mcp        client-to-server RPC (initialize, tools/list, tools/call)
 *   DELETE /mcp        close a session
 *
 * Auth: clients send `Authorization: Bearer <token>` on every request.
 * The token is a Sanctum personal-access token created at /profile on the
 * Laravel app. We don't validate it ourselves — we just forward it to the
 * API on each tool call, and the API enforces validity + ability scope.
 * The advantage: revoking a token at /profile takes effect on the very
 * next tool call, with no server-side cache to bust.
 *
 * Sessions are keyed by an id the SDK generates on the first request; we
 * store them in-process. Multiple clients can connect simultaneously,
 * each with their own token, each isolated.
 */

import crypto from "node:crypto";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { isInitializeRequest } from "@modelcontextprotocol/sdk/types.js";
import express, { type NextFunction, type Request, type Response } from "express";

import type { ApiConfig } from "./api.js";
import { buildServer } from "./server-factory.js";

interface Session {
  transport: StreamableHTTPServerTransport;
  /** Token this session was opened with — every request must match. */
  token: string;
}

const SESSION_HEADER = "mcp-session-id";

export async function runHttp(): Promise<void> {
  const baseUrl = process.env.DRAGONATTACK_API_URL;
  const port = Number(process.env.PORT ?? 3001);
  const host = process.env.HOST ?? "127.0.0.1";

  if (!baseUrl) {
    console.error("Missing DRAGONATTACK_API_URL.");
    process.exit(1);
  }

  const sessions = new Map<string, Session>();
  const app = express();
  app.use(express.json({ limit: "2mb" }));

  // ---------- Auth + handler ----------
  app.all("/mcp", authMiddleware, async (req: Request, res: Response) => {
    const token = (req as Request & { token: string }).token;
    const sessionId = req.header(SESSION_HEADER);

    let session = sessionId ? sessions.get(sessionId) : undefined;

    // Reject mismatched tokens — a session belongs to one token.
    if (session && session.token !== token) {
      res.status(401).json({ error: "session token mismatch" });
      return;
    }

    // New session: only allowed when the body is an MCP initialize call.
    if (!session) {
      if (!(req.method === "POST" && isInitializeRequest(req.body))) {
        res.status(400).json({
          error:
            "no session — open one by POSTing an initialize request without a session id",
        });
        return;
      }

      const transport = new StreamableHTTPServerTransport({
        sessionIdGenerator: () => crypto.randomUUID(),
        onsessioninitialized: (id) => {
          sessions.set(id, { transport, token });
        },
      });
      transport.onclose = () => {
        if (transport.sessionId) sessions.delete(transport.sessionId);
      };

      const config: ApiConfig = { baseUrl: baseUrl!, token };
      const server = buildServer(config);
      await server.connect(transport);

      await transport.handleRequest(req, res, req.body);
      return;
    }

    // Existing session — hand off to its transport.
    await session.transport.handleRequest(req, res, req.body);
  });

  // ---------- Liveness probe (no auth) ----------
  app.get("/healthz", (_req, res) => {
    res.json({ ok: true, sessions: sessions.size });
  });

  app.listen(port, host, () => {
    console.error(`dragonattack-mcp (http) listening on ${host}:${port}`);
    console.error(`forwarding tool calls to ${baseUrl}`);
  });
}

/**
 * Pulls the Bearer token off the Authorization header. We don't verify it
 * here — we hand it to the API which already enforces validity + scope.
 * Just rejects requests that didn't bring a token at all.
 */
function authMiddleware(req: Request, res: Response, next: NextFunction): void {
  const header = req.header("authorization") ?? req.header("Authorization");
  if (!header) {
    res.status(401).json({ error: "missing Authorization header" });
    return;
  }
  const match = header.match(/^Bearer\s+(.+)$/i);
  if (!match) {
    res.status(401).json({ error: "Authorization header must be 'Bearer <token>'" });
    return;
  }
  (req as Request & { token: string }).token = match[1].trim();
  next();
}
