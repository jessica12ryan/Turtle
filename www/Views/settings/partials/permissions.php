<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Permissions</h2>
    <p class="text-sm text-gray-600 mb-6">Grant or revoke individual permissions for each role. The admin role always has full access.</p>

    <form method="POST" action="/settings/permissions" x-data="{
        mode: '<?= $permissionsMode ?? 'default' ?>',
        init() {
            this.$watch('mode', val => this.sync(val));
            this.$nextTick(() => this.sync(this.mode));
        },
        sync(val) {
            this.$el.querySelectorAll('[data-d]').forEach(el => {
                el.checked = val === 'default' ? el.dataset.d === 'true' : el.dataset.o === 'true';
            });
        }
    }">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-6 p-4 bg-gray-50 rounded-lg border">
            <label class="flex items-center space-x-3">
                <input type="radio" name="permissions_mode" value="default" x-model="mode" class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700">Use default permissions</span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1">Permissions will automatically update when the application is updated.</p>
            <label class="flex items-center space-x-3 mt-2">
                <input type="radio" name="permissions_mode" value="custom" x-model="mode" class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700">Custom permissions</span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1">Manually configure each role's permissions. These will be preserved on update.</p>
        </div>

        <?php
        $allPerms = [];
        foreach ($defaults as $role => $perms) {
            foreach ($perms as $p) {
                $allPerms[$p] = true;
            }
        }
        foreach ($overrides as $role => $perms) {
            foreach ($perms as $p) {
                $allPerms[$p] = true;
            }
        }
        $allPerms = array_keys($allPerms);
        sort($allPerms);

        $groupOverrides = [
            'documents' => 'leases',
        ];

        $groups = [];
        foreach ($allPerms as $p) {
            $parts = explode('.', $p, 2);
            $group = $parts[0] ?? '';
            $group = $groupOverrides[$group] ?? $group;
            $groups[$group][] = $p;
        }

        $permissionLabels = [
            'home.access' => 'View Home',
            'properties.access' => 'View Properties',
            'properties.create' => 'Create Properties',
            'properties.edit' => 'Edit Properties',
            'properties.archive' => 'Archive Properties',
            'properties.restore' => 'Restore Properties',
            'properties.delete' => 'Delete Properties',
            'photos.create' => 'Upload Photos',
            'photos.edit' => 'Edit Photos',
            'photos.download' => 'Download Photos',
            'photos.delete' => 'Delete Photos',
            'tenants.access' => 'View Tenants',
            'tenants.create' => 'Create Tenants',
            'tenants.edit' => 'Edit Tenants',
            'tenants.archive' => 'Archive Tenants',
            'tenants.restore' => 'Restore Tenants',
            'tenants.delete' => 'Delete Tenants',
            'leases.access' => 'View Leases',
            'leases.create' => 'Create Leases',
            'leases.archive' => 'Archive Leases',
            'leases.restore' => 'Restore Leases',
            'leases.delete' => 'Delete Leases',
            'documents.download' => 'Download Documents',
            'documents.delete' => 'Delete Documents',
            'tickets.access' => 'View Tickets',
            'tickets.create' => 'Create Tickets',
            'tickets.assign' => 'Assign Tickets',
            'tickets.update_status' => 'Update Ticket Status',
            'tickets.archive' => 'Archive Tickets',
            'tickets.restore' => 'Restore Tickets',
            'tickets.delete' => 'Delete Tickets',
            'tickets.comment' => 'Comment on Tickets',
            'staff.access' => 'View Staff',
            'staff.create' => 'Create Staff',
            'staff.edit' => 'Edit Staff',
            'staff.archive' => 'Archive Staff',
            'staff.restore' => 'Restore Staff',
            'staff.delete' => 'Delete Staff',
            'resources.access' => 'View Resources',
            'resources.create' => 'Create Resources',
            'resources.edit' => 'Edit Resources',
            'resources.delete' => 'Delete Resources',
            'calendar.access' => 'View Calendar',
        ];

        function permColor(string $perm): string
        {
            $parts = explode('.', $perm);
            $action = end($parts);
            $map = [
                'delete' => 'text-red-600',
                'archive' => 'text-orange-600',
                'access' => 'text-green-600',
                'edit' => 'text-blue-600',
                'create' => 'text-yellow-600',
                'assign' => 'text-cyan-600',
                'comment' => 'text-cyan-600',
                'update_status' => 'text-cyan-600',
                'restore' => 'text-orange-600',
                'download' => 'text-purple-600',
            ];
            return $map[$action] ?? 'text-gray-600';
        }

        function permCheckboxColor(string $perm): string
        {
            $parts = explode('.', $perm);
            $action = end($parts);
            $map = [
                'delete' => 'text-red-600 focus:ring-red-500',
                'archive' => 'text-orange-600 focus:ring-orange-500',
                'access' => 'text-green-600 focus:ring-green-500',
                'edit' => 'text-blue-600 focus:ring-blue-500',
                'create' => 'text-yellow-600 focus:ring-yellow-500',
                'assign' => 'text-cyan-600 focus:ring-cyan-500',
                'comment' => 'text-cyan-600 focus:ring-cyan-500',
                'update_status' => 'text-cyan-600 focus:ring-cyan-500',
                'restore' => 'text-orange-600 focus:ring-orange-500',
                'download' => 'text-purple-600 focus:ring-purple-500',
            ];
            return $map[$action] ?? 'text-gray-600 focus:ring-gray-500';
        }
        ?>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 pr-4 font-medium text-gray-500">Permission</th>
                        <?php foreach ($roles as $role): ?>
                            <th class="text-center py-2 px-3 font-medium text-gray-500 capitalize"><?= h($role) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group => $perms): ?>
                        <tr class="border-t border-gray-100">
                            <td colspan="5" class="py-2 font-semibold text-gray-700 capitalize"><?= h($group === 'leases' ? 'Leases & Documents' : $group) ?></td>
                        </tr>
                        <?php foreach ($perms as $perm): ?>
                            <?php $label = $permissionLabels[$perm] ?? $perm; ?>
                            <tr class="hover:bg-gray-50" :class="{ 'opacity-60': mode === 'default' }">
                                <td class="py-1.5 pr-4 <?= permColor($perm) ?>"><?= h($label) ?></td>
                                <?php foreach ($roles as $role): ?>
                                    <?php
                                    $defaultGranted = in_array($perm, $defaults[$role] ?? []);
                                    $overridden = isset($overrides[$role]) && in_array($perm, $overrides[$role]);
                                    ?>
                                    <td class="text-center py-1.5 px-3">
                                        <input type="checkbox"
                                               name="perms[<?= h($role) ?>][]"
                                               value="<?= h($perm) ?>"
                                               :disabled="mode === 'default'"
                                               data-d="<?= $defaultGranted ? 'true' : 'false' ?>"
                                               data-o="<?= ($overridden || $defaultGranted) ? 'true' : 'false' ?>"
                                               class="rounded border-gray-300 <?= permCheckboxColor($perm) ?>"
                                               :class="{ 'opacity-50 cursor-not-allowed': mode === 'default' }">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Save Permissions</button>
        </div>
    </form>
</div>
