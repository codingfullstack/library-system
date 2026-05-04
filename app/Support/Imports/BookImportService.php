<?php

namespace App\Support\Imports;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookImportService
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{created: int, updated: int, skipped: int, details: array<int, array<string, string|int|null>>}
     */
    public function import(User $user, array $rows): array
    {
        abort_unless($user->isSuperAdmin() || $user->isAdmin() || $user->isStaff(), 403);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $details = [];

        DB::transaction(function () use ($user, $rows, &$created, &$updated, &$skipped, &$details): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $title = trim((string) ($row['title'] ?? ''));

                if ($title === '') {
                    throw new \RuntimeException('Eilute ' . $line . ': privalomas laukelis title.');
                }

                $isbn = trim((string) ($row['isbn'] ?? '')) ?: null;

                $book = $isbn
                    ? Book::query()->where('isbn', $isbn)->first()
                    : Book::query()->where('title', $title)->first();

                if ($book) {
                    $skipped++;
                    $details[] = [
                        'line' => $line,
                        'status' => 'praleista',
                        'label' => $title,
                        'message' => $isbn
                            ? 'Knyga jau yra kataloge pagal ISBN.'
                            : 'Knyga jau yra kataloge pagal pavadinima.',
                    ];
                    continue;
                }

                $book = new Book();

                $publisher = $this->resolvePublisher(
                    $row['publisher_name'] ?? $row['publisher'] ?? null,
                    $row['publisher_country'] ?? null
                );
                $categories = collect($this->resolveCategories(
                    $row['category_slugs'] ?? null,
                    $row['category_names'] ?? $row['categories'] ?? null,
                    $line
                ));
                $authors = collect($this->resolveAuthors(
                    $row['author_slugs'] ?? null,
                    $row['author_names'] ?? $row['authors'] ?? null,
                    $line
                ));

                $book->fill([
                    'title' => $title,
                    'subtitle' => $row['subtitle'] ?? null,
                    'isbn' => $isbn,
                    'description' => $row['description'] ?? null,
                    'publisher_id' => $publisher?->id,
                    'category_id' => $categories->first()?->id,
                    'publication_year' => $this->nullableInt($row['publication_year'] ?? null),
                    'language' => $row['language'] ?? null,
                    'page_count' => $this->nullableInt($row['page_count'] ?? null),
                    'edition' => $row['edition'] ?? null,
                    'cover_image' => $row['cover_image'] ?? null,
                ]);

                $book->save();
                $book->authors()->sync($authors->pluck('id')->all());
                $book->categories()->sync($categories->pluck('id')->all());

                $created++;
                $details[] = [
                    'line' => $line,
                    'status' => 'sukurta',
                    'label' => $book->title,
                    'message' => $isbn ? 'Sukurta nauja knyga (' . $isbn . ').' : 'Sukurta nauja knyga.',
                ];
            }

            $this->recordAuditLogAction->handle(
                $user,
                'books_imported',
                null,
                sprintf('Importuotos knygos: sukurta %d, praleista %d.', $created, $skipped),
                [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'rows' => count($rows),
                ]
            );
        });

        return compact('created', 'updated', 'skipped', 'details');
    }

    private function resolvePublisher(?string $name, ?string $country): ?Publisher
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $publisher = Publisher::query()->firstOrCreate(
            ['name' => $name],
            ['country' => $country ?: null]
        );

        if (($country = trim((string) $country)) !== '' && blank($publisher->country)) {
            $publisher->update(['country' => $country]);
        }

        return $publisher;
    }

    /**
     * @return array<int, Category>
     */
    private function resolveCategories(?string $slugValue, ?string $legacyNameValue, int $line): array
    {
        $slugs = $this->splitPipeList($slugValue);

        if ($slugs !== []) {
            return collect($slugs)
                ->map(function (string $slug): Category {
                    return Category::query()->firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $this->humanizeSlug($slug)]
                    );
                })
                ->values()
                ->all();
        }

        return collect($this->splitPipeList($legacyNameValue))
            ->map(function (string $name): Category {
                return Category::query()->firstOrCreate(
                    ['name' => $name],
                    ['slug' => $this->uniqueCategorySlug($name)]
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, Author>
     */
    private function resolveAuthors(?string $slugValue, ?string $legacyNameValue, int $line): array
    {
        $slugs = $this->splitPipeList($slugValue);

        if ($slugs !== []) {
            return collect($slugs)
                ->map(function (string $slug): Author {
                    return Author::query()->firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $this->humanizeSlug($slug)]
                    );
                })
                ->values()
                ->all();
        }

        return collect($this->splitPipeList($legacyNameValue))
            ->map(function (string $name) use ($line): Author {
                $author = Author::query()->where('name', $name)->first();

                if (! $author) {
                    throw new \RuntimeException('Eilute ' . $line . ': autorius "' . $name . '" nerastas. Naudokite author_slugs.');
                }

                return $author;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function splitPipeList(?string $value): array
    {
        return collect(explode('|', (string) $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'kategorija';
        $suffix = 1;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = sprintf('%s-%d', $base !== '' ? $base : 'kategorija', $suffix++);
        }

        return $slug;
    }

    private function nullableInt(?string $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }

    private function humanizeSlug(string $slug): string
    {
        $normalized = str_replace(['-', '_'], ' ', trim($slug));

        return Str::of($normalized)
            ->lower()
            ->title()
            ->value();
    }
}
