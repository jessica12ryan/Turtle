<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('AI Assistant') ?></h1>
    <p class="text-gray-500 mt-1"><?= __('Ask questions, get insights, and automate tasks with the AI Assistant.') ?></p>

</div>

<div class="bg-white rounded-lg shadow flex flex-col md:flex-row h-[65vh]"
     x-data='chat()'
     x-init="init()">
    <!-- Chat list -->
    <div class="w-full md:w-64 border-b md:border-b-0 md:border-r p-3 flex flex-col min-h-0">
        <button type="button" @click="newChat"
                class="w-full bg-indigo-600 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 mb-3"><?= __('New chat') ?></button>
        <div class="flex-1 overflow-y-auto space-y-1">
            <template x-if="conversations.length === 0">
                <p class="text-xs text-gray-500 px-2 py-4"><?= __('No chats yet') ?></p>
            </template>
            <template x-for="c in conversations" :key="c.id">
                <div class="flex items-center rounded-lg"
                     :class="c.id === activeId ? 'bg-indigo-50' : 'hover:bg-gray-50'">
                    <button type="button" @click="selectChat(c.id)" class="flex-1 text-left text-sm px-2 py-2 truncate" x-text="c.title"></button>
                    <button type="button" @click="renameChat(c)" class="px-1 text-gray-400 hover:text-indigo-600" data-testid="rename-chat" title="<?= h(__('Rename chat')) ?>" aria-label="<?= h(__('Rename chat')) ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button type="button" @click="deleteChat(c.id)" class="px-2 text-gray-400 hover:text-red-600" aria-label="<?= h(__('Delete chat')) ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Conversation -->
    <div class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto p-6 space-y-4" x-ref="messages">
            <template x-if="activeId === null && !loading">
                <div class="text-center py-12">
                    <p class="text-gray-500"><?= __('Start a new chat') ?></p>
                </div>
            </template>
            <template x-for="(msg, i) in messages" :key="i">
                <div class="flex items-start space-x-3" :class="msg.role === 'user' ? 'justify-end' : ''">
                    <template x-if="msg.role === 'assistant'">
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </div>
                    </template>
                    <div class="flex flex-col min-w-0 max-w-[75%]" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                        <div :class="msg.role === 'user' ? 'bg-blue-100 text-gray-800 rounded-2xl rounded-tr-sm' : 'bg-gray-100 text-gray-800 rounded-2xl rounded-tl-sm'" class="px-4 py-2.5 text-sm leading-relaxed prose prose-sm max-w-none" x-html="msg.role === 'assistant' ? renderMarkdown(msg.content) : escapeHtml(msg.content)"></div>
                        <p class="text-xs text-gray-400 mt-1" x-show="msg.at_label" x-text="msg.at_label"></p>
                    </div>
                    <template x-if="msg.role === 'user'">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0"><?= strtoupper(substr(\App\Core\Auth::instance()->user()['name'], 0, 1)) ?></div>
                    </template>
                </div>
            </template>
            <template x-if="loading">
                <div class="text-gray-500 text-sm italic"><?= __('Thinking...') ?></div>
            </template>
        </div>

        <div x-show="error" x-cloak class="px-6 py-2 bg-red-50 border-t border-red-200">
            <p class="text-sm text-red-700" x-text="error"></p>
        </div>

        <div class="border-t p-4">
            <form @submit.prevent="sendMessage" class="flex space-x-3">
                <input type="text" x-model="input" x-ref="input" placeholder="<?= __('Ask the AI Assistant...') ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 text-sm" :disabled="loading || activeId === null">
                <button type="submit" :disabled="loading || !input.trim() || activeId === null" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700 font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed"><?= __('Send') ?></button>
            </form>
        </div>
    </div>
</div>

<p class="text-xs text-gray-500 text-center mt-4">
    <?= __('Responses are generated by AI and may not be accurate. Verify important information.') ?>
</p>

