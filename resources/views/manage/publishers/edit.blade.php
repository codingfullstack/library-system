<x-layouts::app :title="'Redaguoti leidyklą'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti leidyklą" :description="$publisher->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Leidyklos informacija" description="Atnaujink leidyklos duomenis.">
                <form method="POST" action="{{ route('manage.publishers.update', $publisher) }}">
                    @method('PUT')
                    @include('manage.publishers._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Leidyklos ir susijusiu knygų istorija viename bloke.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiai leidyklai audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>







