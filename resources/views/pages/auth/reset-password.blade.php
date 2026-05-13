<x-layouts::auth :title="__('Atkurti slaptažodį')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Atkurti slaptažodį')" :description="__('Žemiau įveskite naują slaptažodį')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- El. paštas Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('El. paštas')"
                type="email"
                required
                autocomplete="email"
            />

            <!-- Slaptažodis -->
            <flux:input
                name="password"
                :label="__('Slaptažodis')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Slaptažodis')"
                viewable
            />

            <!-- Patvirtinti slaptažodį -->
            <flux:input
                name="password_confirmation"
                :label="__('Pakartokite slaptažodį')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Pakartokite slaptažodį')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Atkurti slaptažodį') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>







