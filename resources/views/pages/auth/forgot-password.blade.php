<x-layouts::auth :title="__('Pamiršote slaptažodį')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Pamiršote slaptažodį')" :description="__('Įveskite el. paštą, kad gautumėte slaptažodžio atkūrimo nuorodą')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- El. paštas Address -->
            <flux:input
                name="email"
                :label="__('El. pašto adresas')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Siųsti slaptažodžio atkūrimo nuorodą') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Arba grįžkite į') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('prisijungimą') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>







