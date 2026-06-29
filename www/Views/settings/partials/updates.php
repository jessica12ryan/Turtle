<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800"><?= __('Software Updates') ?></h2>
    <p class="text-sm text-gray-500 mt-1"><?= __('Manage application updates and version information.') ?></p>
</div>

<!-- Channel Toggle -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-gray-700"><?= __('Update Channel') ?></h3>
            <p class="text-xs text-gray-500 mt-1"><?= __('Stable: latest tagged release') ?> &bull; <?= __('Development: latest master branch') ?></p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="text-sm font-medium <?= $channel === 'stable' ? 'text-blue-700' : 'text-gray-500' ?>"><?= __('Stable') ?></span>
            <button type="button" id="channel-toggle" onclick="toggleChannel()" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 <?= $channel === 'development' ? 'bg-yellow-500' : 'bg-blue-600' ?>">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 <?= $channel === 'development' ? 'translate-x-6' : 'translate-x-1' ?>"></span>
            </button>
            <span class="text-sm font-medium <?= $channel === 'development' ? 'text-yellow-700' : 'text-gray-500' ?>"><?= __('Development') ?></span>
        </div>
    </div>
</div>

<!-- Version Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500 mb-1"><?= __('Current Version') ?></p>
        <p class="text-2xl font-bold text-gray-800" id="current-version"><?= h($currentVersion) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500 mb-1"><?= __('Latest Available') ?></p>
        <p class="text-2xl font-bold text-gray-800" id="latest-version"><?= h($latestVersion ?: '—') ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500 mb-1"><?= __('Last Checked') ?></p>
        <p class="text-sm font-medium text-gray-800" id="last-check"><?= h($lastCheck ? display_time($lastCheck) : __('Never')) ?></p>
    </div>
</div>

<!-- Loading -->
<div id="checking-section" class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex items-center justify-center space-x-3 py-4">
        <svg class="w-5 h-5 animate-spin text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <span class="text-gray-600 text-sm"><?= __('Checking for updates...') ?></span>
    </div>
</div>

<!-- Error -->
<div id="check-result" class="hidden mb-6"></div>

<!-- Update Available -->
<div id="update-section" class="hidden">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-gray-800"><?= __('Update Available') ?></h2>
                <p class="text-sm text-gray-600 mt-1"><?= __('Version') ?> <span id="update-latest-version"></span> <?= __('is available.') ?></p>
                <div id="release-notes" class="mt-3 p-3 bg-gray-50 rounded-lg text-sm text-gray-700 max-h-40 overflow-y-auto"></div>
                <button id="apply-btn" onclick="applyUpdate()" class="mt-4 bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium">
                    <?= __('Apply Update') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Progress -->
<div id="progress-section" class="hidden">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Applying Update...') ?></h2>
        <div class="mb-4">
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div id="progress-bar" class="bg-blue-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
            <p id="progress-text" class="text-sm text-gray-600 mt-2"><?= __('Starting...') ?></p>
        </div>
        <div id="step-list" class="space-y-2">
            <div class="step-item" data-step="0">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Preparing working directory...') ?></span>
                </div>
            </div>
            <div class="step-item" data-step="1">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Ensuring storage directories...') ?></span>
                </div>
            </div>
            <div class="step-item" data-step="2">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Fetching latest code...') ?></span>
                </div>
            </div>
            <div class="step-item" data-step="3">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Checking for changes...') ?></span>
                </div>
            </div>
            <div class="step-item" data-step="4">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Pulling updates...') ?></span>
                </div>
            </div>
            <div class="step-item" data-step="5">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Running migrations...') ?></span>
                </div>
            </div>
            <div class="step-item" data-step="6">
                <div class="flex items-center space-x-2">
                    <span class="step-icon w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="step-label text-sm text-gray-600"><?= __('Restarting services...') ?></span>
                </div>
            </div>
        </div>
        <div id="error-output" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg hidden">
            <p class="text-sm font-medium text-red-800"><?= __('Errors encountered:') ?></p>
            <pre id="error-text" class="text-xs text-red-700 mt-1 whitespace-pre-wrap"></pre>
        </div>
    </div>
    <div id="complete-section" class="hidden">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <svg class="w-16 h-16 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h2 class="text-xl font-bold text-gray-800 mb-2"><?= __('Update Complete!') ?></h2>
            <p class="text-gray-600 mb-4"><?= __('The application has been updated successfully.') ?></p>
            <button onclick="window.location.href='/settings?tab=updates'" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Reload') ?></button>
        </div>
    </div>
</div>

<!-- Up to Date -->
<div id="up-to-date-section" class="hidden">
    <div class="bg-white rounded-lg shadow p-6 text-center">
        <svg class="w-16 h-16 text-blue-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <h2 class="text-xl font-bold text-gray-800 mb-2"><?= __("You're Up to Date!") ?></h2>
        <p class="text-gray-600"><?= __('Running the latest version.') ?></p>
    </div>
</div>

<script>
let updateId = null;
let pollTimer = null;
let currentChannel = '<?= $channel ?>';

