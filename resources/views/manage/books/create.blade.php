<x-layouts::app :title="'Nauja knyga'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Valdymas"
            title="Nauja knyga"
            description="Sukurk naują katalogo įrašą."
        />

        <x-ui.panel title="Knygos informacija" description="Užpildyk pagrindinius bibliografinius duomenis.">
            <form method="POST" action="{{ route('manage.books.store') }}">
                @include('manage.books._form', ['submitLabel' => 'Sukurti knygą'])
            </form>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
