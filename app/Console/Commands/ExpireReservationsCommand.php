<?php

namespace App\Console\Commands;

use App\Actions\Notifications\CreateUserNotificationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Models\Reservation;
use App\Services\ReservationQueueService;
use App\Support\Notifications\NotificationMessageBuilder;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire ready reservations whose pickup window has elapsed.';

    public function handle(): int
    {
        $expiredCount = 0;

        Reservation::query()
            ->where('status', Reservation::STATUS_READY)
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($reservations) use (&$expiredCount): void {
                foreach ($reservations as $reservationStub) {
                    $bookToSync = DB::transaction(function () use ($reservationStub, &$expiredCount): ?array {
                        $reservationContext = Reservation::query()
                            ->whereKey($reservationStub->id)
                            ->first();

                        if (! $reservationContext) {
                            return null;
                        }

                        app(ReservationQueueService::class)->lockQueueContext(
                            (int) $reservationContext->library_id,
                            (int) $reservationContext->book_id
                        );

                        $reservation = Reservation::query()
                            ->with(['book:id,title', 'pickupBranch:id,name', 'user:id,name,email'])
                            ->whereKey($reservationStub->id)
                            ->where('status', Reservation::STATUS_READY)
                            ->where('expires_at', '<=', now())
                            ->lockForUpdate()
                            ->first();

                        if (! $reservation) {
                            return null;
                        }

                        $pickupBranchId = $reservation->pickup_branch_id;
                        $pickupBranchName = $reservation->pickupBranch?->name;

                        $expireAttributes = [
                            'status' => Reservation::STATUS_EXPIRED,
                            'pickup_branch_id' => null,
                            'assigned_book_copy_id' => null,
                        ];

                        $reservation->update($expireAttributes);

                        $expiredCount++;

                        if ($reservation->user) {
                            DB::afterCommit(fn () => app(CreateUserNotificationAction::class)->handle(
                                $reservation->user,
                                null,
                                NotificationType::RESERVATION_EXPIRED,
                                null,
                                NotificationMessageBuilder::reservationExpired($reservation, $pickupBranchName),
                                NotificationMetadataBuilder::reservation($reservation, [
                                    'pickup_branch_id' => $pickupBranchId,
                                    'pickup_branch_name' => $pickupBranchName,
                                    'expires_at' => $reservation->expires_at?->toDateTimeString(),
                                ]),
                                Reservation::class,
                                $reservation->id
                            ));
                        }

                        return [
                            'library_id' => (int) $reservation->library_id,
                            'book_id' => (int) $reservation->book_id,
                        ];
                    });

                    if ($bookToSync !== null) {
                        app(SyncReservationQueueAction::class)->handle($bookToSync['library_id'], $bookToSync['book_id']);
                    }
                }
            });

        $this->info("Expired reservations: {$expiredCount}");

        return self::SUCCESS;
    }
}
