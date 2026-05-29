<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUser() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    /** Force is_self=false. Self is auto-created on user creation. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            $data['is_self'] = false;
        }
        return $data;
    }
}
