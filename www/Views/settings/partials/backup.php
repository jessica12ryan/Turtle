<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4"><?= __('Backup & Restore') ?></h2>
    <p class="text-sm text-gray-600 mb-6"><?= __('Create a complete backup of all data, settings, and uploaded files, or restore from a previous backup.') ?></p>

    <!-- Backup -->
    <div class="mb-8 p-4 border border-blue-200 rounded-lg bg-blue-50">
        <h3 class="text-md font-semibold text-blue-800 mb-2"><?= __('Create Backup') ?></h3>
        <p class="text-sm text-blue-600 mb-4"><?= __('Downloads a .turtle file containing your entire database, uploaded files, logo, and settings.') ?></p>
        <form method="POST" action="/settings/backup">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button type="submit" id="backup-btn" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 font-medium inline-flex items-center">
                <span id="backup-text"><?= __('Download Backup') ?></span>
                <svg id="backup-spinner" class="hidden animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </button>
        </form>
    </div>

    <!-- Restore -->
    <div class="p-4 border border-yellow-200 rounded-lg bg-yellow-50">
        <h3 class="text-md font-semibold text-yellow-800 mb-2"><?= __('Restore Backup') ?></h3>
        <p class="text-sm text-yellow-700 mb-4"><?= __('Upload a .turtle backup file to restore the application to a previous state. This will replace all current data.') ?></p>
        <form method="POST" action="/settings/restore" enctype="multipart/form-data" id="restore-form">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="mb-4">
                <input type="file" name="backup_file" accept=".turtle" required class="block text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200">
            </div>
            <div class="flex items-center space-x-3">
                <button type="submit" id="restore-btn" class="bg-yellow-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-700 font-medium inline-flex items-center">
                    <span id="restore-text"><?= __('Upload & Restore') ?></span>
                    <svg id="restore-spinner" class="hidden animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>
                <span class="text-xs text-gray-500"><?= __('Warning: This will replace all current data, including users, properties, and settings. This cannot be undone.') ?></span>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('backup-btn')?.addEventListener('click', function() {
    this.disabled = true;
    document.getElementById('backup-text').classList.add('hidden');
    document.getElementById('backup-spinner').classList.remove('hidden');
});

document.getElementById('restore-form')?.addEventListener('submit', function(e) {
    var fileInput = this.querySelector('input[type="file"]');
    if (!fileInput.files.length) {
        alert('<?= __('Please select a backup file.') ?>');
        e.preventDefault();
        return;
    }
    if (!confirm('<?= __('Are you sure you want to restore this backup? All current data will be replaced. This cannot be undone.') ?>')) {
        e.preventDefault();
        return;
    }
    var btn = document.getElementById('restore-btn');
    if (btn.disabled) {
        e.preventDefault();
        return;
    }
    btn.disabled = true;
    document.getElementById('restore-text').classList.add('hidden');
    document.getElementById('restore-spinner').classList.remove('hidden');
});
</script>