<script nonce="<?= h(csp_nonce()) ?>">
const turtleAiStrings = {
    deleteChat: <?= json_encode(__('Delete chat'), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    deleteConfirm: <?= json_encode(__('Delete this chat?'), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    error: <?= json_encode(__('Connection error'), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    renamePrompt: <?= json_encode(__('Enter a name for this chat'), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>
};
const turtleAiCsrf = <?= json_encode(csrf_token(), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function chat() {
    return {
        conversations: <?= json_encode($conversations, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        activeId: null,
        messages: [],
        input: '',
        loading: false,
        error: '',
        init() {
            if (this.conversations.length > 0) this.selectChat(this.conversations[0].id);
        },
        post(path, body) {
            const form = new FormData();
            form.append('_csrf', turtleAiCsrf);
            Object.keys(body || {}).forEach(k => form.append(k, body[k]));
            return fetch((window.baseUrl || '') + path, {
                method: 'POST', body: form, headers: { 'Accept': 'application/json' }
            }).then(r => r.json());
        },
        selectChat(id) {
            if (this.loading) return;
            this.loading = true;
            this.error = '';
            fetch((window.baseUrl || '') + '/ai-assistant/conversations/' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (data.error) { this.error = data.error; return; }
                    this.activeId = data.id;
                    this.messages = Array.isArray(data.messages) ? data.messages : [];
                    this.scrollDown();
                })
                .catch(() => { this.error = turtleAiStrings.error; })
                .finally(() => { this.loading = false; });
        },
        newChat() {
            this.post('/ai-assistant/conversations', {})
                .then(data => {
                    if (data.error) { this.error = data.error; return; }
                    this.conversations.unshift({ id: data.id, title: data.title });
                    this.selectChat(data.id);
                })
                .catch(() => { this.error = turtleAiStrings.error; });
        },
        renameChat(chat) {
            const name = window.prompt(turtleAiStrings.renamePrompt, chat.title);
            if (name === null) return;
            this.post('/ai-assistant/conversations/' + encodeURIComponent(chat.id) + '/rename', { title: name })
                .then(data => {
                    if (data.error) { this.error = data.error; return; }
                    chat.title = data.title;
                })
                .catch(() => { this.error = turtleAiStrings.error; });
        },
        deleteChat(id) {
            if (!window.confirm(turtleAiStrings.deleteConfirm)) return;
            this.post('/ai-assistant/conversations/' + encodeURIComponent(id) + '/delete', {})
                .then(data => {
                    if (data.error) { this.error = data.error; return; }
                    this.conversations = this.conversations.filter(c => c.id !== id);
                    if (this.activeId === id) {
                        this.activeId = null;
                        this.messages = [];
                        if (this.conversations.length > 0) this.selectChat(this.conversations[0].id);
                    }
                })
                .catch(() => { this.error = turtleAiStrings.error; });
        },
        scrollDown() {
            this.$nextTick(() => {
                const el = this.$refs.messages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        renderMarkdown(text) {
            const escaped = this.escapeHtml(text);
            return escaped
                .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre class="bg-gray-800 text-green-400 text-xs p-3 rounded-lg overflow-x-auto my-2"><code>$2</code></pre>')
                .replace(/`([^`]+)`/g, '<code class="bg-gray-200 text-red-600 px-1 rounded text-xs">$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
        },
        sendMessage() {
            const msg = this.input.trim();
            if (!msg || this.loading || this.activeId === null) return;
            this.loading = true;
            this.error = '';
            this.input = '';
            this.messages.push({ role: 'user', content: msg });
            this.scrollDown();

            this.post('/ai-assistant/conversations/' + encodeURIComponent(this.activeId) + '/chat', { message: msg })
                .then(data => {
                    if (data.error) {
                        this.error = data.error;
                        this.messages.pop();
                        return;
                    }
                    this.messages.push({ role: 'assistant', content: data.response });
                    this.scrollDown();
                })
                .catch(() => {
                    this.error = turtleAiStrings.error;
                    this.messages.pop();
                })
                .finally(() => {
                    this.loading = false;
                    this.$nextTick(() => this.$refs.input.focus());
                });
        }
    };
}
</script>
