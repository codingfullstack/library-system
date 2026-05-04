<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookCopyLookupResource;
use App\Queries\BookCopies\FindBookCopyByQrQuery;
use App\Queries\BookCopies\GetVisibleBookCopyQuery;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForModelQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookCopyController extends Controller
{
    public function showPage(
        Request $request,
        string $id,
        GetVisibleBookCopyQuery $getVisibleBookCopyQuery,
        GetRecentAuditLogsForModelQuery $getRecentAuditLogsForModelQuery
    ): View
    {
        $copy = $getVisibleBookCopyQuery->handle($request->user(), $id, ['book', 'branch', 'location', 'activeLoan.user']);
        $actor = $request->user();

        return view('book-copies.show', [
            'copy' => $copy,
            'auditLogs' => $actor?->isSuperAdmin()
                ? $getRecentAuditLogsForModelQuery->handle($copy)
                : collect(),
        ]);
    }

    public function findByQr(
        Request $request,
        string $qrCode,
        FindBookCopyByQrQuery $findBookCopyByQrQuery
    ): JsonResponse
    {
        $copy = $findBookCopyByQrQuery->handle($request->user(), $qrCode, [
            'book',
            'branch',
        ]);

        if (! $copy) {
            return response()->json([
                'message' => 'Egzempliorius nerastas',
            ], 404);
        }

        return response()->json(
            (new BookCopyLookupResource($copy))->resolve()
        );
    }
}
