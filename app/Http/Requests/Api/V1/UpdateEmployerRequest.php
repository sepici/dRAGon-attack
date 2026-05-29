<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEmployerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }

    /**
     * Self's name is fixed. Refuse rename attempts with a friendly 422 instead
     * of the model-level LogicException that would otherwise surface as 500.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $employer = $this->route('employer');
            if ($employer && $employer->is_self
                && $this->has('name')
                && $this->input('name') !== 'Self'
            ) {
                $v->errors()->add(
                    'name',
                    'The Self employer\'s name is fixed and cannot be renamed.',
                );
            }
        });
    }
}
