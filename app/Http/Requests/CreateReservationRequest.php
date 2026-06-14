<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'scope' => ['nullable', Rule::in([Reservation::SCOPE_BRANCH, Reservation::SCOPE_LIBRARY])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas'])) {
            $rules['user_id'] = ['required', 'integer', 'exists:users,id'];
            $rules['expires_at'] = ['nullable', 'date', 'after:now'];
        }

        return $rules;
    }
}








