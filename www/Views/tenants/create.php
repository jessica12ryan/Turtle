<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Add Tenant') ?></h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/tenants">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Full Name') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= old('name') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email') ?> <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="<?= old('email') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="phone" value="<?= old('phone') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required placeholder="(555) 555-5555" x-data x-init="$el.addEventListener('input', function() { let x = this.value.replace(/[^\d]/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/); this.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : ''); })">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Property') ?> <span class="text-red-500">*</span></label>
            <select name="property_id" id="property-select" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Property') ?></option>
                <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (old('property_id') == $p['id'] || ($_GET['property_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= h($p['name']) ?> (<?= h($p['landlord_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_main_tenant" id="is-main-tenant" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700"><?= __('Make this the main tenant') ?></span>
            </label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Lease Start') ?> <span class="text-red-500">*</span></label>
                <input type="date" name="lease_start" id="lease-start" value="<?= old('lease_start') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Lease End') ?></label>
                <input type="date" name="lease_end" id="lease-end" value="<?= old('lease_end') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1"><?= __('Optional — leave blank for month-to-month') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Scheduled Move Out') ?></label>
                <input type="date" name="move_out_date" id="move-out-date" value="<?= old('move_out_date') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1"><?= __('Optional — tenant auto-archives on this date.') ?></p>
            </div>
        </div>
        <div class="mb-4">
            <?php $currentTimezone = old('timezone'); require base_path('www/Views/partials/timezone.php'); ?>
        </div>
        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="send_welcome_email" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700"><?= __('Send tenant welcome email for onboarding') ?></span>
            </label>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700"><?= __('Add Tenant') ?></button>
            <a href="/tenants" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
</div>

<script>
document.querySelector('form').addEventListener('submit', function() {
    document.querySelectorAll('#lease-start, #lease-end, #move-out-date').forEach(el => el.removeAttribute('disabled'));
});

const mainTenants = <?= json_encode($mainTenants) ?>;

function syncLeaseDates() {
    const isMain = document.getElementById('is-main-tenant').checked;
    const propId = document.getElementById('property-select').value;
    const startEl = document.getElementById('lease-start');
    const endEl = document.getElementById('lease-end');
    const moveOutEl = document.getElementById('move-out-date');

    if (isMain) {
        [startEl, endEl, moveOutEl].forEach(el => {
            el.removeAttribute('disabled');
            el.classList.remove('bg-gray-100');
        });
    } else if (propId && mainTenants[propId]) {
        startEl.value = mainTenants[propId].lease_start || '';
        endEl.value = mainTenants[propId].lease_end || '';
        if (mainTenants[propId].move_out_date) {
            moveOutEl.value = mainTenants[propId].move_out_date;
        }
        [startEl, endEl, moveOutEl].forEach(el => {
            el.setAttribute('disabled', 'disabled');
            el.classList.add('bg-gray-100');
        });
    }
}

document.getElementById('is-main-tenant').addEventListener('change', syncLeaseDates);
document.getElementById('property-select').addEventListener('change', syncLeaseDates);
</script>
