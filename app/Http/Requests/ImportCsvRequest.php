<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $resource = (string) $this->route('resource');

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'library_id' => [
                \Illuminate\Validation\Rule::requiredIf(
                    fn () => $this->user()?->isSuperAdmin() && in_array($resource, ['branches', 'locations'], true)
                ),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
        ];
    }
}








