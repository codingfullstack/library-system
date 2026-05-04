<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id;
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
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query->where('library_id', $libraryId))
                    ->ignore($branchId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
