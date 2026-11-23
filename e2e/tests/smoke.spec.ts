import { expect, test, type APIRequestContext, type Locator, type Page } from '@playwright/test';

const admin = {
  name: 'E2E Admin',
  email: 'e2e-admin@example.com',
  password: 'SuperSecret123!',
};

const mailpitUrl = process.env.MAILPIT_URL ?? 'http://localhost:8025';

async function expectPageOk(page: Page, path: string): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status(), `${path} should return 200`).toBe(200);
  await expect(page.locator('body')).not.toContainText('An unexpected error occurred');
}

/** Return a visitor link from the matching email in Mailpit. */
async function linkFromMailpit(request: APIRequestContext, subject: string, pattern: RegExp): Promise<string | null> {
  const list = await request.get(`${mailpitUrl}/api/v1/messages`);
  if (!list.ok()) return null;
  const payload = await list.json();
  const messages = Array.isArray(payload.messages) ? payload.messages : [];
  const linkMessage = messages.find((message: { Subject?: string }) =>
    String(message.Subject ?? '').includes(subject));
  if (!linkMessage) return null;
  const detail = await request.get(`${mailpitUrl}/api/v1/message/${encodeURIComponent(String(linkMessage.ID))}`);
  if (!detail.ok()) return null;
  const match = (await detail.text()).match(pattern);
  return match ? match[0] : null;
}

const guestLinkFromMailpit = (request: APIRequestContext): Promise<string | null> =>
  linkFromMailpit(request, 'Your conversation link', /listings\/chat\/[a-f0-9]{64}/);

const guestHubLinkFromMailpit = (request: APIRequestContext): Promise<string | null> =>
  linkFromMailpit(request, 'Your conversations', /listings\/conversations\/[a-f0-9]{64}/);

const hubLinkFromConversationEmail = (request: APIRequestContext): Promise<string | null> =>
  linkFromMailpit(request, 'Your conversation link', /listings\/conversations\/[a-f0-9]{64}/);

const guestReplyLinkFromMailpit = (request: APIRequestContext): Promise<string | null> =>
  linkFromMailpit(request, 'New reply to your conversation', /listings\/chat\/[a-f0-9]{64}/);

/** True when a context menu event is suppressed on the given element. */
async function contextMenuBlocked(locator: Locator): Promise<boolean> {
  return locator.evaluate((el) => {
    const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    el.dispatchEvent(event);
    return event.defaultPrevented;
  });
}

/** Give a seeded property photos so listing media and ordering are exercised. */
async function addListingPhotos(page: Page, propertyId: number, count = 2): Promise<void> {
  const photos = [
    'iVBORw0KGgoAAAANSUhEUgAAAHgAAABQCAIAAABd+SbeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAxklEQVR4nO3QQREAIAzAMMC/5+GiPEgU9LpnUTivA35hdMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOXBitAZ9Q1fboAAAAAElFTkSuQmCC',
    'iVBORw0KGgoAAAANSUhEUgAAAHgAAABQCAIAAABd+SbeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAyUlEQVR4nO3QQRHAIADAMJhynIOKlQeJgl7nWHvwv+92wCuMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNGRA4D3Ah9SPBpWAAAAAElFTkSuQmCC',
  ];
  await page.goto(`/properties/${propertyId}/edit`);
  for (let index = 0; index < count; index++) {
    await page.locator('#photo-upload-form input[type="file"]').setInputFiles({
      name: `listing-${index + 1}.png`,
      mimeType: 'image/png',
      buffer: Buffer.from(photos[index % photos.length], 'base64'),
    });
    await page.locator('#photo-upload-form button[type="submit"]').click();
    await expect(page.locator(`img[src^="/properties/${propertyId}/photos/"]`)).toHaveCount(index + 1);
  }
}

