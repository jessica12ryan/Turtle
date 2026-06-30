<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Add Property') ?></h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/properties">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Landlord') ?> <span class="text-red-500">*</span></label>
            <select name="landlord_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Landlord') ?></option>
                <?php foreach ($landlords as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= old('landlord_id') == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?> (<?= h($l['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Property Manager') ?> <span class="text-red-500">*</span></label>
            <select name="property_manager_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Property Manager') ?></option>
                <?php foreach ($propertyManagers as $pm): ?>
                    <option value="<?= $pm['id'] ?>" <?= old('property_manager_id') == $pm['id'] ? 'selected' : '' ?>><?= h($pm['name']) ?> (<?= h($pm['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Nickname') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= old('name') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Address') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="address" value="<?= old('address') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Apt/Suite') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
            <input type="text" name="apt_suite" value="<?= old('apt_suite') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('e.g., Apt 2B, Suite 300') ?>">
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('City/Town') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="city" value="<?= old('city') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Country') ?> <span class="text-red-500">*</span></label>
                <select name="country" id="property_country" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value="CA" <?= old('country', default_country()) === 'CA' ? 'selected' : '' ?>>Canada</option>
                    <option value="US" <?= old('country', default_country()) === 'US' ? 'selected' : '' ?>>United States</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" id="region_label"><?= __('Province') ?> <span class="text-red-500">*</span></label>
                <select name="province" id="property_province" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value=""><?= __('Select Province') ?></option>
                    <?php foreach (regions('CA') as $code => $name): ?>
                        <option value="<?= $code ?>" <?= old('province') === $code ? 'selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" id="postal_label"><?= __('Postal Code') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="postal_code" id="property_postal" value="<?= old('postal_code') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required placeholder="A1A 1A1">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Monthly Rent ($)') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
                <input type="number" name="rent_amount" value="<?= old('rent_amount') ?>" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Rent Due Day') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
                <select name="rent_due_day" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select —') ?></option>
                    <?php for ($d = 1; $d <= 28; $d++): ?>
                        <option value="<?= $d ?>" <?= old('rent_due_day') == $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Security Deposit ($)') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
                <input type="number" name="security_deposit" value="<?= old('security_deposit') ?>" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="0.00">
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Heating Type') ?> <span class="text-red-500">*</span></label>
            <select name="heating_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Heating Type') ?></option>
                <option value="Oil - Forced Air" <?= old('heating_type') === 'Oil - Forced Air' ? 'selected' : '' ?>><?= __('Oil - Forced Air') ?></option>
                <option value="Oil - Hot Water" <?= old('heating_type') === 'Oil - Hot Water' ? 'selected' : '' ?>><?= __('Oil - Hot Water') ?></option>
                <option value="Electric" <?= old('heating_type') === 'Electric' ? 'selected' : '' ?>><?= __('Electric') ?></option>
                <option value="Propane" <?= old('heating_type') === 'Propane' ? 'selected' : '' ?>><?= __('Propane') ?></option>
                <option value="Natural Gas" <?= old('heating_type') === 'Natural Gas' ? 'selected' : '' ?>><?= __('Natural Gas') ?></option>
                <option value="Other" <?= old('heating_type') === 'Other' ? 'selected' : '' ?>><?= __('Other') ?></option>
            </select>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 font-medium"><?= __('Add Property') ?></button>
            <a href="/properties" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
</div>

<script>
var regions = <?= json_encode(['CA' => regions('CA'), 'US' => regions('US')]) ?>;
var countrySelect = document.getElementById('property_country');
var provinceSelect = document.getElementById('property_province');
var regionLabel = document.getElementById('region_label');
var postalLabel = document.getElementById('postal_label');
var postalInput = document.getElementById('property_postal');

function updateRegionFields(country) {
    var opts = regions[country] || regions['CA'];
    var selected = provinceSelect.value;
    provinceSelect.innerHTML = '<option value="">Select ' + (country === 'US' ? 'State' : 'Province') + '</option>';
    Object.keys(opts).forEach(function(code) {
        var opt = document.createElement('option');
        opt.value = code;
        opt.textContent = opts[code];
        if (code === selected) opt.selected = true;
        provinceSelect.appendChild(opt);
    });
    regionLabel.innerHTML = (country === 'US' ? 'State' : 'Province') + ' <span class="text-red-500">*</span>';
    postalLabel.innerHTML = (country === 'US' ? 'Zip Code' : 'Postal Code') + ' <span class="text-red-500">*</span>';
    postalInput.placeholder = country === 'US' ? '12345' : 'A1A 1A1';
    if (country === 'US') {
        postalInput.className = postalInput.className.replace('uppercase', '');
    } else if (postalInput.className.indexOf('uppercase') === -1) {
        postalInput.className += ' uppercase';
    }
}

if (countrySelect) {
    updateRegionFields(countrySelect.value);
    countrySelect.addEventListener('change', function() {
        updateRegionFields(this.value);
    });
}
</script>
