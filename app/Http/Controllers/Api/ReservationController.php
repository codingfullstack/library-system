<?php

namespace App\Http\Controllers\Api;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelReservationRequest;
use App\Http\Requests\CreateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use App\Queries\Reservations\GetMemberReservationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(
        Request $request,
        GetLibraryReservationsQuery $getLibraryReservationsQuery,
        GetMemberReservationsQuery $getMemberReservationsQuery
    ): AnonymousResourceCollection {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                'active',
                Reservation::STATUS_WAITING,
                Reservation::STATUS_READY,
                Reservation::STATUS_FULFILLED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_EXPIRED,
            ])],
            'queue' => ['nullable', Rule::in(['first', 'waiting'])],
            'library_id' => ['nullable', 'integer', 'exists:libraries,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'queue' => $validated['queue'] ?? null,
            'library_id' => $validated['library_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'per_page' => $validated['per_page'] ?? 25,
        ];

        $reservations = $user?->role === 'narys'
            ? $getMemberReservationsQuery->handle($user, $filters)
            : $getLibraryReservationsQuery->handle($user, $filters);

        $summary = $user?->role === 'narys'
            ? $getMemberReservationsQuery->summary($user, $filters)
            : $getLibraryReservationsQuery->summary($user, $filters);

        return ReservationResource::collection($reservations)
            ->additional(['summary' => $summary]);
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
            'branch:id,name',
            'pickupBranch:id,name',
        ]);

        return response()->json([
            'message' => 'Rezervacija sėkmingai sukurta.',
            'reservation' => (new ReservationResource($reservation))->resolve(),
        ], 201);
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservationAction
    ): JsonResponse {
        $reservation = $cancelReservationAction->handle(
            $request->user(),
            $reservation,
            $request->validated('reason')
        );

        $reservation->load([
            'book:id,slug,title,subtitle,isbn',
            'user:id,name,email,membership_number',
            'branch:id,name',
            'pickupBranch:id,name',
        ]);

        return response()->json([
            'message' => 'Rezervacija atšaukta.',
            'reservation' => (new ReservationResource($reservation))->resolve(),
        ]);
    }
}