function toggleChannel() {
    const newChannel = currentChannel === 'stable' ? 'development' : 'stable';

    fetch((window.baseUrl || '') + '/settings/update-channel', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=<?= csrf_token() ?>&channel=' + newChannel
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentChannel = data.channel;
            const toggle = document.getElementById('channel-toggle');
            const stableLabel = toggle.previousElementSibling;
            const devLabel = toggle.nextElementSibling;

            if (currentChannel === 'development') {
                toggle.className = 'relative inline-flex h-6 w-11 items-center rounded-full bg-yellow-500 transition-colors duration-200';
                toggle.querySelector('span').className = 'inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 translate-x-6';
                stableLabel.className = 'text-sm font-medium text-gray-500';
                devLabel.className = 'text-sm font-medium text-yellow-700';
            } else {
                toggle.className = 'relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600 transition-colors duration-200';
                toggle.querySelector('span').className = 'inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 translate-x-1';
                stableLabel.className = 'text-sm font-medium text-blue-700';
                devLabel.className = 'text-sm font-medium text-gray-500';
            }

            document.getElementById('latest-version').textContent = '—';
            document.getElementById('last-check').textContent = '<?= __('Never') ?>';
            document.getElementById('update-section').classList.add('hidden');
            document.getElementById('up-to-date-section').classList.add('hidden');
            document.getElementById('checking-section').classList.remove('hidden');
            document.getElementById('check-result').classList.add('hidden');
            checkForUpdates();
        }
    })
    .catch(err => console.error('Failed to switch channel:', err));
}

function checkForUpdates() {
    document.getElementById('checking-section').classList.remove('hidden');
    document.getElementById('check-result').classList.add('hidden');
    document.getElementById('update-section').classList.add('hidden');
    document.getElementById('up-to-date-section').classList.add('hidden');

    fetch((window.baseUrl || '') + '/updates/check', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            document.getElementById('checking-section').classList.add('hidden');

            if (data.error) {
                document.getElementById('check-result').classList.remove('hidden');
                document.getElementById('check-result').innerHTML = '<div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">' + escapeHtml(data.error) + '</div>';
                return;
            }

            document.getElementById('latest-version').textContent = data.latest_version || '—';
            document.getElementById('last-check').textContent = data.last_check || 'Never';

            if (data.update_available) {
                document.getElementById('update-section').classList.remove('hidden');
                document.getElementById('up-to-date-section').classList.add('hidden');
                document.getElementById('update-latest-version').textContent = data.latest_version;

                if (data.channel === 'development') {
                    const behindMsg = data.behind_count ? data.behind_count + ' commit(s) behind.' : '';
                    document.getElementById('release-notes').innerHTML = '<pre class="text-xs">' + escapeHtml(behindMsg) + '</pre>';
                } else if (data.release_body) {
                    document.getElementById('release-notes').innerHTML = marked ? marked.parse(data.release_body) : '<pre class="text-xs">' + escapeHtml(data.release_body) + '</pre>';
                }
            } else {
                document.getElementById('update-section').classList.add('hidden');
                document.getElementById('up-to-date-section').classList.remove('hidden');
            }

            document.getElementById('current-version').textContent = data.current_version;
        })
        .catch(err => {
            document.getElementById('checking-section').classList.add('hidden');
            document.getElementById('check-result').classList.remove('hidden');
            document.getElementById('check-result').innerHTML = '<div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= __('Failed to check for updates. Error:') ?> ' + err.message + '</div>';
        });
}

function applyUpdate() {
    document.getElementById('update-section').classList.add('hidden');
    document.getElementById('progress-section').classList.remove('hidden');
    document.getElementById('apply-btn').disabled = true;

    fetch((window.baseUrl || '') + '/updates/apply', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            updateId = data.update_id;
            pollProgress();
        })
        .catch(err => {
            document.getElementById('progress-text').textContent = '<?= __('Error:') ?> ' + err.message;
        });
}

function pollProgress() {
    if (!updateId) return;

    fetch((window.baseUrl || '') + '/updates/progress?update_id=' + updateId)
        .then(r => r.json())
        .then(data => {
            const totalSteps = 7;
            const doneSteps = data.steps.filter(s => s.status === 'done').length;
            const pct = Math.min(Math.round((doneSteps / totalSteps) * 100), 99);

            document.getElementById('progress-bar').style.width = pct + '%';

            data.steps.forEach((step, i) => {
                const item = document.querySelector('.step-item[data-step="' + i + '"]');
                if (!item) return;

                const icon = item.querySelector('.step-icon');
                const label = item.querySelector('.step-label');

                if (step.status === 'done') {
                    icon.className = 'step-icon w-5 h-5 rounded-full bg-green-500 border-2 border-green-500 flex items-center justify-center flex-shrink-0';
                    icon.innerHTML = '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
                    label.className = 'step-label text-sm text-gray-500 line-through';
                } else if (step.status === 'in_progress') {
                    icon.className = 'step-icon w-5 h-5 rounded-full bg-blue-500 border-2 border-blue-500 flex items-center justify-center flex-shrink-0';
                    icon.innerHTML = '<svg class="w-3 h-3 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
                    label.className = 'step-label text-sm font-medium text-blue-700';
                    document.getElementById('progress-text').textContent = step.label;
                }
            });

            if (data.done) {
                document.getElementById('progress-bar').style.width = '100%';
                document.getElementById('progress-text').textContent = '<?= __('Complete!') ?>';

                if (data.error) {
                    document.getElementById('error-output').classList.remove('hidden');
                    document.getElementById('error-text').textContent = data.error;
                }

                setTimeout(() => {
                    document.getElementById('complete-section').classList.remove('hidden');
                }, 500);

                if (pendingInv) clearInterval(pendingInv);
                return;
            }

            pendingInv = setTimeout(pollProgress, 1500);
        })
        .catch(err => {
            document.getElementById('progress-text').textContent = '<?= __('Error polling progress:') ?> ' + err.message;
        });
}

let pendingInv = null;

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

checkForUpdates();
</script>
