<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;

/**
 * Shared picker data for the cascading Employer → Client → Project drop-down
 * used by the journal "add a deliverable" widget and the deliverables index
 * filter bar.
 *
 * Returns plain PHP arrays (no Eloquent models) so the result can be
 * @json()-encoded straight into Alpine x-data without leaking model state
 * into the view layer.
 *
 * One query per layer — total of 3 queries regardless of how many employers,
 * clients, or projects the user has.
 */
class EmployerScopedPicker
{
    /**
     * @return array{
     *     employers: array<int,array{id:int,name:string,is_self:bool}>,
     *     clientsByEmployer: array<int,array<int,array{id:int,name:string,employer_id:int}>>,
     *     projectsByClient: array<int,array<int,array{id:int,name:string,client_id:int}>>,
     * }
     */
    public static function forUser(User $user): array
    {
        // Employers — Self first (is_self DESC), then by sort_order, then
        // alphabetical. Matches the ordering EmployerController uses on the
        // listing page so the UX is consistent.
        $employerRows = $user->employers()
            ->orderByDesc('is_self')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_self']);

        $employers = $employerRows->map(fn ($e) => [
            'id' => (int) $e->id,
            'name' => $e->name,
            'is_self' => (bool) $e->is_self,
        ])->all();

        $employerIds = $employerRows->pluck('id')->all();

        // Clients keyed by employer_id. Single query, group in PHP — cheaper
        // than per-employer fetches and the dataset is tiny.
        $clientsByEmployer = [];
        foreach ($employerIds as $id) {
            $clientsByEmployer[(int) $id] = [];
        }
        if (! empty($employerIds)) {
            Client::query()
                ->where('owner_id', $user->id)
                ->whereIn('employer_id', $employerIds)
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'employer_id'])
                ->each(function ($c) use (&$clientsByEmployer) {
                    $clientsByEmployer[(int) $c->employer_id][] = [
                        'id' => (int) $c->id,
                        'name' => $c->legal_name,
                        'employer_id' => (int) $c->employer_id,
                    ];
                });
        }

        // Projects keyed by client_id, scoped to the user's clients.
        $clientIds = collect($clientsByEmployer)->flatten(1)->pluck('id')->all();
        $projectsByClient = [];
        foreach ($clientIds as $id) {
            $projectsByClient[(int) $id] = [];
        }
        if (! empty($clientIds)) {
            Project::query()
                ->where('owner_id', $user->id)
                ->whereIn('client_id', $clientIds)
                ->orderBy('name')
                ->get(['id', 'name', 'client_id'])
                ->each(function ($p) use (&$projectsByClient) {
                    $projectsByClient[(int) $p->client_id][] = [
                        'id' => (int) $p->id,
                        'name' => $p->name,
                        'client_id' => (int) $p->client_id,
                    ];
                });
        }

        return [
            'employers' => $employers,
            'clientsByEmployer' => $clientsByEmployer,
            'projectsByClient' => $projectsByClient,
        ];
    }
}
