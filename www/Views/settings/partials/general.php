<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800">General Settings</h2>
    <p class="text-sm text-gray-500 mt-1">Configure application-wide settings.</p>
</div>

<!-- Timezone -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Timezone & NTP</h3>
    <p class="text-sm text-gray-500 mb-4">Set the application timezone and NTP server for accurate time tracking. The NTP server is checked on each page load to ensure time sync.</p>

    <form method="POST" action="/settings/general">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Timezone <span class="text-red-500">*</span></label>
                <select name="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $tz === $selectedTz ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">NTP Server</label>
                <input type="text" name="ntp_server" value="<?= h($mail['ntp_server'] ?: 'time.gov') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">Used for time sync verification. Default: time.gov</p>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Save General Settings</button>
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
</script>
