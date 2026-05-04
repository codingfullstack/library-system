<x-layouts::app :title="'Redaguoti filiala'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti filiala" :description="$branch->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Filialo informacija" description="Atnaujink filialo duomenis.">
                <form method="POST" action="{{ route('manage.branches.update', $branch) }}">
                    @method('PUT')
                    @include('manage.branches._form', ['submitLabel' => 'Issaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmu istorija" description="Filialo, jo vietu ir egzemplioriu pakeitimai vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siam filialui audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
