<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagePublisherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $publisherId = $this->route('publisher')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('publishers', 'name')->ignore($publisherId)],
            'country' => ['nullable', 'string', 'max:255'],
        ];
    }
}








