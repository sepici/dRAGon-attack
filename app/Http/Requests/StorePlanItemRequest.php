<?php

namespace App\Http\Requests;

use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isUser();
    }

    public function rules(): array
    {
        return [
            // The plan_period must exist AND be owned by the current user.
            'plan_period_id' => [
                'required',
                Rule::exists('plan_periods', 'id')
                    ->where('owner_id', $this->user()->id),
            ],

            // The deliverable must exist AND its project must be owned by the
            // current user.
            'deliverable_id' => [
                'required',
                'exists:deliverables,id',
                function ($attribute, $value, $fail) {
                    $deliverable = Deliverable::with('project')->find($value);
                    if (! $deliverable || $deliverable->project->owner_id !== $this->user()->id) {
                        $fail('That deliverable is not yours.');
                    }
                },
                // No duplicates within the same plan period.
                function ($attribute, $value, $fail) {
                    $periodId = $this->input('plan_period_id');
                    if (! $periodId) {
                        return;
                    }
                    $exists = PlanItem::where('plan_period_id', $periodId)
                        ->where('deliverable_id', $value)
                        ->exists();
                    if ($exists) {
                        $fail('This deliverable is already on the plan for this period.');
                    }
                },
            ],

            'allocated_hours' => [
                'required', 'numeric', 'min:0', 'max:9999', 'multiple_of:0.5',
            ],
        ];
    }
}
