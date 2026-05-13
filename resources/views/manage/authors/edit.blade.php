<x-layouts::app :title="'Redaguoti autorių'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti autorių" :description="$author->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Autoriaus informacija" description="Atnaujink autoriaus duomenis.">
                <form method="POST" action="{{ route('manage.authors.update', $author) }}">
                    @method('PUT')
                    @include('manage.authors._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Autoriaus ir susijusiu knygų istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiam autoriui audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>







