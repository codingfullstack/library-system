<?php

namespace App\Http\Controllers\Api;

use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBookCopyLifecycleRequest;
use App\Http\Resources\BookCopyDetailsResource;
use App\Models\BookCopy;
use App\Queries\BookCopies\FindBookCopyByQrQuery;
use App\Queries\BookCopies\GetLibraryBookCopyDetailsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookCopyController extends Controller
{
    public function show(
        Request $request,
        BookCopy $bookCopy,
        GetLibraryBookCopyDetailsQuery $getLibraryBookCopyDetailsQuery
    ): JsonResponse {
        $copy = $getLibraryBookCopyDetailsQuery->handle($request->user(), $bookCopy);

        return response()->json(
            (new BookCopyDetailsResource($copy, $request->user()->can('update', $copy)))->resolve()
        );
    }

    public function findByQr(
        Request $request,
        FindBookCopyByQrQuery $findBookCopyByQrQuery,
        ?string $qrCode = null
    ): JsonResponse {
        $qrCode = trim((string) ($qrCode ?: $request->query('qr_code', '')));

        if ($qrCode === '' || strlen($qrCode) > 128 || ! str_starts_with($qrCode, 'QR-')) {
            return response()->json([
                'message' => 'Neatpažintas QR kodas. Nuskenuokite šios sistemos sugeneruotą knygos QR kodą.',
            ], 422);
        }

        $copy = $findBookCopyByQrQuery->handle($request->user(), $qrCode, [
            'book:id,slug,title,subtitle,isbn',
            'book.reservations.user:id,name,email,membership_number',
            'branch:id,name',
            'location:id,name,room,shelf',
            'statusHistories.user:id,name',
            'activeLoan.user:id,name,email,membership_number',
            'activeLoan.issuer:id,name,email',
            'activeLoan.receiver:id,name,email',
        ]);

        if (! $copy) {
            return response()->json([
                'message' => 'Egzempliorius nerastas',
            ], 404);
        }

        return response()->json(
            (new BookCopyDetailsResource($copy, $request->user()->can('update', $copy)))->resolve()
        );
    }

    public function updateLifecycle(
        ManageBookCopyLifecycleRequest $request,
        BookCopy $bookCopy,
        ChangeBookCopyStatusAction $changeBookCopyStatusAction
    ): JsonResponse {
        $this->authorize('update', $bookCopy);

        if ($bookCopy->activeLoan()->exists()) {
            return response()->json([
                'message' => 'Negalima keisti egzemplioriaus gyvavimo ciklo, kol jis yra aktyviai išduotas.',
            ], 422);
        }

        $targetStatus = $request->validated('target_status');

        $reasonCode = match ($targetStatus) {
            BookCopy::STATUS_LOST => 'marked_lost',
            BookCopy::STATUS_DAMAGED => 'marked_damaged',
            BookCopy::STATUS_MAINTENANCE => 'sent_to_maintenance',
            BookCopy::STATUS_AVAILABLE => 'restored_to_active',
            BookCopy::STATUS_WITHDRAWN => 'nurašyta',
            default => 'status_adjusted',
        };

        $attributes = [];

        if ($targetStatus === BookCopy::STATUS_DAMAGED) {
            $attributes['condition_status'] = 'sugadinta';
        }

        if ($targetStatus === BookCopy::STATUS_AVAILABLE && $bookCopy->condition_status === 'sugadinta') {
            $attributes['condition_status'] = 'gera';
        }

        $copy = $changeBookCopyStatusAction->handle(
            $bookCopy,
            $targetStatus,
            $request->user(),
            $reasonCode,
            $request->validated('reason_notes'),
            $attributes
        );

        return response()->json([
            'message' => 'Egzemplioriaus būsena atnaujinta.',
            'book_copy' => (new BookCopyDetailsResource($copy, $request->user()->can('update', $copy)))->resolve(),
        ]);
    }
}
