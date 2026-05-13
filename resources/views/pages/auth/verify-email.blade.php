<x-layouts::auth :title="__('El. pašto patvirtinimas')">
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('Patvirtinkite el. pašto adresą paspausdami nuorodą, kurią ką tik išsiuntėme.') }}
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                {{ __('Nauja patvirtinimo nuoroda išsiųsta registracijos metu nurodytu el. pašto adresu.') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Siųsti patvirtinimo laišką dar kartą') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    {{ __('Atsijungti') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>







