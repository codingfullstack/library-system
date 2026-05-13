<x-layouts::app :title="'Naujas vartotojas'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Naujas vartotojas" description="Sukurk naują sistemos naudotoją pagal savo rolę ir bibliotekos teises." />

        <x-ui.panel title="Vartotojo informacija" description="Nustatyk rolę, prisijungimo duomenis ir aktyvumą.">
            <livewire:manage.users.user-form />
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>







