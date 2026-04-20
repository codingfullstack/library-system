<?php

namespace App\Http\Controllers;

use App\Models\BookCopy;
use Illuminate\View\View;

class BookCopyController extends Controller
{
    public function showPage(string $id): View
    {
        $copy = BookCopy::query()
            ->with(['book', 'branch'])
            ->findOrFail($id);

        return view('book-copies.show', [
            'copy' => $copy,
        ]);
    }

    public function findByQr(string $qrCode)
    {
        $copy = BookCopy::query()
            ->with(['book', 'branch'])
            ->where('qr_code', $qrCode)
            ->first();

        if (!$copy) {
            return response()->json([
                'message' => 'Egzempliorius nerastas'
            ], 404);
        }

        return response()->json([
            'id' => $copy->id,
            'inventory_code' => $copy->inventory_code,
            'qr_code' => $copy->qr_code,
            'status' => $copy->status,
            'book' => [
                'title' => $copy->book->title ?? null,
            ],
            'branch' => $copy->branch?->name,
        ]);
    }
}