<?php

namespace App\Support;

use App\Models\BookCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuditLogChanges
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<string, string>  $fieldLabels
     * @param  array<string, callable|array<string|int, string>>  $valueMaps
     * @return array<string, mixed>
     */
    public static function fromModel(Model $model, array $fields, array $fieldLabels = [], array $valueMaps = []): array
    {
        $changes = collect($fields)
            ->unique()
            ->values()
            ->map(function (string $field) use ($model, $fieldLabels, $valueMaps) {
                $from = $model->getOriginal($field);
                $to = $model->getAttribute($field);

                return [
                    'field' => $field,
                    'label' => $fieldLabels[$field] ?? self::fieldLabel($field),
                    'from' => self::formatValue($field, $from, $valueMaps[$field] ?? null),
                    'to' => self::formatValue($field, $to, $valueMaps[$field] ?? null),
                ];
            })
            ->all();

        return [
            'changed_fields' => array_values(array_unique($fields)),
            'changes' => $changes,
        ];
    }

    public static function fieldLabel(string $field): string
    {
        return [
            'title' => 'Pavadinimas',
            'subtitle' => 'Paantraštė',
            'isbn' => 'ISBN',
            'description' => 'Aprašymas',
            'publisher_id' => 'Leidykla',
            'category_id' => 'Pagrindinė kategorija',
            'category_ids' => 'Kategorijos',
            'author_ids' => 'Autoriai',
            'publication_year' => 'Leidimo metai',
            'language' => 'Kalba',
            'page_count' => 'Puslapių skaičius',
            'edition' => 'Leidimas',
            'cover_image' => 'Viršelio nuoroda',
            'name' => 'Pavadinimas',
            'slug' => 'Slug',
            'country' => 'Šalis',
            'address' => 'Adresas',
            'city' => 'Miestas',
            'code' => 'Kodas',
            'room' => 'Patalpa',
            'shelf' => 'Lentyna',
            'library_id' => 'Biblioteka',
            'library' => 'Biblioteka',
            'branch_id' => 'Filialas',
            'branch' => 'Filialas',
            'location_id' => 'Vieta',
            'location' => 'Vieta',
            'book_id' => 'Knyga',
            'publisher' => 'Leidykla',
            'authors' => 'Autoriai',
            'inventory_code' => 'Inventoriaus kodas',
            'barcode' => 'Brūkšninis kodas',
            'status' => 'Statusas',
            'lifecycle_status' => 'Gyvavimo ciklas',
            'condition_status' => 'Būklė',
            'acquired_at' => 'Įsigijimo data',
            'notes' => 'Pastabos',
            'email' => 'El. paštas',
            'role' => 'Rolė',
            'phone' => 'Telefonas',
            'is_active' => 'Aktyvumas',
            'is_public' => 'Vieša biblioteka',
            'membership_number' => 'Nario numeris',
        ][$field] ?? str($field)->replace('_', ' ')->title()->value();
    }

    /**
     * @param  callable|array<string|int, string>|null  $valueMap
     */
    public static function formatValue(string $field, mixed $value, callable|array|null $valueMap = null): string
    {
        if ($valueMap instanceof \Closure || is_callable($valueMap)) {
            return (string) $valueMap($value);
        }

        if (is_array($valueMap) && array_key_exists((string) $value, $valueMap)) {
            return (string) $valueMap[(string) $value];
        }

        return match ($field) {
            'status', 'lifecycle_status' => BookCopy::statusLabels()[(string) $value] ?? self::stringify($value),
            'is_active' => $value ? 'Aktyvus' : 'Neaktyvus',
            'is_public' => $value ? 'Vieša' : 'Nevieša',
            'role' => [
                'superadministratorius' => 'Superadmin',
                'administratorius' => 'Admin',
                'darbuotojas' => 'Darbuotojas',
                'narys' => 'Narys',
            ][(string) $value] ?? self::stringify($value),
            default => self::stringify($value),
        };
    }

    public static function stringify(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Taip' : 'Ne';
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof Collection) {
            return $value->map(fn ($item) => self::stringify($item))->join(', ');
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($item) => self::stringify($item))->join(', ');
        }

        return (string) $value;
    }
}









