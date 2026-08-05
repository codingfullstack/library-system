<x-layouts::app :title="'Redaguoti vartotoją'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti vartotoją" :description="$managedUser->name" />

        @if(session('info'))
            <x-ui.alert type="info">{{ session('info') }}</x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Vartotojo informacija" description="Atnaujink leistinus profilio, filialo, narystės ir prisijungimo duomenis.">
                <livewire:manage.users.user-form :managed-user="$managedUser" :key="'manage-user-edit-'.$managedUser->id" />
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Vartotojo profilio, rezervacijų ir išduotų knygų istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiam vartotojui audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
