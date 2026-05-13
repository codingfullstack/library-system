<x-layouts::app :title="'Nauja biblioteka'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Nauja biblioteka" description="Sukurkite biblioteką ir nustatykite jos matomumą sistemoje." />

        <x-ui.panel title="Bibliotekos informacija" description="Pavadinimas, kodas, kontaktai ir būsena.">
            <form method="POST" action="{{ route('manage.libraries.store') }}">
                @include('manage.libraries._form', ['submitLabel' => 'Sukurti biblioteką'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>







