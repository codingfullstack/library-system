@php
    use App\Models\AuditLog;

    $actionLabels = AuditLog::actionLabels();
@endphp

<x-layouts::app :title="'Auditu zurnalas'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Auditu zurnalas</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Perziurekite ir valdykite visus svarbiausius sistemos veiksmus vienoje vietoje.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'audit-logs'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                <flux:icon.clipboard-document class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visi irasai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['total'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pagal dabartinius filtrus</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.plus class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sukurta</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['created'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Kurimo veiksmai</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.pencil-square class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Atnaujinta</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['updated'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pakeitimai</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">
                                <flux:icon.x-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pasalinta</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['deleted'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Trinimai ir atsaukimai</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.audit-logs.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_220px_220px_180px_180px_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input
                                    type="text"
                                    name="search"
                                    value="{{ request('search', '') }}"
                                    class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950"
                                    placeholder="Ieskoti pagal aprasyma, darbuotoja ar biblioteka..."
                                >
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            <div class="xl:min-w-0">
                                <select name="action" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Veiksmas</option>
                                    @foreach ($actionLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <select name="library_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Biblioteka</option>
                                    @foreach ($libraries as $library)
                                        <option value="{{ $library->id }}" @selected((string) request('library_id') === (string) $library->id)>
                                            {{ $library->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <input type="date" name="date_from" value="{{ request('date_from', '') }}" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                            </div>

                            <div class="xl:min-w-0">
                                <input type="date" name="date_to" value="{{ request('date_to', '') }}" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                            </div>

                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>

                            <a href="{{ route('manage.audit-logs.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">
                                Isvalyti
                            </a>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Irasai</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Rasta: {{ $auditLogs->total() }}</p>
                    </div>

                    <div class="p-5">
                        @include('manage.audit-logs._list', [
                            'auditLogs' => $auditLogs,
                            'emptyTitle' => 'Irasu nerasta',
                            'emptyDescription' => 'Pagal dabartinius filtrus veiksmu istorijoje nieko neradome.',
                        ])
                    </div>

                    @if ($auditLogs->isNotEmpty())
                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $auditLogs->firstItem() }}-{{ $auditLogs->lastItem() }} is {{ $auditLogs->total() }}</div>
                            <div>{{ $auditLogs->links() }}</div>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>
