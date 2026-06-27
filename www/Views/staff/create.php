<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Invite Staff Member') ?></h1>
</div>
<div class="bg-white rounded-lg shadow p-6 max-w-lg">
    <script>
    var staffCreateData = function() {
        return {
            role: '<?= old('role') ?>',
            secondaryRoleMap: <?= json_encode($secondaryRoleMap) ?>,
            checked: <?= json_encode(array_values($staffSecondaryRoles)) ?>,
            get validSecondary() { return this.secondaryRoleMap[this.role] || []; },
            toggle(sr) {
                if (this.checked.includes(sr)) {
                    this.checked = this.checked.filter(r => r !== sr);
                } else {
                    this.checked.push(sr);
                }
            },
            isChecked(sr) { return this.checked.includes(sr); }
        };
    };
    </script>
    <form method="POST" action="/staff" x-data="staffCreateData()">
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
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Role') ?> <span class="text-red-500">*</span></label>
            <select name="role" x-model="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value=""><?= __('Select Role') ?></option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role ?>" <?= old('role') === $role ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4" x-show="role && validSecondary.length > 0" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Secondary Roles') ?></label>
            <template x-for="sr in validSecondary" :key="sr">
                <label class="flex items-center space-x-2 mb-1">
                    <input type="checkbox" name="secondary_roles[]" :value="sr" :checked="isChecked(sr)" @click="toggle(sr)" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700" x-text="sr.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())"></span>
                </label>
            </template>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Language') ?></label>
            <select name="language" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value=""><?= __('Use default language') ?></option>
                <?php foreach (languages() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= old('language') === $code ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <?php $currentTimezone = old('timezone'); require base_path('www/Views/partials/timezone.php'); ?>
        </div>
        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="send_welcome_email" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700"><?= __('Send welcome email with temporary password') ?></span>
            </label>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 font-medium"><?= __('Invite Staff') ?></button>
            <a href="/staff" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
</div>
<style>[x-cloak] { display: none !important; }</style>