/** Walk the five-step setup wizard. Only one step is visible at a time. */
async function completeSetup(page: Page): Promise<void> {
  await page.goto('/setup');
  await expect(page.getByRole('heading', { name: 'Welcome to Turtle' })).toBeVisible();

  await page.locator('#site_name').fill('Turtle E2E');
  await page.locator('.step-content[data-step="1"] button[type="button"]').click();

  // Localization defaults (US / English / America/New_York) are fine.
  await page.locator('.step-content[data-step="2"] button[type="button"]').last().click();

  await page.locator('#name').fill(admin.name);
  await page.locator('#email').fill(admin.email);
  await page.locator('#password').fill(admin.password);
  await page.locator('#password_confirmation').fill(admin.password);
  await page.locator('.step-content[data-step="3"] button[type="button"]').last().click();

  // SMTP defaults point at the bundled Mailpit container.
  await page.locator('.step-content[data-step="4"] button[type="button"]').last().click();

  await page.locator('input[name="telemetry_enabled"]').uncheck();
  await page.locator('input[name="load_sample_data"]').check();
  await page.locator('#finish-btn').click();
}

test('fresh install: setup, navigation, ticket, email and re-login', async ({ page, request }) => {
  const pageErrors: string[] = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));

  // A fresh database always routes anonymous visitors to the wizard.
  await page.goto('/');
  await expect(page).toHaveURL(/\/setup$/);

  await completeSetup(page);
  await expect(page).toHaveURL(/\/home$/);
  await expect(page.locator('h1').first()).toBeVisible();

  for (const path of ['/properties', '/tenants', '/tickets', '/settings']) {
    await expectPageOk(page, path);
  }

  // Create a ticket against seeded sample data.
  await page.goto('/tickets/create');
  await page.locator('select[name="property_id"]').selectOption({ label: 'The Wellington' });
  await page.locator('input[name="subject"]').fill('E2E leaking tap');
  await page.locator('textarea[name="description"]').fill('Created by the automated smoke test.');
  await page.locator('select[name="category"]').selectOption('plumbing');
  await page.locator('select[name="priority"]').selectOption('high');
  await page.getByRole('button', { name: 'Submit Ticket' }).click();

  await expect(page).toHaveURL(/\/tickets\/\d+$/);
  await expect(page.locator('body')).toContainText('E2E leaking tap');

  // Two properties in the same grid row get photos, so expansion of one card
  // can be checked against a sibling that stays collapsed. The second gets a
  // third photo so reordering can be exercised.
  await addListingPhotos(page, 1);
  await addListingPhotos(page, 2, 3);

  // Photos can be reordered by dragging; the main photo stays pinned first.
  await page.goto('/properties/2/edit');
  await page.evaluate(() => { (window as any).__photoManagerNoReload = true; });
  const noReload = async (): Promise<void> => {
    expect(await page.evaluate(() => (window as any).__photoManagerNoReload)).toBe(true);
  };
  const photoTiles = page.getByTestId('photo-tile');
  await expect(photoTiles).toHaveCount(3);
  const tileOrder = (): Promise<string[]> => photoTiles.locator('img').evaluateAll((imgs) =>
    imgs.map((img) => (img as HTMLImageElement).getAttribute('src') || ''));
  const waitForOrder = async (expected: string[]): Promise<void> => {
    await expect.poll(async () => {
      try {
        return (await tileOrder()).join('|');
      } catch {
        return '';
      }
    }).toBe(expected.join('|'));
  };
  const originalOrder = await tileOrder();
  await expect(photoTiles.first()).not.toHaveAttribute('draggable', 'true');
  await expect(photoTiles.nth(1).getByTestId('set-as-main')).toBeVisible();

  // Arrow icons stay dark on their light buttons in dark mode.
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const arrowStroke = await photoTiles.nth(1).getByTestId('move-photo-left').locator('svg')
    .evaluate((el) => getComputedStyle(el).stroke);
  expect(arrowStroke).toBe('rgb(30, 41, 59)');
  await page.evaluate(() => document.documentElement.classList.remove('dark'));

  // Use real mouse movements: Playwright's dragTo() dispatches synthetic drag
  // events and misses broken native drag sources (e.g. a non-draggable image).
  await photoTiles.nth(2).scrollIntoViewIfNeeded();
  const from = await photoTiles.nth(2).boundingBox();
  const to = await photoTiles.nth(1).boundingBox();
  if (!from || !to) {
    throw new Error('photo tiles should have bounding boxes');
  }
  await page.mouse.move(from.x + 24, from.y + from.height / 2);
  await page.mouse.down();
  await page.mouse.move(from.x + 36, from.y + from.height / 2 + 12, { steps: 5 });
  await page.mouse.move(to.x + to.width / 2, to.y + to.height / 2, { steps: 12 });
  await page.mouse.up();
  const draggedOrder = [originalOrder[0], originalOrder[2], originalOrder[1]];
  await waitForOrder(draggedOrder);
  await noReload();

  // The arrow buttons offer the same control without drag and drop.
  await photoTiles.nth(2).getByTestId('move-photo-left').click();
  await waitForOrder(originalOrder);
  await noReload();

  // "Set as Main" stays available and moves the chosen photo to the front.
  await photoTiles.nth(1).getByTestId('set-as-main').click();
  await waitForOrder([originalOrder[1], originalOrder[0], originalOrder[2]]);
  await expect(photoTiles.first().getByTestId('main-badge')).toBeVisible();
  await expect(photoTiles.first().getByTestId('set-as-main')).toBeHidden();
  await noReload();

  // Deleting a photo also updates the grid in place.
  const keptOrder = await tileOrder();
  page.once('dialog', (dialog) => dialog.accept());
  await photoTiles.last().getByTestId('delete-photo').click();
  await expect(photoTiles).toHaveCount(2);
  await noReload();
  expect((await tileOrder()).join('|')).toBe(keptOrder.slice(0, 2).join('|'));

  // A real reload confirms the in-place changes were persisted.
  await page.reload();
  await expect(photoTiles).toHaveCount(2);
  await expect(photoTiles.first().getByTestId('main-badge')).toBeVisible();
  expect((await tileOrder()).join('|')).toBe(keptOrder.slice(0, 2).join('|'));

  // Tampered reorder requests are rejected.
  const photoIds = await photoTiles.evaluateAll((tiles) =>
    tiles.map((tile) => Number((tile as HTMLElement).dataset.photoId)));
  const reorderCsrf = await page.locator('#photo-upload-form input[name="_csrf"]').getAttribute('value');
  expect((await page.request.post('/properties/2/photos/reorder', {
    data: { ids: photoIds, _csrf: 'invalid' },
  })).status()).toBe(403);
  expect((await page.request.post('/properties/2/photos/reorder', {
    data: { ids: [photoIds[0], photoIds[1], 999999], _csrf: reorderCsrf },
  })).status()).toBe(400);

  // A property with no tenants adds a View Guest Chats shortcut on the dashboard.
  const createdProperty = await page.request.post('/properties', {
    form: {
      _csrf: reorderCsrf!,
      landlord_id: '6',
      property_manager_id: '2',
      name: 'No Tenant Property',
      address: '1 Test St',
      city: 'Toronto',
      country: 'CA',
      province: 'ON',
      postal_code: 'M5V 1A1',
    },
  });
  expect(createdProperty.ok()).toBe(true);
  await page.goto('/home');
  const noTenantAlert = page.locator('div.bg-yellow-50').filter({ hasText: 'no tenants' });
  await expect(noTenantAlert).toBeVisible();
  await expect(noTenantAlert.getByRole('link', { name: /Add Tenant/ })).toBeVisible();
  await expect(noTenantAlert.getByRole('link', { name: /View Applications/ })).toBeVisible();
  await expect(noTenantAlert.getByRole('link', { name: /View Guest Chats/ })).toBeVisible();

  // The wizard saved mailpit as the SMTP host; send a test email from Settings.
  await page.goto('/settings');
  const csrf = await page.locator('input[name="_csrf"]').first().getAttribute('value');
  expect(csrf, 'settings page should expose a CSRF token').toBeTruthy();

  const mailResponse = await page.request.post('/settings/test-mail', {
    form: { _csrf: csrf! },
  });
  expect(mailResponse.status()).toBe(200);
  expect((await mailResponse.json()).success).toBe(true);

  await expect.poll(async () => {
    const response = await request.get(`${mailpitUrl}/api/v1/messages`);
    if (!response.ok()) {
      return 0;
    }
    return (await response.text()).includes('Test Email from Turtle') ? 1 : 0;
  }, { message: 'test email should arrive in Mailpit' }).toBe(1);

  // Anonymous visitors land on the public listings page, not the login form.
  await page.context().clearCookies();
  await page.goto('/');
  await expect(page).toHaveURL(/\/listings$/);
  await expect(page.getByTestId('login-link')).toBeVisible();
  await expect(page.getByRole('banner').locator('span')).toHaveCount(0);
  await expect(page.getByTestId('listing-card').first()).toBeVisible();
  await expect(page.getByTestId('return-conversation')).toBeVisible();
  await expect(page.getByTestId('listing-card').filter({ hasText: 'The Wellington' }))
    .toContainText('Monthly Rent: $2,200.00');

  // Expanding one card must not stretch its row neighbours.
  const kingCard = page.getByTestId('listing-card').filter({ hasText: 'King Street Lofts' });
  const neighbourCard = page.getByTestId('listing-card').filter({ hasText: 'Harbourfront Condos' });
  const neighbourHeight = await neighbourCard.evaluate((el) => el.getBoundingClientRect().height);
  await kingCard.getByTestId('see-more').click();
  await expect(kingCard.getByTestId('see-more')).toContainText('See less');
  expect(await neighbourCard.evaluate((el) => el.getBoundingClientRect().height)).toBeCloseTo(neighbourHeight, 0);

  // Descriptions are clamped; "See more" reveals the full text and the gallery.
  const photoCard = page.getByTestId('listing-card').filter({ hasText: 'The Wellington' });
  const description = photoCard.getByTestId('listing-description');
  const mainPhoto = photoCard.locator('img.listing-photo').first();
  expect(await contextMenuBlocked(mainPhoto)).toBe(true);
  await expect(mainPhoto).toHaveAttribute('draggable', 'false');
  expect(await contextMenuBlocked(description), 'text menus must stay available').toBe(false);
  await expect(description).toHaveClass(/listing-clamp/);
  await expect(photoCard.getByTestId('see-more')).toBeVisible();
  await expect(photoCard.getByTestId('listing-gallery')).toBeHidden();
  await photoCard.getByTestId('see-more').click();
  await expect(photoCard.getByTestId('see-more')).toContainText('See less');
  await expect(description).toHaveClass(/listing-clamp-expanded/);
  await expect(photoCard.getByTestId('listing-gallery').locator('img')).toHaveCount(2);
  expect(await contextMenuBlocked(photoCard.getByTestId('listing-gallery').locator('img').first())).toBe(true);

  // The photo badge opens a lightbox with navigation; Escape closes it.
  await expect(photoCard.getByTestId('more-photos')).toContainText('View all 2 photos');
  await photoCard.getByTestId('more-photos').click();
  const lightbox = page.getByTestId('photo-lightbox');
  await expect(lightbox).toBeVisible();
  expect(await contextMenuBlocked(lightbox.getByTestId('lightbox-photo').locator('img'))).toBe(true);
  await expect(lightbox).toContainText('1 / 2');
  // Clicking the displayed photo advances, wrapping back to the first one.
  await lightbox.getByTestId('lightbox-photo').click();
  await expect(lightbox).toContainText('2 / 2');
  await lightbox.getByTestId('lightbox-photo').click();
  await expect(lightbox).toContainText('1 / 2');
  await lightbox.getByTestId('lightbox-next').click();
  await expect(lightbox).toContainText('2 / 2');
  // The keyboard arrows navigate too.
  await page.keyboard.press('ArrowLeft');
  await expect(lightbox).toContainText('1 / 2');
  await page.keyboard.press('ArrowRight');
  await expect(lightbox).toContainText('2 / 2');
  await page.keyboard.press('Escape');
  await expect(lightbox).toBeHidden();

  // Opening one property closes any other open message form.
  await page.getByTestId('message-button').first().click();
  await expect(page.locator('[data-testid="message-panel"]:visible')).toHaveCount(1);
  await page.getByTestId('message-button').nth(1).click();
  await expect(page.locator('[data-testid="message-panel"]:visible')).toHaveCount(1);
  await expect(page.getByTestId('message-button').first()).toHaveAttribute('aria-expanded', 'false');
  await page.getByTestId('message-button').first().click();
  await expect(page.getByTestId('message-button').first()).toHaveAttribute('aria-expanded', 'true');

  // A visitor starts a two-way chat from a listing.
  await page.locator('input[name="guest_name"]').first().fill('Guest Visitor');
  await page.locator('input[name="guest_email"]').first().fill('guest@example.com');
  await page.locator('textarea[name="body"]').first().fill('Hello, is this available?');
  await page.getByTestId('send-message').first().click();
  await expect(page).toHaveURL(/\/listings\/chat\/[a-f0-9]{64}$/);
  const guestChatUrl = page.url();
  await expect(page.getByTestId('chat-messages')).toContainText('Hello, is this available?');
  await expect(page.getByText('This conversation is private.', { exact: false })).toBeVisible();

  // The visitor also gets the private link by email, so a cleared browser
  // does not lock them out of their conversation.
  let emailedPath: string | null = null;
  await expect.poll(async () => {
    emailedPath = await guestLinkFromMailpit(request);
    return emailedPath !== null;
  }, { message: 'the visitor should receive the private link by email' }).toBe(true);

  // A second property gets its own conversation, but the same guest hub.
  await page.getByRole('link', { name: 'Back to listings' }).click();
  await expect(page).toHaveURL(/\/listings$/);
  const secondCard = page.getByTestId('listing-card').nth(1);
  await secondCard.getByTestId('message-button').click();
  await secondCard.locator('input[name="guest_name"]').fill('Guest Visitor');
  await secondCard.locator('input[name="guest_email"]').fill('guest@example.com');
  await secondCard.locator('textarea[name="body"]').fill('Do you have parking?');
  await secondCard.getByTestId('send-message').click();
  await expect(page).toHaveURL(/\/listings\/chat\/[a-f0-9]{64}$/);
  const secondChatUrl = page.url();
  expect(secondChatUrl).not.toBe(guestChatUrl);
  await expect(page.getByTestId('chat-messages')).toContainText('Do you have parking?');

  let firstHubPath: string | null = null;
  await expect.poll(async () => {
    firstHubPath = await hubLinkFromConversationEmail(request);
    return firstHubPath !== null;
  }, { message: 'the visitor should receive a hub link by email' }).toBe(true);

  // Session round-trip: logged out visitors must log back in.
  await page.context().clearCookies();
  await page.goto('/login');
  await expect(page).toHaveURL(/\/login$/);
  await page.locator('#email').fill(admin.email);
  await page.locator('#password').fill(admin.password);
  await page.getByRole('button', { name: 'Sign In' }).click();
  await expect(page).toHaveURL(/\/home$/);
  await expect(page.getByTestId('guest-chat-unread-badge')).toHaveText('2');

  // The permissions page labels the guest chat section and new actions clearly.
  await page.goto('/settings?tab=permissions');
  await expect(page.getByText('Guest_chats', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Archive Guest Chats', { exact: true })).toBeVisible();
  await expect(page.getByText('Restore Guest Chats', { exact: true })).toBeVisible();
  await expect(page.getByText('Delete Guest Chats', { exact: true })).toBeVisible();

  // Staff can reply from the dedicated Guest Chats inbox, and the visitor sees it.
  await page.goto('/guest-chats');
  await expect(page.getByTestId('guest-chat-group')).toHaveCount(1);
  await expect(page.getByTestId('guest-chat-group-email')).toHaveText('guest@example.com');
  await expect(page.getByTestId('guest-chat-row')).toHaveCount(2);
  await expect(page.getByTestId('guest-chat-row-name')).toHaveCount(2);
  await expect(page.getByTestId('guest-chat-row-name').first()).toHaveText('Guest Visitor');
  await page.getByTestId('guest-chat-row').filter({ hasText: 'Harbourfront Condos' }).click();
  await expect(page).toHaveURL(/\/guest-chats\/\d+$/);
  await page.locator('textarea[name="body"]').fill('Yes, it is available.');
  await page.getByTestId('guest-reply').click();
  await expect(page.getByText('Yes, it is available.')).toBeVisible();
  await expect(page.getByTestId('guest-chat-unread-badge')).toHaveText('1');

  // The always-on settings tab shows guest chat history like the messenger page.
  await page.goto('/settings?tab=guest_chats');
  await expect(page.getByTestId('guest-chat-history')).toBeVisible();
  await expect(page.locator('a[href="/settings?tab=guest_chats"]')).toHaveClass(/bg-blue-50/);
  await expect(page.getByTestId('guest-chat-conversation')).toHaveCount(2);
  await page.getByTestId('guest-chat-conversation').filter({ hasText: 'Harbourfront Condos' }).click();
  await expect(page.getByTestId('guest-chat-thread')).toContainText('Yes, it is available.');
  await expect(page.locator('input[name="messaging_enabled"]')).toHaveCount(0);
  await page.locator('input[name="q"]').fill('parking');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.getByTestId('guest-chat-conversation')).toHaveCount(1);

  // The guest gets a fresh direct link to the conversation by email.
  let replyEmailPath: string | null = null;
  await expect.poll(async () => {
    replyEmailPath = await guestReplyLinkFromMailpit(request);
    return replyEmailPath !== null;
  }, { message: 'the guest should be emailed about the staff reply' }).toBe(true);
  await page.goto(`/${replyEmailPath!}`);
  await expect(page.getByTestId('chat-messages')).toContainText('Yes, it is available.');

  // The saved link works without a cookie, but a guessed token cannot open it.
  const guessedUrl = guestChatUrl.replace(/[a-f0-9]{64}$/, '0'.repeat(64));
  const strangerResponse = await request.get(guessedUrl);
  expect(strangerResponse.status(), 'a guessed token must not open the chat').toBe(404);
  const savedLinkResponse = await request.get(guestChatUrl);
  expect(savedLinkResponse.status(), 'the saved link must work without a cookie').toBe(200);
  await page.goto(guestChatUrl);
  await expect(page.getByTestId('chat-messages')).toContainText('Yes, it is available.');

  // A visitor who lost every browser trace can request a fresh hub link.
  await page.context().clearCookies();
  await page.goto('/listings');
  await page.getByTestId('recovery-form').locator('input[name="email"]').fill('guest@example.com');
  await page.getByTestId('recovery-form').getByRole('button', { name: 'Email me my link' }).click();
  await expect(page.getByText('If a conversation exists for that address', { exact: false })).toBeVisible();

  let recoveredHubPath: string | null = null;
  await expect.poll(async () => {
    const candidate = await guestHubLinkFromMailpit(request);
    if (!candidate || candidate === firstHubPath) return false;
    recoveredHubPath = candidate;
    return true;
  }, { message: 'a replacement hub link should arrive by email' }).toBe(true);

  expect((await request.get(`/${firstHubPath!}`)).status(), 'the earlier hub link must still work').toBe(200);
  expect((await request.get(`/${recoveredHubPath!}`)).status(), 'the new hub link must work').toBe(200);
  expect((await request.get(`/${emailedPath!}`)).status(), 'the original direct link must still work').toBe(200);

  // The hub lists one row per property and opens each conversation.
  await page.goto(`/${recoveredHubPath!}`);
  await expect(page.getByRole('heading', { name: 'Your conversations' })).toBeVisible();
  await expect(page.getByTestId('hub-conversation-link')).toHaveCount(2);
  // The replied-to conversation is the most recently active one.
  await page.getByTestId('hub-conversation-link').first().click();
  await expect(page).toHaveURL(/\/listings\/chat\/[a-f0-9]{64}$/);
  await expect(page.getByTestId('chat-messages')).toContainText('Hello, is this available?');
  await expect(page.getByTestId('chat-messages')).toContainText('Yes, it is available.');

  await page.goto(`/${recoveredHubPath!}`);
  await page.getByTestId('hub-conversation-link').nth(1).click();
  await expect(page.getByTestId('chat-messages')).toContainText('Do you have parking?');

  // A hub link must not open an arbitrary conversation id.
  const mismatchResponse = await request.get(`/${recoveredHubPath!}/chat/999999`);
  expect(mismatchResponse.status(), 'a hub link must not open another chat').toBe(404);

  await page.goto('/listings');
  await expect(page.getByTestId('conversation-link')).toHaveCount(2);
  await expect(page.getByTestId('hub-link')).toBeVisible();

  // Archiving hides a chat from guests; restoring brings it back; delete removes it.
  await page.context().clearCookies();
  await page.goto('/login');
  await expect(page).toHaveURL(/\/login$/);
  await page.locator('#email').fill(admin.email);
  await page.locator('#password').fill(admin.password);
  await page.getByRole('button', { name: 'Sign In' }).click();
  await expect(page).toHaveURL(/\/home$/);

  await page.goto('/guest-chats');
  const firstRow = page.getByTestId('guest-chat-row-wrapper').filter({ hasText: 'Harbourfront Condos' });
  page.once('dialog', (dialog) => dialog.accept());
  await firstRow.getByTestId('guest-chat-list-archive').click();
  await expect(page).toHaveURL(/\/guest-chats$/);
  await expect(page.getByTestId('guest-chat-row')).toHaveCount(1);
  expect((await request.get(guestChatUrl)).status(), 'an archived chat must be hidden from guests').toBe(404);

  await page.getByTestId('guest-chat-archived-toggle').click();
  await expect(page.getByTestId('guest-chat-archived-badge')).toBeVisible();
  const archivedRow = page.getByTestId('guest-chat-row-wrapper').filter({ hasText: 'Harbourfront Condos' });
  await archivedRow.getByTestId('guest-chat-list-restore').click();
  await expect(page).toHaveURL(/\/guest-chats\/\d+$/);
  expect((await request.get(guestChatUrl)).status(), 'a restored chat must be accessible again').toBe(200);

  await page.goto('/guest-chats');
  const secondRow = page.getByTestId('guest-chat-row-wrapper').filter({ hasText: 'King Street Lofts' });
  page.once('dialog', (dialog) => dialog.accept());
  await secondRow.getByTestId('guest-chat-list-delete').click();
  await expect(page).toHaveURL(/\/guest-chats$/);
  expect((await request.get(secondChatUrl)).status(), 'a deleted chat must be gone').toBe(404);

  expect(pageErrors, `uncaught browser errors: ${pageErrors.join('; ')}`).toEqual([]);
});
