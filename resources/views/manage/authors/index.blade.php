<x-layouts::app :title="'Autorių valdymas'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Autoriai" description="Tvarkyk autorių sąrašą knygų katalogui.">
            <x-slot:actions>
                <a href="{{ route('manage.authors.create') }}" class="app-button-primary">Naujas autorius</a>
            </x-slot:actions>
        </x-ui.page-header>

        @if(session('success'))
            <x-ui.alert>{{ session('success') }}</x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
        @endif

        <x-ui.panel class="mb-6" title="Paieška" description="Ieškok pagal autoriaus vardą arba biografiją.">
            <form method="GET" action="{{ route('manage.authors.index') }}" class="flex flex-col gap-3 sm:flex-row">
                <input type="text" name="search" value="{{ request('search') }}" class="app-input" placeholder="Autoriaus vardas ar biografija">
                <button type="submit" class="app-button-primary">Ieškoti</button>
                <a href="{{ route('manage.authors.index') }}" class="app-button-secondary">Išvalyti</a>
            </form>
        </x-ui.panel>

        <x-ui.panel body-class="p-0">
            @if($authors->count())
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead class="app-table-head">
                            <tr>
                                <th class="app-th">Autorius</th>
                                <th class="app-th">Knygų skaičius</th>
                                <th class="app-th text-right">Veiksmai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach($authors as $author)
                                <tr class="transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                    <td class="app-td">
                                        <div class="font-semibold text-zinc-950 dark:text-white">{{ $author->name }}</div>
                                        @if($author->bio)
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($author->bio, 120) }}</div>
                                        @endif
                                    </td>
                                    <td class="app-td">{{ $author->books_count }}</td>
                                    <td class="app-td">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('manage.authors.edit', $author) }}" class="app-button-secondary">Redaguoti</a>
                                            <form method="POST" action="{{ route('manage.authors.destroy', $author) }}" onsubmit="return confirm('Ar tikrai nori ištrinti šį autorių?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="app-button-danger">Ištrinti</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    {{ $authors->links() }}
                </div>
            @else
                <div class="p-5">
                    <x-ui.empty-state title="Autorių nerasta" description="Sukurk pirmą autorių arba pakeisk paiešką." />
                </div>
            @endif
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>







