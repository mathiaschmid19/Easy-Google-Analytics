# Easy Google Analytics v2.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add role-based tracking exclusion, convert `EGA_Tracking_Output`'s inline `<script>` echoes to `wp_enqueue_script`/`wp_localize_script`, fix `uninstall.php`'s missing trailing newline and stale boilerplate header, centralize the version number behind a single `EGA_VERSION` constant, and add WordPress.org-format `readme.txt` plus missing plugin header metadata.

**Architecture:** A new `EGA_Tracking_Output::is_user_excluded()` check is added as an explicit second condition at the three existing output-gating call sites (not folded into `is_configured()`, to avoid silently changing an interface three classes already depend on). `output_consent_defaults()` and `output_tracking_scripts()` are converted from `echo`-based inline script blocks to `wp_enqueue_script`/`wp_localize_script`/`wp_add_inline_script`, matching the pattern `EGA_Consent` and `EGA_Event_Tracking` already use, with the two `wp_head` hooks kept at their existing priorities (1 and 10).

**Tech Stack:** PHP (WordPress Settings API, Roles API, Script API), vanilla JS, no build step, no automated test framework (manual verification against local WP, per spec — matches v2.0's approach).

**Spec:** `docs/superpowers/specs/2026-08-31-role-exclusion-enqueue-metadata-design.md`

## Global Constraints

- New option `for_you_google_analytics_excluded_roles` is an array of role slugs, sanitized against `array_keys(wp_roles()->get_names())` at save time — unknown/stale slugs are dropped.
- `EGA_Tracking_Output::is_user_excluded()` returns `false` for logged-out visitors unconditionally (no role to match).
- Exclusion is checked as an explicit second condition at each of the three existing gate sites (`EGA_Tracking_Output`'s two `wp_head` methods, `EGA_Consent::banner_enabled()`, `EGA_Event_Tracking::enqueue()`) — `is_configured()` itself is never renamed or have its meaning changed.
- The two `wp_head` hooks in `EGA_Tracking_Output::init()` keep their existing priorities: consent defaults at `1`, tracking scripts at `10`.
- All new/modified script enqueues use the `EGA_VERSION` constant (value `'2.1'`, a bump from `'2.0'` used by v2.0's assets, since this plan is a plugin-version bump) instead of a literal version string — defined once in `Easy Google Analytics.php`, consumed everywhere else. The two external-URL handles (`ega-gtm-container`, `ega-gtag-js`) are the sole exception, passing `null` per their existing documented rationale.
- Text domain for all user-facing strings: `for-you-google-analytics`.
- `uninstall.php` must delete the new option and end with a trailing newline byte.
- No new PHP dependencies, no Composer, no build step for JS.
- Boolean-like options continue using the `'1'`/`''` string convention (unchanged from v2.0) — the new `excluded_roles` option is the first array-valued option in this plugin, sanitized as an array, not the checkbox convention.

---

### Task 1: Role-exclusion settings field

**Files:**
- Modify: `includes/class-settings.php`
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: nothing from other tasks (first task)
- Produces: option `for_you_google_analytics_excluded_roles` (array of role slugs), readable via `get_option('for_you_google_analytics_excluded_roles', array())` — Task 2 (`EGA_Tracking_Output::is_user_excluded()`) reads this exact option name and shape.

- [ ] **Step 1: Add the sanitize callback and register the setting**

In `includes/class-settings.php`, add a new method after `sanitize_checkbox()`:

```php
    public static function sanitize_excluded_roles($input) {
        if (!is_array($input)) {
            return array();
        }

        $valid_roles = array_keys(wp_roles()->get_names());
        $filtered = array_intersect($input, $valid_roles);

        return array_values($filtered);
    }
```

In `register_settings()`, add after the `$checkbox_options` foreach loop:

```php
        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_excluded_roles',
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_excluded_roles'),
                'default'           => array(),
            )
        );
```

- [ ] **Step 2: Add the settings field and its callback**

In `register_fields()`, add after the event-tracking field registration:

```php
        add_settings_field(
            'for_you_google_analytics_excluded_roles',
            'Exclude Roles',
            array(__CLASS__, 'excluded_roles_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );
```

Add the field callback after `event_tracking_field()`:

```php
    public static function excluded_roles_field() {
        $excluded = get_option('for_you_google_analytics_excluded_roles', array());
        $roles = get_editable_roles();

        foreach ($roles as $role_slug => $role_info) {
            $checked = in_array($role_slug, $excluded, true);
            echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="for_you_google_analytics_excluded_roles[]" value="' . esc_attr($role_slug) . '" ' . checked(true, $checked, false) . ' /> ';
            echo esc_html(translate_user_role($role_info['name'])) . '</label>';
        }

        echo '<p class="description">' . esc_html__('Logged-in users with any of the checked roles will not be tracked, and will not see the consent banner or contribute to event-tracking data.', 'for-you-google-analytics') . '</p>';
    }
```

Note: `get_editable_roles()` is defined in `wp-admin/includes/user.php`, which is always loaded on admin-side requests where this field callback runs (it is only ever invoked from within `do_settings_sections()` on the plugin's own admin settings page). `translate_user_role()` is a WordPress core function for translating built-in role display names.

- [ ] **Step 3: Add cleanup to uninstall.php**

Add after the existing `delete_option` lines:

```php
delete_option( 'for_you_google_analytics_excluded_roles' );
```

- [ ] **Step 4: Manually verify**

Read the final `includes/class-settings.php` and confirm: `sanitize_excluded_roles` returns `array()` for non-array input (handles the case where WordPress omits the field entirely from `$_POST` when no checkboxes are checked, which arrives as the field being absent — `register_setting`'s sanitize callback still runs with `null`/the registered default in that case, so the `!is_array($input)` guard covers it); `array_intersect` against `wp_roles()->get_names()` keys correctly drops any slug that isn't a real registered role; the checkbox `name` attribute uses PHP array syntax `[]` so multiple checked boxes all submit under the same key as an array.

- [ ] **Step 5: Commit**

```bash
git add includes/class-settings.php uninstall.php
git commit -m "Add role-exclusion settings field"
```

---

### Task 2: `is_user_excluded()` check and wiring into the three gate sites

**Files:**
- Modify: `includes/class-tracking-output.php`
- Modify: `includes/class-consent.php`
- Modify: `includes/class-event-tracking.php`

**Interfaces:**
- Consumes: option `for_you_google_analytics_excluded_roles` (Task 1)
- Produces: `EGA_Tracking_Output::is_user_excluded()` (public static, no args, returns bool) — this is the shared check Task 3's enqueue-refactored methods also call (Task 3 modifies the same two methods this task touches, so this task's edits land first).

- [ ] **Step 1: Add `is_user_excluded()` to `EGA_Tracking_Output`**

In `includes/class-tracking-output.php`, add after `is_configured()`:

```php
    public static function is_user_excluded() {
        if (!is_user_logged_in()) {
            return false;
        }

        $excluded = get_option('for_you_google_analytics_excluded_roles', array());
        if (empty($excluded)) {
            return false;
        }

        $user = wp_get_current_user();
        return (bool) array_intersect($excluded, (array) $user->roles);
    }
```

- [ ] **Step 2: Wire into `output_consent_defaults()` and `output_tracking_scripts()`**

Change both methods' opening guard from:

```php
        if (!self::is_configured()) {
            return;
        }
```

to:

```php
        if (!self::is_configured() || self::is_user_excluded()) {
            return;
        }
```

(Note: `output_tracking_scripts()` currently has no `is_configured()` guard at its top — it checks `!empty($gtm_id)` and `!empty($ga4_code)` separately inside two `if` blocks instead. Add a single early-return guard at the top of the method instead: `if (self::is_user_excluded()) { return; }` — this is sufficient since the existing per-ID empty-checks already handle the "nothing configured" case, and exclusion should short-circuit before either block runs.)

- [ ] **Step 3: Wire into `EGA_Consent::banner_enabled()`**

In `includes/class-consent.php`, change:

```php
    public static function banner_enabled() {
        return EGA_Tracking_Output::is_configured()
            && get_option('for_you_google_analytics_consent_banner_enabled') === '1';
    }
```

to:

```php
    public static function banner_enabled() {
        return EGA_Tracking_Output::is_configured()
            && !EGA_Tracking_Output::is_user_excluded()
            && get_option('for_you_google_analytics_consent_banner_enabled') === '1';
    }
```

- [ ] **Step 4: Wire into `EGA_Event_Tracking::enqueue()`**

In `includes/class-event-tracking.php`, change:

```php
        if (!EGA_Tracking_Output::is_configured() || !self::any_module_enabled()) {
            return;
        }
```

to:

```php
        if (!EGA_Tracking_Output::is_configured() || !self::any_module_enabled() || EGA_Tracking_Output::is_user_excluded()) {
            return;
        }
```

- [ ] **Step 5: Manually verify**

Confirm all three call sites now check `is_user_excluded()` and that none of them changed `is_configured()` itself — grep the diff for `is_configured` and confirm zero hunks touch its own definition (only call sites gained a sibling condition). Confirm `is_user_excluded()`'s early-return for logged-out visitors (`!is_user_logged_in()`) means the added `get_option`/`wp_get_current_user()` calls never run for anonymous traffic — the common case incurs no new option read.

- [ ] **Step 6: Commit**

```bash
git add includes/class-tracking-output.php includes/class-consent.php includes/class-event-tracking.php
git commit -m "Add role-exclusion check to all three tracking-output gate sites"
```

---

### Task 3: Convert `output_consent_defaults()` to enqueue+localize

**Files:**
- Create: `assets/consent-defaults.js`
- Modify: `includes/class-tracking-output.php`

**Interfaces:**
- Consumes: nothing new (uses the same `is_configured()`/`is_user_excluded()` guard from Task 2)
- Produces: enqueued script handle `ega-consent-defaults`, localized JS global `easyGA4ConsentDefaults` (object with keys `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, `wait_for_update`) — this is a self-contained script; no later task consumes this handle or global.

- [ ] **Step 1: Create `assets/consent-defaults.js`**

```js
(function () {
    'use strict';

    if (typeof easyGA4ConsentDefaults === 'undefined') {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('consent', 'default', easyGA4ConsentDefaults);
})();
```

- [ ] **Step 2: Replace `output_consent_defaults()`'s body**

In `includes/class-tracking-output.php`, replace the entire method:

```php
    public static function output_consent_defaults() {
        if (!self::is_configured() || self::is_user_excluded()) {
            return;
        }

        wp_enqueue_script(
            'ega-consent-defaults',
            EGA_PLUGIN_URL . 'assets/consent-defaults.js',
            array(),
            EGA_VERSION,
            false
        );

        wp_localize_script('ega-consent-defaults', 'easyGA4ConsentDefaults', array(
            'ad_storage'         => 'denied',
            'analytics_storage'  => 'denied',
            'ad_user_data'       => 'denied',
            'ad_personalization' => 'denied',
            'wait_for_update'    => 500,
        ));
    }
```

Note the `false` for the `$in_footer` parameter — this must stay in the `<head>` (not deferred to the footer) since Consent Mode defaults must be established before any tag fires, and this method is hooked to `wp_head` at priority 1, before the GA4/GTM loader at priority 10.

- [ ] **Step 3: Manually verify**

Confirm the method is still hooked via `add_action('wp_head', array(__CLASS__, 'output_consent_defaults'), 1);` in `init()` (unchanged — this task only touches the method body, not its hook registration). Confirm `wp_enqueue_script`'s `$in_footer` argument is `false`, matching the requirement that this script must execute in `wp_head`, not deferred. Confirm the JS file's early-return guard (`typeof easyGA4ConsentDefaults === 'undefined'`) matches the defensive pattern already used in `assets/tracking.js` and `assets/consent-banner.js` for their own localized-config checks.

- [ ] **Step 4: Commit**

```bash
git add assets/consent-defaults.js includes/class-tracking-output.php
git commit -m "Convert consent-defaults output to wp_enqueue_script"
```

---

### Task 4: Convert `output_tracking_scripts()` to enqueue+localize (GA4 + GTM)

**Files:**
- Create: `assets/gtag-loader.js`
- Modify: `includes/class-tracking-output.php`

**Interfaces:**
- Consumes: nothing new (uses the same guard from Task 2)
- Produces: enqueued script handles `ega-gtm-container` (GTM external script), `ega-gtag-js` (GA4 external script), `ega-gtag-loader` (this task's new bootstrap file, dependent on `ega-gtag-js`) — self-contained, no later task consumes these handles.

- [ ] **Step 1: Create `assets/gtag-loader.js`**

```js
(function () {
    'use strict';

    if (typeof easyGA4Loader === 'undefined') {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', easyGA4Loader.ga4Code);
})();
```

- [ ] **Step 2: Replace `output_tracking_scripts()`'s body**

In `includes/class-tracking-output.php`, replace the entire method:

```php
    public static function output_tracking_scripts() {
        if (self::is_user_excluded()) {
            return;
        }

        $ga4_code = get_option('for_you_google_analytics_ga4_code');
        $gtm_id   = get_option('for_you_google_analytics_gtm_id');

        if (!empty($gtm_id)) {
            wp_enqueue_script(
                'ega-gtm-container',
                'https://www.googletagmanager.com/gtm.js?id=' . rawurlencode($gtm_id),
                array(),
                null,
                false
            );
            wp_script_add_data('ega-gtm-container', 'async', true);

            $gtm_bootstrap = "window.dataLayer = window.dataLayer || []; window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});";
            wp_add_inline_script('ega-gtm-container', $gtm_bootstrap, 'before');
        }

        if (!empty($ga4_code)) {
            wp_enqueue_script(
                'ega-gtag-js',
                'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($ga4_code),
                array(),
                null,
                false
            );
            wp_script_add_data('ega-gtag-js', 'async', true);

            wp_enqueue_script(
                'ega-gtag-loader',
                EGA_PLUGIN_URL . 'assets/gtag-loader.js',
                array('ega-gtag-js'),
                EGA_VERSION,
                false
            );
            wp_localize_script('ega-gtag-loader', 'easyGA4Loader', array(
                'ga4Code' => $ga4_code,
            ));
        }
    }
```

Notes on this conversion:
- `rawurlencode()` on `$ga4_code`/`$gtm_id` when building the external URL replaces the old `esc_attr()`/`esc_js()` calls — those were needed because the old code interpolated the value directly into HTML/JS source text; here the value goes into a URL query string via PHP string concatenation before WordPress's script-tag renderer HTML-escapes the whole `src` attribute itself, so URL-encoding is the correct treatment for this context, and `wp_localize_script`'s own `wp_json_encode()`-based escaping handles the `easyGA4Loader.ga4Code` value safely when it reaches `gtag-loader.js`. The `^G-[A-Z0-9]+$`/`^GTM-[A-Z0-9]+$` sanitize regexes (unchanged, still enforced at save time in `class-settings.php`) mean these values can never contain characters `rawurlencode` would need to meaningfully alter anyway — this is defense in depth, not a load-bearing escape.
- `null` for the `$ver` parameter on the two external-URL handles (GTM, GA4) — passing an explicit version string to an external Google-hosted URL would be misleading (WordPress would append `?ver=2.1` to a URL that already has its own `?id=...` query string, and Google controls that endpoint's actual versioning, not this plugin). `null` (WordPress's "no version" signal) matches how core itself registers other external scripts it doesn't version.
- `wp_script_add_data($handle, 'async', true)` is the documented, supported WordPress Script API method for adding the `async` attribute to an enqueued `<script>` tag, replacing the old manually-authored `<script async src="...">`.
- `wp_add_inline_script($handle, $data, 'before')` attaches the tiny GTM bootstrap IIFE immediately before the `ega-gtm-container` script tag — this is the officially-documented WordPress pattern for a small inline script that must run alongside an enqueued one; it is not a regression to raw `echo`, since it is registered through the Script API and receives the same dependency-ordering and escaping treatment as any other enqueued asset.
- The `ega-gtag-loader` handle depends on `ega-gtag-js` (passed in its dependency array), which guarantees WordPress orders the `<script>` tags so `gtag-loader.js` (which calls `gtag(...)`, relying on the external gtag.js having already defined `window.dataLayer`/pushed itself) is emitted after the external gtag.js tag.

- [ ] **Step 3: Manually verify**

Confirm the method is still hooked via `add_action('wp_head', array(__CLASS__, 'output_tracking_scripts'), 10);` in `init()` (unchanged). Confirm the GTM branch and GA4 branch each preserve their original `!empty(...)` guards (behavior parity with v2.0: nothing enqueues if the corresponding ID is empty). Confirm `wp_localize_script` is called only after `wp_enqueue_script('ega-gtag-loader', ...)` (localize must follow the enqueue of the handle it's attaching to, matching the pattern in `EGA_Event_Tracking::enqueue()`). Confirm no remaining `echo`/`?>`/`<?php` inline-HTML blocks remain anywhere in this method — the whole point of this task is zero raw script-tag printing left in `output_tracking_scripts()`.

- [ ] **Step 4: Commit**

```bash
git add assets/gtag-loader.js includes/class-tracking-output.php
git commit -m "Convert GA4/GTM tracking-script output to wp_enqueue_script"
```

---

### Task 5: `uninstall.php` cleanup, `EGA_VERSION` constant, and header metadata

**Files:**
- Modify: `uninstall.php`
- Modify: `Easy Google Analytics.php`
- Modify: `includes/class-settings.php`
- Modify: `includes/class-consent.php`
- Modify: `includes/class-event-tracking.php`

**Interfaces:**
- Consumes: nothing
- Produces: `EGA_VERSION` constant (defined in `Easy Google Analytics.php`, alongside `EGA_PLUGIN_DIR`/`EGA_PLUGIN_URL`) — consumed by every `wp_enqueue_script`/`wp_enqueue_style` call in the plugin, including Task 3/4's new calls in `class-tracking-output.php` (those two tasks already reference `EGA_VERSION` directly; this task must land before or alongside them so the constant exists, or after — PHP constants are resolved at runtime, not edit time, so file-edit order across tasks does not matter here, only that this task's definition and Task 3/4's usages both land before final verification). Nothing consumed by later tasks (Task 6 creates `readme.txt` independently, referencing the same version number this task sets in the plugin header, but does not read this file programmatically).

- [ ] **Step 1: Define `EGA_VERSION` in `Easy Google Analytics.php`**

Change:

```php
define('EGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGA_PLUGIN_URL', plugin_dir_url(__FILE__));
```

to:

```php
define('EGA_VERSION', '2.1');
define('EGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGA_PLUGIN_URL', plugin_dir_url(__FILE__));
```

- [ ] **Step 2: Bump plugin version and add missing headers**

In `Easy Google Analytics.php`, change the header block from:

```php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 2.0
    Text Domain: for-you-google-analytics
 */
```

to:

```php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 2.1
    Requires at least: 5.0
    Requires PHP: 7.4
    License: GPLv2 or later
    License URI: https://www.gnu.org/licenses/gpl-2.0.html
    Text Domain: for-you-google-analytics
 */
```

Note: WordPress's plugin-header parser reads this comment block as plain text (it does not execute PHP to get the version), so `Version: 2.1` here is a separate literal from the `EGA_VERSION` PHP constant defined in Step 1 — both must say `2.1`, but nothing enforces that automatically. This is an accepted, documented duplication (the plugin-header format cannot reference a PHP constant), not an oversight; every other version literal in the codebase (enqueues, admin UI text) reads `EGA_VERSION` instead, which collapses the sync problem from "many places" to "these two."

- [ ] **Step 3: Replace remaining literal version strings with `EGA_VERSION`**

In `includes/class-settings.php`, `enqueue_admin_assets()`: replace both `'2.1'` literals (the `admin.css` and `admin.js` enqueues) with `EGA_VERSION`.

In `includes/class-consent.php`, `enqueue()`: replace both `'2.0'` literals (the `consent-banner.css` and `consent-banner.js` enqueues) with `EGA_VERSION`.

In `includes/class-event-tracking.php`, `enqueue()`: replace the `'2.0'` literal (the `tracking.js` enqueue) with `EGA_VERSION`.

In `includes/class-settings.php`, `render_page()`: change the hardcoded version badge from:

```php
<span class="ega-version-tag">Version 2.0</span>
```

to:

```php
<span class="ega-version-tag"><?php echo esc_html(sprintf(__('Version %s', 'for-you-google-analytics'), EGA_VERSION)); ?></span>
```

- [ ] **Step 4: Verify/fix `uninstall.php`'s trailing newline and header comment**

Read the current `uninstall.php` in full. Replace the leftover WordPress-Plugin-Boilerplate header comment:

```php
 /* For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       #
 * @since      1.0.0
 *
 * @package    Plugin_Name
 */
```

with:

```php
/**
 * Fired when the plugin is uninstalled. Removes all options this plugin
 * has ever stored, including options from earlier plugin versions.
 */
```

If the file's last line (`delete_option( 'for_you_google_analytics_excluded_roles' );` after Task 1's edit) is not followed by a trailing newline character, add one. Concretely: ensure the file ends with `\n` after the final `delete_option(...)` statement, not with the statement as the literal last byte of the file.

- [ ] **Step 5: Manually verify**

Read the final `uninstall.php` and confirm it ends with a newline byte (most editors/Read tools show this as the file simply not having a warning about a missing final newline — if using a shell, `tail -c 1 uninstall.php | xxd` would show `0a`, the newline byte; state which method was used to confirm) and that the boilerplate comment is gone with no dangling reference to `Plugin_Name`/`tommcfarlin`. Read the final plugin header and confirm all four new lines are present with exact WordPress-recognized header keys (`Requires at least`, `Requires PHP`, `License`, `License URI`). Grep the whole plugin directory for the literal strings `'2.0'` and `'2.1'` inside `wp_enqueue_script`/`wp_enqueue_style` calls specifically — confirm zero remain outside of the `EGA_VERSION` definition itself and the two external-URL handles' documented `null`-version exception (Task 4). Confirm the admin settings page (`render_page()`) no longer has a hardcoded `"Version 2.0"` string anywhere.

- [ ] **Step 6: Commit**

```bash
git add uninstall.php "Easy Google Analytics.php" includes/class-settings.php includes/class-consent.php includes/class-event-tracking.php
git commit -m "Centralize version behind EGA_VERSION constant; fix uninstall.php header and trailing newline; add license/PHP headers"
```

---

### Task 6: `readme.txt`

**Files:**
- Create: `readme.txt`

**Interfaces:**
- Consumes: version number `2.1` (set in Task 5's plugin header — this task's `Stable tag` must match)
- Produces: nothing consumed by other tasks (last task)

- [ ] **Step 1: Create `readme.txt`**

```
=== Easy Google Analytics ===
Contributors: aminouhannou
Tags: google analytics, ga4, gtm, consent mode, analytics
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Google Analytics (GA4) and Google Tag Manager tracking to your site, with Google Consent Mode v2, a built-in consent banner, and optional event tracking.

== Description ==

Easy Google Analytics adds Google Analytics (GA4) tracking to your WordPress site's `<head>`, with support for:

* Google Analytics 4 (GA4) measurement ID tracking
* Optional Google Tag Manager (GTM) container support alongside or instead of GA4
* Google Consent Mode v2, with cookieless/anonymized tracking by default until consent is granted
* Automatic detection of the Complianz and Cookiebot consent management plugins, deferring to their consent signal
* A built-in fallback consent banner (accept/reject) when no supported consent plugin is active
* Optional event tracking: outbound link clicks, file download clicks, scroll depth, and form submissions
* Role-based tracking exclusion, so administrators or other roles can be excluded from analytics data

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/easy-google-analytics` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Settings > Google Analytics (GA4) to enter your GA4 measurement ID and/or GTM container ID, and configure consent and event-tracking options.

== Changelog ==

= 2.1 =
* Added role-based tracking exclusion
* Converted inline tracking scripts to wp_enqueue_script/wp_localize_script
* Added readme.txt and plugin header metadata (Requires PHP, License)

= 2.0 =
* Added Google Consent Mode v2 with Complianz/Cookiebot detection and built-in fallback banner
* Added Google Tag Manager support
* Added event tracking (outbound links, downloads, scroll depth, forms)
```

- [ ] **Step 2: Manually verify**

Confirm `Stable tag: 2.1` matches the `Version: 2.1` set in Task 5's plugin header exactly (a mismatch between these two is a common, easily-overlooked WordPress.org submission error). Confirm the changelog does not fabricate any pre-2.0 version entries — this plugin's own commit/version history only supports starting the changelog at 2.0, per the spec's explicit instruction not to invent 1.x release notes.

- [ ] **Step 3: Commit**

```bash
git add readme.txt
git commit -m "Add WordPress.org-format readme.txt"
```

---

## Self-Review Notes

**Spec coverage:** Role-exclusion settings field (Task 1) ✓, `is_user_excluded()` + wiring into all three gate sites (Task 2) ✓, consent-defaults enqueue conversion (Task 3) ✓, GA4/GTM tracking-scripts enqueue conversion (Task 4) ✓, `uninstall.php` trailing newline + boilerplate header cleanup + new option cleanup (Tasks 1 & 5) ✓, `EGA_VERSION` constant centralization across all enqueue call sites and the admin UI version badge (Task 5) ✓, plugin header metadata (Task 5) ✓, `readme.txt` (Task 6) ✓. File structure matches the spec exactly: `assets/consent-defaults.js` (Task 3), `assets/gtag-loader.js` (Task 4), `readme.txt` (Task 6), and the modified files (`class-settings.php`, `class-tracking-output.php`, `class-consent.php`, `class-event-tracking.php`, `uninstall.php`, `Easy Google Analytics.php`).

**Placeholder scan:** No TBD/TODO markers. Every step has complete, runnable code or an explicit verification procedure (e.g. Task 5 Step 5's `tail -c 1 | xxd` check for the trailing-newline byte, and its grep for stray `'2.0'`/`'2.1'` literals, rather than a vague "make sure it's fixed").

**Type/naming consistency:** `is_user_excluded()` is defined once (Task 2, on `EGA_Tracking_Output`) and called identically (`EGA_Tracking_Output::is_user_excluded()` or `self::is_user_excluded()` from within the same class) at all three consuming sites across Tasks 2-4. Option name `for_you_google_analytics_excluded_roles` matches character-for-character between Task 1's `register_setting`/field-rendering and Task 2's `get_option` call. Script handles introduced in Tasks 3-4 (`ega-consent-defaults`, `ega-gtm-container`, `ega-gtag-js`, `ega-gtag-loader`) are each used exactly once, with no naming collisions against v2.0's existing handles (`ega-consent-banner`, `ega-tracking`). `EGA_VERSION` (value `'2.1'`) is defined exactly once (Task 5, `Easy Google Analytics.php`) and referenced by name — never re-literalized — at every enqueue call site across Tasks 3, 4, and 5, plus the admin UI version badge; the plugin-header `Version: 2.1` comment and `readme.txt`'s `Stable tag: 2.1` remain separate literals by necessity (WordPress's header/readme parsers read plain text, not PHP), documented as the one accepted exception in Task 5 Step 2.
