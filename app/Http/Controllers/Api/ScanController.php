<?php

namespace App\Http\Controllers\Api;

use App\Actions\ScanLogs\RecordScanLogAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookCopyScanResource;
use App\Models\ScanLog;
use App\Queries\BookCopies\FindBookCopyByQrQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function scan(
        Request $request,
        FindBookCopyByQrQuery $findBookCopyByQrQuery,
        RecordScanLogAction $recordScanLogAction
    ): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => ['required', 'string', 'max:128'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ]);

        $bookCopy = $findBookCopyByQrQuery->handle($request->user(), $validated['qr_code'], [
            'book:id,title,isbn',
            'branch:id,name',
            'location:id,name,room,shelf',
            'activeLoan.user:id,name,email,membership_number',
        ]);

        if (! $bookCopy) {
            $recordScanLogAction->handle(
                $request->user(),
                $validated['qr_code'],
                ScanLog::TYPE_INFO,
                ScanLog::RESULT_NOT_FOUND,
                deviceInfo: $validated['device_info'] ?? null,
            );

            return response()->json([
                'message' => 'Knyga nerasta',
            ], 404);
        }

        $recordScanLogAction->handle(
            $request->user(),
            $validated['qr_code'],
            ScanLog::TYPE_INFO,
            ScanLog::RESULT_SUCCESS,
            $bookCopy,
            $validated['device_info'] ?? null,
        );

        $canManageCopy = $request->user()->can('update', $bookCopy);

        return response()->json(
            (new BookCopyScanResource($bookCopy, $canManageCopy))->resolve()
        );
    }
}








