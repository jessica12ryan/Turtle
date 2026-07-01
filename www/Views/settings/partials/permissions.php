<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Permissions</h2>
    <p class="text-sm text-gray-600 mb-6">Grant or revoke individual permissions for each role. The admin role always has full access.</p>

    <form method="POST" action="/settings/permissions">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-6 p-4 bg-gray-50 rounded-lg border">
            <label class="flex items-center space-x-3">
                <input type="radio" name="permissions_mode" value="default" <?= ($permissionsMode ?? 'default') === 'default' ? 'checked' : '' ?> onchange="this.form.submit()" class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700">Use default permissions</span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1">Permissions will automatically update when the application is updated.</p>
            <label class="flex items-center space-x-3 mt-2">
                <input type="radio" name="permissions_mode" value="custom" <?= ($permissionsMode ?? 'default') === 'custom' ? 'checked' : '' ?> onchange="this.form.submit()" class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700">Custom permissions</span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1">Manually configure each role's permissions. These will be preserved on update.</p>
        </div>

        <?php
        $permissionLabels = [
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
            'documents.download' => 'Download Files',
            'tickets.access' => 'View Tickets',
            'tickets.create' => 'Create Tickets',
            'tickets.assign' => 'Assign Tickets',
            'tickets.update_status' => 'Update Ticket Status',
            'tickets.archive' => 'Archive Tickets',
            'tickets.restore' => 'Restore Tickets',
            'tickets.delete' => 'Delete Tickets',
            'tickets.comment' => 'Comment on Tickets',
            'tickets.internal_comment' => 'Internal Comments on Tickets',
            'tickets.upload_photos' => 'Upload Photos/Documents to Tickets',
            'tickets.download_photos' => 'Download Photos/Documents from Tickets',
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
            'ai_assistant.access' => 'Use AI Assistant',
            'rents.access' => 'View Rents',
            'rents.payments.create' => 'Add Payments',
            'rents.payments.edit' => 'Edit Payments',
            'rents.payments.archive' => 'Archive Payments',
            'rents.payments.restore' => 'Restore Payments',
            'applications.view' => 'View Applications',
            'applications.edit' => 'Edit Applications',
            'applications.archive' => 'Archive Applications',
            'applications.restore' => 'Restore Applications',
            'applications.delete' => 'Delete Applications',
        ];

        $groupOverrides = [
            'documents' => 'leases',
        ];

        $groupLabels = [
            'ai_assistant' => 'AI Assistant',
        ];

        $hiddenTenantPerms = [
            'properties.access', 'properties.create', 'properties.edit', 'properties.archive', 'properties.restore', 'properties.delete',
            'photos.create', 'photos.edit', 'photos.download', 'photos.delete',
            'tenants.access', 'tenants.create', 'tenants.edit', 'tenants.archive', 'tenants.restore', 'tenants.delete',
            'leases.create', 'leases.archive', 'leases.restore', 'leases.delete',
            'tickets.archive', 'tickets.restore', 'tickets.delete', 'tickets.internal_comment',
            'staff.access', 'staff.create', 'staff.edit', 'staff.archive', 'staff.restore', 'staff.delete',
            'resources.create', 'resources.edit', 'resources.delete',
            'calendar.access',
            'ai_assistant.access',
            'applications.view',
            'applications.edit',
            'applications.archive',
            'applications.restore',
            'applications.delete',
        ];

        $groups = [];
        foreach (array_keys($permissionLabels) as $p) {
            $parts = explode('.', $p, 2);
            $group = $parts[0] ?? '';
            $group = $groupOverrides[$group] ?? $group;
            $groups[$group][] = $p;
        }

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
                'internal_comment' => 'text-cyan-600',
                'update_status' => 'text-cyan-600',
                'restore' => 'text-orange-600',
                'download' => 'text-purple-600',
                'upload_photos' => 'text-cyan-600',
                'download_photos' => 'text-cyan-600',
                'use' => 'text-indigo-600',
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
                'internal_comment' => 'text-cyan-600 focus:ring-cyan-500',
                'update_status' => 'text-cyan-600 focus:ring-cyan-500',
                'restore' => 'text-orange-600 focus:ring-orange-500',
                'download' => 'text-purple-600 focus:ring-purple-500',
                'upload_photos' => 'text-cyan-600 focus:ring-cyan-500',
                'download_photos' => 'text-cyan-600 focus:ring-cyan-500',
                'use' => 'text-indigo-600 focus:ring-indigo-500',
            ];
            return $map[$action] ?? 'text-gray-600 focus:ring-gray-500';
        }
        ?>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-white">
                    <tr class="border-b">
                        <th class="text-left py-2 pr-4 font-medium text-gray-500 bg-white">Permission</th>
                        <?php foreach ($roles as $role): ?>
                            <th class="text-center py-2 px-3 font-medium text-gray-500 bg-white"><?= h(ucwords(str_replace('_', ' ', $role))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group => $perms): ?>
                        <tr class="border-t border-gray-100">
                            <td colspan="5" class="py-2 font-semibold text-gray-700 capitalize"><?= h($groupLabels[$group] ?? ($group === 'leases' ? 'Documents' : $group)) ?></td>
                        </tr>
                        <?php foreach ($perms as $perm): ?>
                            <?php $label = $permissionLabels[$perm] ?? $perm; ?>
                            <?php $isDefault = ($permissionsMode ?? 'default') === 'default'; ?>
                            <tr class="hover:bg-gray-50 <?= $isDefault ? 'opacity-60' : '' ?>">
                                <td class="py-1.5 pr-4 <?= permColor($perm) ?>"><?= h($label) ?></td>
                                <?php foreach ($roles as $role): ?>
                                    <?php $defaultGranted = in_array($perm, $defaults[$role] ?? []); ?>
                                    <?php $overridden = isset($overrides[$role]) && in_array($perm, $overrides[$role]); ?>
                                    <?php $checked = $isDefault ? $defaultGranted : $overridden; ?>
                                    <?php $isTenantHidden = $role === 'tenant' && in_array($perm, $hiddenTenantPerms); ?>
                                    <td class="text-center py-1.5 px-3">
                                        <?php if ($isTenantHidden): ?>
                                            <span class="text-gray-300">—</span>
                                        <?php else: ?>
                                        <input type="checkbox"
                                               name="perms[<?= h($role) ?>][]"
                                               value="<?= h($perm) ?>"
                                               <?= $checked ? 'checked' : '' ?>
                                               <?= $isDefault ? 'disabled' : '' ?>
                                               onchange="this.form.submit()"
                                               class="rounded border-gray-300 <?= permCheckboxColor($perm) ?> <?= $isDefault ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>