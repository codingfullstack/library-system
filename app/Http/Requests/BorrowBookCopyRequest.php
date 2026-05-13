<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BorrowBookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'no_due_date' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'override_reservation' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.date_format' => 'Data turi būti formato YYYY-MM-DD.',
            'due_at.after' => 'Grąžinimo data turi būti vėlesnė nei šiandien.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $noDueDate = (bool) $this->input('no_due_date', false);
            $dueAt = $this->input('due_at');

            if ($noDueDate && ! empty($dueAt)) {
                $validator->errors()->add(
                    'due_at',
                    'Negalima vienu metu nurodyti datos ir pasirinkti „be termino“.'
                );
            }
        });
    }
}








