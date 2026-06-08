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
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(
        Request $request,
        GetLibraryReservationsQuery $getLibraryReservationsQuery,
        GetMemberReservationsQuery $getMemberReservationsQuery
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['rezervuota', 'Ä¯vykdyta', 'atÅ¡aukta', 'pasibaigusi'])],
            'queue' => ['nullable', Rule::in(['first', 'waiting'])],
            'library_id' => ['nullable', 'integer', 'exists:libraries,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'queue' => $validated['queue'] ?? null,
            'library_id' => $validated['library_id'] ?? null,
            'per_page' => $validated['per_page'] ?? 25,
        ];

        $reservations = $user?->role === 'narys'
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
            'book:id,slug,title,subtitle,isbn',
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
            'book:id,slug,title,subtitle,isbn',
            'user:id,name,email,membership_number',
        ]);

        return response()->json([
            'message' => 'Rezervacija atšaukta.',
            'reservation' => (new ReservationResource($reservation))->resolve(),
        ]);
    }
}
