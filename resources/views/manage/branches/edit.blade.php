<x-layouts::app :title="'Redaguoti filialą'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti filialą" :description="$branch->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Filialo informacija" description="Atnaujink filialo duomenis.">
                <form method="POST" action="{{ route('manage.branches.update', $branch) }}">
                    @method('PUT')
                    @include('manage.branches._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Filialo, jo vietų ir kopijų pakeitimai vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiam filialui audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>







