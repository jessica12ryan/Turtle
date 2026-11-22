import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

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

  expect(pageErrors, `uncaught browser errors: ${pageErrors.join('; ')}`).toEqual([]);
});
