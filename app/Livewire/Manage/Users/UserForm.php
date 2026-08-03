<?php

namespace App\Livewire\Manage\Users;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\Branch;
use App\Models\Library;
use App\Models\User;
use App\Support\AuditLogChanges;
use App\Support\UserManagement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class UserForm extends Component
{
    public ?User $managedUser = null;

    public bool $isEditing = false;

    public string $name = '';

    public string $email = '';

    public string $role = 'narys';

    public $libraryId = null;

    public $branchId = null;

    public string $phone = '';

    public bool $isActive = true;

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(?User $managedUser = null): void
    {
        $actor = Auth::user();

        abort_unless($actor, 403);

        if ($managedUser) {
            abort_unless(UserManagement::canManageUser($actor, $managedUser), 404);

            $this->managedUser = $managedUser;
            $this->isEditing = true;
            $this->name = $managedUser->name;
            $this->email = $managedUser->email;
            $this->role = $managedUser->role;
            $this->libraryId = $managedUser->defaultLibraryId();
            $this->branchId = $managedUser->assignedBranchId($this->libraryId);
            $this->phone = (string) ($managedUser->phone ?? '');
            $this->isActive = (bool) $managedUser->is_active;

            return;
        }

        $this->role = UserManagement::defaultRole($actor);
        $this->libraryId = $actor->isSuperAdmin() ? null : $actor->activeLibraryId();
    }

    public function updatedRole(string $value): void
    {
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        if (! UserManagement::requiresLibrary($value)) {
            $this->libraryId = null;
            $this->branchId = null;

            return;
        }

        if (! $actor->isSuperAdmin()) {
            $this->libraryId = $actor->activeLibraryId();
        }

        if ($value !== User::ROLE_STAFF) {
            $this->branchId = null;
        }
    }

    public function save()
    {
        $actor = Auth::user();

        abort_unless($actor, 403);

        if ($this->isEditing && $this->managedUser) {
            abort_unless(UserManagement::canManageUser($actor, $this->managedUser), 404);
        }

        if ($actor->id === $this->managedUser?->id) {
            $this->guardSelfMutation($actor);
        }

        if (! $actor->isSuperAdmin() && $this->managedUser && $this->role !== $this->managedUser->role) {
            throw ValidationException::withMessages([
                'role' => 'Bibliotekos administratorius negali keisti globalios vartotojo rolės.',
            ]);
        }

        $this->validate($this->rules(), [], [
            'name' => 'vardas',
            'email' => 'el. paštas',
            'role' => 'role',
            'libraryId' => 'biblioteka',
            'branchId' => 'filialas',
            'phone' => 'telefonas',
            'isActive' => 'aktyvumas',
            'password' => 'slaptažodis',
        ]);

        if (! UserManagement::canManageRole($actor, $this->role)) {
            throw ValidationException::withMessages([
                'role' => 'Negalite priskirti šios roles.',
            ]);
        }

        if ($actor->isSuperAdmin()) {
            if (! UserManagement::requiresLibrary($this->role)) {
                $this->libraryId = null;
                $this->branchId = null;
            }
        } else {
            $this->libraryId = $actor->activeLibraryId();
        }

        if ($this->role !== User::ROLE_STAFF) {
            $this->branchId = null;
        }

        if ($this->managedUser?->isSuperAdmin() && $this->role !== 'superadministratorius') {
            $this->ensureAnotherSuperAdminExists($this->managedUser);
        }

        if ($actor->isSuperAdmin() && $this->managedUser?->isSuperAdmin() && ! $this->isActive) {
            $this->ensureAnotherSuperAdminExists($this->managedUser);
        }

        $payload = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone ?: null,
            'is_active' => $actor->isSuperAdmin()
                ? $this->isActive
                : (bool) ($this->managedUser?->is_active ?? true),
            'membership_number' => $this->resolveMembershipNumber(),
        ];

        if ($this->password !== '') {
            $payload['password'] = $this->password;
        }

        if ($this->managedUser) {
            $this->managedUser->fill($payload);
            $changedFields = array_keys($this->managedUser->getDirty());
            $changeSummary = AuditLogChanges::fromModel($this->managedUser, $changedFields);
            $this->managedUser->save();
            $this->syncMembershipForSavedUser($this->managedUser);

            app(RecordAuditLogAction::class)->handle(
                $actor,
                'user_updated',
                $this->managedUser,
                sprintf('Atnaujintas vartotojas "%s".', $this->managedUser->name),
                array_merge([
                    'target_user_id' => $this->managedUser->id,
                    'target_user_name' => $this->managedUser->name,
                    'target_user_role' => $this->managedUser->role,
                ], $changeSummary),
                $this->managedUser->defaultLibraryId() ?: $actor->activeLibraryId()
            );

            return redirect()
                ->route('manage.users.index')
                ->with('success', 'Vartotojas atnaujintas.');
        }

        $managedUser = User::create($payload);
        $this->syncMembershipForSavedUser($managedUser);

        app(RecordAuditLogAction::class)->handle(
            $actor,
            'user_created',
            $managedUser,
            sprintf('Sukurtas vartotojas "%s".', $managedUser->name),
            [
                'target_user_id' => $managedUser->id,
                'target_user_name' => $managedUser->name,
                'target_user_role' => $managedUser->role,
            ],
            $managedUser->defaultLibraryId() ?: $actor->activeLibraryId()
        );

        return redirect()
            ->route('manage.users.index')
            ->with('success', 'Vartotojas sukurtas.');
    }

    public function render()
    {
        $actor = Auth::user();

        return view('livewire.manage.users.user-form', [
            'roleOptions' => $actor ? UserManagement::manageableRoles($actor) : [],
            'libraries' => $actor?->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name', 'code'])
                : collect(),
            'branches' => $this->availableBranches(),
            'previewMembershipNumber' => $this->previewMembershipNumber(),
        ]);
    }

    private function rules(): array
    {
        $actor = Auth::user();
        $targetId = $this->managedUser?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetId)],
            'role' => ['required', Rule::in($actor ? UserManagement::manageableRoles($actor) : [])],
            'libraryId' => [
                Rule::requiredIf(fn () => UserManagement::requiresLibrary($this->role)),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
            'branchId' => [
                Rule::requiredIf(fn () => $this->role === User::ROLE_STAFF),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('library_id', $this->libraryId)),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'password' => [
                $this->managedUser ? 'nullable' : 'required',
                'string',
                'min:8',
                'same:passwordConfirmation',
            ],
            'passwordConfirmation' => [$this->managedUser ? 'nullable' : 'required', 'string'],
        ];
    }

    private function resolveMembershipNumber(): ?string
    {
        if ($this->role !== 'narys') {
            return null;
        }

        if ($this->managedUser?->membership_number) {
            return $this->managedUser->membership_number;
        }

        return UserManagement::generateMembershipNumber();
    }

    private function previewMembershipNumber(): ?string
    {
        if ($this->role !== 'narys') {
            return null;
        }

        if ($this->managedUser?->membership_number) {
            return $this->managedUser->membership_number;
        }

        return UserManagement::generateMembershipNumber();
    }

    private function guardSelfMutation(User $actor): void
    {
        if ($this->role !== $actor->role) {
            throw ValidationException::withMessages([
                'role' => 'Negalite keisti savo roles.',
            ]);
        }

        if ((int) ($this->libraryId ?: 0) !== (int) ($actor->activeLibraryId() ?: 0)) {
            throw ValidationException::withMessages([
                'libraryId' => 'Negalite keisti savo bibliotekos.',
            ]);
        }

        if ($this->isActive !== (bool) $actor->is_active) {
            throw ValidationException::withMessages([
                'isActive' => 'Negalite deaktyvuoti savo paskyros.',
            ]);
        }
    }

    private function syncMembershipForSavedUser(User $user): void
    {
        if (! UserManagement::requiresLibrary($this->role) || ! $this->libraryId) {
            return;
        }

        UserManagement::syncLibraryMembership(
            $user,
            (int) $this->libraryId,
            $this->branchId ? (int) $this->branchId : null
        );
    }

    private function availableBranches()
    {
        if (! $this->libraryId || $this->role !== User::ROLE_STAFF) {
            return collect();
        }

        return Branch::query()
            ->where('library_id', $this->libraryId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function ensureAnotherSuperAdminExists(User $user): void
    {
        $hasAnother = User::query()
            ->where('role', 'superadministratorius')
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasAnother) {
            throw ValidationException::withMessages([
                'role' => 'Sistemoje turi likti bent vienas aktyvus superadmin.',
            ]);
        }
    }
}
