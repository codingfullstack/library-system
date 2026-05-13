<x-layouts::app :title="'Nauja leidykla'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Nauja leidykla" description="Sukurk naują leidyklos įrašą." />

        <x-ui.panel title="Leidyklos informacija" description="Pavadinimas ir šalis.">
            <form method="POST" action="{{ route('manage.publishers.store') }}">
                @include('manage.publishers._form', ['submitLabel' => 'Sukurti leidyklą'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>







