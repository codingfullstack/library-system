<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Http\Requests\CreateReservationRequest;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $reservations = Reservation::query()
            ->where('library_id', $request->user()->library_id)
            ->with([
                'book:id,title',
                'user:id,name,email,membership_number',
            ])
            ->latest()
            ->paginate(20);

        return view('reservations.index', [
            'reservations' => $reservations,
        ]);
    }

    public function store(
        CreateReservationRequest $request,
        CreateReservationAction $createReservationAction
    ): RedirectResponse {
        $reservation = $createReservationAction->handle(
            $request->user(),
            $request->validated()
        );

        return back()->with('success', 'Rezervacija sėkmingai sukurta.');
    }

    public function cancel(
        Request $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservationAction
    ): RedirectResponse {
        $cancelReservationAction->handle($request->user(), $reservation);

        return back()->with('success', 'Rezervacija atšaukta.');
    }
}