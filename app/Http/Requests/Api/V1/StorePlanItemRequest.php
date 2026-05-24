<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PlanKind;
use App\Models\Deliverable;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add a deliverable to a plan period.
 *
 * Two ways to specify the period:
 *   - `plan_period_id`     explicit id, OR
 *   - `period_kind`        "weekly" | "monthly" | "quarterly" — resolves to
 *                          the user's current period of that kind (auto-creates
 *                          on first use, matching the web behaviour).
 */
class StorePlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('plan_period_id') && $this->filled('period_kind')) {
            $kind = PlanKind::tryFrom((string) $this->input('period_kind'));
            if ($kind) {
                $period = PlanPeriod::findOrCreateCurrentFor($this->user(), $kind);
                $this->merge(['plan_period_id' => $period->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'plan_period_id' => [
                'required',
                Rule::exists('plan_periods', 'id')->where('owner_id', $this->user()->id),
            ],
            'deliverable_id' => ['required', 'integer'],
            'allocated_hours' => ['required', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'notes' => ['nullable', 'string'],
            'period_kind' => ['nullable', Rule::enum(PlanKind::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Deliverable belongs to this user.
            $owns = Deliverable::query()
                ->where('id', $this->input('deliverable_id'))
                ->whereHas('project', fn ($q) => $q->where('owner_id', $this->user()->id))
                ->exists();
            if (! $owns) {
                $v->errors()->add('deliverable_id', 'That deliverable does not exist or is not yours.');
                return;
            }

            // No duplicates.
            $exists = PlanItem::query()
                ->where('plan_period_id', $this->input('plan_period_id'))
                ->where('deliverable_id', $this->input('deliverable_id'))
                ->exists();
            if ($exists) {
                $v->errors()->add('deliverable_id', 'This deliverable is already on the plan for that period.');
            }
        });
    }
}
