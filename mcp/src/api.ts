/**
 * Thin HTTP wrapper around the dRAGonattack Tracker /api/v1 surface.
 *
 * The MCP server runs out-of-process (Claude Desktop starts it via stdio),
 * so we use plain fetch — no extra deps, no client codegen. The tool
 * handlers in tools.ts get a typed-ish facade with one method per endpoint
 * group.
 */

const DEFAULT_TIMEOUT_MS = 30_000;

export interface ApiConfig {
  /** e.g. https://dragonattack.tr/api/v1 — set via DRAGONATTACK_API_URL env. */
  baseUrl: string;
  /** Sanctum personal-access token (format "id|hash"). */
  token: string;
}

/** Light JSON wrapper that returns the response body or throws an ApiError. */
async function request(
  config: ApiConfig,
  path: string,
  init: { method?: string; query?: Record<string, unknown>; body?: unknown } = {},
): Promise<unknown> {
  const url = new URL(config.baseUrl.replace(/\/$/, "") + path);
  if (init.query) {
    for (const [k, v] of Object.entries(init.query)) {
      if (v === undefined || v === null || v === "") continue;
      url.searchParams.set(k, String(v));
    }
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), DEFAULT_TIMEOUT_MS);
  try {
    const res = await fetch(url, {
      method: init.method ?? "GET",
      headers: {
        Authorization: `Bearer ${config.token}`,
        Accept: "application/json",
        ...(init.body !== undefined ? { "Content-Type": "application/json" } : {}),
      },
      body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
      signal: controller.signal,
    });

    if (res.status === 204) return null;

    const text = await res.text();
    const data = text ? safeJsonParse(text) : null;

    if (!res.ok) {
      throw new ApiError(res.status, data, `${init.method ?? "GET"} ${path} → ${res.status}`);
    }
    return data;
  } finally {
    clearTimeout(timeout);
  }
}

function safeJsonParse(s: string): unknown {
  try {
    return JSON.parse(s);
  } catch {
    return { raw: s };
  }
}

export class ApiError extends Error {
  constructor(public status: number, public body: unknown, message: string) {
    super(message);
  }
}

// ----------------------------------------------------------------------
// Endpoint helpers — one per route group. Each accepts a plain args object
// and returns the JSON body (already unwrapped past Laravel's "data" envelope
// where applicable).
// ----------------------------------------------------------------------

export function api(config: ApiConfig) {
  return {
    // Account
    me: () => unwrap(request(config, "/me")),

    // Clients
    listClients: (page?: number) =>
      request(config, "/clients", { query: { page } }),
    getClient: (id: number) => unwrap(request(config, `/clients/${id}`)),
    createClient: (body: Record<string, unknown>) =>
      unwrap(request(config, "/clients", { method: "POST", body })),
    updateClient: (id: number, body: Record<string, unknown>) =>
      unwrap(request(config, `/clients/${id}`, { method: "PUT", body })),
    deleteClient: (id: number) =>
      request(config, `/clients/${id}`, { method: "DELETE" }),

    // Projects
    listProjects: (q?: { client_id?: number; page?: number }) =>
      request(config, "/projects", { query: q }),
    getProject: (id: number) => unwrap(request(config, `/projects/${id}`)),
    createProject: (body: Record<string, unknown>) =>
      unwrap(request(config, "/projects", { method: "POST", body })),
    updateProject: (id: number, body: Record<string, unknown>) =>
      unwrap(request(config, `/projects/${id}`, { method: "PUT", body })),
    deleteProject: (id: number) =>
      request(config, `/projects/${id}`, { method: "DELETE" }),

    // Deliverables
    listDeliverables: (q?: {
      project_id?: number;
      status?: string;
      moscow?: string;
      name_like?: string;
      completed?: boolean;
      page?: number;
    }) => request(config, "/deliverables", { query: q }),
    getDeliverable: (id: number) =>
      unwrap(request(config, `/deliverables/${id}`)),
    createDeliverable: (body: Record<string, unknown>) =>
      unwrap(request(config, "/deliverables", { method: "POST", body })),
    updateDeliverable: (id: number, body: Record<string, unknown>) =>
      unwrap(request(config, `/deliverables/${id}`, { method: "PUT", body })),
    deleteDeliverable: (id: number) =>
      request(config, `/deliverables/${id}`, { method: "DELETE" }),

    // Plans
    weeklyPlan: () => unwrap(request(config, "/plans/weekly")),
    monthlyPlan: () => unwrap(request(config, "/plans/monthly")),
    quarterlyPlan: () => unwrap(request(config, "/plans/quarterly")),

    // Plan items
    addToPlan: (body: Record<string, unknown>) =>
      unwrap(request(config, "/plan-items", { method: "POST", body })),
    updatePlanItem: (id: number, body: Record<string, unknown>) =>
      unwrap(request(config, `/plan-items/${id}`, { method: "PUT", body })),
    removeFromPlan: (id: number) =>
      request(config, `/plan-items/${id}`, { method: "DELETE" }),

    // Time logs
    listTimeLogs: (q?: {
      date?: string;
      from?: string;
      to?: string;
      deliverable_id?: number;
      ad_hoc?: boolean;
      page?: number;
    }) => request(config, "/time-logs", { query: q }),
    getTimeLog: (id: number) => unwrap(request(config, `/time-logs/${id}`)),
    logTime: (body: Record<string, unknown>) =>
      unwrap(request(config, "/time-logs", { method: "POST", body })),
    updateTimeLog: (id: number, body: Record<string, unknown>) =>
      unwrap(request(config, `/time-logs/${id}`, { method: "PUT", body })),
    deleteTimeLog: (id: number) =>
      request(config, `/time-logs/${id}`, { method: "DELETE" }),
  };
}

/** Strip the {"data": ...} envelope on single-resource responses. */
async function unwrap(p: Promise<unknown>): Promise<unknown> {
  const v = await p;
  if (v && typeof v === "object" && "data" in v) {
    return (v as { data: unknown }).data;
  }
  return v;
}
