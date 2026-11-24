<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800"><?= __('General Settings') ?></h2>
    <p class="text-sm text-gray-500 mt-1"><?= __('Configure application-wide settings.') ?></p>
</div>

<!-- Branding -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Branding') ?></h3>
    <p class="text-sm text-gray-500 mb-4"><?= __('Customize the site name and logo.') ?></p>

    <form method="POST" action="/settings/general" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Site Name') ?></label>
            <input type="text" name="site_name" value="<?= h($siteName ?? 'Turtle') ?>" class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-400 mt-1"><?= __('Used in page titles and branding.') ?></p>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Site URL') ?></label>
            <input type="text" name="site_url" value="<?= h($mail['site_url'] ?? '') ?>" class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= h(site_url()) ?>">
            <p class="text-xs text-gray-400 mt-1"><?= __('Used in email links. Leave blank to use the internal server address.') ?></p>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Logo') ?></label>
            <div class="flex items-center space-x-4 mb-2">
                <img src="<?= h(site_logo()) ?>" alt="Current logo" class="h-10">
                <span class="text-sm text-gray-500"><?= __('Current logo') ?></span>
            </div>
            <div class="space-y-2">
                <label class="flex items-center space-x-2">
                    <input type="radio" name="logo_default" value="1" <?= site_logo() === '/assets/logo.svg' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700"><?= __('Use default logo') ?></span>
                </label>
                <label class="flex items-center space-x-2">
                    <input type="radio" name="logo_default" value="0" <?= site_logo() !== '/assets/logo.svg' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700"><?= __('Upload custom logo') ?></span>
                </label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/svg+xml" class="block text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-400"><?= __('Recommended: 200x50px PNG, JPEG, GIF, or SVG.') ?></p>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Save Branding') ?></button>
    </form>
</div>

<!-- Localization -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Localization') ?></h3>
    <p class="text-sm text-gray-500 mb-4"><?= __('Configure the default country and timezone.') ?></p>

    <form method="POST" action="/settings/general">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Default Country') ?> <span class="text-red-500">*</span></label>
                <select name="default_country" id="default_country" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="CA" <?= ($mail['default_country'] ?? 'CA') === 'CA' ? 'selected' : '' ?>>Canada</option>
                    <option value="US" <?= ($mail['default_country'] ?? 'CA') === 'US' ? 'selected' : '' ?>>United States</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Default Language') ?> <span class="text-red-500">*</span></label>
                <select name="default_language" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php foreach (languages() as $code => $name): ?>
                        <option value="<?= $code ?>" <?= ($mail['default_language'] ?? 'en') === $code ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Default Timezone') ?> <span class="text-red-500">*</span></label>
                <select name="timezone" id="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $tz === $selectedTz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('NTP Server') ?></label>
            <input type="text" name="ntp_server" value="<?= h($mail['ntp_server'] ?: 'time.gov') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-400 mt-1"><?= __('Used for time sync. Default: time.gov') ?></p>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Save Localization') ?></button>
    </form>
</div>

