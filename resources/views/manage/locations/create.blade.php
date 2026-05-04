<x-layouts::app :title="'Nauja vieta'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Nauja vieta" description="Sukurk naują lentyną, kambarį ar kitą saugojimo vietą." />

        <x-ui.panel title="Vietos informacija" description="Filialas, pavadinimas ir fizinė vieta.">
            <form method="POST" action="{{ route('manage.locations.store') }}">
                @include('manage.locations._form', ['submitLabel' => 'Sukurti vietą'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
