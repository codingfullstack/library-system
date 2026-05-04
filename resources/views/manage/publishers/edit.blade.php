<x-layouts::app :title="'Redaguoti leidykla'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti leidykla" :description="$publisher->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Leidyklos informacija" description="Atnaujink leidyklos duomenis.">
                <form method="POST" action="{{ route('manage.publishers.update', $publisher) }}">
                    @method('PUT')
                    @include('manage.publishers._form', ['submitLabel' => 'Issaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmu istorija" description="Leidyklos ir susijusiu knygu istorija viename bloke.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siai leidyklai audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
