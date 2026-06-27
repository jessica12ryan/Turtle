<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Upload Document') ?></h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <?php if (empty($properties)): ?>
        <div class="p-6 text-center text-gray-500">
            <p><?= __('No properties with active tenants exist.') ?></p>
            <a href="/properties" class="text-blue-600 hover:underline mt-2 inline-block"><?= __('View Properties') ?></a>
        </div>
    <?php else: ?>
        <form method="POST" action="/leases" enctype="multipart/form-data" x-data='{
            properties: <?= json_encode(array_map(function($p) {
                return ['id' => $p['id'], 'name' => $p['name'], 'main_tenant_id' => $p['main_tenant_id'] ?? null];
            }, $properties), JSON_HEX_APOS) ?>,
            tenantNames: <?= json_encode($tenantNames, JSON_HEX_APOS) ?>,
            selectedProperty: "<?= $preselectedPropertyId ?? '' ?>",
            get mainTenantName() {
                const prop = this.properties.find(p => p.id == this.selectedProperty);
                return prop && prop.main_tenant_id ? (this.tenantNames[prop.id] || "Unknown") : null;
            }
        }'>
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Property') ?> <span class="text-red-500">*</span></label>
                <select name="property_id" x-model="selectedProperty" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value=""><?= __('Select Property') ?></option>
                    <?php foreach ($properties as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (old('property_id') == $p['id'] || ($preselectedPropertyId == $p['id'] && !old('property_id'))) ? 'selected' : '' ?>><?= h($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4 p-4 bg-gray-50 rounded-lg" x-show="selectedProperty && mainTenantName" x-cloak>
                <p class="text-sm text-gray-700">
                    <span class="font-medium"><?= __('Main Tenant:') ?></span>
                    <span x-text="mainTenantName" class="text-blue-600"></span>
                </p>
                <input type="hidden" name="tenant_id" x-bind:value="selectedProperty ? properties.find(p => p.id == selectedProperty)?.main_tenant_id || '' : ''">
            </div>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg" x-show="selectedProperty && !mainTenantName" x-cloak>
                <p class="text-red-700 text-sm font-medium"><?= __('No tenant exists for this property.') ?> <a x-bind:href="'/tenants/create?property_id=' + selectedProperty" class="text-blue-600 hover:underline"><?= __('Click here to add a tenant.') ?></a></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Title') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="<?= old('title') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Description') ?></label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"><?= old('description') ?></textarea>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Upload Documents') ?></label>
                <input type="file" name="documents[]" multiple class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1"><?= __('PDF, DOC, DOCX, images accepted') ?></p>
            </div>
            <div class="flex space-x-3">
                <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed" x-bind:disabled="selectedProperty && !mainTenantName"><?= __('Upload Document') ?></button>
                <a href="/leases" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>[x-cloak] { display: none !important; }</style>
