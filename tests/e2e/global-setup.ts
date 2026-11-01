import { wpCli, e2e, ensureHtaccess, setupWpumPages } from './fixtures';

async function globalSetup(): Promise<void> {
  console.log('\n[reCAPTCHA E2E] Running global setup...');

  wpCli('rewrite structure "/%postname%/"');
  ensureHtaccess();

  wpCli('plugin activate wp-user-manager');
  wpCli('plugin activate wpum-recaptcha');

  // Notices on screen would break redirects.
  wpCli('config set WP_DEBUG_DISPLAY false --raw');

  setupWpumPages();
  e2e('setup');

  wpCli('rewrite flush');

  console.log('[reCAPTCHA E2E] Global setup complete!\n');
}

export default globalSetup;
