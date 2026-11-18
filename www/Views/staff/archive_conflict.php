<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Archive Staff Member') ?></h1>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-4xl">
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <h3 class="text-lg font-medium text-yellow-800 mb-2"><?= __('Conflicts Detected') ?></h3>
        <p class="text-yellow-700"><?= __('This staff member has properties and/or tickets assigned to them. You must resolve these before archiving.') ?></p>
    </div>

    <form method="POST" action="/staff/<?= $target['id'] ?>/archive-resolve" x-data="archiveConflictResolver()" x-init="init()">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <!-- Properties Section -->
        <?php if (!empty($properties)): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <?= __('Properties') ?> <span class="ml-2 text-sm font-normal text-gray-500">(<?= count($properties) ?>)</span>
            </h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Assign all properties to') ?></label>
                <select x-ref="assignAllProperties" @change="assignAllProperties($event.target.value)" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select Property Manager —') ?></option>
                    <?php foreach ($availableManagers as $mgr): ?>
                        <option value="<?= $mgr['id'] ?>"><?= h($mgr['name']) ?> (<?= h($mgr['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1"><?= __('Select a property manager to assign all properties at once. Individual assignments below will be updated.') ?></p>
            </div>

            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php foreach ($properties as $prop): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-800"><?= h($prop['name']) ?></span>
                        <span class="text-xs text-gray-500">ID: #<?= $prop['id'] ?></span>
                    </div>
                    <select name="property_assignments[<?= $prop['id'] ?>]" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                        <option value=""><?= __('— Select Property Manager —') ?></option>
                        <?php foreach ($availableManagers as $mgr): ?>
                            <option value="<?= $mgr['id'] ?>"><?= h($mgr['name']) ?> (<?= h($mgr['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1"><?= __('Required: Each property must be assigned to an active property manager.') ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tickets Section -->
        <?php if (!empty($tickets)): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <?= __('Tickets') ?> <span class="ml-2 text-sm font-normal text-gray-500">(<?= count($tickets) ?>)</span>
            </h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Assign all tickets to') ?></label>
                <select x-ref="assignAllTickets" @change="assignAllTickets($event.target.value)" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select Property Manager —') ?></option>
                    <option value="unassign"><?= __('Unassign (leave unassigned)') ?></option>
                    <?php foreach ($availableManagers as $mgr): ?>
                        <option value="<?= $mgr['id'] ?>"><?= h($mgr['name']) ?> (<?= h($mgr['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1"><?= __('Select a property manager or "Unassign" to assign all tickets at once. Individual assignments below will be updated.') ?></p>
            </div>

            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php foreach ($tickets as $ticket): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-800"><?= h($ticket['subject']) ?></span>
                        <span class="text-xs text-gray-500"><?= h($ticket['property_name']) ?> • ID: #<?= $ticket['id'] ?></span>
                    </div>
                    <select name="ticket_assignments[<?= $ticket['id'] ?>]" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value=""><?= __('— Select Property Manager —') ?></option>
                        <option value="unassign"><?= __('Unassign (leave unassigned)') ?></option>
                        <?php foreach ($availableManagers as $mgr): ?>
                            <option value="<?= $mgr['id'] ?>"><?= h($mgr['name']) ?> (<?= h($mgr['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1"><?= __('Each ticket must be assigned or explicitly unassigned.') ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <hr class="my-6">

        <div class="flex space-x-3">
            <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 font-medium" :disabled="!canSubmit">
                <?= __('Archive Staff Member') ?>
            </button>
            <a href="/staff/<?= $target['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50 flex items-center justify-center h-10">
                <?= __('Cancel') ?>
            </a>
        </div>

        <p class="text-xs text-gray-500 mt-4"><?= __('WARNING: This will archive this staff member and is not reversible. They will no longer be able to log in.') ?></p>
    </form>
</div>

<script>
function archiveConflictResolver() {
    return {
        init() {
            // Listen for changes on all assignment selects to trigger re-validation
            document.querySelectorAll('select[name^="property_assignments"], select[name^="ticket_assignments"]').forEach(select => {
                select.addEventListener('change', () => {
                    // Force Alpine to re-evaluate the component
                    this.$forceUpdate();
                });
            });
        },
        get canSubmit() {
            // Properties are required - must select a manager
            const propertySelects = document.querySelectorAll('select[name^="property_assignments"]');
            for (const select of propertySelects) {
                if (!select.value) return false;
            }
            // Tickets are required - must select a manager or explicitly unassign
            const ticketSelects = document.querySelectorAll('select[name^="ticket_assignments"]');
            for (const select of ticketSelects) {
                if (!select.value) return false;
            }
            return true;
        },
        assignAllProperties(value) {
            if (!value) return;
            const selects = document.querySelectorAll('select[name^="property_assignments"]');
            selects.forEach(select => {
                select.value = value;
                // Trigger change event so validation runs
                select.dispatchEvent(new Event('change'));
            });
        },
        assignAllTickets(value) {
            const selects = document.querySelectorAll('select[name^="ticket_assignments"]');
            selects.forEach(select => {
                select.value = value;
                // Trigger change event so validation runs
                select.dispatchEvent(new Event('change'));
            });
        }
    };
}
</script>