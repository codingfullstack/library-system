<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManageBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (in_array($this->method(), ['PUT', 'PATCH'], true)) {
            return (bool) $this->user()?->isSuperAdmin();
        }

        return true;
    }

    public function rules(): array
    {
        $bookId = $this->route('book')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:255', Rule::unique('books', 'isbn')->ignore($bookId)],
            'description' => ['nullable', 'string'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'publication_year' => ['nullable', 'integer', 'digits:4', 'min:1000', 'max:' . (now()->year + 1)],
            'language' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'edition' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'author_ids' => ['nullable', 'array'],
            'author_ids.*' => ['integer', 'exists:authors,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $title = trim((string) $this->input('title'));

                if ($title === '') {
                    return;
                }

                $subtitle = trim((string) $this->input('subtitle', ''));
                $bookId = $this->route('book')?->id;
                $selectedAuthorIds = collect($this->input('author_ids', []))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                $duplicate = Book::query()
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                    ->whereRaw('LOWER(COALESCE(subtitle, "")) = ?', [mb_strtolower($subtitle)])
                    ->when($bookId, fn ($query) => $query->whereKeyNot($bookId))
                    ->with('authors:id')
                    ->get()
                    ->first(function (Book $book) use ($selectedAuthorIds): bool {
                        if ($selectedAuthorIds === []) {
                            return true;
                        }

                        $bookAuthorIds = $book->authors
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->sort()
                            ->values()
                            ->all();

                        return $bookAuthorIds === $selectedAuthorIds;
                    });

                if (! $duplicate) {
                    return;
                }

                $validator->errors()->add(
                    'title',
                    sprintf(
                        'Tokia knyga jau yra kataloge%s. Vietoje naujo įrašo redaguokite esamą.',
                        $duplicate->isbn ? ' (ISBN: ' . $duplicate->isbn . ')' : ''
                    )
                );
            },
        ];
    }
}








