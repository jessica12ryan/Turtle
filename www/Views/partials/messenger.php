<?php
$messengerStrings = [
    'messages' => __('Messages'),
    'online' => __('Online'),
    'offline' => __('Offline'),
    'lastSeen' => __('Last Seen:'),
    'never' => __('Never'),
    'noUsers' => __('No other users available.'),
    'selectUser' => __('Select a user to start a conversation.'),
    'noMessages' => __('No messages yet.'),
    'typeMessage' => __('Type a message...'),
    'send' => __('Send'),
    'close' => __('Close'),
    'sendFailed' => __('Unable to send message.'),
    'loadFailed' => __('Unable to load messages.'),
    'you' => __('You'),
    'resize' => __('Resize panel'),
];
?>
<input type="hidden" id="messenger-csrf" value="<?= csrf_token() ?>">

<div id="messenger-panel"
     x-data='messenger(<?= json_encode($messengerStrings, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
     x-cloak
     class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-white shadow-2xl border-l border-gray-200 dark:bg-slate-900 dark:border-slate-700 flex flex-col"
     :class="open ? '' : 'hidden'"
     :style="panelStyle">
    <div class="hidden sm:block absolute left-0 top-0 bottom-0 w-1 cursor-col-resize z-10 hover:bg-blue-500"
         @mousedown="startResize($event)" :title="strings.resize"></div>
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white" x-text="strings.messages"></h2>
        <button type="button" @click="open = false" class="text-gray-500 hover:text-gray-800 dark:hover:text-white" :aria-label="strings.close">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="flex-1 min-h-0 flex">
        <div class="w-1/3 border-r border-gray-200 dark:border-slate-700 overflow-y-auto">
            <template x-if="users.length === 0">
                <p class="p-4 text-sm text-gray-500" x-text="strings.noUsers"></p>
            </template>
            <template x-for="user in users" :key="user.id">
                <button type="button" @click="selectUser(user)"
                        class="w-full text-left px-3 py-3 border-b border-gray-100 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800"
                        :class="selected && selected.id === user.id ? 'bg-blue-50 dark:bg-slate-800' : ''">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-800 dark:text-white break-words leading-tight min-w-0" x-text="user.name" :title="user.name"></span>
                        <span x-show="user.unread > 0" class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5" x-text="user.unread"></span>
                    </div>
                    <div class="flex items-center mt-1">
                        <span class="w-2 h-2 rounded-full mr-1" :class="user.online ? 'bg-green-500' : 'bg-gray-400'"></span>
                        <span class="text-xs text-gray-500" x-text="user.online ? strings.online : (strings.lastSeen + ' ' + (user.last_seen || strings.never))"></span>
                    </div>
                </button>
            </template>
        </div>

        <div class="w-2/3 flex flex-col min-h-0">
            <template x-if="!selected">
                <div class="flex-1 flex items-center justify-center p-4 text-center text-sm text-gray-500" x-text="strings.selectUser"></div>
            </template>

            <template x-if="selected">
                <div class="flex-1 min-h-0 flex flex-col">
                    <div class="px-3 py-2 border-b border-gray-200 dark:border-slate-700 flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full" :class="selected.online ? 'bg-green-500' : 'bg-gray-400'"></span>
                        <span class="text-sm font-medium text-gray-800 dark:text-white break-words min-w-0" x-text="selected.name" :title="selected.name"></span>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2" x-ref="thread">
                        <template x-if="messages.length === 0">
                            <p class="text-sm text-gray-500 text-center" x-text="strings.noMessages"></p>
                        </template>
                        <template x-for="message in messages" :key="message.id">
                            <div class="flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                                     :class="message.mine ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-slate-800 dark:text-slate-200'">
                                    <p class="whitespace-pre-wrap break-words" x-text="message.body"></p>
                                    <p class="text-xs mt-1 opacity-70" x-text="message.time"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <form class="p-3 border-t border-gray-200 dark:border-slate-700 flex items-center space-x-2 shrink-0" @submit.prevent="send()">
                        <input type="text" x-model="text" :placeholder="strings.typeMessage"
                               class="flex-1 min-w-0 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                        <button type="submit" class="shrink-0 whitespace-nowrap bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700" x-text="strings.send"></button>
                    </form>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function messenger(strings) {
    const url = (path) => (window.baseUrl || '') + path;
    const csrf = () => document.getElementById('messenger-csrf').value;

    function playSound() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = new Ctx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.26);
            osc.onended = () => ctx.close();
        } catch (e) {}
    }

    return {
        strings: strings,
        open: false,
        users: [],
        selected: null,
        messages: [],
        text: '',
        unread: 0,
        lastUnread: 0,
        panelWidth: (() => {
            const saved = parseInt(localStorage.getItem('turtle.messengerWidth'), 10);
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
                localStorage.setItem('turtle.messengerWidth', String(this.panelWidth));
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },
        init() {
            // Resolve the Alpine reactive proxy at call time: init() receives
            // the raw data object, whose mutations are not rendered.
            const proxy = () => Alpine.$data(document.getElementById('messenger-panel'));
            window.addEventListener('messenger:toggle', () => {
                const data = proxy();
                if (!data) return;
                data.open = !data.open;
                if (data.open) data.poll(true);
            });
            setTimeout(() => {
                const data = proxy();
                if (!data) return;
                data.loadUsers();
                data.poll();
                this.timer = setInterval(() => {
                    const current = proxy();
                    if (current) current.poll();
                }, 5000);
            }, 0);
        },
        badge(id, count) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = count > 99 ? '99+' : String(count);
            el.style.display = count > 0 ? (id === 'message-badge' ? 'flex' : 'flex') : 'none';
        },
        async poll(forceThread = false) {
            try {
                const res = await fetch(url('/messages/unread'), { credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                this.unread = data.messages || 0;
                this.badge('message-badge', this.unread);
                this.badge('notification-badge', data.notifications || 0);
                if (this.unread > this.lastUnread && this.lastUnread !== 0) playSound();
                this.lastUnread = this.unread;
                await this.loadUsers();
                if (this.open && this.selected && (forceThread || this.unread > 0)) {
                    await this.refreshThread();
                }
            } catch (e) {}
        },
        async loadUsers() {
            try {
                const res = await fetch(url('/messages/users'), { credentials: 'same-origin' });
                if (!res.ok) return;
                this.users = await res.json();
                if (this.selected) {
                    const fresh = this.users.find(u => u.id === this.selected.id);
                    if (fresh) this.selected = fresh;
                }
            } catch (e) {}
        },
        async refreshThread() {
            if (!this.selected) return;
            try {
                const body = new URLSearchParams();
                body.append('user_id', this.selected.id);
                const res = await fetch(url('/messages/thread') + '?' + body.toString(), { credentials: 'same-origin' });
                if (!res.ok) throw new Error('failed');
                const data = await res.json();
                const grew = (data.messages || []).length > this.messages.length;
                this.messages = data.messages || [];
                this.selected.unread = 0;
                if (grew) {
                    this.$nextTick(() => { if (this.$refs.thread) this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight; });
                }
            } catch (e) {
                alert(this.strings.loadFailed);
            }
        },
        async selectUser(user) {
            this.selected = user;
            this.messages = [];
            await this.refreshThread();
            await this.poll();
        },
        async send() {
            const body = this.text.trim();
            if (!body || !this.selected) return;
            try {
                const data = new URLSearchParams();
                data.append('_csrf', csrf());
                data.append('recipient_id', this.selected.id);
                data.append('body', body);
                const res = await fetch(url('/messages/send'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data.toString()
                });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.error || 'failed');
                this.messages.push(result.message);
                this.text = '';
                this.$nextTick(() => { if (this.$refs.thread) this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight; });
            } catch (e) {
                alert(this.strings.sendFailed);
            }
        }
    };
}
</script>
