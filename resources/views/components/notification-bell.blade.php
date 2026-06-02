@php
    $user = auth()->user();
    $notifications = $user?->notifications()->limit(8)->get() ?? collect();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
@endphp

@auth
    <div class="relative" data-notification-bell>
        <flux:dropdown position="bottom" align="end">
            <button
                type="button"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-emerald-500/60 dark:hover:text-emerald-300"
                aria-label="Pranesimai"
            >
                <flux:icon.bell class="size-5" />
                <span
                    class="{{ $unreadCount > 0 ? '' : 'hidden' }} absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[11px] font-bold text-white ring-2 ring-white dark:ring-zinc-900"
                    data-notification-count="{{ $unreadCount }}"
                >{{ $unreadCount }}</span>
            </button>

            <div class="w-80 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900 sm:w-96">
                <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <div>
                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">Pranesimai</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            <span data-notification-count="{{ $unreadCount }}">{{ $unreadCount }}</span> neskaityti
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                        data-mark-all-notifications-read
                    >
                        Pazymeti kaip perskaityta
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto" data-notification-list>
                    @forelse($notifications as $notification)
                        @php
                            $data = $notification->data ?? [];
                            $title = $data['title'] ?? 'Naujas pranesimas';
                            $message = $data['message'] ?? '';
                            $url = $data['url'] ?? route('notifications.index');
                        @endphp

                        <div class="border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-zinc-800 {{ $notification->read_at ? '' : 'bg-emerald-50/45 dark:bg-emerald-500/10' }}" data-notification-item="{{ $notification->id }}">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-zinc-300 dark:bg-zinc-700' : 'bg-emerald-500' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ $url }}" class="block truncate text-sm font-semibold text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                        {{ $title }}
                                    </a>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ $message }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <span class="text-[11px] text-zinc-500 dark:text-zinc-500">{{ $notification->created_at?->format('Y-m-d H:i') }}</span>
                                        @unless($notification->read_at)
                                            <button type="button" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 dark:text-emerald-300" data-mark-notification-read="{{ $notification->id }}">
                                                Pazymeti kaip perskaityta
                                            </button>
                                        @endunless
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">Pranesimu nera</div>
                    @endforelse
                </div>

                <a href="{{ route('notifications.index') }}" class="block border-t border-zinc-100 px-4 py-3 text-center text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Visi pranesimai
                </a>
            </div>
        </flux:dropdown>
    </div>

@endauth
