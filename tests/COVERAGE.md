# Test coverage

Every feature the addon has, and which suite covers it. Update this file in the PR that
adds or changes a test. A feature with no test is a gap, not an oversight to hide.

- **WPUnit**: `tests/wpunit`, Codeception in wp-env, with WP User Manager from its default branch.
- **E2E**: `tests/e2e`, Playwright on the wp-env site (default theme, pretty permalinks). Setup is
  `wp wpumrc-e2e` in `tests/mu-plugins/wpumrc-e2e.php`, which also holds E2E's stand-in for Google.
- **Google is never contacted.** WPUnit answers every HTTP request in
  `AddonTestCase::fake_http()`, using `pre_http_request`. E2E does the same in the mu-plugin, and
  Playwright answers the browser's request for Google's script. The stand-ins behave like the real
  service: success only for the right token and the right secret.
- Status: ✅ covered, ❌ not covered, 🐞 `known-bug` test that fails until the bug is fixed.
  A blank cell means the other suite covers it.

## Known bugs

Tests for open bugs are tagged `known-bug` (WPUnit `@group known-bug`, Playwright
`@known-bug`), and CI skips them. The fix for a bug removes the tag from its tests. To run
them: `codecept run wpunit -g known-bug` or `npx playwright test --grep @known-bug`.

| Bug | Test |
|---|---|
| The text domain never loads: the `plugins_loaded` hook at priority 0 is added while priority 0 is already running | `SmokeTest::test_the_text_domain_loads_while_wordpress_boots` |
| The settings never link to Google's keys page: the URL goes to `sprintf()`, but the text has no `%s` | `SettingsTest::test_the_descriptions_link_to_google` |

Behaviour that's pinned by a passing test but may not be what's wanted:

- **Google being down stops both forms** with the usual error: nobody can register, and nobody
  can log in through WPUM's form.
- **A chosen form with no secret saved** turns every submission away with the library's own
  message, "No secret provided" (`LoginTest`, `RegistrationTest`).

Not tested anywhere:

- **The real widget and the real service.** Nothing here proves Google still answers the way the
  stand-ins do.
- **The missing vendor directory notice** (`vendor_failed_notice`), which deactivates the plugin.
- **The notice that deactivates the addon** when the Pro addon is active: only that it's hooked.
- **Theme overrides** of `forms/form-login.php` or `forms/form-registration.php`.

## Loading and release

| Feature | WPUnit | E2E |
|---|---|---|
| Starts on WPUM's `after_wpum_init`, defines its constants | ✅ | |
| Version is the same in the plugin header, `WPUMR_VERSION`, `readme.txt` Stable Tag and `package.json`, and is in the changelog | ✅ | |
| Requirements checks pass | ✅ | |
| Its own files and the `WPPost` request method load | ✅ | |
| The `.pot` file ships with the addon | ✅ | |
| Stands aside when the Pro addon is active, and says so | ✅ | |
| Text domain loads | 🐞 | |

## The widget

| Feature | WPUnit | E2E |
|---|---|---|
| Shows on the login and registration forms, ahead of the button | ✅ | ✅ |
| Carries the site key, escaped | ✅ | ✅ |
| Loads Google's script, in English by default | ✅ | ✅ |
| `wpum_recaptcha_language` changes the language, and is escaped | ✅ | |
| A site that chose one form gets it on that form only | | ✅ |
| A site that chose nothing gets it on neither | | ✅ |
| The secret key is never on the page | ✅ | |
| Shown again, with the error, on a refused form | ✅ | ✅ |

## Verifying with Google

| Feature | WPUnit | E2E |
|---|---|---|
| Posts the token, the secret and the visitor's IP to siteverify, once, and contacts nothing else | ✅ | |
| Uses `WPPost`, a form post through `wp_remote_post()` | ✅ | |
| Each form that wasn't chosen, and every other WPUM form, is left alone | ✅ | ✅ |
| A good token hands the validation result back unchanged | ✅ | |
| A good token doesn't wipe out an error from an earlier filter | ✅ | |
| A refusal is a `WP_Error` with the code `recaptcha-error` and the addon's message | ✅ | |

## The registration form

| Feature | WPUnit | E2E |
|---|---|---|
| A good token registers the member and moves on | ✅ | ✅ |
| A token Google rejects: no account, the error shown | ✅ | ✅ |
| An unticked box (an empty token): no account, and Google isn't asked | ✅ | ✅ |
| No response at all: no account, and Google isn't asked | ✅ | |
| A token posted as an array: no account | ✅ | |
| The wrong secret: no account | ✅ | |
| No secret saved: no account | ✅ | |
| Google unreachable, or an empty, garbled or verdict-free reply: no account | ✅ | ✅ (unreachable) |
| A site that didn't choose this form registers with no token | ✅ | ✅ |

## The login form

| Feature | WPUnit | E2E |
|---|---|---|
| A good token and the right password log the member in | ✅ | ✅ |
| A token Google rejects keeps the right password out | ✅ | ✅ |
| An unticked box (an empty token) keeps the right password out, and Google isn't asked | ✅ | ✅ |
| No response at all keeps the right password out, and Google isn't asked | ✅ | |
| A rejected token means the password is never checked | ✅ | |
| A good token doesn't excuse a wrong password | ✅ | ✅ |
| The wrong secret keeps the right password out | ✅ | |
| No secret saved keeps the right password out | ✅ | |
| Google unreachable, or an empty, garbled or verdict-free reply, keeps the right password out | ✅ | ✅ (unreachable) |
| A site that didn't choose this form logs in with no token | ✅ | |

## Settings

| Feature | WPUnit | E2E |
|---|---|---|
| Site key, secret key and location are on the Misc tab | ✅ | ✅ |
| The keys are text fields | ✅ | |
| Location is a multiselect of the two forms | ✅ | |
| Other sections' settings are kept | ✅ | |
| The descriptions link to Google | 🐞 | |
