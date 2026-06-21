<h1 class="text-2xl font-bold text-gray-800 mb-6">Settings</h1>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-red-600 mb-4">Reset Data</h2>
    <p class="text-sm text-gray-600 mb-6">Select the data you want to reset. This action cannot be undone. Your admin account will remain active.</p>

    <form method="POST" action="/settings/reset" x-data="{
        resetAll: false,
        toggleAll() {
            this.resetAll = !this.resetAll;
            $el.querySelectorAll('.reset-checkbox:not(#reset_all)').forEach(cb => cb.checked = this.resetAll);
        }
    }">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="space-y-3 mb-6">
            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" id="reset_all" name="reset_all" value="1" @change="toggleAll()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-3 font-medium text-gray-800">Everything</span>
            </label>

            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                <input type="checkbox" name="reset_properties" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-3 text-gray-700">Property Data</span>
            </label>

            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                <input type="checkbox" name="reset_tenants" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-3 text-gray-700">Tenant Data</span>
            </label>

            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                <input type="checkbox" name="reset_staff" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-3 text-gray-700">Staff Data (leaves your admin account alone)</span>
            </label>

            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                <input type="checkbox" name="reset_leases" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-3 text-gray-700">Lease Data</span>
            </label>

            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                <input type="checkbox" name="reset_tickets" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-3 text-gray-700">Ticket Data</span>
            </label>
        </div>

        <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 font-medium" onclick="return confirm('Are you sure you want to reset the selected data? This cannot be undone.')">Reset Selected Data</button>
    </form>
</div>
