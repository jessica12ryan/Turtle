import { expect, test, type Page } from '@playwright/test';

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

  // Session round-trip: logged out visitors must log back in.
  await page.context().clearCookies();
  await page.goto('/login');
  await expect(page).toHaveURL(/\/login$/);
  await page.locator('#email').fill(admin.email);
  await page.locator('#password').fill(admin.password);
  await page.getByRole('button', { name: 'Sign In' }).click();
  await expect(page).toHaveURL(/\/home$/);

  expect(pageErrors, `uncaught browser errors: ${pageErrors.join('; ')}`).toEqual([]);
});
