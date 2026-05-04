<?php

namespace App\Livewire\Manage\Users;

use App\Actions\AuditLogs\RecordAuditLogAction;
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

    public string $role = 'member';

    public $libraryId = null;

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
            $this->libraryId = $managedUser->library_id;
            $this->phone = (string) ($managedUser->phone ?? '');
            $this->isActive = (bool) $managedUser->is_active;

            return;
        }

        $this->role = UserManagement::defaultRole($actor);
        $this->libraryId = $actor->isSuperAdmin() ? null : $actor->library_id;
    }

    public function updatedRole(string $value): void
    {
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        if (! UserManagement::requiresLibrary($value)) {
            $this->libraryId = null;
            return;
        }

        if (! $actor->isSuperAdmin()) {
            $this->libraryId = $actor->library_id;
        }
    }

    public function save()
    {
        $actor = Auth::user();

        abort_unless($actor, 403);

        if ($this->isEditing && $this->managedUser) {
            abort_unless(UserManagement::canManageUser($actor, $this->managedUser), 404);
        }

        $this->validate($this->rules(), [], [
            'name' => 'vardas',
            'email' => 'el. pastas',
            'role' => 'role',
            'libraryId' => 'biblioteka',
            'phone' => 'telefonas',
            'isActive' => 'aktyvumas',
            'password' => 'slaptazodis',
        ]);

        if (! UserManagement::canManageRole($actor, $this->role)) {
            throw ValidationException::withMessages([
                'role' => 'Negalite priskirti sios roles.',
            ]);
        }

        if ($actor->isSuperAdmin()) {
            if (! UserManagement::requiresLibrary($this->role)) {
                $this->libraryId = null;
            }
        } else {
            $this->libraryId = $actor->library_id;
        }

        if ($actor->id === $this->managedUser?->id) {
            $this->guardSelfMutation($actor);
        }

        if ($this->managedUser?->isSuperAdmin() && $this->role !== 'super_admin') {
            $this->ensureAnotherSuperAdminExists($this->managedUser);
        }

        if ($this->managedUser?->isSuperAdmin() && ! $this->isActive) {
            $this->ensureAnotherSuperAdminExists($this->managedUser);
        }

        $payload = [
            'library_id' => UserManagement::requiresLibrary($this->role) ? $this->libraryId : null,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone ?: null,
            'is_active' => $this->isActive,
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
                $this->managedUser->library_id ?: $actor->library_id
            );

            return redirect()
                ->route('manage.users.index')
                ->with('success', 'Vartotojas atnaujintas.');
        }

        $managedUser = User::create($payload);

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
            $managedUser->library_id ?: $actor->library_id
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
        if ($this->role !== 'member' || ! $this->libraryId) {
            return null;
        }

        $libraryChanged = (int) ($this->managedUser?->library_id ?: 0) !== (int) $this->libraryId;
        $roleChangedToMember = $this->managedUser?->exists && $this->managedUser->role !== 'member';

        if (! $this->managedUser || ! $this->managedUser->membership_number || $libraryChanged || $roleChangedToMember) {
            return UserManagement::generateMembershipNumber((int) $this->libraryId);
        }

        return $this->managedUser->membership_number;
    }

    private function previewMembershipNumber(): ?string
    {
        if ($this->role !== 'member' || ! $this->libraryId) {
            return null;
        }

        if ($this->managedUser?->membership_number && (int) $this->managedUser->library_id === (int) $this->libraryId && $this->managedUser->role === 'member') {
            return $this->managedUser->membership_number;
        }

        return UserManagement::generateMembershipNumber((int) $this->libraryId);
    }

    private function guardSelfMutation(User $actor): void
    {
        if ($this->role !== $actor->role) {
            throw ValidationException::withMessages([
                'role' => 'Negalite keisti savo roles.',
            ]);
        }

        if ((int) ($this->libraryId ?: 0) !== (int) ($actor->library_id ?: 0)) {
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

    private function ensureAnotherSuperAdminExists(User $user): void
    {
        $hasAnother = User::query()
            ->where('role', 'super_admin')
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
