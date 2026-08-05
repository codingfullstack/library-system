<?php

namespace App\Livewire\Manage\Users;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Users\TransferLibraryMembershipAction;
use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use App\Support\AuditLogChanges;
use App\Support\UserManagement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class UserForm extends Component
{
    public ?User $managedUser = null;

    public bool $isEditing = false;

    public string $name = '';

    public string $email = '';

    public string $role = User::ROLE_MEMBER;

    public $libraryId = null;

    public $branchId = null;

    public $membershipId = null;

    public $sourceLibraryId = null;

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
            $membership = $this->editableMembershipFor($actor, $managedUser);
            $this->membershipId = $membership?->id;
            $this->sourceLibraryId = $membership?->library_id;
            $this->libraryId = $membership?->library_id ?: ($actor->isSuperAdmin()
                ? $managedUser->defaultLibraryId()
                : $actor->activeLibraryId());
            $this->branchId = $membership?->branch_id;
            $this->phone = (string) ($managedUser->phone ?? '');
            $this->isActive = (bool) $managedUser->is_active;

            return;
        }

        abort_unless(UserManagement::canCreateUsers($actor), 403);

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

    public function updatedLibraryId($value): void
    {
        if ((int) ($value ?: 0) !== (int) ($this->sourceLibraryId ?: 0)) {
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

        if (! $this->isEditing) {
            abort_unless(UserManagement::canCreateUsers($actor), 403);
        }

        if ($actor->id === $this->managedUser?->id) {
            $this->guardSelfMutation($actor);
        }

        $this->enforceRoleContract($actor);

        $this->validate($this->rules(), [], [
            'name' => 'vardas',
            'email' => 'el. paštas',
            'role' => 'paskyros tipas',
            'libraryId' => 'biblioteka',
            'branchId' => 'filialas',
            'phone' => 'telefonas',
            'isActive' => 'aktyvumas',
            'password' => 'slaptažodis',
        ]);

        $allowedRoles = $this->isEditing
            ? UserManagement::manageableRoles($actor)
            : UserManagement::creatableRoles($actor);

        if (! in_array($this->role, $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role' => 'Negalite priskirti šio paskyros tipo.',
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

        if ($this->managedUser?->isSuperAdmin() && $this->role !== User::ROLE_SUPER_ADMIN) {
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
            $changeSummary = ['changed_fields' => [], 'changes' => []];

            DB::transaction(function () use ($payload, &$changeSummary): void {
                $this->managedUser->fill($payload);
                $userChanges = $this->auditChangesForUser($this->managedUser, array_keys($this->managedUser->getDirty()));

                if ($this->managedUser->isDirty()) {
                    $this->managedUser->save();
                }

                $membershipBefore = $this->membershipSnapshot($this->managedUser);
                $membership = $this->syncMembershipForSavedUser($this->managedUser);
                $membershipChanges = $this->auditChangesForMembership($membershipBefore, $membership);

                $changeSummary = $this->mergeAuditChanges($userChanges, $membershipChanges);
            });

            if ($changeSummary['changes'] === []) {
                return redirect()
                    ->route('manage.users.edit', $this->managedUser)
                    ->with('info', 'Nebuvo atlikta jokių pakeitimų.');
            }

            $this->managedUser = $this->managedUser->refresh();

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

        $managedUser = DB::transaction(function () use ($payload): User {
            $user = User::create($payload);
            $this->syncMembershipForSavedUser($user);

            return $user;
        });

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
        $canEditGlobalRole = (bool) $actor?->isSuperAdmin();
        $canChooseAccountType = ! $this->isEditing && UserManagement::canCreateUsers($actor);

        return view('livewire.manage.users.user-form', [
            'roleOptions' => $this->roleOptions($actor),
            'libraries' => $actor?->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name', 'code'])
                : collect(),
            'branches' => $this->availableBranches(),
            'previewMembershipNumber' => $this->previewMembershipNumber(),
            'canEditGlobalRole' => $canEditGlobalRole,
            'canChooseAccountType' => $canChooseAccountType,
            'accountTypeLabel' => $this->accountTypeLabel($this->role),
            'canCreateUsers' => $actor ? UserManagement::canCreateUsers($actor) : false,
        ]);
    }

    private function rules(): array
    {
        $actor = Auth::user();
        $targetId = $this->managedUser?->id;
        $libraryId = $actor?->isSuperAdmin() ? $this->libraryId : $actor?->activeLibraryId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetId)],
            'role' => ['required', Rule::in($this->roleOptions($actor))],
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
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('library_id', $libraryId)),
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

    private function enforceRoleContract(User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($this->managedUser) {
            if ($this->role !== $this->managedUser->role) {
                throw ValidationException::withMessages([
                    'role' => 'Bibliotekos administratorius negali keisti globalios vartotojo rolės.',
                ]);
            }

            if ((int) ($this->libraryId ?: 0) !== (int) ($actor->activeLibraryId() ?: 0)) {
                throw ValidationException::withMessages([
                    'libraryId' => 'Bibliotekos administratorius negali perkelti vartotojo į kitą biblioteką.',
                ]);
            }

            $this->role = $this->managedUser->role;
            $this->libraryId = $actor->activeLibraryId();

            return;
        }

        if (! in_array($this->role, UserManagement::creatableRoles($actor), true)) {
            throw ValidationException::withMessages([
                'role' => 'Bibliotekos administratorius gali kurti tik skaitytojo arba darbuotojo paskyrą.',
            ]);
        }

        $this->libraryId = $actor->activeLibraryId();

        if ($this->role !== User::ROLE_STAFF) {
            $this->branchId = null;
        }
    }

    private function roleOptions(?User $actor): array
    {
        if (! $actor) {
            return [];
        }

        if ($this->isEditing) {
            return $actor->isSuperAdmin()
                ? UserManagement::manageableRoles($actor)
                : [$this->managedUser?->role ?? $this->role];
        }

        return UserManagement::creatableRoles($actor);
    }

    private function resolveMembershipNumber(): ?string
    {
        if ($this->role !== User::ROLE_MEMBER) {
            return null;
        }

        if ($this->managedUser?->membership_number) {
            return $this->managedUser->membership_number;
        }

        return UserManagement::generateMembershipNumber();
    }

    private function previewMembershipNumber(): ?string
    {
        if ($this->role !== User::ROLE_MEMBER) {
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
                'role' => 'Negalite keisti savo rolės.',
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

    private function syncMembershipForSavedUser(User $user): ?LibraryMembership
    {
        if (! UserManagement::requiresLibrary($this->role) || ! $this->libraryId) {
            return null;
        }

        if ($this->managedUser && $this->membershipId) {
            $membership = LibraryMembership::query()->findOrFail($this->membershipId);

            return app(TransferLibraryMembershipAction::class)->execute(
                Auth::user(),
                $membership,
                (int) $this->libraryId,
                $this->branchId ? (int) $this->branchId : null
            );
        }

        return UserManagement::syncLibraryMembership(
            $user,
            (int) $this->libraryId,
            $this->branchId ? (int) $this->branchId : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function auditChangesForUser(User $user, array $dirtyFields): array
    {
        $businessFields = collect($dirtyFields)
            ->reject(fn (string $field) => in_array($field, ['updated_at', 'remember_token'], true))
            ->values()
            ->all();

        $visibleFields = array_values(array_diff($businessFields, ['password']));
        $summary = AuditLogChanges::fromModel($user, $visibleFields, [
            'name' => 'Vardas',
            'email' => 'El. paštas',
            'role' => 'Rolė',
            'phone' => 'Telefonas',
            'is_active' => 'Globalus vartotojo aktyvumas',
            'membership_number' => 'Nario numeris',
        ]);

        if (in_array('password', $businessFields, true)) {
            $summary['changed_fields'][] = 'password';
            $summary['changes'][] = [
                'field' => 'password',
                'label' => 'Slaptažodis',
                'from' => '-',
                'to' => 'Pakeistas',
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function membershipSnapshot(User $user): ?array
    {
        if (! UserManagement::requiresLibrary($this->role) || ! $this->libraryId) {
            return null;
        }

        $membership = $this->membershipId
            ? LibraryMembership::query()->whereKey($this->membershipId)->first()
            : LibraryMembership::query()
                ->where('user_id', $user->id)
                ->where('library_id', $this->libraryId)
                ->first();

        return $membership?->only(['library_id', 'branch_id', 'membership_number', 'is_active']);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @return array<string, mixed>
     */
    private function auditChangesForMembership(?array $before, ?LibraryMembership $membership): array
    {
        if (! $membership) {
            return ['changed_fields' => [], 'changes' => []];
        }

        $after = $membership->only(['library_id', 'branch_id', 'membership_number', 'is_active']);
        $fields = collect(array_keys($after))
            ->filter(fn (string $field) => ($before[$field] ?? null) != $after[$field])
            ->values()
            ->all();

        $changes = collect($fields)
            ->map(fn (string $field) => [
                'field' => 'membership.'.$field,
                'label' => [
                    'branch_id' => 'Narystės filialas',
                    'library_id' => 'Narystės biblioteka',
                    'membership_number' => 'Narystės numeris',
                    'is_active' => 'Narystės aktyvumas',
                ][$field],
                'from' => $this->formatMembershipAuditValue($field, $before[$field] ?? null),
                'to' => $this->formatMembershipAuditValue($field, $after[$field] ?? null),
            ])
            ->all();

        return [
            'changed_fields' => array_map(fn (string $field) => 'membership.'.$field, $fields),
            'changes' => $changes,
        ];
    }

    private function formatMembershipAuditValue(string $field, mixed $value): string
    {
        if ($field === 'library_id') {
            return $value
                ? (Library::query()->whereKey($value)->value('name') ?: (string) $value)
                : '-';
        }

        if ($field === 'branch_id') {
            return $value
                ? (Branch::query()->whereKey($value)->value('name') ?: (string) $value)
                : '-';
        }

        if ($field === 'is_active') {
            return AuditLogChanges::formatValue('is_active', $value);
        }

        return AuditLogChanges::formatValue($field, $value);
    }

    private function editableMembershipFor(User $actor, User $managedUser): ?LibraryMembership
    {
        if (! UserManagement::requiresLibrary($managedUser->role)) {
            return null;
        }

        if (! $actor->isSuperAdmin()) {
            return UserManagement::membershipForActor($actor, $managedUser);
        }

        return $managedUser->activeLibraryMemberships()
            ->orderBy('joined_at')
            ->orderBy('id')
            ->first()
            ?: $managedUser->libraryMemberships()
                ->orderBy('joined_at')
                ->orderBy('id')
                ->first();
    }

    /**
     * @param  array<string, mixed>  ...$summaries
     * @return array<string, mixed>
     */
    private function mergeAuditChanges(array ...$summaries): array
    {
        return [
            'changed_fields' => collect($summaries)
                ->flatMap(fn (array $summary) => $summary['changed_fields'] ?? [])
                ->unique()
                ->values()
                ->all(),
            'changes' => collect($summaries)
                ->flatMap(fn (array $summary) => $summary['changes'] ?? [])
                ->values()
                ->all(),
        ];
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
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasAnother) {
            throw ValidationException::withMessages([
                'role' => 'Sistemoje turi likti bent vienas aktyvus superadmin.',
            ]);
        }
    }

    private function accountTypeLabel(string $role): string
    {
        return match ($role) {
            User::ROLE_SUPER_ADMIN => 'Superadministratorius',
            User::ROLE_ADMIN => 'Administratorius',
            User::ROLE_STAFF => 'Darbuotojas',
            User::ROLE_MEMBER => 'Skaitytojas',
            default => $role,
        };
    }
}
