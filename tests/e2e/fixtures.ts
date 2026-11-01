import { test as base, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

export const PASSWORD = 'TestPass123!';

/**
 * The tokens the stand-in for Google in tests/mu-plugins/wpumrc-e2e.php knows. Anything else is
 * rejected.
 */
export const GOOD_TOKEN = 'e2e-good-token';
export const BAD_TOKEN = 'e2e-bad-token';
export const GOOGLE_DOWN_TOKEN = 'e2e-google-is-down';

export const ERROR = 'Recaptcha validation failed.';

/**
 * Run a WP-CLI command via wp-env against the test site.
 */
export function wpCli(command: string): string {
  const result = execSync(`npx @wordpress/env run tests-cli wp ${command}`, {
    cwd: process.env.PLUGIN_DIR || process.cwd(),
    encoding: 'utf-8',
    timeout: 60000,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  return result.trim();
}

/**
 * Run one of the suite's own WP-CLI commands (tests/mu-plugins/wpumrc-e2e.php) and return
 * its last line of output.
 */
export function e2e(command: string): string {
  const output = execSync(`npx @wordpress/env run tests-cli wp wpumrc-e2e ${command}`, {
    cwd: process.env.PLUGIN_DIR || process.cwd(),
    encoding: 'utf-8',
    timeout: 60000,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  const lines = output.split('\n').map((line) => line.trim()).filter(Boolean);
  return lines.length ? lines[lines.length - 1] : '';
}

/**
 * Choose the forms that get reCAPTCHA: 'login', 'registration', 'login,registration' or 'none'.
 */
export function chooseForms(forms: string): void {
  e2e(`locations ${forms}`);
}

/**
 * Create (or recreate) a member with PASSWORD.
 */
export function createMember(login: string): void {
  e2e(`create_member ${login}`);
}

/**
 * Whether an account with this email exists.
 */
export function accountExists(email: string): boolean {
  return Number(e2e(`account ${email}`)) > 0;
}

export function googleCalls(): number {
  return Number(e2e('google_calls'));
}

export function resetGoogleCalls(): void {
  e2e('reset_google_calls');
}

/**
 * A unique email for a registration.
 */
export function uniqueEmail(prefix: string): string {
  return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1000)}@example.com`;
}

/**
 * What Google's widget does once it's solved: put the token in a field inside the widget.
 * An empty token is a box nobody ticked. Google's script isn't loaded in these tests.
 */
export async function solveWidget(page: Page, token: string): Promise<void> {
  await page.locator('.g-recaptcha').evaluate((widget, value) => {
    const answer = document.createElement('textarea');
    answer.name = 'g-recaptcha-response';
    answer.value = value;
    widget.appendChild(answer);
  }, token);
}

/**
 * Fill in the registration form, without pressing the button.
 */
export async function fillRegistrationForm(page: Page, email: string): Promise<void> {
  await page.locator('#user_email').fill(email);
  await page.locator('#user_password').fill(PASSWORD);

  // WPUM adds this when the site has a privacy page.
  const privacy = page.getByLabel(/privacy policy/);
  if (await privacy.count()) {
    await privacy.check();
  }
}

/**
 * Fill in the login form, without pressing the button.
 */
export async function fillLoginForm(page: Page, login: string, password = PASSWORD): Promise<void> {
  await page.locator('#username').fill(login);
  await page.locator('#password').fill(password);
}

/**
 * Whether the browser holds a login cookie.
 */
export async function isLoggedIn(page: Page): Promise<boolean> {
  const cookies = await page.context().cookies();

  return cookies.some((cookie) => cookie.name.startsWith('wordpress_logged_in_'));
}

/**
 * Log in through wp-login.php. The cookies land in the page's browser context.
 */
export async function loginAs(page: Page, login: string, password = PASSWORD): Promise<void> {
  const response = await page.request.post('/wp-login.php', {
    form: {
      log: login,
      pwd: password,
      'wp-submit': 'Log In',
      redirect_to: '/',
      testcookie: '1',
    },
  });
  await response.dispose();
}

export async function wpAdminLogin(page: Page): Promise<void> {
  await loginAs(page, 'admin', 'password');
}

/**
 * Ensure .htaccess exists for pretty permalinks.
 */
export function ensureHtaccess(): void {
  wpCli(
    `eval 'file_put_contents(ABSPATH . ".htaccess", "# BEGIN WordPress\\n<IfModule mod_rewrite.c>\\nRewriteEngine On\\nRewriteBase /\\nRewriteRule ^index\\\\.php$ - [L]\\nRewriteCond %{REQUEST_FILENAME} !-f\\nRewriteCond %{REQUEST_FILENAME} !-d\\nRewriteRule . /index.php [L]\\n</IfModule>\\n# END WordPress");'`
  );
}

/**
 * Set up the two WPUM pages the addon protects.
 */
export function setupWpumPages(): void {
  const pages = [
    { slug: 'wpum-login', title: 'Login', shortcode: '[wpum_login_form]', option: 'login_page' },
    { slug: 'wpum-register', title: 'Register', shortcode: '[wpum_register]', option: 'registration_page' },
  ];

  for (const p of pages) {
    let pageId = wpCli(`post list --post_type=page --name="${p.slug}" --post_status=publish --field=ID`);

    if (!pageId?.match(/^\d+$/)) {
      pageId = wpCli(
        `post create --post_type=page --post_title="${p.title}" --post_name="${p.slug}" --post_status=publish --post_content='${p.shortcode}' --porcelain`
      );
    }

    // Stored as a string, the way WPUM's settings panel saves page IDs.
    wpCli(`eval 'wpum_update_option("${p.option}", array("${pageId.trim()}"));'`);
  }
}

export const test = base.extend<{
  loginPage: string;
  registerPage: string;
}>({
  loginPage: '/wpum-login/',
  registerPage: '/wpum-register/',

  // Browsers never reach Google: its script is answered here, empty, and the tests read the
  // request to see that the page asked for it.
  page: async ({ page }, use) => {
    await page.route('https://www.google.com/recaptcha/**', (route) =>
      route.fulfill({ contentType: 'text/javascript', body: '' })
    );
    await use(page);
  },
});

export { expect };
