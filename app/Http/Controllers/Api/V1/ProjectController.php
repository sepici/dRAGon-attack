<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $query = Project::query()
            ->where('owner_id', auth()->id())
            ->with('client')
            ->orderBy('name');

        if ($clientId = $request->integer('client_id')) {
            $query->where('client_id', $clientId);
        }

        return ProjectResource::collection($query->paginate());
    }

    public function show(Project $project): ProjectResource
    {
        abort_unless($project->owner_id === auth()->id(), 404);
        $project->load('client');

        return new ProjectResource($project);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            'owner_id' => $request->user()->id,
            ...$request->validated(),
        ]);
        $project->load('client');

        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        abort_unless($project->owner_id === auth()->id(), 404);
        $project->update($request->validated());
        $project->load('client');

        return new ProjectResource($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        abort_unless($project->owner_id === auth()->id(), 404);
        $project->delete();

        return response()->json(null, 204);
    }
}
