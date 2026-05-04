<x-layouts::app :title="'Redaguoti egzemplioriu'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Valdymas"
            title="Redaguoti egzemplioriu"
            :description="$bookCopy->book?->title ?: 'Kopijos duomenys'"
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]">
            <x-ui.panel title="Kopijos informacija" description="Atnaujink vieta, bukle ir kitus kopijos duomenis.">
                <livewire:manage.book-copies.book-copy-form :book-copy="$bookCopy" :key="'manage-book-copy-edit-'.$bookCopy->id" />
            </x-ui.panel>

            @if(auth()->user()?->isSuperAdmin())
                <x-ui.panel title="Veiksmu istorija" description="Kopijos statuso, redagavimo ir kitu veiksmu seka.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siam egzemplioriui audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            @endif
        </div>
    </x-ui.page>
</x-layouts::app>
