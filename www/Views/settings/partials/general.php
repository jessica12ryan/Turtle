<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800">General Settings</h2>
    <p class="text-sm text-gray-500 mt-1">Configure application-wide settings.</p>
</div>

<!-- Branding -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Branding</h3>
    <p class="text-sm text-gray-500 mb-4">Customize the site name and logo displayed throughout the application.</p>

    <form method="POST" action="/settings/general" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
            <input type="text" name="site_name" value="<?= h($siteName ?? 'Turtle') ?>" class="w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-400 mt-1">Used in page titles and branding. Default: Turtle</p>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
            <div class="flex items-center space-x-4 mb-2">
                <img src="<?= h(site_logo()) ?>" alt="Current logo" class="h-10">
                <span class="text-sm text-gray-500">Current logo</span>
            </div>
            <div class="space-y-2">
                <label class="flex items-center space-x-2">
                    <input type="radio" name="logo_default" value="1" <?= site_logo() === '/assets/logo.svg' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">Use default logo</span>
                </label>
                <label class="flex items-center space-x-2">
                    <input type="radio" name="logo_default" value="0" <?= site_logo() !== '/assets/logo.svg' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">Upload custom logo</span>
                </label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/svg+xml" class="block text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-400">Recommended: 200x50px PNG, JPEG, GIF, or SVG. Max dimensions: 400x100px.</p>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Save Branding</button>
    </form>
</div>

<!-- Localization -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Localization</h3>
    <p class="text-sm text-gray-500 mb-4">Configure the default country and timezone. The default country pre-selects the country when adding new properties.</p>

    <form method="POST" action="/settings/general">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Country <span class="text-red-500">*</span></label>
                <select name="default_country" id="default_country" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="CA" <?= ($mail['default_country'] ?? 'CA') === 'CA' ? 'selected' : '' ?>>Canada</option>
                    <option value="US" <?= ($mail['default_country'] ?? 'CA') === 'US' ? 'selected' : '' ?>>United States</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Timezone <span class="text-red-500">*</span></label>
                <select name="timezone" id="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $tz === $selectedTz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">NTP Server</label>
            <input type="text" name="ntp_server" value="<?= h($mail['ntp_server'] ?: 'time.gov') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-400 mt-1">Used for time sync verification. Default: time.gov</p>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Save Localization</button>
    </form>
</div>

<!-- Mail Settings -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Email (SMTP)</h3>
    <p class="text-sm text-gray-500 mb-4">Configure the SMTP server used for sending password resets, notifications, and other emails. Leave username/password blank to use the server without authentication.</p>

    <form method="POST" action="/settings/mail">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host <span class="text-red-500">*</span></label>
                <input type="text" name="mail_host" value="<?= h($mail['mail_host'] ?: 'mailpit') ?>" placeholder="mailpit" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port <span class="text-red-500">*</span></label>
                <input type="number" name="mail_port" value="<?= h($mail['mail_port'] ?: '1025') ?>" placeholder="1025" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <p class="text-xs text-gray-400 mt-1">Common: 25, 465 (SSL), 587 (TLS), 1025 (Mailpit)</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                <input type="text" name="mail_username" value="<?= h($mail['mail_username'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                <input type="password" name="mail_password" value="<?= h($mail['mail_password'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Address <span class="text-red-500">*</span></label>
                <input type="email" name="mail_from_address" value="<?= h($mail['mail_from_address'] ?: 'noreply@turtleapp.com') ?>" placeholder="noreply@turtleapp.com" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Name <span class="text-red-500">*</span></label>
                <input type="text" name="mail_from_name" value="<?= h($mail['mail_from_name'] ?: 'Turtle') ?>" placeholder="Turtle" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Save Mail Settings</button>
            <button type="button" onclick="testMail()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium text-sm">Send Test Email</button>
        </div>
        <div id="mail-test-result" class="mt-3 hidden"></div>
    </form>
</div>

<script>
function testMail() {
    const btn = event.target;
    const result = document.getElementById('mail-test-result');
    btn.disabled = true;
    result.className = 'mt-3 p-3 rounded-lg text-sm hidden';

    fetch('/settings/test-mail', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=<?= csrf_token() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            result.className = 'mt-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
            result.textContent = 'Test email sent! Check your inbox (or Mailpit at http://localhost:8025).';
        } else {
            result.className = 'mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
            result.textContent = data.error || 'Failed to send test email.';
        }
    })
    .catch(err => {
        result.className = 'mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
        result.textContent = 'Connection error: ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        result.classList.remove('hidden');
    });
}

// Timezone filtering by country
var tzByCountry = <?= json_encode($tzByCountry) ?>;
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
