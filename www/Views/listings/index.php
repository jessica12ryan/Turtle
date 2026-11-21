<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-slate-100"><?= __('Properties Available') ?></h1>
    <p class="text-gray-600 mt-2 dark:text-slate-300"><?= __('Browse our listed properties and message the team directly through this site.') ?></p>
</div>

<section class="bg-white rounded-xl shadow p-5 mb-8 dark:bg-slate-800" data-testid="return-conversation">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-slate-100"><?= __('Return to a conversation') ?></h2>
    <?php if (!empty($conversations)): ?>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-300"><?= __('These conversations are saved in this browser:') ?></p>
        <ul class="mt-3 space-y-2">
            <?php foreach (array_slice($conversations, 0, 5) as $chat): ?>
                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-gray-800 dark:text-slate-100">
                        <?= h($chat['property_name']) ?>
                        <span class="text-gray-500 dark:text-slate-400">&middot; <?= h(display_time($chat['last_activity'])) ?></span>
                    </span>
                    <a href="/listings/chat/<?= h($chat['token']) ?>"
                       class="text-blue-600 hover:underline dark:text-blue-400" data-testid="conversation-link">
                        <?= __('View conversation') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($conversations) > 5): ?>
            <p class="mt-2 text-xs text-gray-500 dark:text-slate-400"><?= __('and :count more', [':count' => count($conversations) - 5]) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <p class="mt-4 text-sm text-gray-600 dark:text-slate-300"><?= __('Lost the link? Enter the email address you used and we will send you a new one.') ?></p>
    <form method="POST" action="/listings/conversation/email-link" class="mt-3 flex flex-col sm:flex-row gap-2" data-testid="recovery-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <label class="sr-only" for="return-email"><?= __('Email') ?></label>
        <input id="return-email" type="email" name="email" required maxlength="190" autocomplete="email"
               placeholder="<?= __('Your email address') ?>"
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700">
            <?= __('Email me my link') ?>
        </button>
    </form>
    <?php if (!empty($hub)): ?>
        <p class="mt-4">
            <a href="/listings/conversations/<?= h($hub['token']) ?>"
               class="font-medium text-blue-600 hover:underline dark:text-blue-400" data-testid="hub-link">
                <?= __('View all your conversations') ?>
            </a>
        </p>
    <?php endif; ?>
</section>

<?php if (empty($properties)): ?>
    <div class="bg-white rounded-xl shadow p-10 text-center dark:bg-slate-800">
        <p class="text-gray-600 dark:text-slate-300"><?= __('No properties are currently listed.') ?></p>
    </div>
<?php else: ?>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" x-data="{ openId: null }">
        <?php foreach ($properties as $property): ?>
            <?php
            $pid = (int) $property['id'];
            $ad = render_ad_description($property);
            $hasChat = isset($continueChats[$pid]);
            ?>
            <article class="bg-white rounded-xl shadow overflow-hidden flex flex-col dark:bg-slate-800" data-testid="listing-card">
                <?php if (!empty($property['main_photo_id'])): ?>
                    <img src="/listings/<?= $pid ?>/photos/<?= (int) $property['main_photo_id'] ?>"
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
                        <?php if ($hasChat): ?>
                            <a href="/listings/chat/<?= h($continueChats[$pid]) ?>"
                               class="inline-flex items-center justify-center w-full mb-3 border border-blue-600 text-blue-700 py-2 rounded-lg font-medium hover:bg-blue-50 dark:text-blue-400 dark:border-blue-500 dark:hover:bg-slate-700"
                               data-testid="continue-chat">
                                <?= __('Continue your conversation') ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" @click="openId = openId === <?= $pid ?> ? null : <?= $pid ?>"
                                :aria-expanded="openId === <?= $pid ?> ? 'true' : 'false'"
                                aria-controls="message-form-<?= $pid ?>" data-testid="message-button"
                                class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">
                            <?= $hasChat ? __('Send another message') : __('Send Message') ?>
                        </button>
                        <div id="message-form-<?= $pid ?>" x-show="openId === <?= $pid ?>" x-cloak data-testid="message-panel"
                             class="mt-4 border-t border-gray-200 pt-4 dark:border-slate-700">
                            <form method="POST" action="/listings/<?= $pid ?>/chat" class="space-y-3">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1 dark:text-slate-300" for="guest_name_<?= $pid ?>"><?= __('Your Name') ?></label>
                                    <input id="guest_name_<?= $pid ?>" type="text" name="guest_name" value="<?= old('guest_name') ?>" required maxlength="120" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1 dark:text-slate-300" for="guest_email_<?= $pid ?>"><?= __('Email') ?></label>
                                    <input id="guest_email_<?= $pid ?>" type="email" name="guest_email" value="<?= old('guest_email') ?>" required maxlength="190" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1 dark:text-slate-300" for="guest_body_<?= $pid ?>"><?= __('Message') ?></label>
                                    <textarea id="guest_body_<?= $pid ?>" name="body" rows="3" required maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Type a message...') ?>"><?= old('body') ?></textarea>
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
