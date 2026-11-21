<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-slate-100"><?= __('Properties Available') ?></h1>
    <p class="text-gray-600 mt-2 dark:text-slate-300"><?= __('Browse our listed properties and message the team directly through this site.') ?></p>
</div>

<?php if (empty($properties)): ?>
    <div class="bg-white rounded-xl shadow p-10 text-center dark:bg-slate-800">
        <p class="text-gray-600 dark:text-slate-300"><?= __('No properties are currently listed.') ?></p>
    </div>
<?php else: ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($properties as $property): ?>
            <?php $ad = render_ad_description($property); ?>
            <article class="bg-white rounded-xl shadow overflow-hidden flex flex-col dark:bg-slate-800" x-data="{ open: false }" data-testid="listing-card">
                <?php if (!empty($property['main_photo_id'])): ?>
                    <img src="/listings/<?= (int) $property['id'] ?>/photos/<?= (int) $property['main_photo_id'] ?>"
                         alt="<?= h($property['name']) ?>" class="h-48 w-full object-cover">
                <?php else: ?>
                    <div class="h-48 w-full bg-gray-200 flex items-center justify-center text-gray-400 dark:bg-slate-700">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M16 21V9h2a2 2 0 012 2v10M3 21h18"/></svg>
                    </div>
                <?php endif; ?>
                <div class="p-5 flex-1 flex flex-col">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-slate-100"><?= h($property['name']) ?></h2>
                    <?php $location = trim($property['address'] . ', ' . $property['city'] . ', ' . $property['province'], ', '); ?>
                    <?php if ($location !== ''): ?>
                        <p class="text-sm text-gray-500 mt-1 dark:text-slate-400"><?= h($location) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($property['rent_amount'])): ?>
                        <p class="mt-2 font-semibold text-gray-800 dark:text-slate-100"><?= __('Monthly Rent') ?>: <?= h(number_format((float) $property['rent_amount'], 2)) ?></p>
                    <?php endif; ?>
                    <?php if ($ad !== ''): ?>
                        <div class="mt-3 text-sm text-gray-600 dark:text-slate-300"><?= $ad ?></div>
                    <?php endif; ?>

                    <div class="mt-auto pt-4">
                        <?php if (isset($continueChats[(int) $property['id']])): ?>
                            <a href="/listings/chat/<?= h($continueChats[(int) $property['id']]) ?>"
                               class="inline-block mb-3 text-sm text-blue-600 hover:underline dark:text-blue-400" data-testid="continue-chat">
                                <?= __('Continue conversation') ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" @click="open = !open" data-testid="message-button"
                                class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">
                            <?= __('Send Message') ?>
                        </button>
                        <div x-show="open" x-cloak class="mt-4 border-t border-gray-200 pt-4 dark:border-slate-700">
                            <form method="POST" action="/listings/<?= (int) $property['id'] ?>/chat" class="space-y-3">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1 dark:text-slate-300" for="guest_name_<?= (int) $property['id'] ?>"><?= __('Your Name') ?></label>
                                    <input id="guest_name_<?= (int) $property['id'] ?>" type="text" name="guest_name" value="<?= old('guest_name') ?>" required maxlength="120" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1 dark:text-slate-300" for="guest_email_<?= (int) $property['id'] ?>"><?= __('Email') ?></label>
                                    <input id="guest_email_<?= (int) $property['id'] ?>" type="email" name="guest_email" value="<?= old('guest_email') ?>" required maxlength="190" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1 dark:text-slate-300" for="guest_body_<?= (int) $property['id'] ?>"><?= __('Message') ?></label>
                                    <textarea id="guest_body_<?= (int) $property['id'] ?>" name="body" rows="3" required maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Type a message...') ?>"><?= old('body') ?></textarea>
                                </div>
                                <button type="submit" data-testid="send-message" class="w-full bg-green-600 text-white py-2 rounded-lg font-medium hover:bg-green-700"><?= __('Send Message') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
