<style>
.setup-wide { max-width: 48rem !important; }
.step-indicator { display: flex; align-items: center; justify-content: center; margin-bottom: 2rem; gap: 0; flex-wrap: nowrap; overflow-x: auto; padding: 0 0.5rem; }
.step-dot { display: flex; align-items: center; justify-content: center; min-width: 2rem; width: 2rem; height: 2rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; flex-shrink: 0; transition: all 0.2s; }
.step-dot.active { background: #2563eb; color: #fff; }
.step-dot.completed { background: #16a34a; color: #fff; }
.step-dot.pending { background: #e5e7eb; color: #9ca3af; }
.step-line { width: 2.5rem; height: 2px; flex-shrink: 0; transition: background 0.2s; }
.step-line.active { background: #2563eb; }
.step-line.completed { background: #16a34a; }
.step-line.pending { background: #e5e7eb; }
.step-label { font-size: 0.75rem; color: #9ca3af; text-align: center; margin-top: 0.5rem; }
.step-label.active { color: #2563eb; font-weight: 600; }
.step-label.completed { color: #16a34a; font-weight: 600; }
.step-content { display: none; }
.step-content.active { display: block; }
</style>

<div class="setup-wide" style="margin: 0 auto;">
    <h1 class="text-2xl font-bold text-gray-800 mb-1 text-center"><?= __('Welcome to Turtle') ?></h1>
    <p class="text-gray-500 mb-6 text-center"><?= __("Let's get your portal set up.") ?></p>

    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="flex flex-col items-center">
            <div class="step-dot active" id="dot-1">1</div>
            <span class="step-label active" id="label-1"><?= __('Site Info') ?></span>
        </div>
        <div class="step-line active" id="line-1"></div>
        <div class="flex flex-col items-center">
            <div class="step-dot pending" id="dot-2">2</div>
            <span class="step-label pending" id="label-2"><?= __('Localization') ?></span>
        </div>
        <div class="step-line pending" id="line-2"></div>
        <div class="flex flex-col items-center">
            <div class="step-dot pending" id="dot-3">3</div>
            <span class="step-label pending" id="label-3"><?= __('Account') ?></span>
        </div>
        <div class="step-line pending" id="line-3"></div>
        <div class="flex flex-col items-center">
            <div class="step-dot pending" id="dot-4">4</div>
            <span class="step-label pending" id="label-4"><?= __('Email (SMTP)') ?></span>
        </div>
        <div class="step-line pending" id="line-4"></div>
        <div class="flex flex-col items-center">
            <div class="step-dot pending" id="dot-5">5</div>
            <span class="step-label pending" id="label-5"><?= __('Optional') ?></span>
        </div>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= h($msg) ?></div>
    <?php endif; ?>
    <?php $errs = $_SESSION['_errors'] ?? []; unset($_SESSION['_errors']); ?>
    <?php if (!empty($errs)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside text-sm">
                <?php foreach ($errs as $field => $msgs): ?>
                    <?php foreach ((array)$msgs as $m): ?>
                        <li><?= h($m) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="/setup" enctype="multipart/form-data" id="setup-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <!-- Step 1: Site Information -->
        <div class="step-content active" data-step="1">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Site Information') ?></h2>

            <div class="mb-4">
                <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Site Name') ?></label>
                <input type="text" name="site_name" id="site_name" value="<?= old('site_name', 'Turtle') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-400 mt-1"><?= __('Used in page titles and branding. Default: Turtle') ?></p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Logo') ?></label>
                <div class="flex items-center space-x-2 mb-2">
                    <input type="radio" name="logo_default" value="1" <?= old('logo_default', '1') === '1' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700"><?= __('Use default logo') ?></span>
                </div>
                <div class="flex items-center space-x-2 mb-2">
                    <input type="radio" name="logo_default" value="0" <?= old('logo_default', '1') === '0' ? 'checked' : '' ?> class="text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700"><?= __('Upload custom logo') ?></span>
                </div>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/svg+xml" class="block text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-400 mt-1"><?= __('Recommended: 200x50px PNG, JPEG, GIF, or SVG.') ?></p>
            </div>

            <div class="mb-4">
                <label for="ntp_server" class="block text-sm font-medium text-gray-700 mb-1"><?= __('NTP Server') ?></label>
                <input type="text" name="ntp_server" id="ntp_server" value="<?= old('ntp_server', 'time.gov') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1"><?= __('Used for time sync. Default: time.gov') ?></p>
            </div>

            <div class="flex justify-end mt-6">
                <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Next →') ?></button>
            </div>
        </div>

        <!-- Step 2: Localization -->
        <div class="step-content" data-step="2">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Localization') ?></h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="default_country" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Default Country') ?> <span class="text-red-500">*</span></label>
                    <select name="default_country" id="default_country" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="US" <?= old('default_country', 'US') === 'US' ? 'selected' : '' ?>>United States</option>
                        <option value="CA" <?= old('default_country', 'US') === 'CA' ? 'selected' : '' ?>>Canada</option>
                    </select>
                </div>
                <div>
                    <label for="default_language" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Default Language') ?> <span class="text-red-500">*</span></label>
                    <select name="default_language" id="default_language" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <?php foreach (languages() as $code => $name): ?>
                            <option value="<?= $code ?>" <?= old('default_language', 'en') === $code ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Default Timezone') ?> <span class="text-red-500">*</span></label>
                <select name="timezone" id="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $tz === (old('timezone', $selectedTz)) ? 'selected' : '' ?>><?= $tz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-between mt-6">
                <button type="button" onclick="prevStep()" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium"><?= __('← Back') ?></button>
                <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Next →') ?></button>
            </div>
        </div>

        <!-- Step 3: Create Account -->
        <div class="step-content" data-step="3">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Create Administrator Account') ?></h2>

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Your Name') ?></label>
                <input type="text" name="name" id="name" value="<?= old('name', '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email Address') ?></label>
                <input type="email" name="email" id="email" value="<?= old('email', '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Password') ?></label>
                <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required minlength="8">
            </div>
            <div class="mb-4">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Confirm Password') ?></label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required minlength="8">
            </div>

            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong><?= __('Administrator Account') ?></strong> — <?= __('This account will have full system access, including the ability to manage all properties, tenants, leases, tickets, and staff. You can invite additional landlords, property managers, and staff after setup.') ?>
                </p>
            </div>

            <div class="flex justify-between mt-6">
                <button type="button" onclick="prevStep()" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium"><?= __('← Back') ?></button>
                <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Next →') ?></button>
            </div>
        </div>

        <!-- Step 4: Email (SMTP) -->
        <div class="step-content" data-step="4">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Email (SMTP)') ?> <span class="text-sm font-normal text-gray-500"><?= __('— optional, can configure later') ?></span></h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="mail_host" class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Host') ?></label>
                    <input type="text" name="mail_host" id="mail_host" value="<?= old('mail_host', 'mailpit') ?>" placeholder="mailpit" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="mail_port" class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Port') ?></label>
                    <input type="number" name="mail_port" id="mail_port" value="<?= old('mail_port', '1025') ?>" placeholder="1025" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="mail_username" class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Username') ?></label>
                    <input type="text" name="mail_username" id="mail_username" value="<?= old('mail_username', '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
                </div>
                <div>
                    <label for="mail_password" class="block text-sm font-medium text-gray-700 mb-1"><?= __('SMTP Password') ?></label>
                    <input type="password" name="mail_password" id="mail_password" value="<?= old('mail_password', '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mail_from_address" class="block text-sm font-medium text-gray-700 mb-1"><?= __('From Address') ?></label>
                    <input type="email" name="mail_from_address" id="mail_from_address" value="<?= old('mail_from_address', 'noreply@turtleapp.com') ?>" placeholder="noreply@turtleapp.com" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="mail_from_name" class="block text-sm font-medium text-gray-700 mb-1"><?= __('From Name') ?></label>
                    <input type="text" name="mail_from_name" id="mail_from_name" value="<?= old('mail_from_name', 'Turtle') ?>" placeholder="Turtle" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <button type="button" onclick="prevStep()" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium"><?= __('← Back') ?></button>
                <button type="button" onclick="nextStep()" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Next →') ?></button>
            </div>
        </div>

        <!-- Step 5: Optional -->
        <div class="step-content" data-step="5">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Optional Setup') ?></h2>

            <div class="mb-6">
                <label class="flex items-start space-x-3">
                    <input type="checkbox" name="load_sample_data" value="1" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-gray-600">
                        <strong class="text-gray-800"><?= __('Load sample data') ?></strong><br>
                        <?= __('Creates demo companies, properties, tenants, maintenance tickets, and resources so you can explore the portal immediately.') ?>
                    </span>
                </label>
            </div>

            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800">
                    <strong><?= __('After setup') ?></strong> — <?= __('You can configure email, permissions, and branding settings at any time from') ?> <strong><?= __('Settings → General') ?></strong>. <?= __('Additional staff, tenants, and properties can be added through their respective pages.') ?>
                </p>
            </div>

            <div class="flex justify-between mt-6">
                <button type="button" onclick="prevStep()" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium"><?= __('← Back') ?></button>
                <button type="submit" id="finish-btn" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 font-medium inline-flex items-center">
                    <span id="finish-text"><?= __('Finish Setup') ?></span>
                    <svg id="finish-spinner" class="hidden animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
var currentStep = 1;
var totalSteps = 5;

function showStep(n) {
    document.querySelectorAll('.step-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelector('.step-content[data-step="' + n + '"]').classList.add('active');

    for (var i = 1; i <= totalSteps; i++) {
        var dot = document.getElementById('dot-' + i);
        var label = document.getElementById('label-' + i);
        var line = document.getElementById('line-' + i);

        dot.classList.remove('active', 'completed', 'pending');
        label.classList.remove('active', 'completed', 'pending');

        if (line) {
            line.classList.remove('active', 'completed', 'pending');
        }

        if (i < n) {
            dot.classList.add('completed');
            dot.textContent = '✓';
            label.classList.add('completed');
            if (line) line.classList.add('completed');
        } else if (i === n) {
            dot.classList.add('active');
            dot.textContent = i;
            label.classList.add('active');
            if (line && i < totalSteps) line.classList.add('active');
        } else {
            dot.classList.add('pending');
            dot.textContent = i;
            label.classList.add('pending');
            if (line) line.classList.add('pending');
        }
    }

    currentStep = n;
}

function validateStep(n) {
    var container = document.querySelector('.step-content[data-step="' + n + '"]');
    var required = container.querySelectorAll('[required]');
    var valid = true;
    for (var i = 0; i < required.length; i++) {
        if (!required[i].value.trim()) {
            required[i].classList.add('border-red-500');
            required[i].classList.remove('border-gray-300');
            valid = false;
        } else {
            required[i].classList.remove('border-red-500');
            required[i].classList.add('border-gray-300');
        }
    }

    if (n === 3) {
        var pw = document.getElementById('password');
        var confirm = document.getElementById('password_confirmation');
        if (pw.value && confirm.value && pw.value !== confirm.value) {
            confirm.classList.add('border-red-500');
            confirm.classList.remove('border-gray-300');
            valid = false;
        }
    }

    if (!valid) {
        alert('<?= __('Please fill in all required fields before continuing.') ?>');
    }
    return valid;
}

function nextStep() {
    if (currentStep < totalSteps) {
        if (!validateStep(currentStep)) return;
        showStep(currentStep + 1);
    }
}

function prevStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

// Prevent double-click on finish button
document.getElementById('setup-form').addEventListener('submit', function() {
    var btn = document.getElementById('finish-btn');
    if (btn.disabled) return false;
    btn.disabled = true;
    document.getElementById('finish-text').classList.add('hidden');
    document.getElementById('finish-spinner').classList.remove('hidden');
});

// Handle validation error redirect — show step 3 so errors are visible
(function() {
    var hasErrors = <?= !empty($_SESSION['_errors']) ? 'true' : 'false' ?>;
    if (hasErrors) {
        showStep(3);
    }
})();

// Timezone filtering by country
var tzByCountry = <?= json_encode($tzByCountry) ?>;
var tzSelect = document.getElementById('timezone');
var countrySelect = document.getElementById('default_country');

function filterTimezones(country) {
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
