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

/** Return the visitor-chat path from the most recent link email in Mailpit. */
async function guestLinkFromMailpit(request: APIRequestContext): Promise<string | null> {
  const list = await request.get(`${mailpitUrl}/api/v1/messages`);
  if (!list.ok()) return null;
  const payload = await list.json();
  const messages = Array.isArray(payload.messages) ? payload.messages : [];
  const linkMessage = messages.find((message: { Subject?: string }) =>
    String(message.Subject ?? '').includes('Your conversation link'));
  if (!linkMessage) return null;
  const detail = await request.get(`${mailpitUrl}/api/v1/message/${encodeURIComponent(String(linkMessage.ID))}`);
  if (!detail.ok()) return null;
  const match = (await detail.text()).match(/listings\/chat\/[a-f0-9]{64}/);
  return match ? match[0] : null;
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

  // Session round-trip: logged out visitors must log back in.
  await page.context().clearCookies();
  await page.goto('/login');
  await expect(page).toHaveURL(/\/login$/);
  await page.locator('#email').fill(admin.email);
  await page.locator('#password').fill(admin.password);
  await page.getByRole('button', { name: 'Sign In' }).click();
  await expect(page).toHaveURL(/\/home$/);

  // Staff can reply from the dedicated Guest Chats inbox, and the visitor sees it.
  await page.goto('/guest-chats');
  await expect(page.getByTestId('guest-chat-row')).toHaveCount(1);
  await page.getByTestId('guest-chat-row').first().click();
  await expect(page).toHaveURL(/\/guest-chats\/\d+$/);
  await page.locator('textarea[name="body"]').fill('Yes, it is available.');
  await page.getByTestId('guest-reply').click();
  await expect(page.getByText('Yes, it is available.')).toBeVisible();

  // The saved link works without a cookie, but a guessed token cannot open it.
  const guessedUrl = guestChatUrl.replace(/[a-f0-9]{64}$/, '0'.repeat(64));
  const strangerResponse = await request.get(guessedUrl);
  expect(strangerResponse.status(), 'a guessed token must not open the chat').toBe(404);
  const savedLinkResponse = await request.get(guestChatUrl);
  expect(savedLinkResponse.status(), 'the saved link must work without a cookie').toBe(200);
  await page.goto(guestChatUrl);
  await expect(page.getByTestId('chat-messages')).toContainText('Yes, it is available.');

  // A visitor who lost every browser trace can request a fresh link by email.
  await page.context().clearCookies();
  await page.goto('/listings');
  await page.getByTestId('recovery-form').locator('input[name="email"]').fill('guest@example.com');
  await page.getByTestId('recovery-form').getByRole('button', { name: 'Email me my link' }).click();
  await expect(page.getByText('If a conversation exists for that address', { exact: false })).toBeVisible();
  await expect(page.getByText('You can keep using any earlier links as well.', { exact: false })).toBeVisible();

  let recoveredPath: string | null = null;
  await expect.poll(async () => {
    const candidate = await guestLinkFromMailpit(request);
    if (!candidate || candidate === emailedPath) return false;
    recoveredPath = candidate;
    return true;
  }, { message: 'a replacement visitor link should arrive by email' }).toBe(true);

  expect((await request.get(emailedPath!)).status(), 'the original emailed link must still work').toBe(200);
  expect((await request.get(recoveredPath!)).status(), 'the additional link must work').toBe(200);
  await page.goto(emailedPath!);
  await expect(page.getByTestId('chat-messages')).toContainText('Yes, it is available.');
  await page.goto(recoveredPath!);
  await expect(page.getByTestId('chat-messages')).toContainText('Yes, it is available.');
  await page.goto('/listings');
  await expect(page.getByTestId('conversation-link')).toBeVisible();

  expect(pageErrors, `uncaught browser errors: ${pageErrors.join('; ')}`).toEqual([]);
});
