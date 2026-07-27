<?php

namespace App\Queries\BookCopies;

use App\Models\BookCopy;
use App\Models\Reservation;
use App\Services\ReservationQueueService;
use Illuminate\Support\Collection;

class AttachCurrentReservationForCopies
{
    public function __construct(private readonly ReservationQueueService $queueService) {}

    /**
     * @param  iterable<int, BookCopy>  $copies
     */
    public function handle(iterable $copies, bool $canViewOperationalDetails): void
    {
        if (! $canViewOperationalDetails) {
            return;
        }

        foreach ($copies as $copy) {
            if (! $copy->book_id || ! $copy->library_id) {
                continue;
            }

            $currentReservation = $this->queueService->getEligibleReservationForCopy($copy);

            $currentReservation?->loadMissing([
                'pickupBranch:id,name',
                'user:id,name,email,membership_number',
            ]);

            $copy->setAttribute('current_reservation', $currentReservation);
        }
    }
}
