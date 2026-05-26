<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PlanKind;
use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add an allocation to a plan period. The allocation targets EITHER a
 * deliverable OR a milestone — exactly one of the two ids must be set
 * (mirrors the web form, the model's saving guard, and PlanItem semantics).
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
            'deliverable_id' => ['nullable', 'integer'],
            'milestone_id' => ['nullable', 'integer'],
            'allocated_hours' => ['required', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'notes' => ['nullable', 'string'],
            'period_kind' => ['nullable', Rule::enum(PlanKind::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasDeliv = ! is_null($this->input('deliverable_id'));
            $hasMile = ! is_null($this->input('milestone_id'));

            // Exactly-one invariant — same rule the web form enforces.
            if ($hasDeliv === $hasMile) {
                $v->errors()->add(
                    $hasDeliv ? 'deliverable_id' : 'milestone_id',
                    $hasDeliv
                        ? 'Pick a deliverable OR a milestone, not both.'
                        : 'Pick either a deliverable or a milestone to allocate.',
                );
                return;
            }

            if ($hasDeliv) {
                // Deliverable belongs to this user.
                $owns = Deliverable::query()
                    ->where('id', $this->input('deliverable_id'))
                    ->whereHas('project', fn ($q) => $q->where('owner_id', $this->user()->id))
                    ->exists();
                if (! $owns) {
                    $v->errors()->add('deliverable_id', 'That deliverable does not exist or is not yours.');
                    return;
                }
                $exists = PlanItem::query()
                    ->where('plan_period_id', $this->input('plan_period_id'))
                    ->where('deliverable_id', $this->input('deliverable_id'))
                    ->exists();
                if ($exists) {
                    $v->errors()->add('deliverable_id', 'This deliverable is already on the plan for that period.');
                }
            } else {
                // Milestone path.
                $owns = Milestone::query()
                    ->where('id', $this->input('milestone_id'))
                    ->whereHas('project', fn ($q) => $q->where('owner_id', $this->user()->id))
                    ->exists();
                if (! $owns) {
                    $v->errors()->add('milestone_id', 'That milestone does not exist or is not yours.');
                    return;
                }
                $exists = PlanItem::query()
                    ->where('plan_period_id', $this->input('plan_period_id'))
                    ->where('milestone_id', $this->input('milestone_id'))
                    ->exists();
                if ($exists) {
                    $v->errors()->add('milestone_id', 'This milestone is already on the plan for that period.');
                }
            }
        });
    }
}
