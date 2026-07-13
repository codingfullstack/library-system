<x-layouts::app :title="'Redaguoti knygą'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Valdymas"
            title="Redaguoti knygą"
            :description="$book->title"
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Knygos informacija" description="Atnaujink katalogo įrašą.">
                <form method="POST" action="{{ route('manage.books.update', $book) }}">
                    @method('PUT')
                    @include('manage.books._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Knygos, rezervacijų, išdavimų ir kopijų istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiai knygai audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>







