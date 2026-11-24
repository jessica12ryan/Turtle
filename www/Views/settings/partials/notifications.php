<?php
$hideNotifCell = function (string $event, string $role): bool {
    return ($event === 'staff_welcome' && $role === 'tenant')
        || ($event === 'tenant_welcome' && in_array($role, ['admin', 'landlord', 'property_manager', 'maintenance'], true))
        || ($role === 'tenant' && in_array($event, propertyNotificationEvents(), true));
};
?>
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4"><?= __('Email Notifications') ?></h2>
    <p class="text-sm text-gray-600 mb-6"><?= __('Choose who receives email notifications for each event.') ?></p>
    <p class="text-xs text-gray-500 mb-4"><?= __('Property notifications go to the property manager, or to the landlord when no manager is set.') ?></p>

    <form method="POST" action="/settings/notifications">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-6 p-4 bg-gray-50 rounded-lg border">
            <label class="flex items-center space-x-3">
                <input type="radio" name="notification_mode" value="default" <?= ($notificationMode ?? 'default') === 'default' ? 'checked' : '' ?> data-auto-submit class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700"><?= __('Use default email notifications') ?></span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Use the default notifications designed for Turtle.') ?></p>
            <label class="flex items-center space-x-3 mt-2">
                <input type="radio" name="notification_mode" value="custom" <?= ($notificationMode ?? 'default') === 'custom' ? 'checked' : '' ?> data-auto-submit class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700"><?= __('Custom email notifications') ?></span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Manually configure who receives each notification.') ?></p>
        </div>

        <?php
        $notifLabels = [
            'ticket_assigned' => 'Ticket Assigned',
            'ticket_created' => 'Ticket Created',
            'ticket_comment' => 'Ticket Comment',
            'new_message' => 'New Message',
            'ticket_status_updated' => 'Ticket Status Updated',
            'document_uploaded' => 'Document Uploaded',
            'staff_welcome' => 'Staff Welcome Email',
            'tenant_welcome' => 'Tenant Welcome Email',
            'password_reset' => 'Password Reset',
            'onboarding' => 'Onboarding Email',
            'new_application' => 'New Application',
            'new_guest_chat' => 'New Guest Chat',
            'guest_chat_reply' => 'Guest Chat Reply',
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
                                <?php if ($hideNotifCell($event, $role)): ?>
                                    <td class="text-center py-1.5 px-3 text-gray-300">—</td>
                                <?php else: ?>
                                    <?php $defaultGranted = in_array($role, $notifDefaults[$event] ?? []); ?>
                                    <?php $overridden = isset($notifOverrides[$event]) && in_array($role, $notifOverrides[$event]); ?>
                                    <?php $checked = $isDefault ? $defaultGranted : $overridden; ?>
                                    <td class="text-center py-1.5 px-3">
                                        <input type="checkbox"
                                               name="events[<?= h($event) ?>][]"
                                               value="<?= h($role) ?>"
                                               <?= $checked ? 'checked' : '' ?>
                                               <?= $isDefault ? 'disabled' : '' ?>
                                               data-auto-submit
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 <?= $isDefault ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow p-6 mt-6">
    <h2 class="text-lg font-semibold mb-4"><?= __('In-App Notifications') ?></h2>
    <p class="text-sm text-gray-600 mb-6"><?= __('Choose who receives in-app notifications for each event. Defaults match email notifications.') ?></p>
    <p class="text-xs text-gray-500 mb-4"><?= __('Property notifications go to the property manager, or to the landlord when no manager is set.') ?></p>

    <form method="POST" action="/settings/inapp-notifications">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-6 p-4 bg-gray-50 rounded-lg border">
            <label class="flex items-center space-x-3">
                <input type="radio" name="inapp_mode" value="default" <?= ($inappMode ?? 'default') === 'default' ? 'checked' : '' ?> data-auto-submit class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700"><?= __('Use default in-app notifications') ?></span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Use the default notifications designed for Turtle.') ?></p>
            <label class="flex items-center space-x-3 mt-2">
                <input type="radio" name="inapp_mode" value="custom" <?= ($inappMode ?? 'default') === 'custom' ? 'checked' : '' ?> data-auto-submit class="text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700"><?= __('Custom in-app notifications') ?></span>
            </label>
            <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Manually configure who receives each notification.') ?></p>
        </div>

        <?php
        $inappLabels = notificationEventLabels();
        $inappIsDefault = ($inappMode ?? 'default') === 'default';
        ?>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-white">
                    <tr class="border-b">
                        <th class="text-left py-2 pr-4 font-medium text-gray-500 bg-white"><?= __('Event') ?></th>
                        <?php foreach (($inappRoles ?? []) as $role): ?>
                            <th class="text-center py-2 px-3 font-medium text-gray-500 bg-white"><?= h(ucwords(str_replace('_', ' ', $role))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inappLabels as $event => $label): ?>
                        <tr class="border-t border-gray-100 hover:bg-gray-50 <?= $inappIsDefault ? 'opacity-60' : '' ?>">
                            <td class="py-1.5 pr-4 text-blue-600 font-medium"><?= h($label) ?></td>
                            <?php foreach (($inappRoles ?? []) as $role): ?>
                                <?php if ($hideNotifCell($event, $role)): ?>
                                    <td class="text-center py-1.5 px-3 text-gray-300">—</td>
                                <?php else: ?>
                                    <?php $defaultGranted = in_array($role, ($inappDefaults ?? [])[$event] ?? []); ?>
                                    <?php $overridden = isset($inappOverrides[$event]) && in_array($role, $inappOverrides[$event]); ?>
                                    <?php $checked = $inappIsDefault ? $defaultGranted : $overridden; ?>
                                    <td class="text-center py-1.5 px-3">
                                        <input type="checkbox"
                                               name="inapp_events[<?= h($event) ?>][]"
                                               value="<?= h($role) ?>"
                                               <?= $checked ? 'checked' : '' ?>
                                               <?= $inappIsDefault ? 'disabled' : '' ?>
                                               data-auto-submit
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 <?= $inappIsDefault ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
