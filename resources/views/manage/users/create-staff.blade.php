<x-layouts::app :title="'Sukurti darbuotojo paskyrą'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Sukurti darbuotojo paskyrą" description="Sukurk atskirą darbo paskyrą savo bibliotekos darbuotojui." />

        <x-ui.panel title="Darbuotojo informacija" description="Paskyros tipas nustatomas serveryje. Pasirink priskirtą filialą ir prisijungimo duomenis.">
            <livewire:manage.users.user-form :force-staff="true" />
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
