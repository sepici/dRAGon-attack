<?php

namespace App\Support;

use App\Enums\Moscow;
use App\Enums\Status;

/**
 * Hand-curated OpenAPI 3.1 spec for /api/v1.
 *
 * Served (un-authed) at GET /api/v1/openapi.json so MCP wrappers,
 * ChatGPT Custom GPTs, and OpenAPI-aware tools can self-discover the API.
 *
 * Why hand-curated instead of auto-generated (Scribe, l5-swagger): the
 * surface is small, the description text matters (agent-friendly behaviour
 * lives in the docs), and we want to avoid an extra runtime dependency
 * just to publish a spec.
 */
class OpenApi
{
    public static function build(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => self::info(),
            'servers' => self::servers(),
            'security' => [['bearerAuth' => []]],
            'tags' => self::tags(),
            'paths' => self::paths(),
            'components' => [
                'securitySchemes' => self::securitySchemes(),
                'schemas' => self::schemas(),
                'parameters' => self::sharedParameters(),
                'responses' => self::sharedResponses(),
            ],
        ];
    }

    // ---------- info / servers / tags ----------

    private static function info(): array
    {
        return [
            'title' => 'dRAGonattack Tracker API',
            'version' => '1.0.0',
            'description' => trim('
A weekly/monthly/quarterly RAG status tracker for client deliverables.
Connect your AI agent to read and write your tracker on your behalf.

**Authentication.** All endpoints under `/api/v1/` require a Sanctum
personal-access token. Create one at `/profile` on the web app, then
send it as `Authorization: Bearer <token>`.

**Token abilities.** Tokens are scoped. The endpoints below tag each
operation with the ability it requires (`tracker:read`, `tracker:write`,
`time-logs:read`, `time-logs:write`). The wildcards `read:all` and
`write:all` are expanded to the atomic abilities at token-creation
time, so picking them in the UI is the easy mode.

**Units.** Hours are the storage unit (one workday = 8 hours).
Responses include both `*_hours` and derived `*_days` for every
duration field. Requests accept only the hours form on input.

**Dates.** Where an endpoint accepts a date input it accepts:
ISO `YYYY-MM-DD`, the literal strings `today` / `yesterday` /
`tomorrow`, day-of-week names, and natural-language phrases like
"last monday" or "3 days ago".
'),
        ];
    }

    private static function servers(): array
    {
        return [
            [
                'url' => config('app.url') . '/api/v1',
                'description' => 'Configured server',
            ],
        ];
    }

    private static function tags(): array
    {
        return [
            ['name' => 'Account', 'description' => 'Who am I?'],
            ['name' => 'Clients', 'description' => 'Companies you do work for.'],
            ['name' => 'Projects', 'description' => 'Engagements with a client.'],
            ['name' => 'Milestones', 'description' => 'Optional grouping layer between a project and its deliverables. Use for phased work or forward-planning envelopes.'],
            ['name' => 'Deliverables', 'description' => 'Discrete outputs within a project (optionally attached to a milestone).'],
            ['name' => 'Plans', 'description' => 'Weekly / monthly / quarterly allocation of deliverables and milestones.'],
            ['name' => 'Plan items', 'description' => 'Individual allocations on a plan — either to a deliverable or to a milestone (forward-planning envelope).'],
            ['name' => 'Time logs', 'description' => 'Day-by-day journal entries.'],
        ];
    }

    // ---------- security ----------

    private static function securitySchemes(): array
    {
        return [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'Sanctum personal-access token (format: `id|hash`).',
            ],
        ];
    }

    // ---------- shared parameters and responses ----------

    private static function sharedParameters(): array
    {
        return [
            'PageParam' => [
                'name' => 'page', 'in' => 'query',
                'description' => 'Page number for paginated list endpoints (default 1).',
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            'PerPageParam' => [
                'name' => 'per_page', 'in' => 'query',
                'description' => 'Rows per page (default 15).',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
        ];
    }

    private static function sharedResponses(): array
    {
        return [
            'Unauthorized' => [
                'description' => 'Missing / invalid token.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'Forbidden' => [
                'description' => 'Token lacks the required ability or role.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'NotFound' => [
                'description' => 'Resource not found (or not owned by you).',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            'ValidationError' => [
                'description' => 'Invalid input. The body lists per-field errors.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]],
            ],
            'NoContent' => ['description' => 'Success; no response body.'],
        ];
    }

    // ---------- schemas ----------

    private static function schemas(): array
    {
        $statusValues = array_column(Status::cases(), 'value');
        $moscowValues = array_column(Moscow::cases(), 'value');

        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                ],
                'required' => ['message'],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
            ],

            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'role' => ['type' => 'string', 'enum' => ['user', 'admin', 'viewer']],
                    'capacity' => [
                        'type' => 'object',
                        'properties' => [
                            'weekly_hours' => ['type' => 'number'],
                            'weekly_days' => ['type' => 'number'],
                            'monthly_hours' => ['type' => 'number'],
                            'monthly_days' => ['type' => 'number'],
                        ],
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],

            'Client' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'legal_name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'nullable' => true, 'format' => 'email'],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ClientWrite' => [
                'type' => 'object',
                'required' => ['legal_name'],
                'properties' => [
                    'legal_name' => ['type' => 'string', 'maxLength' => 200],
                    'email' => ['type' => 'string', 'nullable' => true, 'format' => 'email'],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'notes' => ['type' => 'string', 'nullable' => true],
                ],
            ],

            'Project' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'deadline' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                    'client_id' => ['type' => 'integer'],
                    'client' => ['$ref' => '#/components/schemas/Client'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ProjectWrite' => [
                'type' => 'object',
                'required' => ['client_id', 'name'],
                'properties' => [
                    'client_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'maxLength' => 200],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'deadline' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                ],
            ],

            'Deliverable' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'target_hours' => ['type' => 'number'],
                    'target_days' => ['type' => 'number'],
                    'hours_spent' => [
                        'type' => 'number',
                        'description' => 'Derived: SUM(time_logs.hours WHERE deliverable_id = X). Cumulative.',
                    ],
                    'days_spent' => ['type' => 'number'],
                    'remaining_hours' => ['type' => 'number'],
                    'remaining_days' => ['type' => 'number'],
                    'deadline' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                    'status' => ['type' => 'string', 'enum' => $statusValues, 'description' => 'R=Red, A=Amber, G=Green, B=Blocked'],
                    'moscow' => ['type' => 'string', 'nullable' => true, 'enum' => $moscowValues, 'description' => 'M=Must, S=Should, C=Could, W=Won\'t'],
                    'completed_at' => ['type' => 'string', 'nullable' => true, 'format' => 'date-time'],
                    'project_id' => ['type' => 'integer'],
                    'project' => ['$ref' => '#/components/schemas/Project'],
                    'milestone_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Optional grouping under a milestone in the same project.'],
                    'milestone' => ['$ref' => '#/components/schemas/Milestone'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'DeliverableWrite' => [
                'type' => 'object',
                'required' => ['project_id', 'name', 'target_hours'],
                'properties' => [
                    'project_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'maxLength' => 200],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'target_hours' => ['type' => 'number', 'minimum' => 0, 'maximum' => 2000, 'multipleOf' => 0.5],
                    'deadline' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                    'status' => ['type' => 'string', 'enum' => $statusValues, 'default' => 'R'],
                    'moscow' => ['type' => 'string', 'nullable' => true, 'enum' => $moscowValues],
                    'milestone_id' => [
                        'type' => 'integer', 'nullable' => true,
                        'description' => 'Optional. The milestone must belong to the same project as the deliverable.',
                    ],
                ],
            ],

            'Milestone' => [
                'type' => 'object',
                'description' => 'A phase / chunk of work inside a project. Status is DERIVED from child deliverables + the scope_complete gate (see scope_ambiguous).',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'project_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'target_hours' => [
                        'type' => 'number', 'nullable' => true,
                        'description' => 'Manual coarse-grained target. May be null — when null, effective_target_hours sums the children.',
                    ],
                    'target_days' => ['type' => 'number', 'nullable' => true],
                    'effective_target_hours' => [
                        'type' => 'number',
                        'description' => 'target_hours when set, otherwise SUM of child deliverables\' target_hours.',
                    ],
                    'effective_target_days' => ['type' => 'number'],
                    'hours_spent' => [
                        'type' => 'number',
                        'description' => 'Sum of every time_log on any deliverable in this milestone (entire history).',
                    ],
                    'days_spent' => ['type' => 'number'],
                    'deadline' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                    'moscow' => ['type' => 'string', 'nullable' => true, 'enum' => $moscowValues],
                    'scope_complete' => [
                        'type' => 'boolean',
                        'description' => 'Set to true when every deliverable that needs to live under this milestone has been added. Gates derived status: while false, an all-Green milestone reads Amber.',
                    ],
                    'status' => [
                        'type' => 'string', 'enum' => $statusValues,
                        'description' => 'Derived RAGB rollup. Any child Red → Red; any Blocked → Blocked; any Amber → Amber; all Green + scope_complete=true → Green; all Green + scope_complete=false → Amber.',
                    ],
                    'scope_ambiguous' => [
                        'type' => 'boolean',
                        'description' => 'True when all children are Green but scope_complete is false. Hints "tick scope_complete or add the missing deliverables".',
                    ],
                    'sort_order' => ['type' => 'integer'],
                    'project' => ['$ref' => '#/components/schemas/Project'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'MilestoneWrite' => [
                'type' => 'object',
                'required' => ['project_id', 'name'],
                'properties' => [
                    'project_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'maxLength' => 200],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'target_hours' => [
                        'type' => 'number', 'nullable' => true,
                        'minimum' => 0, 'maximum' => 2000, 'multipleOf' => 0.5,
                        'description' => 'Optional manual target. Leave null to let the UI sum children.',
                    ],
                    'deadline' => ['type' => 'string', 'nullable' => true, 'format' => 'date'],
                    'moscow' => ['type' => 'string', 'nullable' => true, 'enum' => $moscowValues],
                    'scope_complete' => [
                        'type' => 'boolean',
                        'description' => 'Defaults to false on create. Set true once every relevant deliverable has been added.',
                    ],
                    'sort_order' => ['type' => 'integer'],
                ],
            ],

            'PlanItem' => [
                'type' => 'object',
                'description' => 'A single allocation on a plan period. Exactly one of deliverable_id or milestone_id is non-null (envelope vs specific-draw model).',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'plan_period_id' => ['type' => 'integer'],
                    'deliverable_id' => ['type' => 'integer', 'nullable' => true],
                    'milestone_id' => ['type' => 'integer', 'nullable' => true],
                    'allocated_hours' => ['type' => 'number'],
                    'allocated_days' => ['type' => 'number'],
                    'hours_spent' => [
                        'type' => 'number',
                        'description' => 'Period-scoped: only counts time_logs whose log_date falls inside the parent plan_period. For milestone allocations, sums across every child deliverable of the milestone.',
                    ],
                    'days_spent' => ['type' => 'number'],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'enum' => $statusValues],
                    'completed_at' => ['type' => 'string', 'nullable' => true, 'format' => 'date-time'],
                    'deliverable' => ['$ref' => '#/components/schemas/Deliverable'],
                    'milestone' => ['$ref' => '#/components/schemas/Milestone'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'PlanItemCreate' => [
                'type' => 'object',
                'required' => ['allocated_hours'],
                'description' => 'Submit EITHER deliverable_id OR milestone_id — exactly one. The other should be omitted (or null).',
                'properties' => [
                    'plan_period_id' => [
                        'type' => 'integer',
                        'description' => 'Explicit plan-period id. Alternative to period_kind.',
                    ],
                    'period_kind' => [
                        'type' => 'string',
                        'enum' => ['weekly', 'monthly', 'quarterly'],
                        'description' => 'Shortcut: resolve to the user\'s current period of that kind, creating it if needed. Use instead of plan_period_id.',
                    ],
                    'deliverable_id' => [
                        'type' => 'integer', 'nullable' => true,
                        'description' => 'Allocate to a specific deliverable. Omit if allocating to a milestone envelope.',
                    ],
                    'milestone_id' => [
                        'type' => 'integer', 'nullable' => true,
                        'description' => 'Forward-planning envelope: allocate to a milestone before its deliverables are scoped. Omit if allocating to a deliverable.',
                    ],
                    'allocated_hours' => ['type' => 'number', 'minimum' => 0, 'maximum' => 2000, 'multipleOf' => 0.5],
                    'notes' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'PlanItemUpdate' => [
                'type' => 'object',
                'properties' => [
                    'allocated_hours' => ['type' => 'number', 'minimum' => 0, 'maximum' => 2000, 'multipleOf' => 0.5],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'enum' => $statusValues],
                    'completed_at' => ['type' => 'string', 'nullable' => true, 'format' => 'date-time'],
                ],
            ],

            'PlanPeriod' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'kind' => ['type' => 'string', 'enum' => ['weekly', 'monthly', 'quarterly']],
                    'starts_on' => ['type' => 'string', 'format' => 'date'],
                    'ends_on' => ['type' => 'string', 'format' => 'date'],
                    'capacity_hours' => ['type' => 'number'],
                    'capacity_days' => ['type' => 'number'],
                    'allocated_hours' => ['type' => 'number'],
                    'allocated_days' => ['type' => 'number'],
                    'spent_hours' => ['type' => 'number'],
                    'spent_days' => ['type' => 'number'],
                    'over_under_hours' => ['type' => 'number'],
                    'over_under_days' => ['type' => 'number'],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/PlanItem'],
                    ],
                ],
            ],

            'TimeLog' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'log_date' => ['type' => 'string', 'format' => 'date'],
                    'hours' => ['type' => 'number'],
                    'days' => ['type' => 'number'],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'deliverable_id' => ['type' => 'integer', 'nullable' => true],
                    'ad_hoc_name' => ['type' => 'string', 'nullable' => true],
                    'is_ad_hoc' => ['type' => 'boolean'],
                    'deliverable' => ['$ref' => '#/components/schemas/Deliverable'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TimeLogCreate' => [
                'type' => 'object',
                'required' => ['hours'],
                'properties' => [
                    'hours' => ['type' => 'number', 'minimum' => 0, 'maximum' => 24, 'multipleOf' => 0.5],
                    'date' => [
                        'type' => 'string',
                        'description' => 'Date for the log. Accepts ISO `YYYY-MM-DD`, the literal strings `today`/`yesterday`/`tomorrow`, day-of-week names, or natural-language ("3 days ago"). Defaults to today.',
                        'example' => 'today',
                    ],
                    'deliverable_id' => [
                        'type' => 'integer',
                        'description' => 'Link the log to a tracked deliverable by id.',
                    ],
                    'deliverable_name' => [
                        'type' => 'string',
                        'description' => 'Alternative to deliverable_id — LIKE-matches against the user\'s deliverable names. If no match the 422 lists candidate names.',
                        'example' => 'acme oauth',
                    ],
                    'ad_hoc_name' => [
                        'type' => 'string',
                        'description' => 'Label for unplanned work that isn\'t linked to a deliverable. Mutually exclusive with deliverable_id/_name.',
                        'maxLength' => 200,
                    ],
                    'notes' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'TimeLogUpdate' => [
                'type' => 'object',
                'properties' => [
                    'hours' => ['type' => 'number', 'minimum' => 0, 'maximum' => 24, 'multipleOf' => 0.5],
                    'date' => ['type' => 'string', 'description' => 'See TimeLogCreate.date.'],
                    'notes' => ['type' => 'string', 'nullable' => true],
                    'ad_hoc_name' => ['type' => 'string', 'maxLength' => 200],
                ],
            ],
        ];
    }

    // ---------- paths ----------

    private static function paths(): array
    {
        return [
            '/me' => self::pathMe(),
            ...self::pathsFor('clients', 'Clients', 'Client'),
            ...self::pathsFor('projects', 'Projects', 'Project'),
            ...self::pathsFor('deliverables', 'Deliverables', 'Deliverable', deliverableFilters: true),
            ...self::pathsFor('milestones', 'Milestones', 'Milestone', milestoneFilters: true),
            '/plans/weekly' => self::pathPlan('weekly', 'Weekly'),
            '/plans/monthly' => self::pathPlan('monthly', 'Monthly'),
            '/plans/quarterly' => self::pathPlan('quarterly', 'Quarterly'),
            '/plan-items' => self::pathPlanItemsCollection(),
            '/plan-items/{id}' => self::pathPlanItemsItem(),
            '/time-logs' => self::pathTimeLogsCollection(),
            '/time-logs/{id}' => self::pathTimeLogsItem(),
        ];
    }

    private static function pathMe(): array
    {
        return [
            'get' => [
                'tags' => ['Account'],
                'summary' => 'Get the authenticated user',
                'description' => 'Identity ping. Use this on connection to confirm the token works.',
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => ['application/json' => [
                            'schema' => self::dataEnvelope(['$ref' => '#/components/schemas/User']),
                        ]],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                ],
            ],
        ];
    }

    /**
     * Builds index/show/store/update/destroy paths for a CRUD resource.
     */
    private static function pathsFor(string $plural, string $tag, string $schema, bool $deliverableFilters = false, bool $milestoneFilters = false): array
    {
        $writeSchema = $schema . 'Write';
        $indexParams = [
            ['$ref' => '#/components/parameters/PageParam'],
            ['$ref' => '#/components/parameters/PerPageParam'],
        ];
        if ($plural === 'projects') {
            $indexParams[] = ['name' => 'client_id', 'in' => 'query', 'schema' => ['type' => 'integer']];
        }
        if ($deliverableFilters) {
            $indexParams = array_merge($indexParams, [
                ['name' => 'project_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => array_column(Status::cases(), 'value')]],
                ['name' => 'moscow', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => array_column(Moscow::cases(), 'value')]],
                ['name' => 'name_like', 'in' => 'query',
                    'description' => 'LIKE %x% over the deliverable name. Agent-friendly fuzzy filter.',
                    'schema' => ['type' => 'string'],
                ],
                ['name' => 'completed', 'in' => 'query', 'schema' => ['type' => 'boolean']],
            ]);
        }
        if ($milestoneFilters) {
            $indexParams = array_merge($indexParams, [
                ['name' => 'project_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ['name' => 'moscow', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => array_column(Moscow::cases(), 'value')]],
                ['name' => 'name_like', 'in' => 'query',
                    'description' => 'LIKE %x% over the milestone name.',
                    'schema' => ['type' => 'string'],
                ],
                ['name' => 'scope_complete', 'in' => 'query',
                    'description' => 'Filter by scope_complete flag (true/false).',
                    'schema' => ['type' => 'boolean'],
                ],
            ]);
        }

        return [
            "/{$plural}" => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => "List {$plural}",
                    'description' => "Paginated. tracker:read required.",
                    'parameters' => $indexParams,
                    'responses' => [
                        '200' => self::listResponse($schema),
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                ],
                'post' => [
                    'tags' => [$tag],
                    'summary' => "Create a {$tag}",
                    'description' => "tracker:write required.",
                    'requestBody' => self::jsonBody($writeSchema),
                    'responses' => [
                        '201' => self::singleResponse($schema, 'Created'),
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ],
                ],
            ],
            "/{$plural}/{id}" => [
                'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                'get' => [
                    'tags' => [$tag],
                    'summary' => "Get a {$tag} by id",
                    'description' => "tracker:read required.",
                    'responses' => [
                        '200' => self::singleResponse($schema),
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                    ],
                ],
                'put' => [
                    'tags' => [$tag],
                    'summary' => "Update a {$tag} (patch-style)",
                    'description' => "Only submitted fields are touched. tracker:write required.",
                    'requestBody' => self::jsonBody($writeSchema),
                    'responses' => [
                        '200' => self::singleResponse($schema),
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ],
                ],
                'delete' => [
                    'tags' => [$tag],
                    'summary' => "Delete a {$tag}",
                    'description' => "tracker:write required.",
                    'responses' => [
                        '204' => ['$ref' => '#/components/responses/NoContent'],
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                    ],
                ],
            ],
        ];
    }

    private static function pathPlan(string $kind, string $label): array
    {
        return [
            'get' => [
                'tags' => ['Plans'],
                'summary' => "{$label} plan",
                'description' => "Current {$kind} plan period with items hydrated. Auto-creates the period on first call. tracker:read required.",
                'responses' => [
                    '200' => self::singleResponse('PlanPeriod'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                ],
            ],
        ];
    }

    private static function pathPlanItemsCollection(): array
    {
        return [
            'post' => [
                'tags' => ['Plan items'],
                'summary' => 'Add a deliverable to a plan',
                'description' => 'Pass either plan_period_id OR period_kind. tracker:write required.',
                'requestBody' => self::jsonBody('PlanItemCreate'),
                'responses' => [
                    '201' => self::singleResponse('PlanItem', 'Created'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ];
    }

    private static function pathPlanItemsItem(): array
    {
        return [
            'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
            'put' => [
                'tags' => ['Plan items'],
                'summary' => 'Update a plan item',
                'description' => 'Patch-style. tracker:write required.',
                'requestBody' => self::jsonBody('PlanItemUpdate'),
                'responses' => [
                    '200' => self::singleResponse('PlanItem'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Plan items'],
                'summary' => 'Remove a deliverable from a plan',
                'description' => 'tracker:write required.',
                'responses' => [
                    '204' => ['$ref' => '#/components/responses/NoContent'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ];
    }

    private static function pathTimeLogsCollection(): array
    {
        return [
            'get' => [
                'tags' => ['Time logs'],
                'summary' => 'List time logs',
                'description' => 'time-logs:read required.',
                'parameters' => [
                    ['$ref' => '#/components/parameters/PageParam'],
                    ['$ref' => '#/components/parameters/PerPageParam'],
                    ['name' => 'date', 'in' => 'query',
                        'description' => 'Single-date filter. Accepts ISO, "today", "yesterday", natural language. Takes precedence over from/to.',
                        'schema' => ['type' => 'string'],
                    ],
                    ['name' => 'from', 'in' => 'query',
                        'description' => 'Inclusive lower bound (same parser as `date`).',
                        'schema' => ['type' => 'string'],
                    ],
                    ['name' => 'to', 'in' => 'query',
                        'description' => 'Inclusive upper bound (same parser as `date`).',
                        'schema' => ['type' => 'string'],
                    ],
                    ['name' => 'deliverable_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'ad_hoc', 'in' => 'query', 'description' => 'true to filter to ad-hoc logs only; false to exclude them.', 'schema' => ['type' => 'boolean']],
                ],
                'responses' => [
                    '200' => self::listResponse('TimeLog'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                ],
            ],
            'post' => [
                'tags' => ['Time logs'],
                'summary' => 'Log time',
                'description' => trim('
Create a time log for the authenticated user. **The agent\'s
primary write operation.** Pass exactly one of:

  - `deliverable_id` (integer) — explicit
  - `deliverable_name` (string) — LIKE-matched against your deliverables
  - `ad_hoc_name` (string) — unplanned work

A 422 from a missed `deliverable_name` lookup lists candidate names so
the agent can retry with a working call.

time-logs:write required.
'),
                'requestBody' => self::jsonBody('TimeLogCreate'),
                'responses' => [
                    '201' => self::singleResponse('TimeLog', 'Created'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ];
    }

    private static function pathTimeLogsItem(): array
    {
        return [
            'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
            'get' => [
                'tags' => ['Time logs'],
                'summary' => 'Get a time log by id',
                'description' => 'time-logs:read required.',
                'responses' => [
                    '200' => self::singleResponse('TimeLog'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'put' => [
                'tags' => ['Time logs'],
                'summary' => 'Update a time log',
                'description' => 'Patch-style. time-logs:write required.',
                'requestBody' => self::jsonBody('TimeLogUpdate'),
                'responses' => [
                    '200' => self::singleResponse('TimeLog'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
            'delete' => [
                'tags' => ['Time logs'],
                'summary' => 'Delete a time log',
                'description' => 'time-logs:write required.',
                'responses' => [
                    '204' => ['$ref' => '#/components/responses/NoContent'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ];
    }

    // ---------- response helpers ----------

    /**
     * Wraps a schema in our Laravel API Resource envelope: { "data": <schema> }.
     */
    private static function dataEnvelope(array $schema): array
    {
        return [
            'type' => 'object',
            'properties' => ['data' => $schema],
        ];
    }

    private static function singleResponse(string $schemaName, string $description = 'OK'): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => [
                'schema' => self::dataEnvelope(['$ref' => '#/components/schemas/' . $schemaName]),
            ]],
        ];
    }

    private static function listResponse(string $itemSchemaName): array
    {
        return [
            'description' => 'OK',
            'content' => ['application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/' . $itemSchemaName],
                        ],
                        'links' => [
                            'type' => 'object',
                            'properties' => [
                                'first' => ['type' => 'string', 'nullable' => true],
                                'last' => ['type' => 'string', 'nullable' => true],
                                'prev' => ['type' => 'string', 'nullable' => true],
                                'next' => ['type' => 'string', 'nullable' => true],
                            ],
                        ],
                        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                    ],
                ],
            ]],
        ];
    }

    private static function jsonBody(string $schemaName): array
    {
        return [
            'required' => true,
            'content' => ['application/json' => [
                'schema' => ['$ref' => '#/components/schemas/' . $schemaName],
            ]],
        ];
    }
}
