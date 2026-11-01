import {
  test,
  expect,
  chooseForms,
  createMember,
  accountExists,
  googleCalls,
  resetGoogleCalls,
  uniqueEmail,
  solveWidget,
  fillRegistrationForm,
  fillLoginForm,
  isLoggedIn,
  wpAdminLogin,
  GOOD_TOKEN,
  BAD_TOKEN,
  GOOGLE_DOWN_TOKEN,
  ERROR,
} from './fixtures';

const WIDGET = '.g-recaptcha';
const ERROR_MESSAGE = '.wpum-message.error';
const REGISTER = 'input[name="submit_registration"]';
const LOGIN = 'input[name="submit_login"]';

test.describe('Which forms get the widget', () => {
  test.afterEach(() => {
    chooseForms('login,registration');
  });

  test('both forms, with the site key and Google\'s script', async ({ page, loginPage, registerPage }) => {
    const script = page.waitForRequest(/www\.google\.com\/recaptcha\/api\.js\?hl=en/);

    await page.goto(loginPage);

    await script;
    await expect(page.locator(`#wpum-submit-login-form ${WIDGET}`)).toHaveAttribute('data-sitekey', 'e2e-site-key');

    await page.goto(registerPage);

    await expect(page.locator(`#wpum-submit-registration-form ${WIDGET}`)).toHaveAttribute('data-sitekey', 'e2e-site-key');
  });

  test('a site that chose only login', async ({ page, loginPage, registerPage }) => {
    chooseForms('login');

    await page.goto(loginPage);
    await expect(page.locator(WIDGET)).toHaveCount(1);

    await page.goto(registerPage);
    await expect(page.locator(REGISTER)).toBeVisible();
    await expect(page.locator(WIDGET)).toHaveCount(0);
  });

  test('a site that chose only registration', async ({ page, loginPage, registerPage }) => {
    chooseForms('registration');

    await page.goto(registerPage);
    await expect(page.locator(WIDGET)).toHaveCount(1);

    await page.goto(loginPage);
    await expect(page.locator(LOGIN)).toBeVisible();
    await expect(page.locator(WIDGET)).toHaveCount(0);
  });

  test('a site that chose nothing', async ({ page, loginPage, registerPage }) => {
    chooseForms('none');

    await page.goto(loginPage);
    await expect(page.locator(LOGIN)).toBeVisible();
    await expect(page.locator(WIDGET)).toHaveCount(0);

    await page.goto(registerPage);
    await expect(page.locator(REGISTER)).toBeVisible();
    await expect(page.locator(WIDGET)).toHaveCount(0);
  });
});

test.describe('Registering', () => {
  test('a solved reCAPTCHA registers the member', async ({ page, registerPage }) => {
    const email = uniqueEmail('solved');

    await page.goto(registerPage);
    await fillRegistrationForm(page, email);
    await solveWidget(page, GOOD_TOKEN);
    await page.locator(REGISTER).click();

    await expect(page).toHaveURL(/registration=success/);
    expect(accountExists(email)).toBe(true);
  });

  test('a token Google rejects creates no account', async ({ page, registerPage }) => {
    const email = uniqueEmail('rejected');

    await page.goto(registerPage);
    await fillRegistrationForm(page, email);
    await solveWidget(page, BAD_TOKEN);
    await page.locator(REGISTER).click();

    await expect(page.locator(ERROR_MESSAGE)).toContainText(ERROR);
    await expect(page).not.toHaveURL(/registration=success/);
    await expect(page.locator(WIDGET)).toHaveCount(1);
    expect(accountExists(email)).toBe(false);
  });

  test('an unticked box creates no account, and Google is never asked', async ({ page, registerPage }) => {
    const email = uniqueEmail('unticked');
    resetGoogleCalls();

    await page.goto(registerPage);
    await fillRegistrationForm(page, email);
    await solveWidget(page, '');
    await page.locator(REGISTER).click();

    await expect(page.locator(ERROR_MESSAGE)).toContainText(ERROR);
    expect(accountExists(email)).toBe(false);
    expect(googleCalls()).toBe(0);
  });

  test('Google being down creates no account', async ({ page, registerPage }) => {
    const email = uniqueEmail('down');

    await page.goto(registerPage);
    await fillRegistrationForm(page, email);
    await solveWidget(page, GOOGLE_DOWN_TOKEN);
    await page.locator(REGISTER).click();

    await expect(page.locator(ERROR_MESSAGE)).toContainText(ERROR);
    expect(accountExists(email)).toBe(false);
  });

  test('a form the site did not choose works without reCAPTCHA', async ({ page, registerPage }) => {
    const email = uniqueEmail('unchosen');
    chooseForms('login');

    try {
      await page.goto(registerPage);
      await fillRegistrationForm(page, email);
      await page.locator(REGISTER).click();

      await expect(page).toHaveURL(/registration=success/);
      expect(accountExists(email)).toBe(true);
    } finally {
      chooseForms('login,registration');
    }
  });
});

