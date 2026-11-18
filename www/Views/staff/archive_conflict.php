<?php
$managersById = [];
foreach ($availableManagers ?? [] as $m) {
    $managersById[(int)$m['id']] = $m;
}
$resolverConfig = [
    'properties' => array_map(fn($p) => (string)$p['id'], $properties ?? []),
    'tickets' => array_map(fn($t) => (string)$t['id'], $tickets ?? []),
    'hasManagers' => !empty($availableManagers),
    'managers' => array_map(fn($m) => [
        'id' => (string)$m['id'],
        'label' => $m['name'] . ' (' . $m['email'] . ')',
    ], $availableManagers ?? []),
    'propertyTickets' => $propertyTickets ?? [],
    'ticketProperty' => $ticketProperty ?? [],
    'ticketAccess' => $ticketAccess ?? [],
];
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Archive Staff Member') ?></h1>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-4xl">
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <h3 class="text-lg font-medium text-yellow-800 mb-2"><?= __('Conflicts Detected') ?></h3>
        <p class="text-yellow-700"><?= __('This staff member has properties and/or tickets assigned to them. You must resolve these before archiving.') ?></p>
    </div>

    <form method="POST" action="/staff/<?= $target['id'] ?>/archive-resolve" x-data='archiveConflictResolver(<?= json_encode($resolverConfig, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <?php if (empty($availableManagers)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
            <?= __('There are no other active property managers to reassign to. Create or restore one first.') ?>
        </div>
        <?php endif; ?>

        <!-- Properties Section -->
        <?php if (!empty($properties)): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <?= __('Properties') ?> <span class="ml-2 text-sm font-normal text-gray-500">(<?= count($properties) ?>)</span>
            </h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Assign all properties to') ?></label>
                <select @change="assignAllProperties($event.target.value); $event.target.value = ''" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select Property Manager —') ?></option>
                    <?php foreach (($commonPropertyManagers ?? []) as $mgrId): ?>
                        <?php $mgr = $managersById[(int)$mgrId] ?? null; if (!$mgr) continue; ?>
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
                    <select name="property_assignments[<?= $prop['id'] ?>]" x-model="propertyValues['<?= (int)$prop['id'] ?>']" @change="onPropertyChange('<?= (int)$prop['id'] ?>', $event)" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
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
                <select x-ref="bulkTickets" @change="assignAllTickets($event.target.value); $event.target.value = ''" class="w-full md:w-1/3 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select Property Manager —') ?></option>
                    <option value="unassign"><?= __('Unassign (leave unassigned)') ?></option>
                    <?php foreach (($commonTicketManagers ?? []) as $mgrId): ?>
                        <?php $mgr = $managersById[(int)$mgrId] ?? null; if (!$mgr) continue; ?>
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
                    <select name="ticket_assignments[<?= $ticket['id'] ?>]" x-model="ticketValues['<?= (int)$ticket['id'] ?>']" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                        <option value=""><?= __('— Select Property Manager —') ?></option>
                        <option value="unassign"><?= __('Unassign (leave unassigned)') ?></option>
                        <?php foreach (($ticketAccess[$ticket['id']] ?? []) as $mgrId): ?>
                            <?php $mgr = $managersById[(int)$mgrId] ?? null; if (!$mgr) continue; ?>
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
            <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed" :disabled="!canSubmit">
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
function archiveConflictResolver(config) {
    const blank = (ids) => Object.fromEntries(ids.map(id => [id, '']));
    return {
        hasManagers: config.hasManagers,
        managers: config.managers,
        propertyValues: blank(config.properties),
        ticketValues: blank(config.tickets),
        propertyTickets: config.propertyTickets,
        ticketProperty: config.ticketProperty,
        ticketAccess: config.ticketAccess,
        // Reactive: re-evaluates whenever any x-model value above changes
        get canSubmit() {
            if (!this.hasManagers) return false;
            return Object.values(this.propertyValues).every(v => v !== '')
                && Object.values(this.ticketValues).every(v => v !== '');
        },
        allowedManagerIdsFor(ticketId) {
            const allowed = new Set(this.ticketAccess[ticketId] || []);
            const propertyId = this.ticketProperty[ticketId];
            const selected = this.propertyValues[propertyId];
            if (selected) allowed.add(String(selected));
            return allowed;
        },
        get allTicketManagers() {
            const ticketIds = Object.keys(this.ticketProperty);
            let common = null;
            for (const ticketId of ticketIds) {
                const allowed = this.allowedManagerIdsFor(ticketId);
                common = common === null ? allowed : new Set([...common].filter(id => allowed.has(id)));
            }
            return common === null
                ? this.managers
                : this.managers.filter(m => common.has(String(m.id)));
        },
        renderManagerOptions(select, allowed, current) {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = <?= json_encode(__('— Select Property Manager —'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            select.appendChild(placeholder);

            const unassign = document.createElement('option');
            unassign.value = 'unassign';
            unassign.textContent = <?= json_encode(__('Unassign (leave unassigned)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            select.appendChild(unassign);

            for (const manager of this.managers) {
                if (!allowed.has(String(manager.id))) continue;
                const option = document.createElement('option');
                option.value = manager.id;
                option.textContent = manager.label;
                select.appendChild(option);
            }

            if (current !== '' && current !== 'unassign' && !allowed.has(current)) {
                current = '';
            }
            select.value = current;
            return current;
        },
        refreshTicketOptions(ticketId) {
            const select = document.querySelector(`select[name="ticket_assignments[${ticketId}]"]`);
            if (!select) return;
            const allowed = this.allowedManagerIdsFor(ticketId);
            const current = String(this.ticketValues[ticketId] || '');
            this.ticketValues[ticketId] = this.renderManagerOptions(select, allowed, current);
        },
        refreshBulkTicketOptions() {
            const select = this.$refs.bulkTickets;
            if (!select) return;
            const allowed = new Set(this.allTicketManagers.map(m => String(m.id)));
            this.renderManagerOptions(select, allowed, '');
        },
        onPropertyChange(propertyId, event) {
            this.propertyValues[propertyId] = event.target.value;
            for (const ticketId of (this.propertyTickets[propertyId] || [])) {
                this.refreshTicketOptions(ticketId);
            }
            this.refreshBulkTicketOptions();
        },
        assignAllProperties(value) {
            if (!value) return;
            for (const id in this.propertyValues) {
                this.propertyValues[id] = value;
                this.onPropertyChange(id, { target: { value } });
            }
        },
        assignAllTickets(value) {
            if (!value) return;
            for (const id in this.ticketValues) {
                const allowed = this.allowedManagerIdsFor(id);
                if (value === 'unassign' || allowed.has(String(value))) {
                    this.ticketValues[id] = value;
                } else {
                    this.ticketValues[id] = '';
                }
            }
        }
    };
}
</script>
