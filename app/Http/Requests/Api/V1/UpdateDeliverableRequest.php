<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Moscow;
use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'sometimes',
                Rule::exists('projects', 'id')->where('owner_id', $this->user()->id),
            ],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'target_hours' => ['sometimes', 'numeric', 'min:0', 'max:2000', 'multiple_of:0.5'],
            'deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'moscow' => ['sometimes', 'nullable', Rule::enum(Moscow::class)],
            'completed_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
