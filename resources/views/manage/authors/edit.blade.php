<x-layouts::app :title="'Redaguoti autoriu'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti autoriu" :description="$author->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Autoriaus informacija" description="Atnaujink autoriaus duomenis.">
                <form method="POST" action="{{ route('manage.authors.update', $author) }}">
                    @method('PUT')
                    @include('manage.authors._form', ['submitLabel' => 'Issaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmu istorija" description="Autoriaus ir susijusiu knygu istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siam autoriui audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
