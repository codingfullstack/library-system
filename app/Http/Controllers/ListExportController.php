<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BookCopy;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Books\GetLibraryBooksQuery;
use App\Queries\Loans\GetActiveLibraryLoansQuery;
use App\Queries\Management\AuditLogs\GetAuditLogsQuery;
use App\Queries\Management\BookCopies\GetManageBookCopiesQuery;
use App\Queries\Management\Branches\GetManageBranchesQuery;
use App\Queries\Management\Categories\GetManageCategoriesQuery;
use App\Queries\Management\Locations\GetManageLocationsQuery;
use App\Queries\Management\Publishers\GetManagePublishersQuery;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use App\Queries\Users\GetManageUsersQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ListExportController extends Controller
{
    public function __invoke(
        string $resource,
        Request $request,
        GetLibraryBooksQuery $getLibraryBooksQuery,
        GetActiveLibraryLoansQuery $getActiveLibraryLoansQuery,
        GetLibraryReservationsQuery $getLibraryReservationsQuery,
        GetManageBookCopiesQuery $getManageBookCopiesQuery,
        GetManageUsersQuery $getManageUsersQuery,
        GetManageBranchesQuery $getManageBranchesQuery,
        GetManageCategoriesQuery $getManageCategoriesQuery,
        GetManagePublishersQuery $getManagePublishersQuery,
        GetManageLocationsQuery $getManageLocationsQuery,
        GetAuditLogsQuery $getAuditLogsQuery
    ): Response {
        $user = $request->user();

        abort_unless($user?->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas']), 403);

        return match ($resource) {
            'books' => $this->booksExport($request, $getLibraryBooksQuery),
            'loans' => $this->loansExport($request, $getActiveLibraryLoansQuery),
            'reservations' => $this->reservationsExport($request, $getLibraryReservationsQuery),
            'book-copies' => $this->bookCopiesExport($request, $getManageBookCopiesQuery),
            'users' => $this->usersExport($request, $getManageUsersQuery),
            'branches' => $this->branchesExport($request, $getManageBranchesQuery),
            'categories' => $this->categoriesExport($request, $getManageCategoriesQuery),
            'publishers' => $this->publishersExport($request, $getManagePublishersQuery),
            'locations' => $this->locationsExport($request, $getManageLocationsQuery),
            'audit-logs' => $this->auditLogsExport($request, $getAuditLogsQuery),
            default => abort(404),
        };
    }

    private function booksExport(Request $request, GetLibraryBooksQuery $query): Response
    {
        $books = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'author_id' => $request->query('author_id'),
            'publisher_id' => $request->query('publisher_id'),
            'library_id' => $request->query('library_id'),
            'availability' => $request->query('availability'),
            'sort' => $request->query('sort', 'title'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $books->map(function ($book) {
            $status = $book->available_copies_count > 0
                ? 'Aktyvi'
                : ($book->loaned_copies_count > 0 ? 'Išduota' : 'Neprieinama');

            return [
                $book->title,
                $book->subtitle,
                $book->isbn,
                $book->authors->pluck('name')->filter()->join(', '),
                $book->categories->pluck('name')->filter()->join(', '),
                $book->publisher?->name,
                $book->copies_count,
                $book->available_copies_count,
                $book->active_reservations_count,
                $status,
                $book->updated_at?->format('Y-m-d H:i'),
            ];
        })->all();

        return $this->csvResponse(
            'knygos',
            ['Pavadinimas', 'Paantraštė', 'ISBN', 'Autoriai', 'Kategorijos', 'Leidykla', 'Egzemplioriai', 'Laisvi', 'Rezervacijos', 'Būsena', 'Atnaujinta'],
            $rows
        );
    }

    private function loansExport(Request $request, GetActiveLibraryLoansQuery $query): Response
    {
        $loans = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'member_id' => $request->query('member_id'),
            'employee_id' => $request->query('employee_id'),
            'overdue' => $request->query('overdue'),
            'due_date' => $request->query('due_date'),
            'library_id' => $request->query('library_id'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $loans->map(function ($loan) {
            $dueDate = $loan->due_at;
            $daysUntilDue = $dueDate ? now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false) : null;
            $status = $loan->is_overdue
                ? 'Vėluoja'
                : (($daysUntilDue !== null && $daysUntilDue <= 2) ? 'Grąžinti netrukus' : 'Aktyvi');

            return [
                $loan->bookCopy?->book?->title,
                $loan->bookCopy?->book?->isbn,
                $loan->user?->name,
                $loan->user?->membership_number,
                $loan->bookCopy?->inventory_code,
                $loan->bookCopy?->branch?->name,
                $loan->borrowed_at?->format('Y-m-d H:i'),
                $loan->due_at?->format('Y-m-d H:i'),
                $loan->returned_at?->format('Y-m-d H:i'),
                $status,
            ];
        })->all();

        return $this->csvResponse(
            'išduotos-knygos',
            ['Knyga', 'ISBN', 'Narys', 'Nario numeris', 'Kopija', 'Filialas', 'Išduota', 'Grąžinti iki', 'Grąžinta', 'Būsena'],
            $rows
        );
    }

    private function reservationsExport(Request $request, GetLibraryReservationsQuery $query): Response
    {
        $reservations = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'queue' => $request->query('queue'),
            'library_id' => $request->query('library_id'),
            'reservation_date' => $request->query('reservation_date'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $reservations->map(fn ($reservation) => [
            $reservation->book?->title,
            $reservation->book?->isbn,
            $reservation->user?->name,
            $reservation->user?->membership_number,
            $reservation->library?->name,
            $reservation->reserved_at?->format('Y-m-d H:i'),
            $reservation->expires_at?->format('Y-m-d H:i'),
            $this->reservationStatusLabel($reservation->status),
            $reservation->isPending() ? $reservation->queue_position : null,
        ])->all();

        return $this->csvResponse(
            'rezervacijos',
            ['Knyga', 'ISBN', 'Narys', 'Nario numeris', 'Biblioteka', 'Rezervuota', 'Galioja iki', 'Būsena', 'Eilės nr.'],
            $rows
        );
    }

    private function bookCopiesExport(Request $request, GetManageBookCopiesQuery $query): Response
    {
        $copies = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'branch_id' => $request->query('branch_id'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $copies->map(function ($copy) {
            $locationLabel = $copy->location
                ? collect([$copy->location->name, $copy->location->room, $copy->location->shelf])->filter()->join(' / ')
                : null;

            return [
                $copy->book?->title,
                $copy->book?->isbn,
                $copy->inventory_code,
                $copy->barcode,
                $copy->branch?->name,
                $locationLabel,
                $copy->statusLabel(),
                ucfirst((string) $copy->condition_status),
                $copy->updated_at?->format('Y-m-d H:i'),
            ];
        })->all();

        return $this->csvResponse(
            'egzemplioriai',
            ['Knyga', 'ISBN', 'Inventoriaus kodas', 'Brūkšninis kodas', 'Filialas', 'Vieta', 'Būsena', 'Būklė', 'Atnaujinta'],
            $rows
        );
    }

    private function usersExport(Request $request, GetManageUsersQuery $query): Response
    {
        $users = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'role' => $request->query('role'),
            'aktyvi' => $request->query('aktyvi'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $users->map(fn ($user) => [
            $user->name,
            $user->membership_number,
            $user->email,
            $user->phone,
            $this->userRoleLabel($user->role),
            $user->library?->name,
            $user->is_active ? 'Aktyvus' : 'Neaktyvūs',
        ])->all();

        return $this->csvResponse(
            'vartotojai',
            ['Vardas', 'Kortelės numeris', 'El. paštas', 'Telefonas', 'Tipas', 'Biblioteka', 'Statusas'],
            $rows
        );
    }

    private function branchesExport(Request $request, GetManageBranchesQuery $query): Response
    {
        $branches = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $branches->map(fn ($branch) => [
            $branch->name,
            $branch->code,
            $branch->library?->name,
            $branch->city,
            $branch->address,
            $branch->locations_count,
            $branch->book_copies_count,
        ])->all();

        return $this->csvResponse(
            'filialai',
            ['Pavadinimas', 'Kodas', 'Biblioteka', 'Miestas', 'Adresas', 'Vietos', 'Egzemplioriai'],
            $rows
        );
    }

    private function categoriesExport(Request $request, GetManageCategoriesQuery $query): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $categories = $query->handle([
            'search' => $request->query('search'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $categories->map(fn ($category) => [
            $category->name,
            $category->slug,
            $category->description,
            $category->books_count,
            $category->books_count > 0 ? 'Aktyvi' : 'Tuščia',
        ])->all();

        return $this->csvResponse(
            'kategorijos',
            ['Pavadinimas', 'Slug', 'Aprašas', 'Knygų skaičius', 'Būsena'],
            $rows
        );
    }

    private function publishersExport(Request $request, GetManagePublishersQuery $query): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $publishers = $query->handle([
            'search' => $request->query('search'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $publishers->map(fn ($publisher) => [
            $publisher->name,
            $publisher->country,
            $publisher->books_count,
            $publisher->books_count > 0 ? 'Aktyvi' : 'Be knygų',
        ])->all();

        return $this->csvResponse(
            'leidyklos',
            ['Pavadinimas', 'Šalis', 'Knygų skaičius', 'Būsena'],
            $rows
        );
    }

    private function locationsExport(Request $request, GetManageLocationsQuery $query): Response
    {
        $locations = $query->handle($request->user(), [
            'search' => $request->query('search'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $locations->map(fn ($location) => [
            $location->name,
            $location->code,
            $location->branch?->name,
            $location->library?->name,
            $location->room,
            $location->shelf,
            $location->book_copies_count,
        ])->all();

        return $this->csvResponse(
            'vietos',
            ['Pavadinimas', 'Kodas', 'Filialas', 'Biblioteka', 'Kambarys', 'Lentyna', 'Egzemplioriai'],
            $rows
        );
    }

    private function auditLogsExport(Request $request, GetAuditLogsQuery $query): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $auditLogs = $query->handle([
            'search' => trim((string) $request->query('search', '')),
            'action' => $request->query('action'),
            'library_id' => $request->query('library_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'per_page' => 5000,
        ])->getCollection();

        $rows = $auditLogs->map(fn ($auditLog) => [
            $auditLog->created_at?->format('Y-m-d H:i:s'),
            $auditLog->actionLabel(),
            $auditLog->description,
            $auditLog->actor?->name,
            $auditLog->actor?->email,
            $auditLog->library?->name,
            $auditLog->auditable_type,
            $auditLog->auditable_id,
        ])->all();

        return $this->csvResponse(
            'audito-zurnalas',
            ['Data', 'Veiksmas', 'Aprašas', 'Atliko', 'El. paštas', 'Biblioteka', 'Objekto tipas', 'Objekto ID'],
            $rows
        );
    }

    private function reservationStatusLabel(string $status): string
    {
        return match ($status) {
            Reservation::STATUS_RESERVED => 'Aktyvi',
            Reservation::STATUS_FULFILLED => 'Įvykdyta',
            Reservation::STATUS_CANCELLED => 'Atšaukta',
            Reservation::STATUS_EXPIRED => 'Pasibaigusi',
            default => $status,
        };
    }

    private function userRoleLabel(string $role): string
    {
        return match ($role) {
            User::ROLE_SUPER_ADMIN => 'Superadministratorius',
            User::ROLE_ADMIN => 'Administratorius',
            User::ROLE_STAFF => 'Darbuotojas',
            User::ROLE_MEMBER => 'Skaitytojas',
            default => $role,
        };
    }

    private function csvResponse(string $baseFilename, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        $filename = sprintf('%s-%s.csv', $baseFilename, now()->format('Y-m-d-His'));

        return response("\xEF\xBB\xBF" . $content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}










