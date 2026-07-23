<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBookCopyLifecycleRequest;
use App\Http\Requests\ManageBookCopyRequest;
use App\Models\BookCopy;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForModelQuery;
use App\Queries\Management\BookCopies\GetManageBookCopiesQuery;
use App\Services\BookCopyBranchTransferService;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookCopyController extends Controller
{
    public function index(Request $request, GetManageBookCopiesQuery $getManageBookCopiesQuery): View
    {
        return view('manage.book-copies.index', [
            'bookCopies' => $getManageBookCopiesQuery->handle($request->user(), [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'branch_id' => $request->query('branch_id'),
                'per_page' => $request->query('per_page', 10),
            ]),
            'summary' => $getManageBookCopiesQuery->summary($request->user()),
            'branches' => $getManageBookCopiesQuery->branches($request->user()),
            'statusLabels' => BookCopy::statusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('manage.book-copies.create');
    }

    public function store(
        ManageBookCopyRequest $request,
        ChangeBookCopyStatusAction $changeBookCopyStatusAction,
        BookCopyBranchTransferService $transferService
    ): RedirectResponse
    {
        $libraryId = $transferService->libraryIdForCreate($request->user(), $request->input('library_id'));
        $branchId = $transferService->resolveBranchId($request->user(), $libraryId, $request->input('branch_id'));

        $copy = BookCopy::create([
            'library_id' => $libraryId,
            'book_id' => $request->integer('book_id'),
            'branch_id' => $branchId,
            'location_id' => $request->input('location_id') ? $request->integer('location_id') : null,
            'inventory_code' => $request->validated('inventory_code'),
            'qr_code' => $this->generateQrCode($libraryId),
            'barcode' => $request->validated('barcode'),
            'status' => $request->validated('status'),
            'condition_status' => $request->validated('condition_status'),
            'acquired_at' => $request->validated('acquired_at'),
            'notes' => $request->validated('notes'),
        ]);

        $changeBookCopyStatusAction->handle(
            $copy,
            $request->validated('status'),
            $request->user(),
            'created',
            $request->validated('notes') ?: 'Kopija sukurta sistemoje.'
        );

        $copy->loadMissing('book:id,slug,title');

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'book_copy_created',
            $copy,
            sprintf('Sukurta kopija %s knygai "%s".', $copy->inventory_code, $copy->book?->title ?: 'be pavadinimo'),
            [
                'inventory_code' => $copy->inventory_code,
                'book_id' => $copy->book_id,
                'book_title' => $copy->book?->title,
                'branch_id' => $copy->branch_id,
                'location_id' => $copy->location_id,
            ],
            $copy->library_id
        );

        return redirect()
            ->route('book-copies.show', $copy->id)
            ->with('success', 'Kopija sėkmingai pridėta prie esamos knygos.');
    }

    public function edit(
        Request $request,
        BookCopy $bookCopy,
        GetRecentAuditLogsForModelQuery $getRecentAuditLogsForModelQuery
    ): View {
        $this->authorize('update', $bookCopy);

        $bookCopy->loadMissing(['book.authors:id,name', 'book.publisher:id,name', 'book.categories:id,name']);

        return view('manage.book-copies.edit', [
            'bookCopy' => $bookCopy,
            'auditLogs' => $request->user()?->isSuperAdmin()
                ? $getRecentAuditLogsForModelQuery->handle($bookCopy)
                : collect(),
        ]);
    }

    public function update(
        ManageBookCopyRequest $request,
        BookCopy $bookCopy,
        BookCopyBranchTransferService $transferService
    ): RedirectResponse
    {
        $this->authorize('update', $bookCopy);

        $bookCopy->loadMissing('branch:id,name');

        $originalBranchId = $bookCopy->branch_id ? (int) $bookCopy->branch_id : null;
        $originalBranchName = $bookCopy->branch?->name;
        $libraryId = $transferService->libraryIdForUpdate($request->user(), $bookCopy, $request->input('library_id'));
        $branchId = $transferService->resolveBranchId($request->user(), $libraryId, $request->input('branch_id'), $bookCopy);

        $bookCopy->fill([
            'library_id' => $libraryId,
            'book_id' => $request->integer('book_id'),
            'branch_id' => $branchId,
            'location_id' => $request->input('location_id') ? $request->integer('location_id') : null,
            'inventory_code' => $request->validated('inventory_code'),
            'barcode' => $request->validated('barcode'),
            'status' => $request->validated('status'),
            'condition_status' => $request->validated('condition_status'),
            'acquired_at' => $request->validated('acquired_at'),
            'notes' => $request->validated('notes'),
        ]);
        $changedFields = array_keys($bookCopy->getDirty());
        $changeSummary = AuditLogChanges::fromModel($bookCopy, $changedFields);
        $bookCopy->save();
        $bookCopy->loadMissing('branch:id,name');

        if (in_array('branch_id', $changedFields, true)) {
            $changeSummary['transfer'] = [
                'old_branch_id' => $originalBranchId,
                'old_branch_name' => $originalBranchName,
                'new_branch_id' => $bookCopy->branch_id ? (int) $bookCopy->branch_id : null,
                'new_branch_name' => $bookCopy->branch?->name,
                'transferred_by' => $request->user()->id,
                'transferred_at' => now()->toDateTimeString(),
            ];
        }

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'book_copy_updated',
            $bookCopy,
            sprintf('Atnaujinta kopija %s.', $bookCopy->inventory_code),
            array_merge([
                'inventory_code' => $bookCopy->inventory_code,
            ], $changeSummary),
            $bookCopy->library_id
        );

        return redirect()
            ->route('book-copies.show', $bookCopy->id)
            ->with('success', 'Kopija atnaujinta.');
    }

    public function destroy(Request $request, BookCopy $bookCopy): RedirectResponse
    {
        $this->authorize('delete', $bookCopy);

        if ($bookCopy->activeLoan()->exists()) {
            return back()->with('error', 'Kopijos ištrinti negalima, nes ji šiuo metu išduota.');
        }

        if ($bookCopy->loans()->exists()) {
            return back()->with('error', 'Kopijos ištrinti negalima, nes ji turi išduotų knygų istoriją.');
        }

        if ($bookCopy->scanLogs()->exists()) {
            return back()->with('error', 'Kopijos ištrinti negalima, nes ji turi skenavimo istoriją.');
        }

        if ($bookCopy->reservations()->exists()) {
            return back()->with('error', 'Kopijos istrinti negalima, nes ji turi rezervaciju istorija.');
        }

        if ($bookCopy->statusHistories()->exists() || $bookCopy->auditLogs()->exists()) {
            return back()->with('error', 'Kopijos istrinti negalima, nes ji turi audito arba busenos istorija.');
        }

        $bookCopy->loadMissing([
            'book:id,slug,title',
            'branch:id,name',
            'location:id,name,room,shelf',
        ]);

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'book_copy_deleted',
            $bookCopy,
            sprintf('Ištrinta kopija %s.', $bookCopy->inventory_code),
            [
                'inventory_code' => $bookCopy->inventory_code,
                'book_id' => $bookCopy->book_id,
                'book_title' => $bookCopy->book?->title,
                'snapshot' => [
                    'inventory_code' => $bookCopy->inventory_code,
                    'barcode' => $bookCopy->barcode,
                    'status' => $bookCopy->statusLabel(),
                    'condition_status' => $bookCopy->condition_status,
                    'branch' => $bookCopy->branch?->name,
                    'location' => $bookCopy->location
                        ? collect([$bookCopy->location->name, $bookCopy->location->room, $bookCopy->location->shelf])->filter()->join(' / ')
                        : null,
                    'acquired_at' => $bookCopy->acquired_at?->format('Y-m-d'),
                ],
            ],
            $bookCopy->library_id
        );

        $bookCopy->delete();

        return redirect()
            ->route('books.index')
            ->with('success', 'Kopija ištrinta.');
    }

    public function updateLifecycle(
        ManageBookCopyLifecycleRequest $request,
        BookCopy $bookCopy,
        ChangeBookCopyStatusAction $changeBookCopyStatusAction
    ): RedirectResponse {
        $this->authorize('update', $bookCopy);

        if ($bookCopy->activeLoan()->exists()) {
            return back()->with('error', 'Negalima keisti kopijos gyvavimo ciklo, kol ji yra aktyviai išduota.');
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
            $attributes['condition_status'] = BookCopy::CONDITION_DAMAGED;
        }

        if ($targetStatus === BookCopy::STATUS_AVAILABLE && $bookCopy->condition_status === BookCopy::CONDITION_DAMAGED) {
            $attributes['condition_status'] = BookCopy::CONDITION_GOOD;
        }

        $changeBookCopyStatusAction->handle(
            $bookCopy,
            $targetStatus,
            $request->user(),
            $reasonCode,
            $request->validated('reason_notes'),
            $attributes
        );

        return redirect()
            ->route('book-copies.show', $bookCopy)
            ->with('success', 'Kopijos būsena atnaujinta.');
    }

    private function ensureVisible(Request $request, BookCopy $bookCopy): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        abort_unless($bookCopy->library_id === $request->user()->activeLibraryId(), 404);
    }

    private function generateQrCode(int $libraryId): string
    {
        do {
            $candidate = 'QR-'.$libraryId.'-'.strtoupper(Str::random(12));
        } while (
            BookCopy::query()
                ->where('library_id', $libraryId)
                ->where('qr_code', $candidate)
                ->exists()
        );

        return $candidate;
    }
}
