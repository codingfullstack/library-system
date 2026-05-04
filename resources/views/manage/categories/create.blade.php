<x-layouts::app :title="'Nauja kategorija'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Nauja kategorija" description="Sukurk naują kategoriją katalogui." />

        <x-ui.panel title="Kategorijos informacija" description="Pavadinimas, slug ir trumpas aprašymas.">
            <form method="POST" action="{{ route('manage.categories.store') }}">
                @include('manage.categories._form', ['submitLabel' => 'Sukurti kategoriją'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
