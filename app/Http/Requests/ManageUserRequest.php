<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $actor = $this->user();
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->id : null;
        $allowedRoles = $actor ? UserManagement::manageableRoles($actor) : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetId)],
            'role' => ['required', Rule::in($allowedRoles)],
            'library_id' => [
                Rule::requiredIf(fn () => UserManagement::requiresLibrary((string) $this->input('role'))),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [
                $targetId ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $actor = $this->user();
                $role = (string) $this->input('role');
                $libraryId = $this->input('library_id');

                if (! $actor) {
                    return;
                }

                if (! UserManagement::canManageRole($actor, $role)) {
                    $validator->errors()->add('role', 'Negalite priskirti sios roles.');
                }

                if (! $actor->isSuperAdmin() && UserManagement::requiresLibrary($role)) {
                    if ((int) $libraryId !== (int) $actor->library_id) {
                        $validator->errors()->add('library_id', 'Galite priskirti tik savo biblioteka.');
                    }
                }

                if ($role === 'super_admin' && $libraryId) {
                    $validator->errors()->add('library_id', 'Superadmin rolei biblioteka nepriskiriama.');
                }
            },
        ];
    }
}
