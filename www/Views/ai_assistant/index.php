<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('AI Assistant') ?></h1>
    <p class="text-gray-500 mt-1"><?= __('Ask questions, get insights, and automate tasks with the AI Assistant.') ?></p>
</div>

<div class="bg-white rounded-lg shadow flex flex-col" style="height: 65vh;" x-data="chat()">
    <!-- Messages -->
    <div class="flex-1 overflow-y-auto p-6 space-y-4" x-ref="messages">
        <template x-for="(msg, i) in messages" :key="i">
            <div class="flex items-start space-x-3" :class="msg.role === 'user' ? 'justify-end' : ''">
                <template x-if="msg.role === 'assistant'">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/></svg>
                    </div>
                </template>
                <div :class="msg.role === 'user' ? 'bg-blue-100 text-gray-800 rounded-2xl rounded-tr-sm' : 'bg-gray-100 text-gray-800 rounded-2xl rounded-tl-sm'" class="max-w-[75%] px-4 py-2.5 text-sm leading-relaxed prose prose-sm max-w-none" x-html="msg.role === 'assistant' ? renderMarkdown(msg.content) : escapeHtml(msg.content)"></div>
                <template x-if="msg.role === 'user'">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0"><?= strtoupper(substr(\App\Core\Auth::instance()->user()['name'], 0, 1)) ?></div>
                </template>
            </div>
        </template>
        <template x-if="loading">
            <div class="flex items-start space-x-3">
                <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0">
                    <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/></svg>
                </div>
                <div class="bg-gray-100 text-gray-500 rounded-2xl rounded-tl-sm px-4 py-2.5 text-sm italic"><?= __('Thinking...') ?></div>
            </div>
        </template>
        <template x-if="messages.length === 0 && !loading">
            <div class="text-center py-12">
                <svg class="w-12 h-12 text-indigo-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/></svg>
                <p class="text-gray-500"><?= __('Ask me anything about your properties, tenants, or tickets.') ?></p>
            </div>
        </template>
    </div>

    <!-- Error -->
    <div x-show="error" x-cloak class="px-6 py-2 bg-red-50 border-t border-red-200">
        <p class="text-sm text-red-700" x-text="error"></p>
    </div>

    <!-- Input -->
    <div class="border-t p-4">
        <form @submit.prevent="sendMessage" class="flex space-x-3">
            <input type="text" x-model="input" x-ref="input" placeholder="<?= __('Ask the AI Assistant...') ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 text-sm" :disabled="loading">
            <button type="submit" :disabled="loading || !input.trim()" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700 font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                <span><?= __('Send') ?></span>
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2"><?= __('Responses are generated by AI and may not be accurate. Verify important information.') ?></p>
    </div>
</div>

<script>
function chat() {
    return {
        messages: [],
        input: '',
        loading: false,
        error: '',
        init() {
            this.$nextTick(() => this.scrollDown());
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
            if (!msg || this.loading) return;

            this.messages.push({ role: 'user', content: msg });
            this.input = '';
            this.loading = true;
            this.error = '';
            this.scrollDown();

            const formData = new FormData();
            formData.append('_csrf', '<?= csrf_token() ?>');
            formData.append('message', msg);

            fetch('/ai-assistant/chat', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    this.error = data.error;
                } else {
                    this.messages.push({ role: 'assistant', content: data.response });
                }
                this.scrollDown();
            })
            .catch(err => {
                this.error = '<?= __('Connection error') ?>: ' + err.message;
                console.error('AI Assistant fetch error:', err);
            })
            .finally(() => {
                this.loading = false;
                this.scrollDown();
                this.$nextTick(() => this.$refs.input.focus());
            });
        }
    };
}
</script>
