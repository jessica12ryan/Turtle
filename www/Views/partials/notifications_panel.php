<?php
$panelStrings = [
    'title' => __('Notifications'),
    'markAll' => __('Mark all as read'),
    'markRead' => __('Mark read'),
    'showRead' => __('Show read'),
    'showingRead' => __('Showing read'),
    'none' => __('No notifications.'),
    'viewAll' => __('View all notifications'),
    'close' => __('Close'),
    'resize' => __('Resize panel'),
];
?>
<input type="hidden" id="notifications-csrf" value="<?= csrf_token() ?>">

<div id="notifications-panel"
     x-data='notificationsPanel(<?= json_encode($panelStrings, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
     x-cloak
     class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-white shadow-2xl border-l border-gray-200 dark:bg-slate-900 dark:border-slate-700 flex flex-col"
     :class="open ? '' : 'hidden'"
     :style="panelStyle">
    <div class="hidden sm:block absolute left-0 top-0 bottom-0 w-1 cursor-col-resize z-10 hover:bg-blue-500"
         @mousedown="startResize($event)" :title="strings.resize"></div>

    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white" x-text="strings.title"></h2>
        <button type="button" @click="open = false" class="text-gray-500 hover:text-gray-800 dark:hover:text-white" :aria-label="strings.close">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="px-4 py-2 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between space-x-2">
        <button type="button" @click="toggleRead()" class="text-xs text-blue-600 dark:text-blue-400 hover:underline" x-text="showRead ? strings.showingRead : strings.showRead"></button>
        <button type="button" @click="markAll()" class="text-xs text-blue-600 dark:text-blue-400 hover:underline" x-text="strings.markAll"></button>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-200 dark:divide-slate-700">
        <template x-if="notifications.length === 0">
            <p class="p-6 text-center text-sm text-gray-500 dark:text-gray-400" x-text="strings.none"></p>
        </template>
        <template x-for="notification in notifications" :key="notification.id">
            <div class="px-4 py-3" :class="notification.read ? '' : 'bg-blue-50 dark:bg-blue-950/30'">
                <div class="flex justify-between items-start">
                    <p class="text-sm font-medium text-gray-800 dark:text-white" x-text="notification.label"></p>
                    <span class="text-xs text-gray-400 ml-2 whitespace-nowrap" x-text="notification.time"></span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-wrap break-words" x-text="notification.data"></p>
                <button x-show="!notification.read" type="button" @click="markRead(notification.id)"
                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-2" x-text="strings.markRead"></button>
            </div>
        </template>
    </div>

    <div class="px-4 py-3 border-t border-gray-200 dark:border-slate-700">
        <a href="/notifications" class="text-sm text-blue-600 dark:text-blue-400 hover:underline" x-text="strings.viewAll"></a>
    </div>
</div>

<script>
function notificationsPanel(strings) {
    const url = (path) => (window.baseUrl || '') + path;
    const csrf = () => document.getElementById('notifications-csrf').value;
    const xhrHeaders = () => ({ 'X-Requested-With': 'XMLHttpRequest' });
    const body = () => {
        const data = new URLSearchParams();
        data.append('_csrf', csrf());
        return data.toString();
    };

    return {
        strings: strings,
        open: false,
        showRead: false,
        notifications: [],
        panelWidth: (() => {
            const saved = parseInt(localStorage.getItem('turtle.panelWidth') || localStorage.getItem('turtle.messengerWidth'), 10);
            return isNaN(saved) ? 384 : saved;
        })(),
        get panelStyle() {
            if (window.innerWidth < 640) return '';
            return 'width: ' + this.panelWidth + 'px';
        },
        startResize(event) {
            event.preventDefault();
            const startX = event.clientX;
            const startWidth = this.panelWidth;
            const onMove = (moveEvent) => {
                const next = startWidth + (startX - moveEvent.clientX);
                this.panelWidth = Math.max(320, Math.min(window.innerWidth * 0.8, Math.round(next)));
            };
            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                localStorage.setItem('turtle.panelWidth', String(this.panelWidth));
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },
        init() {
            const proxy = () => Alpine.$data(document.getElementById('notifications-panel'));
            window.addEventListener('notifications:toggle', () => {
                const data = proxy();
                if (!data) return;
                data.open = !data.open;
                const messenger = document.getElementById('messenger-panel');
                if (data.open && messenger && Alpine.$data(messenger)) {
                    Alpine.$data(messenger).open = false;
                }
                if (data.open) data.load();
            });
            window.addEventListener('messenger:toggle', () => {
                const data = proxy();
                if (data) data.open = false;
            });
            setTimeout(() => {
                const data = proxy();
                if (!data) return;
                data.poll();
                data.timer = setInterval(() => { const current = proxy(); if (current) current.poll(); }, 15000);
            }, 0);
        },
        badge(count) {
            const el = document.getElementById('notification-badge');
            if (!el) return;
            el.textContent = count > 99 ? '99+' : String(count);
            el.style.display = count > 0 ? 'flex' : 'none';
        },
        async poll() {
            try {
                const res = await fetch(url('/notifications/unread'), { credentials: 'same-origin', headers: xhrHeaders() });
                if (!res.ok) return;
                const data = await res.json();
                this.badge(data.count || 0);
                if (this.open) await this.load();
            } catch (e) {}
        },
        async load() {
            try {
                const res = await fetch(url('/notifications/list') + '?show_read=' + (this.showRead ? '1' : '0'), {
                    credentials: 'same-origin',
                    headers: xhrHeaders()
                });
                if (!res.ok) return;
                this.notifications = await res.json();
            } catch (e) {}
        },
        toggleRead() {
            this.showRead = !this.showRead;
            this.load();
        },
        async markRead(id) {
            try {
                const res = await fetch(url('/notifications/' + id + '/read'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: Object.assign(xhrHeaders(), { 'Content-Type': 'application/x-www-form-urlencoded' }),
                    body: body()
                });
                if (res.ok) {
                    await this.load();
                    await this.poll();
                }
            } catch (e) {}
        },
        async markAll() {
            try {
                const res = await fetch(url('/notifications/read-all'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: Object.assign(xhrHeaders(), { 'Content-Type': 'application/x-www-form-urlencoded' }),
                    body: body()
                });
                if (res.ok) {
                    await this.load();
                    await this.poll();
                }
            } catch (e) {}
        }
    };
}
</script>
