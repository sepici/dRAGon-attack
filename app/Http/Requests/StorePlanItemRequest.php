<?php

namespace App\Http\Requests;

use App\Models\Deliverable;
use App\Models\Milestone;
use App\Models\PlanItem;
use App\Models\PlanPeriod;
use App\Support\TimeUnits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the "add an allocation to a plan" form.
 *
 * A plan item targets EITHER a deliverable OR a milestone — exactly one
 * (see App\Models\PlanItem). The form uses a radio toggle so the user
 * picks a kind first; the unused id arrives blank and the
 * ConvertEmptyStringsToNull middleware folds it to null before we see it.
 * We enforce the exactly-one rule here, in addition to the saving guard on
 * the model.
 */
class StorePlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isUser();
    }

    protected function prepareForValidation(): void
    {
        // Days → hours. filled() correctly excludes both '' and null, so the
        // ConvertEmptyStringsToNull middleware doesn't accidentally coerce
        // blanks into 0 hours (see M12b for the same fix in milestone forms).
        if ($this->filled('allocated_days')) {
            $this->merge([
                'allocated_hours' => TimeUnits::hoursFromDays((float) $this->input('allocated_days')),
            ]);
        }

        // Normalise blank ids to null so the exactly-one check + exists rules
        // both behave consistently regardless of how the form submits empty.
        foreach (['deliverable_id', 'milestone_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === null) {
                $this->merge([$key => null]);
            }
        }
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            // The plan period must exist AND be owned by the current user.
            'plan_period_id' => [
                'required',
                Rule::exists('plan_periods', 'id')->where('owner_id', $user->id),
            ],

            // Each id is nullable on its own — the joint "exactly one" check
            // lives in withValidator() below, because rules attached to a
            // nullable attribute don't run when the value is null (so the
            // closure can't enforce the both-null case).
            'deliverable_id' => [
                'nullable',
                'integer',
                'exists:deliverables,id',
                function ($attribute, $value, $fail) use ($user) {
                    if (is_null($value)) {
                        return;
                    }
                    $deliverable = Deliverable::with('project')->find($value);
                    if (! $deliverable || $deliverable->project->owner_id !== $user->id) {
                        $fail('That deliverable is not yours.');
                        return;
                    }
                    $periodId = $this->input('plan_period_id');
                    if ($periodId && PlanItem::where('plan_period_id', $periodId)
                        ->where('deliverable_id', $value)
                        ->exists()) {
                        $fail('This deliverable is already on the plan for this period.');
                    }
                },
            ],

            'milestone_id' => [
                'nullable',
                'integer',
                'exists:milestones,id',
                function ($attribute, $value, $fail) use ($user) {
                    if (is_null($value)) {
                        return;
                    }
                    $milestone = Milestone::with('project')->find($value);
                    if (! $milestone || $milestone->project->owner_id !== $user->id) {
                        $fail('That milestone is not yours.');
                        return;
                    }
                    $periodId = $this->input('plan_period_id');
                    if ($periodId && PlanItem::where('plan_period_id', $periodId)
                        ->where('milestone_id', $value)
                        ->exists()) {
                        $fail('This milestone is already on the plan for this period.');
                    }
                },
            ],

            'allocated_days' => ['required', 'numeric', 'min:0', 'max:250', 'multiple_of:0.5'],
            'allocated_hours' => ['required', 'numeric', 'min:0', 'max:2000'],
        ];
    }

    /**
     * Enforce the exactly-one-of-deliverable_or_milestone rule. Attached as
     * an `after` hook so it always runs — attaching it to a nullable field's
     * rule list wouldn't fire when both ids are null.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasDeliv = ! is_null($this->input('deliverable_id'));
            $hasMile = ! is_null($this->input('milestone_id'));
            if ($hasDeliv === $hasMile) {
                $validator->errors()->add('deliverable_id', $hasDeliv
                    ? 'Pick a deliverable OR a milestone, not both.'
                    : 'Pick either a deliverable or a milestone to allocate.');
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            unset($data['allocated_days']);
        }
        return $data;
    }
}
