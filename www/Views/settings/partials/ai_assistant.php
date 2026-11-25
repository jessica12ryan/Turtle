<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800"><?= __('AI Assistant') ?></h2>
    <p class="text-sm text-gray-500 mt-1"><?= __('Choose one AI provider and configure its API key. Only the selected provider is used.') ?></p>
</div>

<div class="bg-white rounded-lg shadow p-6 mb-6" x-data="aiSettings()">
    <form method="POST" action="/settings/ai-assistant">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="ai_assistant_enabled" value="1" <?= ($ai['ai_assistant_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="ml-2 text-sm font-medium text-gray-700"><?= __('Enable AI Assistant') ?></span>
            </label>
            <p class="text-xs text-gray-400 mt-1"><?= __('Staff with the AI Assistant permission can use it when enabled. Tenants never have access.') ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('AI Provider') ?></label>
                <select name="ai_provider" x-model="provider" @change="loadModels()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($aiProviders as $id => $definition): ?>
                        <option value="<?= h($id) ?>" <?= $ai['ai_provider'] === $id ? 'selected' : '' ?>><?= h(__($definition['label'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Model') ?></label>
                <div class="flex space-x-2">
                    <select name="ai_model" x-model="model" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        <template x-for="m in models" :key="m"><option :value="m" x-text="m"></option></template>
                    </select>
                    <button type="button" @click="loadModels()" class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg border text-sm hover:bg-gray-200"><?= __('Refresh models') ?></button>
                </div>
                <p class="text-xs text-gray-400 mt-1" x-show="warning" x-text="warning"></p>
            </div>
        </div>

        <?php
        $keyFields = [
            'openai' => ['openai_api_key', 'OpenAI API Key'],
            'claude' => ['anthropic_api_key', 'Anthropic API Key'],
            'gemini' => ['gemini_api_key', 'Gemini API Key'],
            'opencode' => ['opencode_api_key', 'OpenCode API Key'],
            'opencode_server' => ['opencode_server_password', 'OpenCode Server Password'],
        ];
        foreach ($keyFields as $id => [$field, $label]):
            ?>
            <div class="mb-4" x-show="provider === '<?= h($id) ?>'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __($label) ?></label>
                <input type="password" name="<?= h($field) ?>" value="<?= h($ai[$field] ?? '') ?>" class="w-full max-w-lg border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" autocomplete="off" :disabled="provider !== '<?= h($id) ?>'">
            </div>
        <?php endforeach; ?>

        <div class="mb-4" x-show="provider === 'opencode'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Base URL') ?></label>
            <input type="text" name="opencode_base_url" value="<?= h($ai['opencode_base_url'] ?? '') ?>" placeholder="https://opencode.ai/zen/v1" class="w-full max-w-lg border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" :disabled="provider !== 'opencode'">
            <p class="text-xs text-gray-400 mt-1"><?= __('Use the OpenCode Zen gateway URL or your self-hosted server URL.') ?></p>
        </div>

        <div class="mb-4" x-show="provider === 'opencode_server'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('OpenCode Server URL') ?></label>
            <input type="text" name="opencode_server_base_url" value="<?= h($ai['opencode_server_base_url'] ?? '') ?>" placeholder="http://localhost:4096" class="w-full max-w-lg border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" :disabled="provider !== 'opencode_server'">
            <p class="text-xs text-gray-400 mt-1"><?= __('The password above is sent as HTTP Basic authentication with the username "opencode".') ?></p>
        </div>

        <p class="text-xs text-gray-400 mb-4"><?= __('Your API keys are stored in the database and used only for AI Assistant requests.') ?></p>
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 font-medium"><?= __('Save AI Settings') ?></button>
    </form>
</div>

<script nonce="<?= h(csp_nonce()) ?>">
const turtleAiSettings = {
    csrf: <?= json_encode(csrf_token(), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    initialProvider: <?= json_encode($ai['ai_provider'], JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    initialModel: <?= json_encode($ai['ai_model'], JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    loadError: <?= json_encode(__('Could not load models from the provider.'), JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>
};

function aiSettings() {
    return {
        provider: turtleAiSettings.initialProvider,
        model: turtleAiSettings.initialModel,
        models: [turtleAiSettings.initialModel],
        warning: '',
        loadModels() {
            this.warning = '';
            const form = new FormData();
            form.append('_csrf', turtleAiSettings.csrf);
            form.append('ai_provider', this.provider);
            const keyField = this.provider === 'claude' ? 'anthropic_api_key'
                : this.provider === 'opencode_server' ? 'opencode_server_password'
                : this.provider + '_api_key';
            const keyInput = document.querySelector('input[name="' + keyField + '"]');
            form.append('api_key', keyInput ? keyInput.value : '');
            const baseField = this.provider === 'opencode' ? 'opencode_base_url'
                : this.provider === 'opencode_server' ? 'opencode_server_base_url'
                : '';
            const baseInput = baseField ? document.querySelector('input[name="' + baseField + '"]') : null;
            form.append('base_url', baseInput ? baseInput.value : '');
            fetch((window.baseUrl || '') + '/settings/ai-assistant/models', {
                method: 'POST', body: form, headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.warning = data.warning || '';
                const list = Array.isArray(data.models) ? data.models : [];
                if (list.length === 0) return;
                this.models = list;
                if (!list.includes(this.model)) this.model = list[0];
            })
            .catch(() => { this.warning = turtleAiSettings.loadError; });
        }
    };
}
</script>
