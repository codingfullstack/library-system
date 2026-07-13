<?php

namespace App\Support\Reports;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardReportExport
{
    /**
     * @return array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, scalar|null>>}>
     */
    public function sections(array $report): array
    {
        return [
            [
                'title' => 'Suvestinė',
                'headers' => ['Rodiklis', 'Reikšmė'],
                'rows' => [
                    ['Knygų kopijos', $report['summary']['book_copies_count']],
                    ['Laisvi kopijos', $report['summary']['available_book_copies_count']],
                    ['Aktyviai išduotos knygos', $report['summary']['active_loans_count']],
                    ['Grąžintos išduotos knygos', $report['summary']['returned_loans_count']],
                    ['Aktyvios rezervacijos', $report['summary']['active_reservations_count']],
                    ['Įvykdytos rezervacijos', $report['summary']['fulfilled_reservations_count']],
                    ['Vėluojančios išduotos knygos', $report['summary']['overdue_loans_count']],
                    ['Prarasti kopijos', $report['summary']['lost_book_copies_count']],
                    ['Sugadinti kopijos', $report['summary']['damaged_book_copies_count']],
                    ['Tvarkomi kopijos', $report['summary']['maintenance_book_copies_count']],
                    ['Nurašyti kopijos', $report['summary']['withdrawn_book_copies_count']],
                    ['Aktyvūs nariai', $report['summary']['active_members_count']],
                ],
            ],
            [
                'title' => 'Bibliotekų palyginimas',
                'headers' => ['Biblioteka', 'Egz.', 'Laisvi', 'Aktyviai išduotos', 'Visi išdavimai', 'Aktyvios rezervacijos', 'Visos rezervacijos', 'Aktyvūs nariai', 'Vėluojančios', 'Prarasti', 'Sugadinti', 'Tvarkomi', 'Nurašyti'],
                'rows' => collect($report['libraryComparison'])->map(fn ($library) => [
                    $library->name,
                    $library->book_copies_count,
                    $library->available_book_copies_count,
                    $library->active_loans_count,
                    $library->loans_count,
                    $library->active_reservations_count,
                    $library->reservations_count,
                    $library->active_members_count,
                    $library->overdue_loans_count,
                    $library->lost_book_copies_count,
                    $library->damaged_book_copies_count,
                    $library->maintenance_book_copies_count,
                    $library->withdrawn_book_copies_count,
                ])->all(),
            ],
            [
                'title' => 'Veiklos laiko juosta',
                'headers' => ['Periodas', 'Išduota', 'Grąžinta', 'Rezervuota'],
                'rows' => collect($report['activityTimeline'])->map(fn ($row) => [
                    $row->label,
                    $row->issued_loans_count,
                    $row->returned_loans_count,
                    $row->reservations_count,
                ])->all(),
            ],
            [
                'title' => 'Populiariausios knygos',
                'headers' => ['Knyga', 'ISBN', 'Išdavimai', 'Rezervacijos'],
                'rows' => collect($report['popularBooks'])->map(fn ($book) => [
                    $book->title,
                    $book->isbn,
                    $book->loans_count,
                    $book->reservations_count,
                ])->all(),
            ],
            [
                'title' => 'Top autoriai',
                'headers' => ['Autorius', 'Knygų', 'Išdavimai', 'Rezervacijos'],
                'rows' => collect($report['popularAuthors'])->map(fn ($author) => [
                    $author->name,
                    $author->books_count,
                    $author->loans_count,
                    $author->reservations_count,
                ])->all(),
            ],
            [
                'title' => 'Top kategorijos',
                'headers' => ['Kategorija', 'Knygų', 'Išdavimai', 'Rezervacijos'],
                'rows' => collect($report['popularCategories'])->map(fn ($category) => [
                    $category->name,
                    $category->books_count,
                    $category->loans_count,
                    $category->reservations_count,
                ])->all(),
            ],
            [
                'title' => 'Top leidyklos',
                'headers' => ['Leidykla', 'Knygų', 'Išdavimai', 'Rezervacijos'],
                'rows' => collect($report['popularPublishers'])->map(fn ($publisher) => [
                    $publisher->name,
                    $publisher->books_count,
                    $publisher->loans_count,
                    $publisher->reservations_count,
                ])->all(),
            ],
            [
                'title' => 'Top kopijos',
                'headers' => ['Inventoriaus kodas', 'Knyga', 'Biblioteka', 'Filialas', 'Būsena', 'Išdavimai'],
                'rows' => collect($report['popularBookCopies'])->map(fn ($copy) => [
                    $copy->inventory_code,
                    $copy->book?->title,
                    $copy->library?->name,
                    $copy->branch?->name,
                    $copy->statusLabel(),
                    $copy->loans_count,
                ])->all(),
            ],
            [
                'title' => 'Aktyviausi nariai',
                'headers' => ['Narys', 'Nario numeris', 'Biblioteka', 'Aktyvumo taškai', 'Išduotos knygos', 'Rezervacijos'],
                'rows' => collect($report['activeMembers'])->map(fn ($member) => [
                    $member->name,
                    $member->membership_number,
                    $member->library?->name,
                    $member->activity_points,
                    $member->loans_count,
                    $member->reservations_count,
                ])->all(),
            ],
            [
                'title' => 'Kopijų būsenos',
                'headers' => ['Būsena', 'Kiekis'],
                'rows' => collect($report['copiesByStatus'])->map(fn ($status) => [
                    $status->label,
                    $status->count,
                ])->all(),
            ],
            [
                'title' => 'Kopijos pagal filialus',
                'headers' => ['Biblioteka', 'Filialas', 'Egz.', 'Laisvi', 'Išduoti', 'Aktyvios rezervacijos', 'Prarasti', 'Sugadinti', 'Tvarkomi', 'Nurašyti'],
                'rows' => collect($report['copiesByBranch'])->map(fn ($branch) => [
                    $branch->library?->name,
                    $branch->name,
                    $branch->book_copies_count,
                    $branch->available_book_copies_count,
                    $branch->loaned_book_copies_count,
                    $branch->active_reservations_count,
                    $branch->lost_book_copies_count,
                    $branch->damaged_book_copies_count,
                    $branch->maintenance_book_copies_count,
                    $branch->withdrawn_book_copies_count,
                ])->all(),
            ],
        ];
    }

    public function filename(User $user, array $filters, string $format): string
    {
        $scope = $user->isSuperAdmin()
            ? 'visos-bibliotekos'
            : Str::slug((string) ($user->library?->code ?: $user->library?->name ?: 'biblioteka'));

        $period = Str::slug((string) Arr::get($filters, 'period_label', 'visas-laikotarpis'));

        return sprintf('ataskaita-%s-%s.%s', $scope, $period, $format);
    }

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, scalar|null>>}>  $sections
     */
    public function csvResponse(array $sections, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($sections as $index => $section) {
            fputcsv($handle, [$section['title']]);
            fputcsv($handle, $section['headers']);

            foreach ($section['rows'] as $row) {
                fputcsv($handle, $row);
            }

            if ($index !== array_key_last($sections)) {
                fputcsv($handle, []);
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, scalar|null>>}>  $sections
     */
    public function xlsResponse(array $sections, string $filename, array $report): Response
    {
        return response()
            ->view('exports.dashboard-report-excel', [
                'sections' => $sections,
                'report' => $report,
            ])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}








