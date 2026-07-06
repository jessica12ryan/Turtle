<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4"><?= __('Email Notifications') ?></h2>
    <p class="text-sm text-gray-600 mb-6"><?= __('Choose who receives email notifications for each event.') ?></p>

    <form method="POST" action="/settings/notifications">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-6 p-4 bg-gray-50 rounded-lg border">
            <label class="flex items-center space-x-3">
                <input type="radio" name="notification_mode" value="default" <?= ($notificationMode ?? 'default') === 'default' ? 'checked' : '' ?> onchange="this.form.submit()" class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700"><?= __('Use default email notifications') ?></span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Use the default notifications designed for Turtle.') ?></p>
            <label class="flex items-center space-x-3 mt-2">
                <input type="radio" name="notification_mode" value="custom" <?= ($notificationMode ?? 'default') === 'custom' ? 'checked' : '' ?> onchange="this.form.submit()" class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700"><?= __('Custom email notifications') ?></span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Manually configure who receives each notification.') ?></p>
        </div>

        <?php
        $notifLabels = [
            'ticket_assigned' => 'Ticket Assigned',
            'ticket_status_updated' => 'Ticket Status Updated',
            'document_uploaded' => 'Document Uploaded',
            'staff_welcome' => 'Staff Welcome Email',
            'tenant_welcome' => 'Tenant Welcome Email',
            'password_reset' => 'Password Reset',
            'onboarding' => 'Onboarding Email',
        ];

        $isDefault = ($notificationMode ?? 'default') === 'default';
        ?>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-white">
                    <tr class="border-b">
                        <th class="text-left py-2 pr-4 font-medium text-gray-500 bg-white"><?= __('Event') ?></th>
                        <?php foreach ($notifRoles as $role): ?>
                            <th class="text-center py-2 px-3 font-medium text-gray-500 bg-white"><?= h(ucwords(str_replace('_', ' ', $role))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifLabels as $event => $label): ?>
                        <tr class="border-t border-gray-100 hover:bg-gray-50 <?= $isDefault ? 'opacity-60' : '' ?>">
                            <td class="py-1.5 pr-4 text-blue-600 font-medium"><?= h($label) ?></td>
                            <?php foreach ($notifRoles as $role): ?>
                                <?php $defaultGranted = in_array($role, $notifDefaults[$event] ?? []); ?>
                                <?php $overridden = isset($notifOverrides[$event]) && in_array($role, $notifOverrides[$event]); ?>
                                <?php $checked = $isDefault ? $defaultGranted : $overridden; ?>
                                <td class="text-center py-1.5 px-3">
                                    <input type="checkbox"
                                           name="events[<?= h($event) ?>][]"
                                           value="<?= h($role) ?>"
                                           <?= $checked ? 'checked' : '' ?>
                                           <?= $isDefault ? 'disabled' : '' ?>
                                           onchange="this.form.submit()"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 <?= $isDefault ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
