import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const authUserId = document.querySelector('meta[name="auth-user-id"]')?.content;
const routes = {
    recent: document.querySelector('meta[name="notifications-recent-url"]')?.content,
    unreadCount: document.querySelector('meta[name="notifications-unread-count-url"]')?.content,
    markAllRead: document.querySelector('meta[name="notifications-mark-all-read-url"]')?.content,
    markReadTemplate: document.querySelector('meta[name="notifications-mark-read-url-template"]')?.content,
    broadcastingAuth: document.querySelector('meta[name="broadcasting-auth-url"]')?.content,
};

const state = {
    unreadCount: Number(document.querySelector('[data-notification-count]')?.dataset.notificationCount || 0),
    items: [],
};

const request = async (url, options = {}) => {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
        ...options,
    });

    if (!response.ok) {
        throw new Error(`Request failed with ${response.status}`);
    }

    return response.json();
};

const formatTime = (value) => {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('lt-LT', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const normaliseNotification = (notification) => ({
    id: notification.id,
    kind: notification.kind || notification.type,
    title: notification.title || 'Naujas pranesimas',
    message: notification.message || '',
    url: notification.url || '/notifications',
    read_at: notification.read_at || null,
    created_at: notification.created_at || new Date().toISOString(),
});

const updateUnreadCount = (count) => {
    state.unreadCount = Math.max(Number(count || 0), 0);

    document.querySelectorAll('[data-notification-count]').forEach((element) => {
        element.dataset.notificationCount = String(state.unreadCount);
        element.textContent = String(state.unreadCount);
        element.classList.toggle('hidden', state.unreadCount === 0);
    });
};

const renderDropdown = () => {
    const list = document.querySelector('[data-notification-list]');

    if (!list) {
        return;
    }

    if (state.items.length === 0) {
        list.innerHTML = '<div class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">Pranesimu nera</div>';
        return;
    }

    list.innerHTML = state.items.map((item) => `
        <div class="group border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-zinc-800 ${item.read_at ? '' : 'bg-emerald-50/45 dark:bg-emerald-500/10'}" data-notification-item="${item.id}">
            <div class="flex items-start gap-3">
                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${item.read_at ? 'bg-zinc-300 dark:bg-zinc-700' : 'bg-emerald-500'}"></span>
                <div class="min-w-0 flex-1">
                    <a href="${escapeHtml(item.url)}" class="block truncate text-sm font-semibold text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">${escapeHtml(item.title)}</a>
                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-zinc-600 dark:text-zinc-400">${escapeHtml(item.message)}</p>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <span class="text-[11px] text-zinc-500 dark:text-zinc-500">${formatTime(item.created_at)}</span>
                        ${item.read_at ? '' : `<button type="button" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 dark:text-emerald-300" data-mark-notification-read="${item.id}">Pazymeti kaip perskaityta</button>`}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
};

const showToast = (notification) => {
    const stack = document.querySelector('[data-notification-toasts]');

    if (!stack) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-zinc-200 bg-white p-4 shadow-lg ring-1 ring-black/5 transition dark:border-zinc-700 dark:bg-zinc-900';
    toast.innerHTML = `
        <div class="flex gap-3">
            <span class="mt-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0" /></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-zinc-950 dark:text-white">${escapeHtml(notification.title)}</p>
                <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-400">${escapeHtml(notification.message)}</p>
            </div>
        </div>
    `;

    stack.appendChild(toast);
    setTimeout(() => toast.remove(), 6000);
};

const fetchRecent = async () => {
    if (!routes.recent) {
        return;
    }

    const data = await request(routes.recent);
    state.items = (data.items || []).map(normaliseNotification);
    updateUnreadCount(data.unread_count);
    renderDropdown();
};

const fetchUnreadCount = async () => {
    if (!routes.unreadCount) {
        return;
    }

    const data = await request(routes.unreadCount);
    updateUnreadCount(data.unread_count);
};

const refreshNotificationsPage = async () => {
    const target = document.querySelector('[data-notifications-page-content]');

    if (!target) {
        return;
    }

    const response = await fetch(window.location.href, {
        headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return;
    }

    const html = await response.text();
    const documentFragment = new DOMParser().parseFromString(html, 'text/html');
    const freshContent = documentFragment.querySelector('[data-notifications-page-content]');

    if (freshContent) {
        target.innerHTML = freshContent.innerHTML;
    }
};

const pollNotifications = async () => {
    if (routes.recent) {
        await fetchRecent();
        await refreshNotificationsPage();
        return;
    }

    await fetchUnreadCount();
    await refreshNotificationsPage();
};

const prependNotification = (notification) => {
    const item = normaliseNotification(notification);
    state.items = [item, ...state.items.filter((existing) => existing.id !== item.id)].slice(0, 8);
    updateUnreadCount(state.unreadCount + 1);
    renderDropdown();
    showToast(item);
    refreshNotificationsPage().catch(() => {});
};

document.addEventListener('click', async (event) => {
    const markButton = event.target.closest('[data-mark-notification-read]');
    const markAllButton = event.target.closest('[data-mark-all-notifications-read]');

    if (markButton && routes.markReadTemplate) {
        const id = markButton.dataset.markNotificationRead;
        const data = await request(routes.markReadTemplate.replace('__ID__', id), { method: 'PATCH' });
        state.items = state.items.map((item) => item.id === id ? { ...item, read_at: new Date().toISOString() } : item);
        updateUnreadCount(data.unread_count);
        renderDropdown();
        refreshNotificationsPage().catch(() => {});
    }

    if (markAllButton && routes.markAllRead) {
        event.preventDefault();
        const data = await request(routes.markAllRead, { method: 'POST' });
        state.items = state.items.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() }));
        updateUnreadCount(data.unread_count);
        renderDropdown();
        refreshNotificationsPage().catch(() => {});
    }
});

if (authUserId) {
    fetchRecent().catch(() => {});
    window.Pusher = Pusher;

    try {
        if (import.meta.env.VITE_REVERB_APP_KEY) {
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
                wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
                wssPort: import.meta.env.VITE_REVERB_PORT || 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: routes.broadcastingAuth || '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            });

            window.Echo.private(`users.${authUserId}`)
                .notification((notification) => prependNotification(notification));
        }
    } catch (error) {
        console.warn('Realtime notifications are unavailable. Falling back to polling.', error);
    }

    setInterval(() => {
        pollNotifications().catch(() => {});
    }, 30000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollNotifications().catch(() => {});
        }
    });
}
