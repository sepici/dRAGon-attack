<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Moscow;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The API speaks hours, not days — the web FormRequests convert at the
 * edge because humans think in days; agents already know the unit.
 */
class StoreDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where('owner_id', $this->user()->id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target_hours' => ['required', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(Status::class)],
            'moscow' => ['nullable', Rule::enum(Moscow::class)],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && ! isset($data['status'])) {
            $data['status'] = Status::Red->value;
        }
        return $data;
    }
}
