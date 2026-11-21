<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100"><?= __('Conversation') ?></h1>
        <p class="text-sm text-gray-500 mt-1 dark:text-slate-400">
            <?= __('About') ?>: <?= h($chat['property_name']) ?> &middot; <?= h($chat['guest_name']) ?> (<?= h($chat['guest_email']) ?>)
        </p>
    </div>
    <a href="/guest-chats" class="text-sm text-blue-600 hover:underline dark:text-blue-400"><?= __('Back to Guest Chats') ?></a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden max-w-4xl dark:bg-slate-800">
    <div id="guest-chat-messages" class="p-5 space-y-3" style="max-height: 55vh; overflow-y: auto;">
        <?php foreach ($messages as $message): ?>
            <div class="flex <?= $message['mine'] ? 'justify-end' : 'justify-start' ?>" data-message-id="<?= (int) $message['id'] ?>">
                <div class="max-w-[80%] rounded-lg px-4 py-2 <?= $message['mine'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-100' ?>">
                    <div class="whitespace-pre-wrap break-words text-sm"><?= h($message['body']) ?></div>
                    <div class="text-[10px] mt-1 opacity-70"><?= h($message['time']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <form id="guest-chat-reply-form" method="POST" action="/guest-chats/<?= (int) $chat['id'] ?>/reply" class="border-t border-gray-200 p-4 flex gap-2 dark:border-slate-700">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <textarea id="guest-chat-reply-body" name="body" rows="1" required maxlength="2000" placeholder="<?= __('Type a message...') ?>" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
        <button type="submit" data-testid="guest-reply" class="bg-blue-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-blue-700"><?= __('Reply') ?></button>
    </form>
</div>

<script>
(function () {
    const chatId = <?= (int) $chat['id'] ?>;
    const url = (path) => (window.baseUrl || '') + path;
    const list = document.getElementById('guest-chat-messages');
    const form = document.getElementById('guest-chat-reply-form');

    function scrollToBottom() {
        list.scrollTop = list.scrollHeight;
    }
    function render(messages) {
        list.textContent = '';
        messages.forEach(function (message) {
            const wrap = document.createElement('div');
            wrap.className = 'flex ' + (message.mine ? 'justify-end' : 'justify-start');
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
        });
        scrollToBottom();
    }
    async function poll() {
        try {
            const res = await fetch(url('/guest-chats/' + chatId + '/messages'), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.messages) render(data.messages);
            }
        } catch (e) {}
    }
    scrollToBottom();
    setInterval(poll, 5000);
})();
</script>
