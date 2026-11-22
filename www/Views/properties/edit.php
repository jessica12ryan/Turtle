<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Edit Property') ?></h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
    <form method="POST" action="/properties/<?= $property['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Landlord') ?> <span class="text-red-500">*</span></label>
            <select name="landlord_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Landlord') ?></option>
                <?php foreach ($landlords as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $property['landlord_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?> (<?= h($l['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Property Manager') ?> <span class="text-red-500">*</span></label>
            <select name="property_manager_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Property Manager') ?></option>
                <?php foreach ($propertyManagers as $pm): ?>
                    <option value="<?= $pm['id'] ?>" <?= $property['property_manager_id'] == $pm['id'] ? 'selected' : '' ?>><?= h($pm['name']) ?> (<?= h($pm['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Nickname') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($property['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Address') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="address" value="<?= h($property['address']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Apt/Suite') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
            <input type="text" name="apt_suite" value="<?= h($property['apt_suite'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('e.g., Apt 2B, Suite 300') ?>">
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('City/Town') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="city" value="<?= h($property['city']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Country') ?> <span class="text-red-500">*</span></label>
                <select name="country" id="property_country" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value="CA" <?= ($property['country'] ?? 'CA') === 'CA' ? 'selected' : '' ?>>Canada</option>
                    <option value="US" <?= ($property['country'] ?? 'CA') === 'US' ? 'selected' : '' ?>>United States</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" id="region_label"><?= __('Province') ?> <span class="text-red-500">*</span></label>
                <select name="province" id="property_province" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value=""><?= __('Select Province') ?></option>
                    <?php foreach (regions($property['country'] ?? 'CA') as $code => $name): ?>
                        <option value="<?= $code ?>" <?= ($property['province'] ?? '') === $code ? 'selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" id="postal_label"><?= __('Postal Code') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="postal_code" id="property_postal" value="<?= h($property['postal_code']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500<?= ($property['country'] ?? 'CA') === 'CA' ? ' uppercase' : '' ?>" required placeholder="<?= ($property['country'] ?? 'CA') === 'US' ? '12345' : 'A1A 1A1' ?>">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Monthly Rent ($)') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
                <input type="number" name="rent_amount" value="<?= h($property['rent_amount'] ?? '') ?>" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="0.00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Rent Due Day') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
                <select name="rent_due_day" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select —') ?></option>
                    <?php for ($d = 1; $d <= 28; $d++): ?>
                        <option value="<?= $d ?>" <?= ($property['rent_due_day'] ?? '') == $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Security Deposit ($)') ?> <span class="text-gray-400 text-xs">(<?= __('optional') ?>)</span></label>
                <input type="number" name="security_deposit" value="<?= h($property['security_deposit'] ?? '') ?>" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="0.00">
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Heating Type') ?> <span class="text-red-500">*</span></label>
            <select name="heating_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Heating Type') ?></option>
                <option value="Oil - Forced Air" <?= ($property['heating_type'] ?? '') === 'Oil - Forced Air' ? 'selected' : '' ?>><?= __('Oil - Forced Air') ?></option>
                <option value="Oil - Hot Water" <?= ($property['heating_type'] ?? '') === 'Oil - Hot Water' ? 'selected' : '' ?>><?= __('Oil - Hot Water') ?></option>
                <option value="Electric" <?= ($property['heating_type'] ?? '') === 'Electric' ? 'selected' : '' ?>><?= __('Electric') ?></option>
                <option value="Propane" <?= ($property['heating_type'] ?? '') === 'Propane' ? 'selected' : '' ?>><?= __('Propane') ?></option>
                <option value="Natural Gas" <?= ($property['heating_type'] ?? '') === 'Natural Gas' ? 'selected' : '' ?>><?= __('Natural Gas') ?></option>
                <option value="Other" <?= ($property['heating_type'] ?? '') === 'Other' ? 'selected' : '' ?>><?= __('Other') ?></option>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1" for="ad_description"><?= __('Advertisement Description') ?></label>
            <textarea id="ad_description" name="ad_description" rows="4" maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Describe this property for the public listings page.') ?>"><?= h($property['ad_description'] ?? '') ?></textarea>
            <p class="text-xs text-gray-500 mt-1"><?= __('Shown on the public listings page while this property is listed.') ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= __('Available placeholders') ?>: {{name}}, {{address}}, {{city}}, {{province}}, {{rent}}, {{heating}}, {{deposit}}</p>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Update Property') ?></button>
            <a href="/properties/<?= $property['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
</div>

<!-- Photos -->
<div class="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Property Photos') ?></h2>

    <form id="photo-upload-form" class="mb-6" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Add Photo') ?></label>
        <div class="flex items-center space-x-3">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
            <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm font-medium flex-shrink-0"><?= __('Upload') ?></button>
        </div>
        <p id="upload-error" class="text-sm text-red-600 mt-1 hidden"></p>
    </form>

    <?php if (empty($photos)): ?>
        <p class="text-sm text-gray-500"><?= __('No photos uploaded yet.') ?></p>
    <?php else: ?>
        <?php
        $mainPhotoId = null;
        foreach ($photos as $photo) {
            if (!empty($photo['is_main'])) {
                $mainPhotoId = (int) $photo['id'];
                break;
            }
        }
        $photoIdsJson = h(json_encode(array_map('intval', array_column($photos, 'id')), JSON_INVALID_UTF8_SUBSTITUTE));
        $reorderError = h(json_encode(__('Could not reorder photos.'), JSON_INVALID_UTF8_SUBSTITUTE));
        $setMainError = h(json_encode(__('Could not set the main photo.'), JSON_INVALID_UTF8_SUBSTITUTE));
        $deleteError = h(json_encode(__('Could not delete the photo.'), JSON_INVALID_UTF8_SUBSTITUTE));
        $deleteConfirm = h(json_encode(__('Delete this photo?'), JSON_INVALID_UTF8_SUBSTITUTE));
        ?>
        <p class="text-xs text-gray-500 mb-3"><?= __('Drag photos to reorder. The main photo stays first.') ?></p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4" data-photo-grid
             x-data="{
                 draggedId: null,
                 overId: null,
                 photoIds: <?= $photoIdsJson ?>,
                 mainId: <?= $mainPhotoId !== null ? (int) $mainPhotoId : 'null' ?>,
                 csrf: '<?= h(csrf_token()) ?>',
                 applyOrder(ids) {
                     this.photoIds = ids;
                     const grid = document.querySelector('[data-photo-grid]');
                     const tiles = [...grid.querySelectorAll('[data-photo-id]')];
                     const byId = new Map(tiles.map(tile => [Number(tile.dataset.photoId), tile]));
                     ids.forEach(id => {
                         if (byId.has(id)) { grid.appendChild(byId.get(id)); }
                     });
                 },
                 save(ids) {
                     fetch((window.baseUrl || '') + '/properties/<?= (int) $property['id'] ?>/photos/reorder', {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                         body: JSON.stringify({ ids: ids, _csrf: this.csrf })
                     })
                     .then(r => r.json().then(data => ({ ok: r.ok, data: data })))
                     .then(result => {
                         if (result.ok && result.data.success) {
                             this.applyOrder(ids);
                         } else {
                             alert(result.data.error || <?= $reorderError ?>);
                         }
                     })
                     .catch(() => alert(<?= $reorderError ?>));
                 },
                 setMain(photoId) {
                     const body = new URLSearchParams({ _csrf: this.csrf });
                     fetch((window.baseUrl || '') + '/properties/<?= (int) $property['id'] ?>/photos/' + photoId + '/main', {
                         method: 'POST',
                         headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                         body: body
                     })
                     .then(r => r.json().then(data => ({ ok: r.ok, data: data })))
                     .then(result => {
                         if (result.ok && result.data.success) {
                             this.mainId = photoId;
                             this.applyOrder([photoId, ...this.photoIds.filter(id => id !== photoId)]);
                         } else {
                             alert(result.data.error || <?= $setMainError ?>);
                         }
                     })
                     .catch(() => alert(<?= $setMainError ?>));
                 },
                 remove(photoId) {
                     if (!confirm(<?= $deleteConfirm ?>)) { return; }
                     const body = new URLSearchParams({ _csrf: this.csrf });
                     fetch((window.baseUrl || '') + '/properties/<?= (int) $property['id'] ?>/photos/' + photoId + '/delete', {
                         method: 'POST',
                         headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                         body: body
                     })
                     .then(r => r.json().then(data => ({ ok: r.ok, data: data })))
                     .then(result => {
                         if (result.ok && result.data.success) {
                             const grid = document.querySelector('[data-photo-grid]');
                             const tile = [...grid.querySelectorAll('[data-photo-id]')]
                                 .find(node => Number(node.dataset.photoId) === photoId);
                             if (tile) { tile.remove(); }
                             if (this.mainId === photoId) { this.mainId = null; }
                             this.applyOrder(this.photoIds.filter(id => id !== photoId));
                         } else {
                             alert(result.data.error || <?= $deleteError ?>);
                         }
                     })
                     .catch(() => alert(<?= $deleteError ?>));
                 },
                 reorder(targetId) {
                     const from = this.photoIds.indexOf(this.draggedId);
                     let to = this.photoIds.indexOf(targetId);
                     this.overId = null;
                     if (from < 0 || to < 0 || from === to || this.draggedId === this.mainId) { return; }
                     if (this.mainId !== null && to === 0) { to = 1; }
                     const ids = [...this.photoIds];
                     ids.splice(to, 0, ids.splice(from, 1)[0]);
                     this.save(ids);
                 },
                 move(photoId, direction) {
                     if (photoId === this.mainId) { return; }
                     const ids = [...this.photoIds];
                     const from = ids.indexOf(photoId);
                     const to = from + direction;
                     if (from < 0 || to < 0 || to >= ids.length) { return; }
                     if (this.mainId !== null && to === 0) { return; }
                     [ids[from], ids[to]] = [ids[to], ids[from]];
                     this.save(ids);
                 }
             }">
            <?php foreach ($photos as $photo): ?>
                <?php $photoId = (int) $photo['id']; ?>
                <div class="relative group border rounded-lg overflow-hidden"
                     data-testid="photo-tile" data-photo-id="<?= $photoId ?>"
                     :draggable="mainId === <?= $photoId ?> ? 'false' : 'true'"
                     @dragstart="draggedId = mainId === <?= $photoId ?> ? null : <?= $photoId ?>"
                     @dragend="draggedId = null; overId = null"
                     @dragover.prevent="overId = <?= $photoId ?>"
                     @drop.prevent="reorder(<?= $photoId ?>)"
                     :class="(mainId === <?= $photoId ?> ? 'ring-2 ring-blue-500' : 'cursor-move') +
                             (draggedId === <?= $photoId ?> ? ' opacity-50' : (overId === <?= $photoId ?> && draggedId !== <?= $photoId ?> ? ' ring-2 ring-green-500' : ''))">
                    <img src="/properties/<?= (int) $property['id'] ?>/photos/<?= $photoId ?>" alt="<?= h($photo['original_name']) ?>" class="w-full h-32 object-cover pointer-events-none">
                    <span x-show="mainId === <?= $photoId ?>" x-cloak data-testid="main-badge" class="absolute top-1 left-1 bg-blue-600 text-white text-xs px-2 py-0.5 rounded"><?= __('Main') ?></span>
                    <form method="POST" action="/properties/<?= (int) $property['id'] ?>/photos/<?= $photoId ?>/main"
                          @submit.prevent="setMain(<?= $photoId ?>)" x-show="mainId !== <?= $photoId ?>" x-cloak
                          class="absolute top-1 right-1 z-10">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <button type="submit" data-testid="set-as-main" class="bg-blue-600 text-white text-xs px-2 py-1 rounded hover:bg-blue-700"><?= __('Set as Main') ?></button>
                    </form>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition flex items-center justify-center space-x-2 opacity-0 group-hover:opacity-100">
                        <a href="/properties/<?= (int) $property['id'] ?>/photos/<?= $photoId ?>/download" class="bg-white text-gray-800 p-1.5 rounded-full hover:bg-gray-100" title="<?= __('Download') ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </a>
                        <form method="POST" action="/properties/<?= (int) $property['id'] ?>/photos/<?= $photoId ?>/delete"
                              @submit.prevent="remove(<?= $photoId ?>)" class="inline">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <button type="submit" data-testid="delete-photo" class="bg-red-600 text-white text-xs px-3 py-1.5 rounded hover:bg-red-700"><?= __('Delete') ?></button>
                        </form>
                    </div>
                    <div x-show="mainId !== <?= $photoId ?>" x-cloak class="absolute bottom-1 left-1 z-10 flex gap-1">
                        <button type="button" @click="move(<?= $photoId ?>, -1)"
                                :disabled="photoIds.indexOf(<?= $photoId ?>) === (mainId === null ? 0 : 1)"
                                data-testid="move-photo-left" aria-label="<?= __('Move photo left') ?>" title="<?= __('Move photo left') ?>"
                                class="bg-white/90 text-gray-800 rounded p-1 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="move(<?= $photoId ?>, 1)"
                                :disabled="photoIds.indexOf(<?= $photoId ?>) === photoIds.length - 1"
                                data-testid="move-photo-right" aria-label="<?= __('Move photo right') ?>" title="<?= __('Move photo right') ?>"
                                class="bg-white/90 text-gray-800 rounded p-1 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (can('properties.archive')): ?>
    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <div class="mt-0 pt-0 border-t-0">
            <h3 class="text-lg font-medium text-red-600 mb-2"><?= __('Danger Zone') ?></h3>
            <form method="POST" action="/properties/<?= $property['id'] ?>/delete" onsubmit="return confirm('<?= __('WARNING: This will permanently delete this property and all its associated data. This is NOT reversible. Continue?') ?>')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm"><?= __('Delete Property') ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
var regions = <?= json_encode(['CA' => regions('CA'), 'US' => regions('US')], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
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
        postalInput.className = postalInput.className.replace('uppercase', '').trim();
    } else if (postalInput.className.indexOf('uppercase') === -1) {
        postalInput.className += ' uppercase';
    }
}

if (countrySelect) {
    countrySelect.addEventListener('change', function() {
        updateRegionFields(this.value);
    });
}

document.getElementById('photo-upload-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const errorEl = document.getElementById('upload-error');
    errorEl.classList.add('hidden');

    const formData = new FormData(form);

    fetch((window.baseUrl || '') + '/properties/<?= $property['id'] ?>/photos', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            errorEl.textContent = data.error;
            errorEl.classList.remove('hidden');
        } else {
            window.location.reload();
        }
    })
    .catch(err => {
        errorEl.textContent = '<?= __('Upload failed.') ?>';
        errorEl.classList.remove('hidden');
    });
});
</script>
