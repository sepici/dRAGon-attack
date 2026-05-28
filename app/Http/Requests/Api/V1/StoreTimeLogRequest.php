<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Deliverable;
use App\Support\DateInput;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Agent-friendly time-log creation.
 *
 * Accepts EITHER:
 *   - `deliverable_id`        an integer id, OR
 *   - `deliverable_name`      a substring; we LIKE-match against the user's
 *                              deliverables and pick the first hit, OR
 *   - `ad_hoc_name`           free-text for unplanned work.
 *
 * Exactly one of those three must be present. After `prepareForValidation`
 * the canonical fields are `deliverable_id` (resolved) or `ad_hoc_name`.
 *
 *   {
 *     "hours": 2.5,
 *     "deliverable_name": "acme oauth",  // OR deliverable_id, OR ad_hoc_name
 *     "date": "today",                       // optional, defaults to today
 *     "notes": "OAuth wiring"                // optional
 *   }
 *
 * Date input accepts "today", "yesterday", ISO, or anything Carbon parses.
 */
class StoreTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    /**
     * Resolve fuzzy inputs to canonical fields before rules run, so the
     * rest of the validation can be straightforward.
     */
    protected function prepareForValidation(): void
    {
        $merged = [];

        // Resolve deliverable_name → deliverable_id when only the name is given.
        if (! $this->filled('deliverable_id') && $this->filled('deliverable_name')) {
            $name = trim((string) $this->input('deliverable_name'));
            $resolved = Deliverable::query()
                ->whereHas('project', fn ($q) => $q->where('owner_id', $this->user()->id))
                ->where('name', 'like', '%' . $name . '%')
                ->orderBy('name')
                ->first();
            if ($resolved) {
                $merged['deliverable_id'] = $resolved->id;
                $merged['resolved_from_name'] = $name; // debug breadcrumb
            }
        }

        // Default date to today.
        if (! $this->filled('date')) {
            $merged['date'] = CarbonImmutable::now()->toDateString();
        } else {
            $parsed = DateInput::parse((string) $this->input('date'));
            if ($parsed) {
                $merged['date'] = $parsed->toDateString();
            }
        }

        if ($merged) {
            $this->merge($merged);
        }
    }

    public function rules(): array
    {
        return [
            'hours' => ['required', 'numeric', 'min:0', 'max:24', 'multiple_of:0.5'],
            'date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string'],

            'deliverable_id' => ['nullable', 'integer'],
            'ad_hoc_name' => ['nullable', 'string', 'max:200'],
            // Kept for diagnostics — the name was already resolved above.
            'deliverable_name' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasDeliverable = $this->filled('deliverable_id');
            $hasAdHoc = $this->filled('ad_hoc_name');
            $triedNameButFailed = $this->filled('deliverable_name') && ! $hasDeliverable;

            // Exactly one of "linked to deliverable" or "ad-hoc" must be set.
            if (! $hasDeliverable && ! $hasAdHoc) {
                if ($triedNameButFailed) {
                    $candidates = Deliverable::query()
                        ->whereHas('project', fn ($q) => $q->where('owner_id', $this->user()->id))
                        ->orderBy('name')
                        ->limit(5)
                        ->pluck('name', 'id')
                        ->all();
                    $v->errors()->add(
                        'deliverable_name',
                        'No deliverable matched "' . $this->input('deliverable_name') . '". '
                        . 'Try one of: '
                        . json_encode($candidates, JSON_UNESCAPED_UNICODE)
                        . ' — or pass deliverable_id / ad_hoc_name explicitly.',
                    );
                } else {
                    $v->errors()->add('deliverable_id', 'Provide deliverable_id, deliverable_name, or ad_hoc_name.');
                }
                return;
            }

            if ($hasDeliverable && $hasAdHoc) {
                $v->errors()->add('ad_hoc_name', 'Pass either deliverable_id/name OR ad_hoc_name, not both.');
                return;
            }

            // Ownership check on the resolved deliverable_id.
            if ($hasDeliverable) {
                $owned = Deliverable::query()
                    ->where('id', $this->input('deliverable_id'))
                    ->whereHas('project', fn ($q) => $q->where('owner_id', $this->user()->id))
                    ->exists();
                if (! $owned) {
                    $v->errors()->add('deliverable_id', 'That deliverable does not exist or is not yours.');
                }
            }
        });
    }
}
