<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForUserQuery;
use App\Queries\Users\GetManagedUserDetailsQuery;
use App\Queries\Users\GetManageUsersQuery;
use App\Queries\Users\HasAnotherActiveSuperAdminQuery;
use App\Support\UserManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, GetManageUsersQuery $getManageUsersQuery): View
    {
        $actor = $request->user();
        $users = $getManageUsersQuery->handle($actor, [
            'search' => $request->query('search'),
            'role' => $request->query('role'),
            'aktyvi' => $request->query('aktyvi'),
        ]);

        return view('manage.users.index', [
            'users' => $users,
            'manageableRoles' => UserManagement::manageableRoles($actor),
        ]);
    }

    public function create(Request $request): View
    {
        return view('manage.users.create');
    }

    public function show(
        Request $request,
        User $user,
        GetManagedUserDetailsQuery $getManagedUserDetailsQuery,
        GetRecentAuditLogsForUserQuery $getRecentAuditLogsForUserQuery
    ): View
    {
        $actor = $request->user();
        $this->ensureVisible($actor, $user);
        $user = $getManagedUserDetailsQuery->handle($user);

        return view('manage.users.show', [
            'managedUser' => $user,
            'recentLoans' => $user->loans()
                ->with('bookCopy.book:id,title')
                ->latest('borrowed_at')
                ->paginate(5, ['*'], 'user-loans-page')
                ->withQueryString(),
            'recentReservations' => $user->reservations()
                ->with('book:id,title')
                ->latest('reserved_at')
                ->paginate(5, ['*'], 'user-reservations-page')
                ->withQueryString(),
            'auditLogs' => $actor->isSuperAdmin()
                ? $getRecentAuditLogsForUserQuery->handle($user)
                : collect(),
        ]);
    }

    public function edit(
        Request $request,
        User $user,
        GetRecentAuditLogsForUserQuery $getRecentAuditLogsForUserQuery
    ): View
    {
        $actor = $request->user();
        $this->ensureVisible($actor, $user);

        return view('manage.users.edit', [
            'managedUser' => $user,
            'auditLogs' => $actor->isSuperAdmin()
                ? $getRecentAuditLogsForUserQuery->handle($user)
                : collect(),
        ]);
    }

    public function destroy(
        Request $request,
        User $user,
        HasAnotherActiveSuperAdminQuery $hasAnotherActiveSuperAdminQuery
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureVisible($actor, $user);

        if ($actor->id === $user->id) {
            return back()->with('error', 'Negalite ištrinti savo paskyros.');
        }

        if ($user->isSuperAdmin()) {
            $this->ensureAnotherSuperAdminExists($user, $hasAnotherActiveSuperAdminQuery);
        }

        if (
            $user->loans()->exists()
            || $user->reservations()->exists()
            || $user->issuedLoans()->exists()
            || $user->receivedLoans()->exists()
            || $user->scanLogs()->exists()
        ) {
            return back()->with('error', 'Vartotojo ištrinti negalima, nes jis turi susijusios istorijos.');
        }

        $user->loadMissing('libraryMemberships.library:id,name');

        app(RecordAuditLogAction::class)->handle(
            $actor,
            'user_deleted',
            $user,
            sprintf('Ištrintas vartotojas "%s".', $user->name),
            [
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'target_user_role' => $user->role,
                'snapshot' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'membership_number' => $user->membership_number,
                    'library' => $user->library?->name,
                    'is_active' => $user->is_active,
                ],
            ],
            $user->defaultLibraryId() ?: $actor->activeLibraryId()
        );

        $user->delete();

        return redirect()
            ->route('manage.users.index')
            ->with('success', 'Vartotojas ištrintas.');
    }

    public function toggleActive(
        Request $request,
        User $user,
        HasAnotherActiveSuperAdminQuery $hasAnotherActiveSuperAdminQuery
    ): RedirectResponse {
        $actor = $request->user();
        $this->ensureVisible($actor, $user);

        if ($actor->id === $user->id) {
            return back()->with('error', 'Negalite aktyvuoti arba deaktyvuoti savo paskyros iš sąrašo.');
        }

        if ($user->isSuperAdmin() && $user->is_active) {
            $this->ensureAnotherSuperAdminExists($user, $hasAnotherActiveSuperAdminQuery);
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);
        UserManagement::syncUserMembershipActivity($user);

        app(RecordAuditLogAction::class)->handle(
            $actor,
            'user_toggled_active',
            $user,
            sprintf(
                'Vartotojas "%s" %s.',
                $user->name,
                $user->is_active ? 'aktyvuotas' : 'deaktyvuotas'
            ),
            [
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'target_user_role' => $user->role,
                'is_active' => $user->is_active,
            ],
            $user->defaultLibraryId() ?: $actor->activeLibraryId()
        );

        return back()->with('success', $user->is_active ? 'Vartotojas aktyvuotas.' : 'Vartotojas deaktyvuotas.');
    }

    private function ensureVisible(User $actor, User $user): void
    {
        abort_unless(UserManagement::canManageUser($actor, $user), 404);
    }

    private function ensureAnotherSuperAdminExists(
        User $user,
        HasAnotherActiveSuperAdminQuery $hasAnotherActiveSuperAdminQuery
    ): void {
        if (! $hasAnotherActiveSuperAdminQuery->handle($user)) {
            throw ValidationException::withMessages([
                'role' => 'Sistemoje turi likti bent vienas aktyvus superadmin.',
            ]);
        }
    }
}








