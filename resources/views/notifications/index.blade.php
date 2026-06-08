<x-layouts::app :title="'Pranešimai'">
    @php
        $categoryMeta = static function ($type): array {
            return match ($type) {
                'loan_overdue', 'book_due_soon' => ['label' => 'Priminimas', 'category' => 'reminder', 'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300', 'iconWrap' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300', 'icon' => 'bell-alert'],
                'reservation_ready', 'reservation_cancelled', 'reservation_fulfilled' => ['label' => 'Rezervacija', 'category' => 'reservation', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'iconWrap' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'icon' => 'folder-git-2'],
                'book_returned' => ['label' => 'Informacija', 'category' => 'info', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'iconWrap' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'icon' => 'book-open-text'],
                'system', 'new_user', 'qr_scan' => ['label' => 'Sistemos', 'category' => 'system', 'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300', 'iconWrap' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300', 'icon' => 'cog-6-tooth'],
                'report_ready', 'issuance_summary' => ['label' => 'Ataskaita', 'category' => 'report', 'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300', 'iconWrap' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300', 'icon' => 'clipboard-document'],
                default => ['label' => 'Kiti', 'category' => 'other', 'badge' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300', 'iconWrap' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300', 'icon' => 'ellipsis-horizontal-circle'],
            };
        };
    @endphp

    <x-ui.page class="max-w-none overflow-x-hidden px-3 py-0 sm:px-6 lg:px-8">
        <div class="min-w-0 bg-[#f7f8fa] py-6 dark:bg-zinc-950 sm:py-8">
            <div class="mx-auto w-full max-w-[1500px] min-w-0 space-y-5 sm:space-y-6" data-notifications-page-content>
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-3xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Pranešimai</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Peržiūrėkite ir valdykite sistemos pranešimus</p>
                    </div>

                    <form method="POST" action="{{ route('notifications.mark-all-read', request()->query()) }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit" data-mark-all-notifications-read class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20 sm:w-auto">
                            <flux:icon.check class="size-4" />
                            Pažymėti visus kaip perskaitytus
                        </button>
                    </form>
                </div>

                <div class="grid min-w-0 gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-[22px] border border-emerald-200 bg-white px-5 py-4 shadow-sm dark:border-emerald-900/30 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.inbox-stack class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visi</div>
                                <div class="mt-1 text-3xl font-bold text-emerald-600 dark:text-emerald-300">{{ $notifications->total() }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <flux:icon.bell-alert class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Neskaityti</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $unreadCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                <flux:icon.cog-6-tooth class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sistemos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $systemCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.bell class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Priminimai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $reminderCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                <flux:icon.ellipsis-horizontal-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Kiti</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $otherCount }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="w-full min-w-0 overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                        <form method="GET" action="{{ route('notifications.index') }}" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_200px_minmax(0,1fr)_180px]">
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kategorija</label>
                                <select name="category" class="app-input h-10 rounded-xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="all" @selected(($filters['category'] ?? 'all') === 'all')>Visos kategorijos</option>
                                    <option value="system" @selected(($filters['category'] ?? '') === 'system')>Sistemos</option>
                                    <option value="reminder" @selected(($filters['category'] ?? '') === 'reminder')>Priminimai</option>
                                    <option value="reservation" @selected(($filters['category'] ?? '') === 'reservation')>Rezervacijos</option>
                                    <option value="warning" @selected(($filters['category'] ?? '') === 'warning')>Įspėjimai</option>
                                    <option value="info" @selected(($filters['category'] ?? '') === 'info')>Informacija</option>
                                    <option value="report" @selected(($filters['category'] ?? '') === 'report')>Ataskaitos</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</label>
                                <select name="status" class="app-input h-10 rounded-xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Visos būsenos</option>
                                    <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Neskaityti</option>
                                    <option value="read" @selected(($filters['status'] ?? '') === 'read')>Perskaityti</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Data</label>
                                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="app-input h-10 rounded-xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Rikiuoti pagal</label>
                                <select name="sort" class="app-input h-10 rounded-xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Naujausi pirmiau</option>
                                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Seniausi pirmiau</option>
                                    <option value="unread_first" @selected(($filters['sort'] ?? '') === 'unread_first')>Neskaityti pirmiau</option>
                                </select>
                            </div>

                            <div class="flex items-end gap-3">
                                <button type="submit" class="app-button-secondary h-10 w-full rounded-xl px-4 sm:w-auto">
                                    <flux:icon.funnel class="mr-2 size-4" />
                                    Filtruoti
                                </button>
                            </div>
                        </form>
                    </div>

                    @if($notifications->count())
                        <div class="grid gap-3 p-4 md:hidden">
                            @foreach($notifications as $notification)
                                @php
                                    $data = $notification->data ?? [];
                                    $kind = $data['kind'] ?? $notification->type;
                                    $title = $data['title'] ?? 'Naujas pranešimas';
                                    $message = $data['message'] ?? '';
                                    $url = $data['url'] ?? route('notifications.index');
                                    $meta = $categoryMeta($kind);
                                @endphp

                                <article class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/50">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl {{ $meta['iconWrap'] }}">
                                            @switch($meta['icon'])
                                                @case('bell-alert')
                                                    <flux:icon.bell-alert class="size-5" />
                                                    @break
                                                @case('folder-git-2')
                                                    <flux:icon.folder-git-2 class="size-5" />
                                                    @break
                                                @case('book-open-text')
                                                    <flux:icon.book-open-text class="size-5" />
                                                    @break
                                                @case('cog-6-tooth')
                                                    <flux:icon.cog-6-tooth class="size-5" />
                                                    @break
                                                @case('clipboard-document')
                                                    <flux:icon.clipboard-document class="size-5" />
                                                    @break
                                                @default
                                                    <flux:icon.ellipsis-horizontal-circle class="size-5" />
                                            @endswitch
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $meta['badge'] }}">
                                                    {{ $meta['label'] }}
                                                </span>
                                                @if($notification->read_at)
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                        Perskaitytas
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">
                                                        <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                                                        Neskaitytas
                                                    </span>
                                                @endif
                                            </div>

                                            <a href="{{ $url }}" class="mt-3 block break-words text-sm font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                {{ $title }}
                                            </a>

                                            @if($message !== '')
                                                <p class="mt-1 break-words text-sm leading-5 text-zinc-600 dark:text-zinc-400">{{ $message }}</p>
                                            @endif

                                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span>{{ $notification->created_at?->format('Y-m-d H:i') }}</span>
                                                @unless($notification->read_at)
                                                    <button type="button" class="font-semibold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300" data-mark-notification-read="{{ $notification->id }}">
                                                        Pažymėti kaip perskaitytą
                                                    </button>
                                                @endunless
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Pranešimas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kategorija</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Data</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($notifications as $notification)
                                        @php
                                            $data = $notification->data ?? [];
                                            $kind = $data['kind'] ?? $notification->type;
                                            $title = $data['title'] ?? 'Naujas pranešimas';
                                            $message = $data['message'] ?? '';
                                            $url = $data['url'] ?? route('notifications.index');
                                            $meta = $categoryMeta($kind);
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-top">
                                                <div class="flex items-center gap-3">
                                                    <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                                    <span class="h-2 w-2 rounded-full {{ $notification->read_at ? 'bg-zinc-300 dark:bg-zinc-600' : 'bg-violet-500' }}"></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-top">
                                                <div class="flex items-start gap-3">
                                                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $meta['iconWrap'] }}">
                                                        @switch($meta['icon'])
                                                            @case('bell-alert')
                                                                <flux:icon.bell-alert class="size-5" />
                                                                @break
                                                            @case('folder-git-2')
                                                                <flux:icon.folder-git-2 class="size-5" />
                                                                @break
                                                            @case('book-open-text')
                                                                <flux:icon.book-open-text class="size-5" />
                                                                @break
                                                            @case('cog-6-tooth')
                                                                <flux:icon.cog-6-tooth class="size-5" />
                                                                @break
                                                            @case('clipboard-document')
                                                                <flux:icon.clipboard-document class="size-5" />
                                                                @break
                                                            @default
                                                                <flux:icon.ellipsis-horizontal-circle class="size-5" />
                                                        @endswitch
                                                    </span>
                                                    <div class="min-w-0">
                                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $title }}</div>
                                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $message }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-top">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $meta['badge'] }}">
                                                    {{ $meta['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-top text-sm text-zinc-600 dark:text-zinc-400">
                                                <div>{{ $notification->created_at?->diffForHumans() }}</div>
                                                <div class="mt-1 text-xs">{{ $notification->created_at?->format('Y-m-d H:i') }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-top">
                                                @if($notification->read_at)
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                        Perskaitytas
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-2 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">
                                                        <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                                                        Neskaitytas
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 align-top">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ $url }}" title="Peržiūrėti pranešimą" aria-label="Peržiūrėti pranešimą" class="transition hover:text-zinc-800 dark:hover:text-zinc-200">
                                                        <flux:icon.eye class="size-4" />
                                                    </a>
                                                    @unless($notification->read_at)
                                                        <button type="button" title="Pažymėti kaip perskaitytą" aria-label="Pažymėti kaip perskaitytą" class="transition hover:text-emerald-700 dark:hover:text-emerald-300" data-mark-notification-read="{{ $notification->id }}">
                                                            <flux:icon.check class="size-4" />
                                                        </button>
                                                    @endunless
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $notifications->firstItem() }}-{{ $notifications->lastItem() }} is {{ $notifications->total() }}</div>
                            <div>{{ $notifications->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state
                                title="Pranešimų nėra"
                                description="Kai sistema turės ką pranešti, nauji įrašai atsiras čia."
                            />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







