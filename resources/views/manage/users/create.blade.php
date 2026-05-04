<x-layouts::app :title="'Naujas vartotojas'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Naujas vartotojas" description="Sukurk nauja sistemos naudotoja pagal savo role ir bibliotekos teises." />

        <x-ui.panel title="Vartotojo informacija" description="Nustatyk role, prisijungimo duomenis ir aktyvuma.">
            <livewire:manage.users.user-form />
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
