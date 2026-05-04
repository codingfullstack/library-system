<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locationId = $this->route('location')?->id;
        $libraryId = $this->user()?->isSuperAdmin()
            ? $this->input('library_id')
            : $this->user()?->library_id;

        return [
            'library_id' => [
                Rule::requiredIf(fn () => $this->user()?->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('library_id', $libraryId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('locations', 'code')
                    ->where(fn ($query) => $query->where('branch_id', $this->input('branch_id')))
                    ->ignore($locationId),
            ],
            'room' => ['nullable', 'string', 'max:255'],
            'shelf' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
