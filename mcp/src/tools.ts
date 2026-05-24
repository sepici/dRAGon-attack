/**
 * MCP tool catalog. Each entry is:
 *   - `definition`: the JSON Schema description Claude sees and uses to
 *     decide whether to call this tool;
 *   - `handler`: the async function that turns the tool args into an
 *     API call and returns a JSON payload.
 *
 * Descriptions are tuned to be Claude-readable — short imperative summary
 * first, then mechanics. Hours are the duration unit on input; the API
 * also returns derived days in the responses.
 */

import { ApiError, type ApiConfig, api } from "./api.js";

type Json = Record<string, unknown>;
type ToolHandler = (args: Json) => Promise<unknown>;

export interface Tool {
  definition: {
    name: string;
    description: string;
    inputSchema: {
      type: "object";
      properties: Record<string, unknown>;
      required?: string[];
    };
  };
  handler: ToolHandler;
}

const dateField = {
  type: "string",
  description:
    "ISO 'YYYY-MM-DD', or 'today' / 'yesterday' / 'tomorrow', or natural language like 'last monday' or '3 days ago'.",
};

export function buildTools(config: ApiConfig): Tool[] {
  const a = api(config);

  return [
    // ---------- Account ----------
    {
      definition: {
        name: "whoami",
        description:
          "Show who the agent is acting as. Returns the authenticated user's name, email, role, and weekly/monthly capacity (in hours and days).",
        inputSchema: { type: "object", properties: {} },
      },
      handler: async () => a.me(),
    },

    // ---------- Clients ----------
    {
      definition: {
        name: "list_clients",
        description: "List all clients the user does work for. Paginated.",
        inputSchema: {
          type: "object",
          properties: {
            page: { type: "integer", minimum: 1, description: "Page number (default 1)." },
          },
        },
      },
      handler: async ({ page }) => a.listClients(page as number | undefined),
    },
    {
      definition: {
        name: "get_client",
        description: "Get a single client by id.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "integer" } },
          required: ["id"],
        },
      },
      handler: async ({ id }) => a.getClient(id as number),
    },

    // ---------- Projects ----------
    {
      definition: {
        name: "list_projects",
        description:
          "List the user's projects. Optional filter by client_id. Paginated.",
        inputSchema: {
          type: "object",
          properties: {
            client_id: { type: "integer", description: "Restrict to one client." },
            page: { type: "integer", minimum: 1 },
          },
        },
      },
      handler: async ({ client_id, page }) =>
        a.listProjects({ client_id: client_id as number | undefined, page: page as number | undefined }),
    },
    {
      definition: {
        name: "get_project",
        description: "Get a single project by id, with its client inlined.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "integer" } },
          required: ["id"],
        },
      },
      handler: async ({ id }) => a.getProject(id as number),
    },

    // ---------- Deliverables ----------
    {
      definition: {
        name: "list_deliverables",
        description:
          "List deliverables. Supports filters: project_id, status (R/A/G/B), moscow (M/S/C/W), name_like (LIKE %x% — agent-friendly fuzzy match), completed (true/false). Use this to find a deliverable's id before logging time against it.",
        inputSchema: {
          type: "object",
          properties: {
            project_id: { type: "integer" },
            status: { type: "string", enum: ["R", "A", "G", "B"] },
            moscow: { type: "string", enum: ["M", "S", "C", "W"] },
            name_like: { type: "string", description: "Substring match against the name." },
            completed: { type: "boolean" },
            page: { type: "integer", minimum: 1 },
          },
        },
      },
      handler: async (args) =>
        a.listDeliverables({
          project_id: args.project_id as number | undefined,
          status: args.status as string | undefined,
          moscow: args.moscow as string | undefined,
          name_like: args.name_like as string | undefined,
          completed: args.completed as boolean | undefined,
          page: args.page as number | undefined,
        }),
    },
    {
      definition: {
        name: "get_deliverable",
        description: "Get a single deliverable by id, with derived hours_spent.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "integer" } },
          required: ["id"],
        },
      },
      handler: async ({ id }) => a.getDeliverable(id as number),
    },
    {
      definition: {
        name: "create_deliverable",
        description:
          "Create a new deliverable under a project the user owns. Hours are the duration unit on input (1 day = 8h). Status defaults to 'R' if not set.",
        inputSchema: {
          type: "object",
          properties: {
            project_id: { type: "integer" },
            name: { type: "string", maxLength: 200 },
            description: { type: "string" },
            target_hours: { type: "number", minimum: 0, multipleOf: 0.5 },
            deadline: { type: "string", format: "date", description: "ISO YYYY-MM-DD." },
            status: { type: "string", enum: ["R", "A", "G", "B"] },
            moscow: { type: "string", enum: ["M", "S", "C", "W"] },
          },
          required: ["project_id", "name", "target_hours"],
        },
      },
      handler: async (args) => a.createDeliverable(args),
    },
    {
      definition: {
        name: "update_deliverable",
        description:
          "Patch-style update of a deliverable. Only include the fields you want to change.",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "integer" },
            name: { type: "string", maxLength: 200 },
            description: { type: "string" },
            target_hours: { type: "number", minimum: 0, multipleOf: 0.5 },
            deadline: { type: "string", format: "date" },
            status: { type: "string", enum: ["R", "A", "G", "B"] },
            moscow: { type: "string", enum: ["M", "S", "C", "W"] },
            completed_at: { type: "string", description: "ISO date-time or null." },
          },
          required: ["id"],
        },
      },
      handler: async ({ id, ...body }) => a.updateDeliverable(id as number, body),
    },

    // ---------- Plans ----------
    {
      definition: {
        name: "get_weekly_plan",
        description:
          "Get this week's plan: capacity, allocated, spent, items hydrated with deliverable + project + client.",
        inputSchema: { type: "object", properties: {} },
      },
      handler: async () => a.weeklyPlan(),
    },
    {
      definition: {
        name: "get_monthly_plan",
        description: "Get this month's plan with the same shape as the weekly plan.",
        inputSchema: { type: "object", properties: {} },
      },
      handler: async () => a.monthlyPlan(),
    },
    {
      definition: {
        name: "get_quarterly_plan",
        description: "Get this quarter's plan (current month + next two).",
        inputSchema: { type: "object", properties: {} },
      },
      handler: async () => a.quarterlyPlan(),
    },

    // ---------- Plan items ----------
    {
      definition: {
        name: "add_to_plan",
        description:
          "Add a deliverable to a plan. The easy mode is to pass period_kind ('weekly'|'monthly'|'quarterly') instead of plan_period_id — that auto-resolves to (and creates if needed) the user's current period.",
        inputSchema: {
          type: "object",
          properties: {
            period_kind: {
              type: "string",
              enum: ["weekly", "monthly", "quarterly"],
              description: "Shortcut. Alternative to plan_period_id.",
            },
            plan_period_id: { type: "integer", description: "Explicit period id." },
            deliverable_id: { type: "integer" },
            allocated_hours: { type: "number", minimum: 0, multipleOf: 0.5 },
            notes: { type: "string" },
          },
          required: ["deliverable_id", "allocated_hours"],
        },
      },
      handler: async (args) => a.addToPlan(args),
    },
    {
      definition: {
        name: "update_plan_item",
        description:
          "Patch-style update of a plan-item row (re-allocate hours, change notes, mark complete).",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "integer" },
            allocated_hours: { type: "number", minimum: 0, multipleOf: 0.5 },
            notes: { type: "string" },
            status: { type: "string", enum: ["R", "A", "G", "B"] },
            completed_at: { type: "string", description: "ISO date-time, or null to un-complete." },
          },
          required: ["id"],
        },
      },
      handler: async ({ id, ...body }) => a.updatePlanItem(id as number, body),
    },
    {
      definition: {
        name: "remove_from_plan",
        description: "Remove a deliverable from a plan (by plan-item id).",
        inputSchema: {
          type: "object",
          properties: { id: { type: "integer" } },
          required: ["id"],
        },
      },
      handler: async ({ id }) => {
        await a.removeFromPlan(id as number);
        return { ok: true };
      },
    },

    // ---------- Time logs ----------
    {
      definition: {
        name: "list_time_logs",
        description:
          "List the user's time logs. Filter by single 'date' (takes precedence), or a 'from'/'to' range. All date inputs accept relative strings.",
        inputSchema: {
          type: "object",
          properties: {
            date: dateField,
            from: dateField,
            to: dateField,
            deliverable_id: { type: "integer" },
            ad_hoc: {
              type: "boolean",
              description: "true → only ad-hoc; false → only deliverable-linked.",
            },
            page: { type: "integer", minimum: 1 },
          },
        },
      },
      handler: async (args) =>
        a.listTimeLogs({
          date: args.date as string | undefined,
          from: args.from as string | undefined,
          to: args.to as string | undefined,
          deliverable_id: args.deliverable_id as number | undefined,
          ad_hoc: args.ad_hoc as boolean | undefined,
          page: args.page as number | undefined,
        }),
    },
    {
      definition: {
        name: "log_time",
        description:
          "**THE primary write tool.** Log hours on a deliverable or as ad-hoc work. Pass exactly one of: deliverable_id (explicit), deliverable_name (fuzzy LIKE match — pass any unique substring like 'magnolia oauth'), or ad_hoc_name (for unplanned work). 'date' defaults to today; accepts 'today'/'yesterday'/ISO/natural language.",
        inputSchema: {
          type: "object",
          properties: {
            hours: { type: "number", minimum: 0, maximum: 24, multipleOf: 0.5 },
            deliverable_id: { type: "integer" },
            deliverable_name: {
              type: "string",
              description: "Fuzzy substring; LIKE-matched against the user's deliverable names.",
            },
            ad_hoc_name: { type: "string", maxLength: 200, description: "For unplanned work." },
            date: dateField,
            notes: { type: "string" },
          },
          required: ["hours"],
        },
      },
      handler: async (args) => a.logTime(args),
    },
    {
      definition: {
        name: "update_time_log",
        description: "Patch-style update of a time log (hours, date, notes).",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "integer" },
            hours: { type: "number", minimum: 0, maximum: 24, multipleOf: 0.5 },
            date: dateField,
            notes: { type: "string" },
            ad_hoc_name: { type: "string", maxLength: 200 },
          },
          required: ["id"],
        },
      },
      handler: async ({ id, ...body }) => a.updateTimeLog(id as number, body),
    },
    {
      definition: {
        name: "delete_time_log",
        description: "Delete a time log by id.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "integer" } },
          required: ["id"],
        },
      },
      handler: async ({ id }) => {
        await a.deleteTimeLog(id as number);
        return { ok: true };
      },
    },
  ];
}

/**
 * Run a tool by name, formatting any thrown ApiError into something Claude
 * can read and recover from.
 */
export async function runTool(
  tools: Tool[],
  name: string,
  args: Json,
): Promise<{ ok: true; data: unknown } | { ok: false; error: unknown }> {
  const tool = tools.find((t) => t.definition.name === name);
  if (!tool) {
    return { ok: false, error: `Unknown tool: ${name}` };
  }
  try {
    const data = await tool.handler(args);
    return { ok: true, data };
  } catch (e) {
    if (e instanceof ApiError) {
      return { ok: false, error: { status: e.status, body: e.body } };
    }
    return { ok: false, error: String(e) };
  }
}
