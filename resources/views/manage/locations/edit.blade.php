<x-layouts::app :title="'Redaguoti vietą'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti vietą" :description="$location->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Vietos informacija" description="Atnaujink vietos duomenis.">
                <form method="POST" action="{{ route('manage.locations.update', $location) }}">
                    @method('PUT')
                    @include('manage.locations._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Vietos ir joje laikomų kopijų istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiai vietai audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>







