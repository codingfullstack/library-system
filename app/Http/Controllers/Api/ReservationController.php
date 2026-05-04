<?php

namespace App\Http\Controllers\Api;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use App\Queries\Reservations\GetMemberReservationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(
        Request $request,
        GetLibraryReservationsQuery $getLibraryReservationsQuery,
        GetMemberReservationsQuery $getMemberReservationsQuery
    ): JsonResponse
    {
        $user = $request->user();
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'queue' => $request->query('queue'),
            'library_id' => $request->query('library_id'),
            'per_page' => $request->query('per_page', 1000),
        ];

        $reservations = $user?->role === 'member'
            ? $getMemberReservationsQuery->handle($user, $filters)
            : $getLibraryReservationsQuery->handle($user, $filters);

        return response()->json([
            'data' => ReservationResource::collection(collect($reservations->items()))->resolve(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    public function store(
        CreateReservationRequest $request,
        CreateReservationAction $createReservationAction
    ): JsonResponse {
        $reservation = $createReservationAction->handle(
            $request->user(),
            $request->validated()
        );

        $reservation->load([
            'book:id,title,subtitle,isbn',
            'user:id,name,email,membership_number',
        ]);

        return response()->json([
            'message' => 'Rezervacija sėkmingai sukurta.',
            'reservation' => (new ReservationResource($reservation))->resolve(),
        ], 201);
    }

    public function cancel(
        Request $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservationAction
    ): JsonResponse {
        $reservation = $cancelReservationAction->handle($request->user(), $reservation);

        $reservation->load([
            'book:id,title,subtitle,isbn',
            'user:id,name,email,membership_number',
        ]);

        return response()->json([
            'message' => 'Rezervacija atšaukta.',
            'reservation' => (new ReservationResource($reservation))->resolve(),
        ]);
    }
}
