<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employer'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    /**
     * Self's name is fixed at "Self". A rename attempt is rejected here so the
     * user gets a friendly form error instead of the model-level LogicException.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $employer = $this->route('employer');
            if ($employer && $employer->is_self && $this->input('name') !== 'Self') {
                $v->errors()->add(
                    'name',
                    'The Self employer\'s name is fixed and cannot be renamed.',
                );
            }
        });
    }
}
