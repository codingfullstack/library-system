<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $authorId = $this->route('author')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('authors', 'name')->ignore($authorId)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('authors', 'slug')->ignore($authorId)],
            'bio' => ['nullable', 'string'],
        ];
    }
}








