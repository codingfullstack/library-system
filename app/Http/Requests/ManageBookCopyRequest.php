<?php

namespace App\Http\Requests;

use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Location;
use App\Services\BookCopyBranchTransferService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $libraryId = $this->libraryId();
        $bookCopyId = $this->currentBookCopyId();

        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'library_id' => [
                Rule::requiredIf(fn () => $this->user()?->isSuperAdmin() && ! $this->currentBookCopy()),
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
            'status' => [
                Rule::requiredIf(fn () => ! $this->currentBookCopy()),
                'nullable',
                Rule::in(array_keys(BookCopy::statusLabels())),
            ],
            'condition_status' => ['required', Rule::in($this->allowedConditionValuesForGeneralForm())],
            'acquired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'condition_status.in' => BookCopy::damagedConditionGeneralEditMessage(),
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $libraryId = $this->libraryId();
                $bookCopy = $this->currentBookCopy();

                try {
                    app(BookCopyBranchTransferService::class)->resolveBranchId(
                        $this->user(),
                        $libraryId,
                        $this->input('branch_id'),
                        $bookCopy
                    );
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }

                $branchId = $this->input('branch_id');
                $locationId = $this->input('location_id');

                if (! $branchId || ! $locationId) {
                    return;
                }

                $branch = Branch::query()->find($branchId);
                $location = Location::query()->find($locationId);

                if (
                    ! $branch
                    || ! $location
                    || (int) $location->branch_id !== (int) $branch->id
                    || (int) $location->library_id !== (int) $libraryId
                ) {
                    $validator->errors()->add('location_id', 'Pasirinkta vieta nepriklauso šiam filialui.');
                }
            },
        ];
    }

    private function currentBookCopy(): ?BookCopy
    {
        $bookCopy = $this->route('book_copy') ?? $this->route('bookCopy');

        return $bookCopy instanceof BookCopy ? $bookCopy : null;
    }

    private function currentBookCopyId(): int|string|null
    {
        $bookCopy = $this->currentBookCopy();

        return $bookCopy?->getKey();
    }

    /**
     * @return list<string>
     */
    private function allowedConditionValuesForGeneralForm(): array
    {
        return BookCopy::generalEditableConditionValues();
    }

    private function libraryId(): int
    {
        $bookCopy = $this->currentBookCopy();
        $transferService = app(BookCopyBranchTransferService::class);

        return $bookCopy
            ? $transferService->libraryIdForUpdate($this->user(), $bookCopy, $this->input('library_id'))
            : $transferService->libraryIdForCreate($this->user(), $this->input('library_id'));
    }
}
