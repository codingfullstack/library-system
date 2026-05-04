<?php

namespace App\Http\Requests;

use App\Models\BookCopy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageBookCopyLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bookCopy = $this->route('bookCopy');

        return $this->user() !== null
            && $bookCopy instanceof BookCopy
            && $this->user()->can('update', $bookCopy);
    }

    public function rules(): array
    {
        $bookCopy = $this->route('bookCopy');
        $allowedStatuses = $bookCopy instanceof BookCopy
            ? $bookCopy->availableLifecycleTransitions()
            : [];

        return [
            'target_status' => ['required', Rule::in($allowedStatuses)],
            'reason_notes' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'target_status' => 'tikslinis statusas',
            'reason_notes' => 'priezastis',
        ];
    }
}
