<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Išvaizdos nustatymai')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Išvaizdos nustatymai') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Išvaizda')" :subheading="__('Atnaujinkite paskyros išvaizdos nustatymus')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Šviesi') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Tamsi') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Sistemos') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>







