<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
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
}