<!-- AI Assistant -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('AI Assistant') ?></h3>
    <p class="text-sm text-gray-500 mb-4"><?= __('Configure the AI Assistant integration. An OpenAI API key is required to use the assistant.') ?></p>

    <form method="POST" action="/settings/general">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="ai_assistant_enabled" value="1" <?= ($mail['ai_assistant_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="ml-2 text-sm font-medium text-gray-700"><?= __('Enable AI Assistant') ?></span>
            </label>
            <p class="text-xs text-gray-400 mt-1"><?= __('Staff with the AI Assistant permission can use it when enabled. Tenants never have access.') ?></p>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('OpenAI API Key') ?></label>
            <input type="password" name="openai_api_key" value="<?= h($mail['openai_api_key'] ?? '') ?>" class="w-full max-w-lg border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off" placeholder="sk-...">
            <p class="text-xs text-gray-400 mt-1"><?= __('Your API key is stored securely in the database and used only for AI Assistant requests.') ?></p>
        </div>

        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 font-medium"><?= __('Save AI Settings') ?></button>
    </form>
</div>

<!-- Mail Settings -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Email (SMTP)') ?></h3>
    <p class="text-sm text-gray-500 mb-4"><?= __('Configure the SMTP server.') ?></p>

    <form method="POST" action="/settings/mail">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Host') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="mail_host" value="<?= h($mail['mail_host'] ?: 'mailpit') ?>" placeholder="mailpit" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Port') ?> <span class="text-red-500">*</span></label>
                <input type="number" name="mail_port" value="<?= h($mail['mail_port'] ?: '1025') ?>" placeholder="1025" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <p class="text-xs text-gray-400 mt-1"><?= __('Common: 25, 465 (SSL), 587 (TLS), 1025 (Mailpit)') ?></p>
            </div>
        </div>

        <div class="mb-4">
            <label for="mail_auth_mode" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Authentication Method') ?></label>
            <select id="mail_auth_mode" name="mail_auth_mode" data-action="toggleMailAuth" class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="password" <?= ($mail['mail_auth_mode'] ?? 'password') === 'password' ? 'selected' : '' ?>><?= __('Password') ?></option>
                <option value="oauth2" <?= ($mail['mail_auth_mode'] ?? '') === 'oauth2' ? 'selected' : '' ?>><?= __('OAuth2 (Google)') ?></option>
            </select>
        </div>

        <div id="mail-password-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Username') ?></label>
                <input type="text" name="mail_username" value="<?= h($mail['mail_username'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Password') ?></label>
                <input type="password" name="mail_password" value="<?= h($mail['mail_password'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
            </div>
        </div>

        <div id="mail-oauth-fields" class="mb-4">
            <p class="text-xs text-gray-500 mb-3"><?= __('Create a Google Cloud OAuth client with the Gmail API scope, then add the redirect URI below to it.') ?></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Google Client ID') ?></label>
                    <input type="text" name="mail_oauth_client_id" value="<?= h($mail['mail_oauth_client_id'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Google Client Secret') ?></label>
                    <input type="password" name="mail_oauth_client_secret" value="<?= h($mail['mail_oauth_client_secret'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Gmail Address') ?></label>
                    <input type="email" name="mail_oauth_email" value="<?= h($mail['mail_oauth_email'] ?? '') ?>" placeholder="you@gmail.com" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Refresh Token (optional)') ?></label>
                    <input type="password" name="mail_oauth_refresh_token" value="<?= h($mail['mail_oauth_refresh_token'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
                    <p class="text-xs text-gray-400 mt-1"><?= __('Paste a token generated outside Turtle instead of using the Connect button.') ?></p>
                </div>
            </div>
            <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                <p class="text-xs font-medium text-gray-500 mb-1"><?= __('Redirect URI') ?></p>
                <code class="text-xs text-gray-700 break-all"><?= h($mailOauthRedirectUri) ?></code>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-3">
                <a href="/oauth/google/connect" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 font-medium text-sm"><?= __('Connect with Google') ?></a>
                <?php if ($mailOauthConnected): ?>
                    <span class="text-sm text-green-700 font-medium"><?= __('Gmail OAuth2 is connected.') ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('From Address') ?> <span class="text-red-500">*</span></label>
                <input type="email" name="mail_from_address" value="<?= h($mail['mail_from_address'] ?: 'noreply@turtleapp.com') ?>" placeholder="noreply@turtleapp.com" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('From Name') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="mail_from_name" value="<?= h($mail['mail_from_name'] ?: 'Turtle') ?>" placeholder="Turtle" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Save Mail Settings') ?></button>
            <button type="button" data-action="testMail" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium text-sm"><?= __('Send Test Email') ?></button>
        </div>
        <div id="mail-test-result" class="mt-3 hidden"></div>
    </form>

    <?php if ($mailOauthConnected): ?>
        <form method="POST" action="/oauth/google/disconnect" class="mt-4 border-t pt-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 font-medium text-sm"><?= __('Disconnect Google') ?></button>
        </form>
    <?php endif; ?>
</div>

<script nonce="<?= h(csp_nonce()) ?>">
function toggleMailAuth() {
    var mode = document.getElementById('mail_auth_mode').value;
    document.getElementById('mail-password-fields').classList.toggle('hidden', mode !== 'password');
    document.getElementById('mail-oauth-fields').classList.toggle('hidden', mode !== 'oauth2');
}
toggleMailAuth();

function testMail() {
    const btn = event.target;
    const result = document.getElementById('mail-test-result');
    btn.disabled = true;
    result.className = 'mt-3 p-3 rounded-lg text-sm hidden';

    fetch((window.baseUrl || '') + '/settings/test-mail', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=<?= csrf_token() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            result.className = 'mt-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
            result.textContent = '<?= __('Test email sent! Check your inbox.') ?>';
        } else {
            result.className = 'mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
            result.textContent = data.error || '<?= __('Failed to send test email.') ?>';
        }
    })
    .catch(err => {
        result.className = 'mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
        result.textContent = '<?= __('Connection error:') ?> ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        result.classList.remove('hidden');
    });
}

// Timezone filtering by country
var tzByCountry = <?= json_encode($tzByCountry, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var tzSelect = document.getElementById('timezone');
var countrySelect = document.getElementById('default_country');

function filterTimezones(country) {
    if (!tzSelect || !country) return;
    var selected = tzSelect.value;
    var tzs = tzByCountry[country] || [];
    var generic = tzByCountry['generic'] || [];
    var all = tzs.concat(generic.filter(function(t) { return tzs.indexOf(t) === -1; }));
    tzSelect.innerHTML = '';
    all.forEach(function(tz) {
        var opt = document.createElement('option');
        opt.value = tz;
        opt.textContent = tz;
        tzSelect.appendChild(opt);
    });
    if (all.indexOf(selected) !== -1) {
        tzSelect.value = selected;
    } else {
        tzSelect.value = all[0] || '';
    }
}

if (countrySelect) {
    filterTimezones(countrySelect.value);
    countrySelect.addEventListener('change', function() {
        filterTimezones(this.value);
    });
}
</script>
