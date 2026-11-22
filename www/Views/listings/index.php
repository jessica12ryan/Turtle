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
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 items-start"
         x-data="{
             openId: null,
             detailsId: null,
             lightboxPhotos: [],
             lightboxIndex: 0,
             openLightbox(photos, index) { this.lightboxPhotos = photos; this.lightboxIndex = index; },
             closeLightbox() { this.lightboxPhotos = []; },
             nextPhoto() { if (this.lightboxPhotos.length) { this.lightboxIndex = (this.lightboxIndex + 1) % this.lightboxPhotos.length; } },
             prevPhoto() { if (this.lightboxPhotos.length) { this.lightboxIndex = (this.lightboxIndex - 1 + this.lightboxPhotos.length) % this.lightboxPhotos.length; } }
         }">
        <?php foreach ($properties as $property): ?>
            <?php
            $pid = (int) $property['id'];
            $ad = render_ad_description($property);
            $hasChat = isset($continueChats[$pid]);
            $gallery = $photos[$pid] ?? [];
            $galleryJson = h(json_encode($gallery, JSON_INVALID_UTF8_SUBSTITUTE));
            $photoCount = count($gallery);
            $isLongAd = mb_strlen(trim(strip_tags($ad))) > 160 || substr_count($ad, '<br') > 2;
            $showDetails = $ad !== '' && ($isLongAd || $photoCount > 1);
            ?>
            <article class="bg-white rounded-xl shadow overflow-hidden flex flex-col dark:bg-slate-800" data-testid="listing-card">
                <?php if (!empty($property['main_photo_id'])): ?>
                    <div class="relative">
                        <img src="/listings/<?= $pid ?>/photos/<?= (int) $property['main_photo_id'] ?>"
                             alt="<?= h($property['name']) ?>" class="h-48 w-full object-cover">
                        <?php if ($photoCount > 1): ?>
                            <button type="button" @click="openLightbox(<?= $galleryJson ?>, 0)"
                                    data-testid="more-photos"
                                    class="absolute bottom-2 right-2 flex items-center gap-1 bg-black/60 hover:bg-black/80 text-white text-xs font-medium px-2.5 py-1 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4-4 3 3 5-6 4 7M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
                                <?= __('View all :count photos', [':count' => $photoCount]) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="h-48 w-full bg-gray-200 flex items-center justify-center text-gray-400 relative dark:bg-slate-700">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M16 21V9h2a2 2 0 012 2v10M3 21h18"/></svg>
                        <?php if ($photoCount > 1): ?>
                            <button type="button" @click="openLightbox(<?= $galleryJson ?>, 0)"
                                    data-testid="more-photos"
                                    class="absolute bottom-2 right-2 flex items-center gap-1 bg-black/60 hover:bg-black/80 text-white text-xs font-medium px-2.5 py-1 rounded-full">
                                <?= __('View all :count photos', [':count' => $photoCount]) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="p-5 flex-1 flex flex-col">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-slate-100"><?= h($property['name']) ?></h2>
                    <?php $location = trim($property['address'] . ', ' . $property['city'] . ', ' . $property['province'], ', '); ?>
                    <?php if ($location !== ''): ?>
                        <p class="text-sm text-gray-500 mt-1 dark:text-slate-400"><?= h($location) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($property['rent_amount'])): ?>
                        <p class="mt-2 font-semibold text-gray-800 dark:text-slate-100"><?= __('Monthly Rent') ?>: $<?= h(number_format((float) $property['rent_amount'], 2)) ?></p>
                    <?php endif; ?>
                    <?php if ($ad !== '' || $photoCount > 1): ?>
                        <div class="mt-3 text-sm text-gray-600 dark:text-slate-300">
                            <?php if ($ad !== ''): ?>
                                <div id="description-<?= $pid ?>" data-testid="listing-description"
                                     class="listing-clamp" :class="detailsId === <?= $pid ?> ? 'listing-clamp-expanded' : ''">
                                    <?= $ad ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($showDetails): ?>
                                <button type="button" @click="detailsId = detailsId === <?= $pid ?> ? null : <?= $pid ?>"
                                        :aria-expanded="detailsId === <?= $pid ?> ? 'true' : 'false'"
                                        aria-controls="description-<?= $pid ?>"
                                        data-testid="see-more"
                                        class="mt-1 text-blue-600 hover:underline dark:text-blue-400 font-medium">
                                    <span x-show="detailsId !== <?= $pid ?>"><?= __('See more') ?></span>
                                    <span x-show="detailsId === <?= $pid ?>" x-cloak><?= __('See less') ?></span>
                                </button>
                            <?php endif; ?>
                            <?php if ($photoCount > 1): ?>
                                <div x-show="detailsId === <?= $pid ?>" x-cloak data-testid="listing-gallery" class="mt-3">
                                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400 mb-2"><?= __('Photos') ?></p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <?php foreach ($gallery as $index => $photo): ?>
                                            <button type="button" @click="openLightbox(<?= $galleryJson ?>, <?= $index ?>)"
                                                    class="rounded-lg overflow-hidden border border-gray-200 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <img src="<?= h($photo['src']) ?>" alt="<?= h($photo['alt']) ?>" class="w-full h-20 object-cover">
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
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

        <div x-show="lightboxPhotos.length > 0" x-cloak
             class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
             @click.self="closeLightbox()" @keydown.escape.window="closeLightbox()"
             role="dialog" aria-modal="true" aria-label="<?= __('Photos') ?>" data-testid="photo-lightbox">
            <template x-if="lightboxPhotos.length > 0">
                <div class="relative flex items-center justify-center w-full max-w-4xl">
                    <button type="button" @click="closeLightbox()" aria-label="<?= __('Close') ?>"
                            class="absolute -top-2 right-0 text-white bg-black/50 hover:bg-black/70 rounded-full w-9 h-9 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <button type="button" @click="prevPhoto()" aria-label="<?= __('Previous') ?>"
                            class="flex-shrink-0 text-white bg-black/50 hover:bg-black/70 rounded-full w-10 h-10 flex items-center justify-center mr-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="nextPhoto()" aria-label="<?= __('Next') ?>"
                            data-testid="lightbox-photo" class="cursor-pointer focus:outline-none focus:ring-2 focus:ring-white rounded-lg">
                        <img :src="lightboxPhotos[lightboxIndex].src" :alt="lightboxPhotos[lightboxIndex].alt"
                             class="max-h-[80vh] max-w-full rounded-lg shadow-xl object-contain">
                    </button>
                    <button type="button" @click="nextPhoto()" aria-label="<?= __('Next') ?>" data-testid="lightbox-next"
                            class="flex-shrink-0 text-white bg-black/50 hover:bg-black/70 rounded-full w-10 h-10 flex items-center justify-center ml-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <p class="absolute bottom-0 left-0 right-0 text-center text-white text-sm"
                       x-text="(lightboxIndex + 1) + ' / ' + lightboxPhotos.length"></p>
                </div>
            </template>
        </div>
    </div>
<?php endif; ?>
