<?php $chatToken = (string) $token; ?>
<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100"><?= __('Conversation') ?></h1>
            <p class="text-sm text-gray-500 mt-1 dark:text-slate-400"><?= __('About') ?>: <?= h($chat['property_name']) ?> &middot; <?= h($chat['guest_name']) ?></p>
        </div>
        <a href="/listings" class="text-sm text-blue-600 hover:underline dark:text-blue-400"><?= __('Back to listings') ?></a>
    </div>

    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3 mb-4 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200">
        <?= __('Only you can see this conversation. Save this page address to return to it later.') ?>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden dark:bg-slate-800">
        <div id="guest-chat-messages" class="p-5 space-y-3" style="max-height: 55vh; overflow-y: auto;" data-testid="chat-messages">
            <?php foreach ($messages as $message): ?>
                <div class="flex <?= $message['mine'] ? 'justify-end' : 'justify-start' ?>" data-message-id="<?= (int) $message['id'] ?>">
                    <div class="max-w-[80%] rounded-lg px-4 py-2 <?= $message['mine'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-100' ?>">
                        <div class="whitespace-pre-wrap break-words text-sm"><?= h($message['body']) ?></div>
                        <div class="text-[10px] mt-1 opacity-70"><?= h($message['time']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form id="guest-chat-form" method="POST" action="/listings/chat/<?= h($chatToken) ?>/send" class="border-t border-gray-200 p-4 flex gap-2 dark:border-slate-700">
            <input type="hidden" id="guest-chat-csrf" name="_csrf" value="<?= csrf_token() ?>">
            <textarea id="guest-chat-body" name="body" rows="1" required maxlength="2000" placeholder="<?= __('Type a message...') ?>" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
            <button type="submit" data-testid="chat-send" class="bg-blue-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-blue-700"><?= __('Send') ?></button>
        </form>
        <p id="guest-chat-error" class="hidden px-4 pb-4 text-sm text-red-600"></p>
    </div>
</div>
<script>
(function () {
    const token = <?= json_encode($chatToken) ?>;
    const url = (path) => (window.baseUrl || '') + path;
    const list = document.getElementById('guest-chat-messages');
    const form = document.getElementById('guest-chat-form');
    const body = document.getElementById('guest-chat-body');
    const errorEl = document.getElementById('guest-chat-error');
    const csrf = document.getElementById('guest-chat-csrf').value;
    const sendError = <?= json_encode(__('Unable to send message.')) ?>;
    let lastId = 0;

    function scrollToBottom() {
        list.scrollTop = list.scrollHeight;
    }

    function render(messages) {
        list.textContent = '';
        messages.forEach(function (message) {
            const wrap = document.createElement('div');
            wrap.className = 'flex ' + (message.mine ? 'justify-end' : 'justify-start');
            wrap.setAttribute('data-message-id', String(message.id));
            const bubble = document.createElement('div');
            bubble.className = 'max-w-[80%] rounded-lg px-4 py-2 ' + (message.mine ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-100');
            const text = document.createElement('div');
            text.className = 'whitespace-pre-wrap break-words text-sm';
            text.textContent = message.body;
            const time = document.createElement('div');
            time.className = 'text-[10px] mt-1 opacity-70';
            time.textContent = message.time;
            bubble.appendChild(text);
            bubble.appendChild(time);
            wrap.appendChild(bubble);
            list.appendChild(wrap);
            lastId = Math.max(lastId, Number(message.id) || 0);
        });
        scrollToBottom();
    }

    async function poll() {
        try {
            const res = await fetch(url('/listings/chat/' + token + '/messages'), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.messages && data.messages.length) render(data.messages);
        } catch (e) {}
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const message = body.value.trim();
        if (!message) return;
        errorEl.classList.add('hidden');
        const data = new URLSearchParams();
        data.append('_csrf', csrf);
        data.append('body', message);
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, { 'Content-Type': 'application/x-www-form-urlencoded' }),
                body: data.toString()
            });
            if (!res.ok) {
                const payload = await res.json().catch(() => ({}));
                errorEl.textContent = payload.error || sendError;
                errorEl.classList.remove('hidden');
                return;
            }
            body.value = '';
            await poll();
        } catch (e) {
            errorEl.textContent = sendError;
            errorEl.classList.remove('hidden');
        }
    });

    scrollToBottom();
    setInterval(poll, 5000);
})();
</script>
