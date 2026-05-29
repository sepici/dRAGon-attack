<?php

namespace App\Http\Requests;

use App\Models\Employer;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employer::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }

    /**
     * Force the new employer to NEVER be marked Self at creation time.
     * Self is auto-created by the User observer; users adding employers via
     * this form are always adding non-Self rows.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            $data['is_self'] = false;
        }
        return $data;
    }
}
