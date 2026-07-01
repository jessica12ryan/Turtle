<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2"><?= __('Tenancy Application') ?></h1>
    <p class="text-gray-500 mb-6"><?= __('Please fill out this form to apply for tenancy. All fields marked with * are required unless noted otherwise.') ?></p>

    <?php if ($notes): ?>
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-6 text-sm text-gray-700">
            <?= nl2br(h($notes)) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/applications" class="space-y-8" x-data="applicationForm()" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <!-- Property ID -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Property') ?></h2>
            <div class="mb-2">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Property ID') ?></label>
                <select name="property_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select a property (optional) —') ?></option>
                    <?php foreach ($properties as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= h($p['name']) ?> — <?= h($p['address']) ?>, <?= h($p['city']) ?>, <?= h($p['province']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1"><?= __('If you were given a property ID, enter it here.') ?></p>
            </div>
        </div>

        <!-- Applicant Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Applicant Information (Main Tenant)') ?></h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Last Name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="primary_last_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('First Name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="primary_first_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Middle Name(s)') ?></label>
                    <input type="text" name="primary_middle_names" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Birth Date') ?> <span class="text-red-500">*</span></label>
                    <input type="date" name="primary_birth_date" required max="<?= date('Y-m-d', strtotime('-18 years')) ?>" @change="checkAge('primary_birth_date')" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <div x-show="ageErrors['primary_birth_date']" class="text-red-600 text-xs mt-1"><?= __('You must be at least 18 years old.') ?></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
                    <input type="tel" name="primary_phone" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email Address') ?> <span class="text-red-500">*</span></label>
                    <input type="email" name="primary_email" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Government Issued Photo ID') ?> <span class="text-red-500">*</span></label>
                <p class="text-xs text-gray-500 mb-1"><?= __('Drivers License, Passport, Military ID, Ontario Health Card') ?></p>
                <input type="file" name="primary_photo_id" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Current Address -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Current Address') ?></h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Street Address') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="primary_address_street" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Apt or Suite') ?></label>
                    <input type="text" name="primary_address_apt_suite" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('e.g., Apt 2B, Suite 300') ?>">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('City/Town') ?> <span class="text-red-500">*</span></label>
                        <input type="text" name="primary_address_city" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(region_label(default_country())) ?> <span class="text-red-500">*</span></label>
                        <select name="primary_address_province" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value=""><?= default_country() === 'US' ? __('Select State') : __('Select Province') ?></option>
                            <?php foreach (regions(default_country()) as $code => $name): ?>
                                <option value="<?= $code ?>"><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(postal_label(default_country())) ?> <span class="text-red-500">*</span></label>
                        <input type="text" name="primary_address_postal_code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 uppercase" placeholder="<?= default_country() === 'US' ? '12345' : 'A1A 1A1' ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Date Moved In') ?> <span class="text-red-500">*</span></label>
                        <input type="date" name="primary_address_date_moved_in" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Reason For Leaving') ?> <span class="text-red-500">*</span></label>
                    <textarea name="primary_address_reason_leaving" required rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>
        </div>

        <!-- Employment & Income Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Employment & Income Information') ?></h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Occupation/Title') ?></label>
                    <input type="text" name="primary_employment_occupation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Employer/Company') ?></label>
                    <input type="text" name="primary_employment_employer" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Street Address') ?></label>
                    <input type="text" name="primary_employment_street" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Suite Number') ?></label>
                    <input type="text" name="primary_employment_suite" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('City/Town') ?></label>
                    <input type="text" name="primary_employment_city" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(region_label(default_country())) ?></label>
                    <select name="primary_employment_province" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value=""><?= default_country() === 'US' ? __('Select State') : __('Select Province') ?></option>
                        <?php foreach (regions(default_country()) as $code => $name): ?>
                            <option value="<?= $code ?>"><?= h($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(postal_label(default_country())) ?></label>
                    <input type="text" name="primary_employment_postal_code" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 uppercase" placeholder="<?= default_country() === 'US' ? '12345' : 'A1A 1A1' ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Start Date') ?></label>
                    <input type="date" name="primary_employment_start_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Supervisor\'s Name') ?></label>
                    <input type="text" name="primary_employment_supervisor_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?></label>
                    <input type="tel" name="primary_employment_phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Other Income Source') ?></label>
                    <input type="text" name="primary_employment_other_income" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Emergency Contact') ?></h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Last Name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="primary_emergency_last_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('First Name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="primary_emergency_first_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Relationship') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="primary_emergency_relationship" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
                    <input type="tel" name="primary_emergency_phone" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                </div>
            </div>
        </div>

        <!-- Background Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Background Information') ?></h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Have you ever been evicted from a tenancy?') ?> <span class="text-red-500">*</span></label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center"><input type="radio" name="primary_background_evicted" value="yes" x-model="primary_evicted" class="mr-1"> <?= __('Yes') ?></label>
                        <label class="inline-flex items-center"><input type="radio" name="primary_background_evicted" value="no" x-model="primary_evicted" class="mr-1"> <?= __('No') ?></label>
                    </div>
                    <textarea name="primary_background_evicted_details" rows="2" placeholder="<?= __('If yes, please provide details') ?>" x-show="primary_evicted === 'yes'" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Have you ever been convicted of a crime for which you have not received a pardon?') ?> <span class="text-red-500">*</span></label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center"><input type="radio" name="primary_background_convicted" value="yes" x-model="primary_convicted" class="mr-1"> <?= __('Yes') ?></label>
                        <label class="inline-flex items-center"><input type="radio" name="primary_background_convicted" value="no" x-model="primary_convicted" class="mr-1"> <?= __('No') ?></label>
                    </div>
                    <textarea name="primary_background_convicted_details" rows="2" placeholder="<?= __('If yes, please provide details') ?>" x-show="primary_convicted === 'yes'" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Have you ever willfully or intentionally refused to pay rent when due?') ?> <span class="text-red-500">*</span></label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center"><input type="radio" name="primary_background_refused_rent" value="yes" x-model="primary_refused_rent" class="mr-1"> <?= __('Yes') ?></label>
                        <label class="inline-flex items-center"><input type="radio" name="primary_background_refused_rent" value="no" x-model="primary_refused_rent" class="mr-1"> <?= __('No') ?></label>
                    </div>
                    <textarea name="primary_background_refused_rent_details" rows="2" placeholder="<?= __('If yes, please provide details') ?>" x-show="primary_refused_rent === 'yes'" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>
        </div>

        <!-- Other Tenants (18 and older) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Other Tenants (18 and older)') ?></h2>
            <p class="text-sm text-gray-500 mb-4"><?= __('List all other adults who will be living in the unit. Each person will need to fill out their own sections below.') ?></p>
            <template x-for="(tenant, i) in otherTenants" :key="i">
                <div class="p-4 border border-gray-200 rounded-lg mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-medium text-gray-700" x-text="'<?= __('Tenant') ?> #' + (i + 2)"></h3>
                        <button type="button" class="text-red-600 hover:text-red-800 text-sm" x-on:click="removeOtherTenant(i)"><?= __('Remove') ?></button>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Last Name') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'other_tenant_last_name[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('First Name') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'other_tenant_first_name[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Middle Name(s)') ?></label>
                            <input type="text" :name="'other_tenant_middle_names[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Birth Date') ?> <span class="text-red-500">*</span></label>
                            <input type="date" :name="'other_tenant_birth_date[' + i + ']'" required max="<?= date('Y-m-d', strtotime('-18 years')) ?>" @change="checkAge('other_tenant_birth_date', i)" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <div x-show="ageErrors['other_tenant_birth_date_' + i]" class="text-red-600 text-xs mt-1"><?= __('Must be at least 18 years old') ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
                            <input type="tel" :name="'other_tenant_phone[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email Address') ?> <span class="text-red-500">*</span></label>
                            <input type="email" :name="'other_tenant_email[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Relationship') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'other_tenant_relationship[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Government Issued Photo ID') ?> <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500 mb-1"><?= __('Drivers License, Passport, Military ID, Ontario Health Card') ?></p>
                        <input type="file" :name="'other_tenant_photo_id[' + i + ']'" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Current Address for other tenant -->
                    <h4 class="font-medium text-gray-600 mb-2 mt-4"><?= __('Current Address') ?></h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Street Address') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'other_tenant_address_street[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Apt or Suite') ?></label>
                            <input type="text" :name="'other_tenant_address_apt_suite[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('City/Town') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'other_tenant_address_city[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(region_label(default_country())) ?> <span class="text-red-500">*</span></label>
                            <select :name="'other_tenant_address_province[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value=""><?= default_country() === 'US' ? __('Select State') : __('Select Province') ?></option>
                                <?php foreach (regions(default_country()) as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= h($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(postal_label(default_country())) ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'other_tenant_address_postal_code[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 uppercase" placeholder="<?= default_country() === 'US' ? '12345' : 'A1A 1A1' ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Date Moved In') ?></label>
                            <input type="date" :name="'other_tenant_address_date_moved_in[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Reason For Leaving') ?></label>
                            <textarea :name="'other_tenant_address_reason_leaving[' + i + ']'" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>

                    <!-- Employment & Income for other tenant -->
                    <h4 class="font-medium text-gray-600 mb-2 mt-4"><?= __('Employment & Income Information') ?></h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Occupation/Title') ?></label>
                            <input type="text" :name="'other_tenant_employment_occupation[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Employer/Company') ?></label>
                            <input type="text" :name="'other_tenant_employment_employer[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Street Address') ?></label>
                            <input type="text" :name="'other_tenant_employment_street[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Suite Number') ?></label>
                            <input type="text" :name="'other_tenant_employment_suite[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('City/Town') ?></label>
                            <input type="text" :name="'other_tenant_employment_city[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(region_label(default_country())) ?></label>
                            <select :name="'other_tenant_employment_province[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value=""><?= default_country() === 'US' ? __('Select State') : __('Select Province') ?></option>
                                <?php foreach (regions(default_country()) as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= h($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __(postal_label(default_country())) ?></label>
                            <input type="text" :name="'other_tenant_employment_postal_code[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 uppercase" placeholder="<?= default_country() === 'US' ? '12345' : 'A1A 1A1' ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Start Date') ?></label>
                            <input type="date" :name="'other_tenant_employment_start_date[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Supervisor\'s Name') ?></label>
                            <input type="text" :name="'other_tenant_employment_supervisor_name[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?></label>
                            <input type="tel" :name="'other_tenant_employment_phone[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Other Income Source') ?></label>
                            <input type="text" :name="'other_tenant_employment_other_income[' + i + ']'" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Background Information for other tenant -->
                    <h4 class="font-medium text-gray-600 mb-2 mt-4"><?= __('Background Information') ?></h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Have you ever been evicted from a tenancy?') ?> <span class="text-red-500">*</span></label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center"><input type="radio" :name="'other_tenant_background_evicted[' + i + ']'" value="yes" x-model="tenant.evicted" class="mr-1"> <?= __('Yes') ?></label>
                                <label class="inline-flex items-center"><input type="radio" :name="'other_tenant_background_evicted[' + i + ']'" value="no" x-model="tenant.evicted" class="mr-1"> <?= __('No') ?></label>
                            </div>
                            <textarea :name="'other_tenant_background_evicted_details[' + i + ']'" rows="2" placeholder="<?= __('If yes, please provide details') ?>" x-show="tenant.evicted === 'yes'" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Have you ever been convicted of a crime for which you have not received a pardon?') ?> <span class="text-red-500">*</span></label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center"><input type="radio" :name="'other_tenant_background_convicted[' + i + ']'" value="yes" x-model="tenant.convicted" class="mr-1"> <?= __('Yes') ?></label>
                                <label class="inline-flex items-center"><input type="radio" :name="'other_tenant_background_convicted[' + i + ']'" value="no" x-model="tenant.convicted" class="mr-1"> <?= __('No') ?></label>
                            </div>
                            <textarea :name="'other_tenant_background_convicted_details[' + i + ']'" rows="2" placeholder="<?= __('If yes, please provide details') ?>" x-show="tenant.convicted === 'yes'" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Have you ever willfully or intentionally refused to pay rent when due?') ?> <span class="text-red-500">*</span></label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center"><input type="radio" :name="'other_tenant_background_refused_rent[' + i + ']'" value="yes" x-model="tenant.refused_rent" class="mr-1"> <?= __('Yes') ?></label>
                                <label class="inline-flex items-center"><input type="radio" :name="'other_tenant_background_refused_rent[' + i + ']'" value="no" x-model="tenant.refused_rent" class="mr-1"> <?= __('No') ?></label>
                            </div>
                            <textarea :name="'other_tenant_background_refused_rent_details[' + i + ']'" rows="2" placeholder="<?= __('If yes, please provide details') ?>" x-show="tenant.refused_rent === 'yes'" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                </div>
            </template>
            <button type="button" class="text-sm text-blue-600 hover:text-blue-800 font-medium" x-on:click="addOtherTenant()">+ <?= __('Add Another Tenant') ?></button>
        </div>

        <!-- Other Occupants (Under 18) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Other Occupants (Under 18)') ?></h2>
            <template x-for="(occ, i) in otherOccupants" :key="i">
                <div class="p-4 border border-gray-200 rounded-lg mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-medium text-gray-700" x-text="'<?= __('Occupant') ?> #' + (i + 1)"></h3>
                        <button type="button" class="text-red-600 hover:text-red-800 text-sm" x-on:click="removeOtherOccupant(i)"><?= __('Remove') ?></button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Last Name') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'occupant_last_name[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('First Name') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'occupant_first_name[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Age') ?> <span class="text-red-500">*</span></label>
                            <input type="number" :name="'occupant_age[' + i + ']'" min="0" max="17" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Relationship') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'occupant_relationship[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
            </template>
            <button type="button" class="text-sm text-blue-600 hover:text-blue-800 font-medium" x-on:click="addOtherOccupant()">+ <?= __('Add Another Occupant') ?></button>
        </div>

        <!-- Personal References -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Personal References') ?></h2>
            <template x-for="(ref, i) in references" :key="i">
                <div class="p-4 border border-gray-200 rounded-lg mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-medium text-gray-700" x-text="'<?= __('Reference') ?> #' + (i + 1)"></h3>
                        <button type="button" class="text-red-600 hover:text-red-800 text-sm" x-on:click="removeReference(i)"><?= __('Remove') ?></button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Last Name') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'reference_last_name[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('First Name') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'reference_first_name[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Relationship') ?> <span class="text-red-500">*</span></label>
                            <input type="text" :name="'reference_relationship[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
                            <input type="tel" :name="'reference_phone[' + i + ']'" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                        </div>
                    </div>
                </div>
            </template>
            <button type="button" class="text-sm text-blue-600 hover:text-blue-800 font-medium" x-on:click="addReference()">+ <?= __('Add Another Reference') ?></button>
        </div>

        <!-- Other Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Other Information') ?></h2>
            <textarea name="primary_other_info" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Any additional information you wish to provide') ?>"></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-yellow-600 text-white px-8 py-3 rounded-lg hover:bg-yellow-700 font-medium text-lg"><?= __('Submit Application') ?></button>
        </div>
    </form>
</div>

<script>
function applicationForm() {
    return {
        otherTenants: [],
        otherOccupants: [],
        references: [],
        ageErrors: {},
        primary_evicted: '',
        primary_convicted: '',
        primary_refused_rent: '',
        addOtherTenant() { this.otherTenants.push({evicted: '', convicted: '', refused_rent: ''}); },
        removeOtherTenant(i) { this.otherTenants.splice(i, 1); },
        addOtherOccupant() { this.otherOccupants.push({}); },
        removeOtherOccupant(i) { this.otherOccupants.splice(i, 1); },
        addReference() { this.references.push({}); },
        removeReference(i) { this.references.splice(i, 1); },
        checkAge(field, index) {
            const selector = typeof index !== 'undefined' ? `[name="${field}[${index}]"]` : `[name="${field}"]`;
            const el = document.querySelector(selector);
            const key = typeof index !== 'undefined' ? `${field}_${index}` : field;
            if (el && el.value) {
                const birthDate = new Date(el.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
                if (age < 18) this.ageErrors[key] = true;
                else delete this.ageErrors[key];
            } else {
                delete this.ageErrors[key];
            }
        }
    }
}
</script>
