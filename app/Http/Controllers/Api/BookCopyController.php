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
        $qrCode = $this->extractSystemBookCopyCode(
            trim((string) ($qrCode ?: $request->query('qr_code', '')))
        );

        if ($qrCode === '' || strlen($qrCode) > 128 || ! $this->isSystemBookCopyCode($qrCode)) {
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
                'message' => 'Kopija nerasta',
            ], 404);
        }

        return response()->json(
            (new BookCopyDetailsResource($copy, $request->user()->can('update', $copy)))->resolve()
        );
    }

    private function isSystemBookCopyCode(string $qrCode): bool
    {
        return str_starts_with($qrCode, 'QR-')
            || preg_match('/^[A-Z0-9]+-(QR|BC)-[A-Z0-9-]+$/i', $qrCode) === 1;
    }

    private function extractSystemBookCopyCode(string $qrCode): string
    {
        if ($qrCode === '' || $this->isSystemBookCopyCode($qrCode)) {
            return $qrCode;
        }

        if (preg_match('/(?:^|[^A-Z0-9])([A-Z0-9]+-(?:QR|BC)-[A-Z0-9-]+)(?:$|[^A-Z0-9-])/i', $qrCode, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/(?:^|[^A-Z0-9])(QR-[A-Z0-9-]+)(?:$|[^A-Z0-9-])/i', $qrCode, $matches) === 1) {
            return $matches[1];
        }

        return $qrCode;
    }

    public function updateLifecycle(
        ManageBookCopyLifecycleRequest $request,
        BookCopy $bookCopy,
        ChangeBookCopyStatusAction $changeBookCopyStatusAction
    ): JsonResponse {
        $this->authorize('update', $bookCopy);

        if ($bookCopy->activeLoan()->exists()) {
            return response()->json([
                'message' => 'Negalima keisti kopijos gyvavimo ciklo, kol ji yra aktyviai išduota.',
            ], 422);
        }

        $targetStatus = $request->validated('target_status');

        $reasonCode = match ($targetStatus) {
            BookCopy::STATUS_LOST => 'marked_lost',
            BookCopy::STATUS_MAINTENANCE => 'sent_to_maintenance',
            BookCopy::STATUS_IN_CIRCULATION => 'restored_to_circulation',
            BookCopy::STATUS_WITHDRAWN => 'nurašyta',
            default => 'status_adjusted',
        };

        $copy = $changeBookCopyStatusAction->handle(
            $bookCopy,
            $targetStatus,
            $request->user(),
            $reasonCode,
            $request->validated('reason_notes')
        );

        return response()->json([
            'message' => 'Kopijos būsena atnaujinta.',
            'book_copy' => (new BookCopyDetailsResource($copy, $request->user()->can('update', $copy)))->resolve(),
        ]);
    }
}
