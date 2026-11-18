<?php
$resolverConfig = [
    'managersByProperty' => $managersByProperty ?? [],
    'managerLabels' => $managerNames ?? [],
];
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Restore Ticket') ?></h1>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <h3 class="text-lg font-medium text-yellow-800 mb-2"><?= __('Conflicts Detected') ?></h3>
        <p class="text-yellow-700"><?= __('This ticket cannot be restored as-is. Resolve the conflicts below.') ?></p>
    </div>

    <form method="POST"
          action="/tickets/<?= $ticket['id'] ?>/restore-resolve"
          x-data="ticketRestoreResolver(<?= json_encode($resolverConfig, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <?php if ($conflicts['property_missing']): ?>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Property') ?></label>
            <select name="property_id"
                    x-ref="propertySelect"
                    @change="onPropertyChange($event.target.value)"
                    class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                    required>
                <option value=""><?= __('— Select Property —') ?></option>
                <?php foreach ($properties as $property): ?>
                    <option value="<?= $property['id'] ?>"><?= h($property['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1"><?= __('The ticket is linked to a property that no longer exists. Choose a new property.') ?></p>
        </div>
        <?php endif; ?>

        <?php if ($conflicts['assignee_invalid']): ?>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Assigned To') ?></label>
            <select name="assigned_to"
                    x-ref="assigneeSelect"
                    class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                    required>
                <option value=""><?= __('— Select Staff Member —') ?></option>
                <option value="unassign"><?= __('Unassigned') ?></option>
                <?php foreach ($initialManagers as $manager): ?>
                    <option value="<?= $manager['id'] ?>"><?= h($manager['name']) ?> (<?= h($manager['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1"><?= __('The ticket is assigned to a staff member who is no longer active. Choose a new assignee or unassign it.') ?></p>
        </div>
        <?php endif; ?>

        <hr class="my-6">

        <div class="flex space-x-3">
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium">
                <?= __('Restore') ?>
            </button>
            <a href="/tickets?show_archived=1" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50 flex items-center justify-center h-10">
                <?= __('Cancel') ?>
            </a>
        </div>
    </form>
</div>

<script>
function ticketRestoreResolver(config) {
    return {
        managersByProperty: config.managersByProperty,
        managerLabels: config.managerLabels,
        onPropertyChange(propertyId) {
            const select = this.$refs.assigneeSelect;
            if (!select) return;

            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = <?= json_encode(__('— Select Staff Member —'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            select.appendChild(placeholder);

            const unassign = document.createElement('option');
            unassign.value = 'unassign';
            unassign.textContent = <?= json_encode(__('Unassigned'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            select.appendChild(unassign);

            for (const id of (this.managersByProperty[propertyId] || [])) {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = this.managerLabels[id] || id;
                select.appendChild(option);
            }

            select.value = '';
        }
    };
}
</script>
