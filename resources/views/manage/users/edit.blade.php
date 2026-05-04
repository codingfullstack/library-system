<x-layouts::app :title="'Redaguoti vartotoja'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti vartotoja" :description="$managedUser->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Vartotojo informacija" description="Atnaujink role, biblioteka, aktyvuma ir prisijungimo duomenis.">
                <livewire:manage.users.user-form :managed-user="$managedUser" :key="'manage-user-edit-'.$managedUser->id" />
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmu istorija" description="Vartotojo profilio, rezervaciju ir isduotu knygu istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siam vartotojui audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
