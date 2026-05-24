<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanPeriodResource;
use App\Models\PlanPeriod;

/**
 * GET /api/v1/plans/weekly
 * GET /api/v1/plans/monthly
 * GET /api/v1/plans/quarterly
 *
 * Returns the *current* plan period for the authenticated user, with
 * hydrated items. Period is auto-created on first call (matches the web
 * controller's findOrCreateCurrentFor behaviour).
 */
class PlanController extends Controller
{
    public function weekly(): PlanPeriodResource
    {
        return $this->show(PlanKind::Weekly);
    }

    public function monthly(): PlanPeriodResource
    {
        return $this->show(PlanKind::Monthly);
    }

    public function quarterly(): PlanPeriodResource
    {
        return $this->show(PlanKind::Quarterly);
    }

    private function show(PlanKind $kind): PlanPeriodResource
    {
        $user = auth()->user();
        $period = PlanPeriod::findOrCreateCurrentFor($user, $kind);

        $period->load([
            'items' => fn ($q) => $q->whereNotNull('deliverable_id')
                ->with('deliverable.project.client'),
        ]);
        $period->loadHoursSpent();

        return new PlanPeriodResource($period);
    }
}
