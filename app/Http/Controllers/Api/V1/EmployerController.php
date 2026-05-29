<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployerRequest;
use App\Http\Requests\Api\V1\UpdateEmployerRequest;
use App\Http\Resources\EmployerResource;
use App\Models\Employer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * REST surface for Employers (M13e).
 *
 *   GET    /api/v1/employers              index (paginated, Self-first)
 *   GET    /api/v1/employers/{id}         show
 *   POST   /api/v1/employers              store (always creates a non-Self row)
 *   PUT    /api/v1/employers/{id}         update (rejects renaming Self)
 *   DELETE /api/v1/employers/{id}         destroy (Self + non-empty rows refused)
 *
 * All operations scoped to the authenticated user via owner_id.
 */
class EmployerController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $query = Employer::query()
            ->where('owner_id', auth()->id())
            ->withCount('clients')
            ->orderByDesc('is_self')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->has('is_self')) {
            $query->where(
                'is_self',
                filter_var($request->input('is_self'), FILTER_VALIDATE_BOOLEAN),
            );
        }

        return EmployerResource::collection($query->paginate());
    }

    public function show(Employer $employer): EmployerResource
    {
        abort_unless($employer->owner_id === auth()->id(), 404);
        $employer->loadCount('clients');

        return new EmployerResource($employer);
    }

    public function store(StoreEmployerRequest $request): JsonResponse
    {
        $employer = Employer::create([
            'owner_id' => $request->user()->id,
            ...$request->validated(),
        ]);
        $employer->loadCount('clients');

        return (new EmployerResource($employer))->response()->setStatusCode(201);
    }

    public function update(UpdateEmployerRequest $request, Employer $employer): EmployerResource
    {
        abort_unless($employer->owner_id === auth()->id(), 404);
        $employer->update($request->validated());
        $employer->loadCount('clients');

        return new EmployerResource($employer);
    }

    public function destroy(Employer $employer): JsonResponse
    {
        abort_unless($employer->owner_id === auth()->id(), 404);

        // 422 for the two refusal cases gives the agent a structured signal
        // — both more recoverable than the model's bare LogicException.
        if ($employer->is_self) {
            return response()->json([
                'message' => 'The Self employer cannot be deleted.',
                'errors' => ['employer' => ['cannot_delete_self']],
            ], 422);
        }
        if ($employer->clients()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this employer — it still has clients. Move or delete them first.',
                'errors' => ['employer' => ['has_clients']],
            ], 422);
        }

        $employer->delete();

        return response()->json(null, 204);
    }
}