test.describe('Logging in', () => {
  test('a solved reCAPTCHA and the right password log the member in', async ({ page, loginPage }) => {
    createMember('rc_solved');

    await page.goto(loginPage);
    await fillLoginForm(page, 'rc_solved');
    await solveWidget(page, GOOD_TOKEN);
    await page.locator(LOGIN).click();

    await expect.poll(() => isLoggedIn(page)).toBe(true);
  });

  test('a token Google rejects keeps the right password out', async ({ page, loginPage }) => {
    createMember('rc_rejected');

    await page.goto(loginPage);
    await fillLoginForm(page, 'rc_rejected');
    await solveWidget(page, BAD_TOKEN);
    await page.locator(LOGIN).click();

    await expect(page.locator(ERROR_MESSAGE)).toContainText(ERROR);
    expect(await isLoggedIn(page)).toBe(false);
  });

  test('an unticked box keeps the right password out', async ({ page, loginPage }) => {
    createMember('rc_unticked');

    await page.goto(loginPage);
    await fillLoginForm(page, 'rc_unticked');
    await solveWidget(page, '');
    await page.locator(LOGIN).click();

    await expect(page.locator(ERROR_MESSAGE)).toContainText(ERROR);
    expect(await isLoggedIn(page)).toBe(false);
  });

  test('Google being down keeps the right password out', async ({ page, loginPage }) => {
    createMember('rc_down');

    await page.goto(loginPage);
    await fillLoginForm(page, 'rc_down');
    await solveWidget(page, GOOGLE_DOWN_TOKEN);
    await page.locator(LOGIN).click();

    await expect(page.locator(ERROR_MESSAGE)).toContainText(ERROR);
    expect(await isLoggedIn(page)).toBe(false);
  });

  test('a solved reCAPTCHA doesn\'t excuse a wrong password', async ({ page, loginPage }) => {
    createMember('rc_wrong');

    await page.goto(loginPage);
    await fillLoginForm(page, 'rc_wrong', 'NotMyP@ssw0rd!');
    await solveWidget(page, GOOD_TOKEN);
    await page.locator(LOGIN).click();

    await expect(page.locator(ERROR_MESSAGE)).toBeVisible();
    await expect(page.locator(ERROR_MESSAGE)).not.toContainText(ERROR);
    expect(await isLoggedIn(page)).toBe(false);
  });
});

test.describe('Settings', () => {
  test('the keys and the location are on the Misc tab', async ({ page }) => {
    await wpAdminLogin(page);

    await page.goto('/wp-admin/users.php?page=wpum-settings#/general/misc');

    await expect(page.getByText('Google reCAPTCHA site key')).toBeVisible({ timeout: 30000 });
    await expect(page.getByText('Google reCAPTCHA secret key')).toBeVisible();
    await expect(page.getByText('Google reCAPTCHA display location:')).toBeVisible();
  });
});
