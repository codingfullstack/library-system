<x-layouts::app :title="'Naujas vartotojas'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Naujas vartotojas" description="Sukurk skaitytojo paskyrą arba, jei esi superadministratorius, globaliai valdyk paskyros tipą." />

        <x-ui.panel title="Vartotojo informacija" description="Nustatyk prisijungimo duomenis, biblioteką ir leistinus paskyros laukus.">
            <livewire:manage.users.user-form />
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
