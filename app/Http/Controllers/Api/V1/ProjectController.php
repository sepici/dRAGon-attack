<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
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
}
