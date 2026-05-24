<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Requests\Api\V1\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClientController extends Controller
{
    public function index(): ResourceCollection
    {
        $clients = Client::query()
            ->where('owner_id', auth()->id())
            ->orderBy('legal_name')
            ->paginate();

        return ClientResource::collection($clients);
    }

    public function show(Client $client): ClientResource
    {
        abort_unless($client->owner_id === auth()->id(), 404);

        return new ClientResource($client);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            'owner_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        abort_unless($client->owner_id === auth()->id(), 404);
        $client->update($request->validated());

        return new ClientResource($client);
    }

    public function destroy(Client $client): JsonResponse
    {
        abort_unless($client->owner_id === auth()->id(), 404);
        $client->delete();

        return response()->json(null, 204);
    }
}
