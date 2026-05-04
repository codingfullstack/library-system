<x-layouts::app :title="'Redaguoti knyga'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Valdymas"
            title="Redaguoti knyga"
            :description="$book->title"
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Knygos informacija" description="Atnaujink katalogo irasa.">
                <form method="POST" action="{{ route('manage.books.update', $book) }}">
                    @method('PUT')
                    @include('manage.books._form', ['submitLabel' => 'Issaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmu istorija" description="Knygos, rezervaciju, isdavimu ir egzemplioriu istorija vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siai knygai audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
