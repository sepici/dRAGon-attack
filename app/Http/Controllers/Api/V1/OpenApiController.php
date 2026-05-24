<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\OpenApi;
use Illuminate\Http\JsonResponse;

/**
 * Public OpenAPI 3.1 spec for /api/v1.
 *
 * Served un-authenticated so MCP wrappers, ChatGPT Custom GPTs, and other
 * spec-consuming tools can discover the API surface without a token. The
 * spec describes endpoints only — no user data leaks here.
 */
class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(
            OpenApi::build(),
            200,
            ['Cache-Control' => 'public, max-age=300'],
        );
    }
}
