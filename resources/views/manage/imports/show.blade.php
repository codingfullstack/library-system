<x-layouts::app :title="$config['title']">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1100px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $config['title'] }}</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $config['description'] }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('manage.imports.template', $resource) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Atsisiųsti šabloną
                        </a>

                        <a href="{{ route($config['index_route']) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.arrow-left class="size-4" />
                            Grįžti į sąrašą
                        </a>
                    </div>
                </div>

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">CSV ikelimas</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Tinka `.csv` ir `.txt` failai. Skirtukas gali būti kablelis arba kabliataskis.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('manage.imports.store', $resource) }}" enctype="multipart/form-data" class="space-y-5 px-5 py-5">
                        @csrf

                        @if($libraries->isNotEmpty())
                            <div>
                                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Biblioteka</label>
                                <select name="library_id" class="app-input h-12 rounded-2xl border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Pasirinkite biblioteka</option>
                                    @foreach($libraries as $library)
                                        <option value="{{ $library->id }}" {{ (string) old('library_id') === (string) $library->id ? 'selected' : '' }}>
                                            {{ $library->name }} ({{ $library->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    CSV faile bibliotekos nerašome. Ji paimama iš čia pasirinktos bibliotekos.
                                </p>
                                @error('library_id')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Failas</label>
                            <input type="file" name="file" accept=".csv,.txt" class="app-input h-12 rounded-2xl border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950">
                            @error('file')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/50">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">Laukeliai faile</div>

                            @if(! empty($config['schema_fields']))
                                <div class="mt-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">Tiesioginiai lenteles laukai</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($config['schema_fields'] as $header)
                                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-medium text-zinc-700 shadow-sm dark:bg-zinc-900 dark:text-zinc-200">{{ $header }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(! empty($config['relation_fields']))
                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">Pagalbiniai ryšio laukai</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($config['relation_fields'] as $header)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 shadow-sm dark:bg-emerald-950/40 dark:text-emerald-300">{{ $header }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(! empty($config['notes']))
                                <div class="mt-4 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                    @foreach($config['notes'] as $note)
                                        <p>{{ $note }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                                <flux:icon.arrow-up-tray class="size-4" />
                                Importuoti
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







