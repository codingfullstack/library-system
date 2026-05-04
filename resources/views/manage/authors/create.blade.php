<x-layouts::app :title="'Naujas autorius'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Naujas autorius" description="Sukurk naują autorių katalogui." />

        <x-ui.panel title="Autoriaus informacija" description="Vardas ir trumpa biografija.">
            <form method="POST" action="{{ route('manage.authors.store') }}">
                @include('manage.authors._form', ['submitLabel' => 'Sukurti autorių'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
