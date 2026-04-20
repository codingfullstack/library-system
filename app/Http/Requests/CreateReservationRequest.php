<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (in_array($this->user()?->role, ['admin', 'staff'], true)) {
            $rules['user_id'] = ['required', 'integer', 'exists:users,id'];
            $rules['expires_at'] = ['nullable', 'date', 'after:now'];
        }

        return $rules;
    }
}