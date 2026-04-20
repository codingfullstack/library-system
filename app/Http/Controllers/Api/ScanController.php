<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\ScanLog;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function scan(Request $request)
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $bookCopy = BookCopy::with('book')
            ->where('qr_code', $request->qr_code)
            ->first();

        if (! $bookCopy) {
            return response()->json([
                'message' => 'Knyga nerasta',
            ], 404);
        }

        // log
        ScanLog::create([
            'book_copy_id' => $bookCopy->id,
            'user_id' => $request->user()->id,
            'scan_value' => $request->qr_code,
            'scan_type' => 'info',
            'result' => 'success',
        ]);

        return response()->json([
            'book_copy' => $bookCopy,
            'book' => $bookCopy->book,
            'status' => $bookCopy->status,
            'can_borrow' => $bookCopy->status === 'available',
            'can_return' => $bookCopy->status === 'loaned',
        ]);
    }
        public function borrow(Request $request)
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $bookCopy = BookCopy::where('qr_code', $request->qr_code)->firstOrFail();

        if ($bookCopy->status !== 'available') {
            return response()->json([
                'message' => 'Knyga nepasiekiama',
            ], 400);
        }

        $loan = Loan::create([
            'book_copy_id' => $bookCopy->id,
            'user_id' => $request->user()->id,
            'issued_by' => $request->user()->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
            'status' => 'active',
        ]);

        $bookCopy->update([
            'status' => 'loaned',
        ]);

        ScanLog::create([
            'book_copy_id' => $bookCopy->id,
            'user_id' => $request->user()->id,
            'scan_value' => $request->qr_code,
            'scan_type' => 'borrow',
            'result' => 'success',
        ]);

        return response()->json([
            'message' => 'Knyga paimta',
            'loan' => $loan,
        ]);
    }
    public function return(Request $request)
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $bookCopy = BookCopy::where('qr_code', $request->qr_code)->firstOrFail();

        $loan = Loan::where('book_copy_id', $bookCopy->id)
            ->where('status', 'active')
            ->first();

        if (! $loan) {
            return response()->json([
                'message' => 'Aktyvi paskola nerasta',
            ], 404);
        }

        $loan->update([
            'status' => 'returned',
            'returned_at' => now(),
            'received_by' => $request->user()->id,
        ]);

        $bookCopy->update([
            'status' => 'available',
        ]);

        ScanLog::create([
            'book_copy_id' => $bookCopy->id,
            'user_id' => $request->user()->id,
            'scan_value' => $request->qr_code,
            'scan_type' => 'return',
            'result' => 'success',
        ]);

        return response()->json([
            'message' => 'Knyga grąžinta',
        ]);
    }
}
