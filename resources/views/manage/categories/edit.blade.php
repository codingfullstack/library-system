<x-layouts::app :title="'Redaguoti kategoriją'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti kategoriją" :description="$category->name" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Kategorijos informacija" description="Atnaujink kategorijos duomenis.">
                <form method="POST" action="{{ route('manage.categories.update', $category) }}">
                    @method('PUT')
                    @include('manage.categories._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmų istorija" description="Kategorijos ir susijusiu knygų pakeitimai vienoje vietoje.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmų dar nėra',
                        'emptyDescription' => 'Šiai kategorijai audito įrašų dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>







