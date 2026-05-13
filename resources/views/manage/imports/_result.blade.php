@php
    $importReport = session('import_report');
@endphp

@if(is_array($importReport) && ! empty($importReport['details']))
    @php
        $failedCount = (int) ($importReport['failed'] ?? 0);
    @endphp
    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-800">
            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Importo rezultatai</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $importReport['title'] ?? 'Importas' }}: sukurta {{ $importReport['created'] ?? 0 }}, praleista {{ $importReport['skipped'] ?? 0 }}, klaidų {{ $failedCount }}.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Eilute</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Įrašas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Pastaba</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach($importReport['details'] as $detail)
                        @php
                            $status = (string) ($detail['status'] ?? '');
                            $badgeClasses = match ($status) {
                                'sukurta' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                'klaida' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                            };
                        @endphp
                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $detail['line'] ?? '-' }}</td>
                            <td class="px-4 py-4 align-middle">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                    {{ ucfirst((string) ($detail['status'] ?? '-')) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-middle text-sm font-medium text-zinc-950 dark:text-white">
                                {{ $detail['label'] ?? '-' }}
                            </td>
                            <td class="px-4 py-4 align-middle text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $detail['message'] ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif







