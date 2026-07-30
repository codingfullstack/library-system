<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Http\Requests\CancelReservationRequest;
use App\Http\Requests\CreateReservationRequest;
use App\Models\Reservation;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use App\Queries\Reservations\GetMemberReservationsQuery;
use App\Queries\Reservations\GetReservationIndexFiltersDataQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationController extends Controller
{
    public function index(
        Request $request,
        GetMemberReservationsQuery $getMemberReservationsQuery,
        GetLibraryReservationsQuery $getLibraryReservationsQuery,
        GetReservationIndexFiltersDataQuery $getReservationIndexFiltersDataQuery
    ): View {
        $user = $request->user();
        $libraryId = $user->activeLibraryId();
        $effectiveRole = $user->effectiveRole($libraryId);

        return match ($effectiveRole) {
            'narys' => $this->memberIndex($request, $getMemberReservationsQuery),
            'darbuotojas', 'administratorius', 'superadministratorius' => $this->staffIndex(
                $request,
                $getLibraryReservationsQuery,
                $getReservationIndexFiltersDataQuery
            ),
            default => throw new HttpException(403),
        };
    }

    private function memberIndex(Request $request, GetMemberReservationsQuery $getMemberReservationsQuery): View
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'branch_id' => $request->query('branch_id'),
            'reservation_date' => $request->query('reservation_date'),
            'per_page' => $request->query('per_page', 15),
        ];

        return view('account.reservations.index', [
            'reservations' => $getMemberReservationsQuery->handle($request->user(), $filters),
            'summary' => $getMemberReservationsQuery->summary($request->user(), $filters),
        ]);
    }

    private function staffIndex(
        Request $request,
        GetLibraryReservationsQuery $getLibraryReservationsQuery,
        GetReservationIndexFiltersDataQuery $getReservationIndexFiltersDataQuery
    ): View {
        $scope = $request->user()->isSuperAdmin() && blank($request->query('library_id')) ? 'global' : 'library';

        $reservations = $getLibraryReservationsQuery->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'queue' => $request->query('queue'),
            'library_id' => $request->query('library_id'),
            'scope' => $scope,
            'branch_id' => $request->query('branch_id'),
            'reservation_date' => $request->query('reservation_date'),
            'per_page' => $request->query('per_page', 20),
        ]);

        return view('reservations.index', array_merge(
            [
                'reservations' => $reservations,
                'summary' => $getLibraryReservationsQuery->summary($request->user(), [
                    'library_id' => $request->query('library_id'),
                    'scope' => $scope,
                    'branch_id' => $request->query('branch_id'),
                ]),
            ],
            $getReservationIndexFiltersDataQuery->handle($request->user(), [
                'library_id' => $request->query('library_id'),
            ])
        ));
    }

    public function store(
        CreateReservationRequest $request,
        CreateReservationAction $createReservationAction
    ): RedirectResponse {
        $createReservationAction->handle(
            $request->user(),
            $request->validated()
        );

        return back()->with('success', 'Rezervacija sėkmingai sukurta.');
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservationAction
    ): RedirectResponse {
        $cancelReservationAction->handle(
            $request->user(),
            $reservation,
            $request->validated('reason')
        );

        return back()->with('success', 'Rezervacija atšaukta.');
    }
}
