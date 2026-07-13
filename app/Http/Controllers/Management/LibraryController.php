<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageLibraryRequest;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use App\Support\AuditLogChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $search = trim((string) $request->query('search', ''));

        $libraries = Library::query()
            ->withCount([
                'branches',
                'locations',
                'bookCopies',
                'memberships',
                'memberships as staff_users_count' => fn (Builder $query) => $query
                    ->whereHas('user', fn (Builder $userQuery) => $userQuery
                        ->whereIn('role', ['administratorius', 'darbuotojas'])),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $scope) use ($search) {
                $scope
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('manage.libraries.index', [
            'libraries' => $libraries,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('manage.libraries.create', [
            'library' => new Library([
                'is_active' => true,
                'is_public' => true,
            ]),
        ]);
    }

    public function store(ManageLibraryRequest $request): RedirectResponse
    {
        $library = Library::create($this->payload($request));

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'library_created',
            $library,
            sprintf('Sukurta biblioteka "%s".', $library->name),
            ['library_name' => $library->name],
            $library->id
        );

        return redirect()
            ->route('manage.libraries.edit', $library)
            ->with('success', 'Biblioteka sukurta. Dabar galite priskirti administratorius ir darbuotojus.');
    }

    public function edit(Request $request, Library $library): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $staffUsers = $library->members()
            ->whereIn('users.role', ['administratorius', 'darbuotojas'])
            ->orderByPivot('is_active', 'desc')
            ->orderBy('users.name')
            ->get();

        return view('manage.libraries.edit', [
            'library' => $library,
            'staffUsers' => $staffUsers,
        ]);
    }

    public function update(ManageLibraryRequest $request, Library $library): RedirectResponse
    {
        $library->fill($this->payload($request));
        $changedFields = array_keys($library->getDirty());
        $changeSummary = AuditLogChanges::fromModel($library, $changedFields);
        $library->save();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'library_updated',
            $library,
            sprintf('Atnaujinta biblioteka "%s".', $library->name),
            array_merge(['library_name' => $library->name], $changeSummary),
            $library->id
        );

        return redirect()
            ->route('manage.libraries.edit', $library)
            ->with('success', 'Biblioteka atnaujinta.');
    }

    public function destroy(Request $request, Library $library): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        if ($this->hasBlockingRelations($library)) {
            return back()->with('error', 'Bibliotekos ištrinti negalima, nes ji turi filialų, vietų, kopijų, vartotojų arba veiklos įrašų.');
        }

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'library_deleted',
            $library,
            sprintf('Ištrinta biblioteka "%s".', $library->name),
            [
                'snapshot' => $library->only(['name', 'code', 'email', 'phone', 'address', 'city']),
            ],
            $library->id
        );

        $library->delete();

        return redirect()
            ->route('manage.libraries.index')
            ->with('success', 'Biblioteka ištrinta.');
    }

    public function assignStaff(Request $request, Library $library): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in(['administratorius', 'darbuotojas'])],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->firstOrFail();

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Superadmin vartotojui bibliotekos narystės priskirti nereikia.');
        }

        $user->forceFill([
            'role' => $data['role'],
            'membership_number' => null,
            'is_active' => true,
        ])->save();

        LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $library->id,
                'user_id' => $user->id,
            ],
            [
                'membership_number' => null,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'library_staff_assigned',
            $library,
            sprintf('Vartotojas "%s" priskirtas bibliotekai "%s" kaip %s.', $user->email, $library->name, $data['role']),
            [
                'library_name' => $library->name,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'role' => $data['role'],
            ],
            $library->id
        );

        return back()->with('success', 'Vartotojas priskirtas bibliotekai.');
    }

    public function toggleStaff(Request $request, Library $library, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $this->ensureStaffUser($library, $user);

        $membership = $this->staffMembership($library, $user);
        $membership->update([
            'is_active' => ! $membership->is_active,
        ]);

        return back()->with('success', $membership->is_active ? 'Darbuotojo prieiga aktyvuota.' : 'Darbuotojo prieiga deaktyvuota.');
    }

    public function destroyStaff(Request $request, Library $library, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $this->ensureStaffUser($library, $user);

        if (
            $user->loans()->exists()
            || $user->reservations()->exists()
            || $user->issuedLoans()->exists()
            || $user->receivedLoans()->exists()
            || $user->scanLogs()->exists()
        ) {
            return back()->with('error', 'Darbuotojo atskirti negalima, nes paskyra turi susijusios istorijos.');
        }

        $this->staffMembership($library, $user)->delete();

        if (! $user->activeLibraryMemberships()->exists()) {
            $user->forceFill(['role' => 'narys'])->save();
        }

        return back()->with('success', 'Darbuotojas atskirtas nuo bibliotekos.');
    }

    private function payload(ManageLibraryRequest $request): array
    {
        return [
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'address' => $request->validated('address'),
            'city' => $request->validated('city'),
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
        ];
    }

    private function hasBlockingRelations(Library $library): bool
    {
        return $library->branches()->exists()
            || $library->locations()->exists()
            || $library->bookCopies()->exists()
            || $library->loans()->exists()
            || $library->reservations()->exists()
            || $library->memberships()->exists();
    }

    private function ensureStaffUser(Library $library, User $user): void
    {
        abort_unless($this->staffMembership($library, $user)->exists, 404);
    }

    private function staffMembership(Library $library, User $user): LibraryMembership
    {
        return LibraryMembership::query()
            ->where('library_id', $library->id)
            ->where('user_id', $user->id)
            ->whereHas('user', fn (Builder $query) => $query->whereIn('role', ['administratorius', 'darbuotojas']))
            ->firstOrFail();
    }
}








