<x-layouts::app :title="'Naujas filialas'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Naujas filialas" description="Sukurk naują bibliotekos filialą." />

        <x-ui.panel title="Filialo informacija" description="Pavadinimas, kodas ir vieta.">
            <form method="POST" action="{{ route('manage.branches.store') }}">
                @include('manage.branches._form', ['submitLabel' => 'Sukurti filialą'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>







