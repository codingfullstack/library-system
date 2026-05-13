<?php

namespace App\Http\Requests;

use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageBookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $bookCopy = $this->route('bookCopy') ?? $this->route('book_copy');

        if ($bookCopy instanceof BookCopy) {
            return $user->can('update', $bookCopy);
        }

        return $user->can('create', BookCopy::class);
    }

    public function rules(): array
    {
        $libraryId = $this->user()?->isSuperAdmin()
            ? $this->input('library_id')
            : $this->user()?->activeLibraryId();
        $bookCopyId = $this->currentBookCopyId();

        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
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
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('library_id', $libraryId)),
            ],
            'inventory_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('book_copies', 'inventory_code')
                    ->where(fn ($query) => $query->where('library_id', $libraryId))
                    ->ignore($bookCopyId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('book_copies', 'barcode')
                    ->where(fn ($query) => $query->where('library_id', $libraryId))
                    ->ignore($bookCopyId),
            ],
            'status' => ['required', Rule::in(['laisva', 'išduota', 'prarasta', 'sugadinta', 'tvarkoma', 'nurašyta'])],
            'condition_status' => ['required', Rule::in(['nauja', 'gera', 'padėvėta', 'sugadinta'])],
            'acquired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentBookCopyId(): int|string|null
    {
        $bookCopy = $this->route('book_copy') ?? $this->route('bookCopy');

        if (! $bookCopy) {
            return null;
        }

        return is_object($bookCopy) ? $bookCopy->getKey() : $bookCopy;
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $branchId = $this->input('branch_id');
                $locationId = $this->input('location_id');

                if (! $branchId || ! $locationId) {
                    return;
                }

                $branch = Branch::query()->find($branchId);
                $location = Location::query()->find($locationId);

                if (! $branch || ! $location || $location->branch_id !== $branch->id) {
                    $validator->errors()->add('location_id', 'Pasirinkta vieta nepriklauso šiam filialui.');
                }
            },
        ];
    }
}








