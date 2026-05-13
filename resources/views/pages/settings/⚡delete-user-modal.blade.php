<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Ar tikrai norite ištrinti paskyrą?') }}</flux:heading>

            <flux:subheading>
                {{ __('Ištrynus paskyrą, visi jos duomenys bus pašalinti visam laikui. Įveskite slaptažodį, kad patvirtintumėte paskyros ištrynimą.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Slaptažodis')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Atšaukti') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Ištrinti paskyrą') }}
            </flux:button>
        </div>
    </form>
</flux:modal>







