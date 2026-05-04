@props([
    'auditLogs' => collect(),
    'emptyTitle' => 'Irasu nerasta',
    'emptyDescription' => 'Siam irasui veiksmu istorijos dar nera.',
    'tab' => null,
])

@php
    use App\Support\AuditLogChanges;
@endphp

@if($auditLogs->isEmpty())
    <x-ui.empty-state :title="$emptyTitle" :description="$emptyDescription" />
@else
    <div class="space-y-3">
        @foreach($auditLogs as $auditLog)
            @php
                $tone = $auditLog->actionTone();

                $toneClasses = match ($tone) {
                    'created' => [
                        'card' => 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/10',
                        'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                        'dot' => 'bg-emerald-500',
                        'label' => 'Sukurta',
                    ],
                    'updated' => [
                        'card' => 'border-amber-200 bg-amber-50/35 dark:border-amber-900/40 dark:bg-amber-950/10',
                        'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                        'dot' => 'bg-amber-500',
                        'label' => 'Atnaujinta',
                    ],
                    'deleted' => [
                        'card' => 'border-red-200 bg-red-50/35 dark:border-red-900/40 dark:bg-red-950/10',
                        'badge' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                        'dot' => 'bg-red-500',
                        'label' => 'Pasalinta',
                    ],
                    default => [
                        'card' => 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950/40',
                        'badge' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
                        'dot' => 'bg-sky-500',
                        'label' => 'Veiksmas',
                    ],
                };
            @endphp

            <div class="rounded-xl border p-5 {{ $toneClasses['card'] }}">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $toneClasses['badge'] }}">
                                <span class="h-2 w-2 rounded-full {{ $toneClasses['dot'] }}"></span>
                                {{ $auditLog->actionLabel() }}
                            </span>

                            <span class="inline-flex rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-900/70 dark:text-zinc-300 dark:ring-zinc-800">
                                {{ $toneClasses['label'] }}
                            </span>

                            @if ($auditLog->library)
                                <span class="inline-flex rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-900/70 dark:text-zinc-300 dark:ring-zinc-800">
                                    {{ $auditLog->library->name }}
                                </span>
                            @endif
                        </div>

                        <div class="text-base font-semibold text-zinc-950 dark:text-white">
                            {{ $auditLog->description }}
                        </div>

                        <div class="grid gap-3 lg:grid-cols-2">
                            <div class="rounded-lg bg-white/70 p-3 ring-1 ring-zinc-200 dark:bg-zinc-900/50 dark:ring-zinc-800">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Atliko
                                </div>
                                <div class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $auditLog->actor?->name ?: 'Sistema' }}
                                </div>
                                @if ($auditLog->actor?->email)
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $auditLog->actor->email }}
                                    </div>
                                @endif
                            </div>

                            @if (! empty($auditLog->metadata['target_member_name']) || ! empty($auditLog->metadata['target_status_label']) || ! empty($auditLog->metadata['inventory_code']))
                                <div class="rounded-lg bg-white/70 p-3 ring-1 ring-zinc-200 dark:bg-zinc-900/50 dark:ring-zinc-800">
                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        Papildoma informacija
                                    </div>

                                    @if (! empty($auditLog->metadata['target_member_name']))
                                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                            Narys: <span class="font-medium text-zinc-950 dark:text-white">{{ $auditLog->metadata['target_member_name'] }}</span>
                                        </div>
                                    @endif

                                    @if (! empty($auditLog->metadata['target_status_label']))
                                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                            Naujas statusas: <span class="font-medium text-zinc-950 dark:text-white">{{ $auditLog->metadata['target_status_label'] }}</span>
                                        </div>
                                    @endif

                                    @if (! empty($auditLog->metadata['inventory_code']))
                                        <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                            Kopija: <span class="font-medium text-zinc-950 dark:text-white">{{ $auditLog->metadata['inventory_code'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if (! empty($auditLog->metadata['snapshot']))
                            <div class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Issaugotas kontekstas
                                </div>

                                <div class="grid gap-2 md:grid-cols-2">
                                    @foreach ($auditLog->metadata['snapshot'] as $field => $value)
                                        @php
                                            $formattedValue = \App\Support\AuditLogChanges::stringify($value);
                                        @endphp

                                        @if ($formattedValue !== '-')
                                            <div class="rounded-lg bg-white/80 p-3 ring-1 ring-zinc-200 dark:bg-zinc-900/60 dark:ring-zinc-800">
                                                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                                    {{ AuditLogChanges::fieldLabel((string) $field) }}
                                                </div>
                                                <div class="mt-1 text-sm text-zinc-900 dark:text-white">
                                                    {{ $formattedValue }}
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (! empty($auditLog->metadata['changes']))
                            <div class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Kas pasikeite
                                </div>

                                <div class="grid gap-2">
                                    @foreach ($auditLog->metadata['changes'] as $change)
                                        <div class="rounded-lg bg-white/80 p-3 ring-1 ring-zinc-200 dark:bg-zinc-900/60 dark:ring-zinc-800">
                                            <div class="text-sm font-semibold text-zinc-950 dark:text-white">
                                                {{ $change['label'] ?? $change['field'] ?? 'Laukas' }}
                                            </div>
                                            <div class="mt-2 grid gap-2 lg:grid-cols-[1fr_auto_1fr] lg:items-center">
                                                <div class="rounded-md bg-zinc-100 px-3 py-2 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                    {{ $change['from'] ?? '-' }}
                                                </div>
                                                <div class="text-center text-xs font-semibold uppercase tracking-wide text-zinc-400">
                                                    i
                                                </div>
                                                <div class="rounded-md bg-white px-3 py-2 text-sm font-medium text-zinc-950 ring-1 ring-zinc-200 dark:bg-zinc-950 dark:text-white dark:ring-zinc-700">
                                                    {{ $change['to'] ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @elseif (! empty($auditLog->metadata['changed_fields']))
                            <div class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Pakeisti laukai
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @foreach ($auditLog->metadata['changed_fields'] as $field)
                                        <span class="inline-flex rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-900/70 dark:text-zinc-300 dark:ring-zinc-800">
                                            {{ AuditLogChanges::fieldLabel((string) $field) }}
                                        </span>
                                    @endforeach
                                </div>

                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Sio senesnio iraso pilnos reiksmiu istorijos dar neturime. Nauji atnaujinimai rodomi su pakeitimu is -> i.
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-lg bg-white/80 px-3 py-2 text-xs text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-900/70 dark:text-zinc-400 dark:ring-zinc-800 xl:text-right">
                        {{ $auditLog->created_at?->format('Y-m-d H:i:s') }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (method_exists($auditLogs, 'hasPages') && $auditLogs->hasPages())
        <div class="pt-4">
            @if($tab)
                {{ $auditLogs->appends(['tab' => $tab])->links() }}
            @else
                {{ $auditLogs->links() }}
            @endif
        </div>
    @endif
@endif
