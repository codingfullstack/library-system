<x-layouts::auth :title="__('Pakartokite slaptažodį')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Pakartokite slaptažodį')"
            :description="__('Tai saugi sistemos sritis. Prieš tęsdami patvirtinkite slaptažodį.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Slaptažodis')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Slaptažodis')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Patvirtinti') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>







