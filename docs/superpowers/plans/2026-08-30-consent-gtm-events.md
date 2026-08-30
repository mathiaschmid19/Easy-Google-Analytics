# Easy Google Analytics v2.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Easy Google Analytics from a static gtag.js snippet echoer into a plugin with real value: Google Consent Mode v2 (with Complianz/Cookiebot detection and a fallback banner), optional GTM support alongside GA4, and four independently-togglable GA4 event-tracking helpers.

**Architecture:** Split the current single-file plugin into a thin loader plus `includes/` classes (settings, consent, tracking-output, event-tracking) and `assets/` (tracking.js, consent-banner.js/css). Consent state is the gate everything else depends on: Consent Mode defaults to denied, tracking scripts always load (per Google's CMv2 pattern), and both the built-in banner and the event-tracking JS check consent before granting/firing.

**Tech Stack:** PHP (WordPress Settings API, `wp_enqueue_script`/`wp_localize_script`), vanilla JS (no build step, no framework — matches plugin's existing zero-dependency style), no automated test framework (manual verification against local WP, per spec).

**Spec:** `docs/superpowers/specs/2026-08-30-consent-gtm-events-design.md`

## Global Constraints

- GA4 ID format: `^G-[A-Z0-9]+$` (existing, unchanged).
- GTM ID format: `^GTM-[A-Z0-9]+$`.
- All new options use the `for_you_google_analytics_` prefix, matching existing naming.
- Boolean options are stored as `'1'` (checked) or `''` (unchecked) — WordPress checkbox convention, never PHP `true`/`false` (options API stores scalars).
- Text domain for all user-facing strings: `for-you-google-analytics` (already declared in plugin header).
- No new PHP dependencies, no Composer, no build step for JS — plain enqueued files only.
- `uninstall.php` must delete every option this plan introduces, no exceptions.
- Consent Mode default block must run on `wp_head` at priority `1`, strictly before the gtag.js/GTM loader (priority `10`, existing).
- No GA4/GTM event or config call may fire from `tracking.js` or `consent-banner.js` while `analytics_storage` is `denied`.

---

### Task 1: Split into includes/ structure, migrate existing settings code

**Files:**
- Create: `includes/class-settings.php`
- Modify: `Easy Google Analytics.php` (strip to thin loader)
- Modify: `uninstall.php` (no option changes yet, just confirm it still works after the split)

**Interfaces:**
- Consumes: nothing (first task)
- Produces: `EGA_Settings` class with public static method `EGA_Settings::init()` that registers all existing admin hooks (menu, settings, settings fields). Later tasks call `EGA_Settings::init()` from the loader alongside their own `init()` calls, and later tasks add fields to the existing `for_you_google_analytics_section` section via the same `add_settings_field` pattern.

This task is a pure refactor — no behavior change. It establishes the file structure the rest of the plan builds on.

- [ ] **Step 1: Create `includes/class-settings.php` with the migrated settings code**

```php
<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Settings {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_init', array(__CLASS__, 'register_fields'));
    }

    public static function menu() {
        add_options_page(
            'Google Analytics Settings (GA4)',
            'Google Analytics (GA4)',
            'manage_options',
            'for_you_google_analytics',
            array(__CLASS__, 'render_page')
        );
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h2>Google Analytics (GA4) Settings</h2>
            <?php settings_errors('for_you_google_analytics_ga4_code'); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('for_you_google_analytics_options');
                do_settings_sections('for_you_google_analytics');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings() {
        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_ga4_code',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_ga4_code'),
                'default'           => '',
            )
        );
    }

    public static function sanitize_ga4_code($input) {
        $input = strtoupper(sanitize_text_field($input));

        if (empty($input)) {
            return '';
        }

        if (!preg_match('/^G-[A-Z0-9]+$/', $input)) {
            add_settings_error(
                'for_you_google_analytics_ga4_code',
                'invalid_ga4_code',
                __('Invalid GA4 tracking code. It must be in the format G-XXXXXXXXXX.', 'for-you-google-analytics')
            );
            return get_option('for_you_google_analytics_ga4_code', '');
        }

        return $input;
    }

    public static function register_fields() {
        add_settings_section(
            'for_you_google_analytics_section',
            'Google Analytics (GA4) Tracking Code',
            array(__CLASS__, 'section_callback'),
            'for_you_google_analytics'
        );

        add_settings_field(
            'for_you_google_analytics_ga4_code',
            'GA4 Tracking Code',
            array(__CLASS__, 'ga4_code_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );
    }

    public static function section_callback() {
        echo '<p>Enter your Google Analytics (GA4) tracking code below:</p>';
    }

    public static function ga4_code_field() {
        $ga4_code = get_option('for_you_google_analytics_ga4_code');
        echo '<input type="text" name="for_you_google_analytics_ga4_code" value="' . esc_attr($ga4_code) . '" placeholder="G-XXXXXXXXXX" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Format: G-XXXXXXXXXX', 'for-you-google-analytics') . '</p>';
    }
}
```

- [ ] **Step 2: Replace `Easy Google Analytics.php` with the thin loader**

```php
<?php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 2.0
    Text Domain: for-you-google-analytics
 */

if (!defined('WPINC')) {
    die;
}

define('EGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EGA_PLUGIN_DIR . 'includes/class-settings.php';

EGA_Settings::init();
```

- [ ] **Step 3: Manually verify no regressions**

There is no automated test suite for this plugin (confirmed in spec). Verify by reading through the new `class-settings.php` line by line against the original file's logic (already done above — the migration is copy-paste plus `self::`/`array(__CLASS__, ...)` conversion, no logic changes). Confirm every function from the original file has a corresponding method call site.

- [ ] **Step 4: Commit**

```bash
git add "Easy Google Analytics.php" includes/class-settings.php
git commit -m "Split settings code into includes/class-settings.php"
```

---

### Task 2: Add GTM Container ID field and sanitization

**Files:**
- Modify: `includes/class-settings.php`
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: `EGA_Settings` class from Task 1 (adds a field/method to it)
- Produces: option `for_you_google_analytics_gtm_id`, readable via `get_option('for_you_google_analytics_gtm_id')` — Task 4 (tracking output) reads this.

- [ ] **Step 1: Add GTM sanitize callback and settings registration**

In `includes/class-settings.php`, modify `register_settings()`:

```php
    public static function register_settings() {
        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_ga4_code',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_ga4_code'),
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_gtm_id',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_gtm_id'),
                'default'           => '',
            )
        );
    }
```

Add a new method after `sanitize_ga4_code()`:

```php
    public static function sanitize_gtm_id($input) {
        $input = strtoupper(sanitize_text_field($input));

        if (empty($input)) {
            return '';
        }

        if (!preg_match('/^GTM-[A-Z0-9]+$/', $input)) {
            add_settings_error(
                'for_you_google_analytics_gtm_id',
                'invalid_gtm_id',
                __('Invalid GTM Container ID. It must be in the format GTM-XXXXXXX.', 'for-you-google-analytics')
            );
            return get_option('for_you_google_analytics_gtm_id', '');
        }

        return $input;
    }
```

- [ ] **Step 2: Add the GTM field and surface its settings errors**

Modify `register_fields()` to add the field:

```php
        add_settings_field(
            'for_you_google_analytics_gtm_id',
            'GTM Container ID',
            array(__CLASS__, 'gtm_id_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );
```

Add the field callback after `ga4_code_field()`:

```php
    public static function gtm_id_field() {
        $gtm_id = get_option('for_you_google_analytics_gtm_id');
        echo '<input type="text" name="for_you_google_analytics_gtm_id" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXXXXX" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Format: GTM-XXXXXXX. If set, GA4 is typically configured inside GTM instead of loading separately.', 'for-you-google-analytics') . '</p>';
    }
```

Modify `render_page()`'s `settings_errors()` call site to also surface GTM errors — replace:

```php
                <?php settings_errors('for_you_google_analytics_ga4_code'); ?>
```

with:

```php
                <?php
                settings_errors('for_you_google_analytics_ga4_code');
                settings_errors('for_you_google_analytics_gtm_id');
                ?>
```

- [ ] **Step 3: Add cleanup to uninstall.php**

```php
delete_option( 'for_you_google_analytics_gtm_id' );
```

- [ ] **Step 4: Manually verify**

Since there's no test harness, verify by re-reading `sanitize_gtm_id` against the regex requirement (`^GTM-[A-Z0-9]+$`) and confirming it mirrors `sanitize_ga4_code`'s structure exactly (same revert-on-invalid behavior, same `add_settings_error` pattern using its own option-name slug so the two error messages don't collide).

- [ ] **Step 5: Commit**

```bash
git add includes/class-settings.php uninstall.php
git commit -m "Add GTM Container ID field with validation"
```

---

### Task 3: Consent checkbox field + Complianz/Cookiebot-aware description

**Files:**
- Modify: `includes/class-settings.php`
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: `EGA_Settings` class from Tasks 1-2
- Produces: option `for_you_google_analytics_consent_banner_enabled` (`'1'`/`''`) — Task 5 (consent class) reads this to decide whether to enqueue the fallback banner assets.

- [ ] **Step 1: Add a shared checkbox sanitizer and register the consent setting**

Add a generic boolean sanitizer (reused by Task 6's four checkboxes too) after `sanitize_gtm_id()`:

```php
    public static function sanitize_checkbox($input) {
        return ($input === '1') ? '1' : '';
    }
```

In `register_settings()`, add:

```php
        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_consent_banner_enabled',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
                'default'           => '',
            )
        );
```

- [ ] **Step 2: Add the consent banner field**

In `register_fields()`, add:

```php
        add_settings_field(
            'for_you_google_analytics_consent_banner_enabled',
            'Consent Banner',
            array(__CLASS__, 'consent_banner_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );
```

Add the field callback:

```php
    public static function consent_banner_field() {
        $enabled = get_option('for_you_google_analytics_consent_banner_enabled');
        echo '<label><input type="checkbox" name="for_you_google_analytics_consent_banner_enabled" value="1" ' . checked('1', $enabled, false) . ' /> ';
        echo esc_html__('Enable built-in consent banner', 'for-you-google-analytics') . '</label>';
        echo '<p class="description">' . esc_html__('Only shown when Complianz or Cookiebot isn\'t detected on the page.', 'for-you-google-analytics') . '</p>';
    }
```

- [ ] **Step 3: Add cleanup to uninstall.php**

```php
delete_option( 'for_you_google_analytics_consent_banner_enabled' );
```

- [ ] **Step 4: Manually verify**

Confirm `checked('1', $enabled, false)` renders `checked="checked"` only when the stored value is exactly `'1'` (WordPress `checked()` does a loose `==` comparison by default when the third arg is omitted, but passing `false` as third arg here just means "return instead of echo" — reread the WP core `checked()` signature if unsure: `checked($checked, $current = true, $echo = true)`; the third param controls echo, not comparison — so this call is: compare `'1' == $enabled`, echo `false` (return the string instead of printing twice)). Confirm this doesn't double-print by checking the surrounding `echo` already wraps it.

- [ ] **Step 5: Commit**

```bash
git add includes/class-settings.php uninstall.php
git commit -m "Add consent banner enable/disable checkbox"
```

---

### Task 4: Consent Mode v2 defaults + tracking output (GA4 + GTM)

**Files:**
- Create: `includes/class-tracking-output.php`
- Modify: `Easy Google Analytics.php` (require + init the new class, remove old `for_you_google_analytics_output`/`wp_head` hook — this task supersedes it)

**Interfaces:**
- Consumes: `get_option('for_you_google_analytics_ga4_code')`, `get_option('for_you_google_analytics_gtm_id')` (from Tasks 1-2)
- Produces: `EGA_Tracking_Output::init()`. Emits the Consent Mode default block + GA4/GTM script tags. Task 5 (consent class) and Task 6 (event tracking) both depend on the `dataLayer`/`gtag` globals this task establishes existing on every page load where GA4 or GTM is configured.

- [ ] **Step 1: Create `includes/class-tracking-output.php`**

```php
<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Tracking_Output {

    public static function init() {
        add_action('wp_head', array(__CLASS__, 'output_consent_defaults'), 1);
        add_action('wp_head', array(__CLASS__, 'output_tracking_scripts'), 10);
    }

    public static function is_configured() {
        $ga4 = get_option('for_you_google_analytics_ga4_code');
        $gtm = get_option('for_you_google_analytics_gtm_id');
        return !empty($ga4) || !empty($gtm);
    }

    public static function output_consent_defaults() {
        if (!self::is_configured()) {
            return;
        }
        ?>
        <!-- Google Consent Mode v2 defaults (Easy Google Analytics) -->
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'analytics_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'wait_for_update': 500
            });
        </script>
        <?php
    }

    public static function output_tracking_scripts() {
        $ga4_code = get_option('for_you_google_analytics_ga4_code');
        $gtm_id   = get_option('for_you_google_analytics_gtm_id');

        if (!empty($gtm_id)) {
            ?>
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
            <!-- End Google Tag Manager -->
            <?php
        }

        if (!empty($ga4_code)) {
            ?>
            <!-- Global site tag (gtag.js) - Google Analytics (GA4) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_code); ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', '<?php echo esc_js($ga4_code); ?>');
            </script>
            <?php
        }
    }
}
```

- [ ] **Step 2: Wire it into the loader and remove the superseded output code**

In `Easy Google Analytics.php`, the loader currently only has `EGA_Settings`. Update to:

```php
<?php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 2.0
    Text Domain: for-you-google-analytics
 */

if (!defined('WPINC')) {
    die;
}

define('EGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EGA_PLUGIN_DIR . 'includes/class-settings.php';
require_once EGA_PLUGIN_DIR . 'includes/class-tracking-output.php';

EGA_Settings::init();
EGA_Tracking_Output::init();
```

(Note: Task 1's loader had no old `for_you_google_analytics_output` function to remove — that function lived in the original monolithic file and was already left behind when Task 1 migrated only the settings code into `class-settings.php`. Confirm the loader has no leftover `add_action('wp_head', 'for_you_google_analytics_output', ...)` line or function definition from the pre-Task-1 file. If Task 1 was executed exactly as written above, this is already clean.)

- [ ] **Step 3: Manually verify**

Read through `output_consent_defaults()` and `output_tracking_scripts()` and confirm: (a) consent defaults hook at priority `1`, tracking scripts at priority `10` — satisfies the Global Constraint ordering; (b) both bail out via `is_configured()`/`empty()` checks when nothing is set, matching the spec's "nothing configured → nothing enqueues" edge case; (c) `esc_js()` used for values inside `<script>` JS-string context, `esc_attr()` used for the HTML attribute context (`src="..."`) — matches the security fix pattern from the earlier review.

- [ ] **Step 4: Commit**

```bash
git add "Easy Google Analytics.php" includes/class-tracking-output.php
git commit -m "Add Consent Mode v2 defaults and GTM+GA4 tracking output"
```

---

### Task 5: Consent detection + fallback banner (PHP enqueue side)

**Files:**
- Create: `includes/class-consent.php`
- Create: `assets/consent-banner.css`
- Modify: `Easy Google Analytics.php` (require + init)

**Interfaces:**
- Consumes: `get_option('for_you_google_analytics_consent_banner_enabled')` (Task 3), `EGA_Tracking_Output::is_configured()` (Task 4)
- Produces: enqueues `assets/consent-banner.js` (written in Task 5b below) with handle `ega-consent-banner`, footer markup with id `ega-consent-banner` containing buttons `#ega-consent-accept` / `#ega-consent-reject` — Task 5's JS (next step) binds to these exact IDs.

- [ ] **Step 1: Create `assets/consent-banner.css`**

```css
#ega-consent-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 999999;
    background: #1e1e1e;
    color: #fff;
    padding: 16px 20px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 14px;
    line-height: 1.5;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.2);
}

#ega-consent-banner p {
    margin: 0;
    flex: 1 1 300px;
}

#ega-consent-banner .ega-consent-actions {
    display: flex;
    gap: 8px;
    flex: 0 0 auto;
}

#ega-consent-banner button {
    cursor: pointer;
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    font-size: 14px;
}

#ega-consent-accept {
    background: #2271b1;
    color: #fff;
}

#ega-consent-reject {
    background: transparent;
    color: #fff;
    border: 1px solid #fff;
}

#ega-consent-banner[hidden] {
    display: none;
}
```

- [ ] **Step 2: Create `includes/class-consent.php`**

```php
<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Consent {

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_footer', array(__CLASS__, 'render_banner_markup'));
    }

    public static function banner_enabled() {
        return EGA_Tracking_Output::is_configured()
            && get_option('for_you_google_analytics_consent_banner_enabled') === '1';
    }

    public static function enqueue() {
        if (!self::banner_enabled()) {
            return;
        }

        wp_enqueue_style(
            'ega-consent-banner',
            EGA_PLUGIN_URL . 'assets/consent-banner.css',
            array(),
            '2.0'
        );

        wp_enqueue_script(
            'ega-consent-banner',
            EGA_PLUGIN_URL . 'assets/consent-banner.js',
            array(),
            '2.0',
            true
        );
    }

    public static function render_banner_markup() {
        if (!self::banner_enabled()) {
            return;
        }
        ?>
        <div id="ega-consent-banner" hidden>
            <p><?php esc_html_e('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics'); ?></p>
            <div class="ega-consent-actions">
                <button type="button" id="ega-consent-reject"><?php esc_html_e('Reject', 'for-you-google-analytics'); ?></button>
                <button type="button" id="ega-consent-accept"><?php esc_html_e('Accept', 'for-you-google-analytics'); ?></button>
            </div>
        </div>
        <?php
    }
}
```

Note: the banner `<div>` renders with `hidden` by default; `consent-banner.js` (Task 5, next step) removes the `hidden` attribute only after confirming no CMP was detected and no existing consent cookie is present — this avoids a flash-of-banner on every page load before JS runs, and avoids showing it when a CMP already handles consent.

- [ ] **Step 3: Wire into loader**

In `Easy Google Analytics.php`, add after the `class-tracking-output.php` require:

```php
require_once EGA_PLUGIN_DIR . 'includes/class-consent.php';
```

and after `EGA_Tracking_Output::init();`:

```php
EGA_Consent::init();
```

- [ ] **Step 4: Manually verify**

Confirm `EGA_Consent` is required/init'd *after* `EGA_Tracking_Output` in the loader (PHP class-loading order doesn't strictly require this since `banner_enabled()` only calls `EGA_Tracking_Output::is_configured()` at runtime inside a hook callback, well after all files are loaded — but keep the require order matching dependency order for readability). Confirm the banner markup only outputs when both configured-and-enabled, matching Task 3's field description promise ("only shown when...").

- [ ] **Step 5: Commit**

```bash
git add "Easy Google Analytics.php" includes/class-consent.php assets/consent-banner.css
git commit -m "Add consent banner PHP enqueue and markup"
```

---

### Task 6: Consent detection + banner JS logic (Complianz/Cookiebot fallback)

**Files:**
- Create: `assets/consent-banner.js`

**Interfaces:**
- Consumes: DOM elements `#ega-consent-banner`, `#ega-consent-accept`, `#ega-consent-reject` (Task 5); global `gtag` function (Task 4, always defined once `output_consent_defaults()` has run, since that task defines `function gtag(){dataLayer.push(arguments);}` unconditionally whenever GA4 or GTM is configured — which is the only case this script is even enqueued, per `banner_enabled()`'s check).
- Produces: cookie `easygoogleanalytics_consent` (`granted`/`denied`, 1 year). Task 7 (tracking.js) reads this same cookie to decide whether to fire events.

- [ ] **Step 1: Create `assets/consent-banner.js`**

```js
(function () {
    'use strict';

    var COOKIE_NAME = 'easygoogleanalytics_consent';

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function setCookie(name, value) {
        var oneYear = 60 * 60 * 24 * 365;
        document.cookie = name + '=' + encodeURIComponent(value) + '; max-age=' + oneYear + '; path=/; SameSite=Lax';
    }

    function updateConsent(granted) {
        if (typeof window.gtag !== 'function') {
            return;
        }
        window.gtag('consent', 'update', {
            ad_storage: granted ? 'granted' : 'denied',
            analytics_storage: granted ? 'granted' : 'denied',
            ad_user_data: granted ? 'granted' : 'denied',
            ad_personalization: granted ? 'granted' : 'denied'
        });
    }

    function detectComplianz() {
        return typeof window.complianz !== 'undefined' || getCookie('cmplz_marketing') !== null || getCookie('cmplz_statistics') !== null;
    }

    function detectCookiebot() {
        return typeof window.Cookiebot !== 'undefined';
    }

    function bindComplianz() {
        document.addEventListener('cmplz_status_change', function () {
            var granted = getCookie('cmplz_statistics') === 'allow';
            updateConsent(granted);
        });
    }

    function bindCookiebot() {
        window.addEventListener('CookiebotOnAccept', function () {
            var granted = window.Cookiebot && window.Cookiebot.consent && window.Cookiebot.consent.statistics;
            updateConsent(!!granted);
        });
        window.addEventListener('CookiebotOnDecline', function () {
            updateConsent(false);
        });
    }

    function initFallbackBanner() {
        var existing = getCookie(COOKIE_NAME);
        if (existing === 'granted') {
            updateConsent(true);
            return;
        }
        if (existing === 'denied') {
            return;
        }

        var banner = document.getElementById('ega-consent-banner');
        if (!banner) {
            return;
        }

        banner.removeAttribute('hidden');

        var acceptBtn = document.getElementById('ega-consent-accept');
        var rejectBtn = document.getElementById('ega-consent-reject');

        acceptBtn.addEventListener('click', function () {
            setCookie(COOKIE_NAME, 'granted');
            updateConsent(true);
            banner.setAttribute('hidden', 'hidden');
        });

        rejectBtn.addEventListener('click', function () {
            setCookie(COOKIE_NAME, 'denied');
            updateConsent(false);
            banner.setAttribute('hidden', 'hidden');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (detectComplianz()) {
            bindComplianz();
            return;
        }
        if (detectCookiebot()) {
            bindCookiebot();
            return;
        }
        initFallbackBanner();
    });
})();
```

- [ ] **Step 2: Manually verify against the spec's consent flow**

Re-read spec section "Consent flow" steps 2-3 and confirm: CMP detection runs before the fallback banner (both `if` branches `return` early, `initFallbackBanner()` is the fallthrough); Complianz/Cookiebot paths never touch `#ega-consent-banner` (matches spec: "the plugin's own banner is not rendered in this case"); accepted/rejected state persists via the `easygoogleanalytics_consent` cookie so returning visitors aren't reprompted (matches spec step 3).

- [ ] **Step 3: Browser-based manual test**

In a local WP install with the plugin active, a GA4 code set, and "Enable built-in consent banner" checked:
1. Load the site in a fresh incognito window with no Complianz/Cookiebot installed. Confirm the banner appears at the bottom of the page.
2. Open devtools console, run `document.cookie` before clicking anything — confirm no `easygoogleanalytics_consent` cookie yet.
3. Click Accept. Confirm the banner hides, `document.cookie` now contains `easygoogleanalytics_consent=granted`, and the Network tab shows a `collect` request to Google (indicating `analytics_storage` flipped to granted).
4. Reload the page. Confirm the banner does NOT reappear (cookie already set).
5. In devtools console before page load (via a snippet or by stubbing), set `window.Cookiebot = {consent: {statistics: true}}` and reload — confirm the banner never appears in this case.

- [ ] **Step 4: Commit**

```bash
git add assets/consent-banner.js
git commit -m "Add consent detection and fallback banner JS logic"
```

---

### Task 7: Event tracking checkboxes (settings)

**Files:**
- Modify: `includes/class-settings.php`
- Modify: `uninstall.php`

**Interfaces:**
- Consumes: `sanitize_checkbox()` (Task 3)
- Produces: options `for_you_google_analytics_track_outbound`, `for_you_google_analytics_track_downloads`, `for_you_google_analytics_track_scroll`, `for_you_google_analytics_track_forms` (each `'1'`/`''`) — Task 8 (event-tracking class) reads all four.

- [ ] **Step 1: Register the four settings**

In `register_settings()`, add:

```php
        $checkbox_options = array(
            'for_you_google_analytics_track_outbound',
            'for_you_google_analytics_track_downloads',
            'for_you_google_analytics_track_scroll',
            'for_you_google_analytics_track_forms',
        );

        foreach ($checkbox_options as $option_name) {
            register_setting(
                'for_you_google_analytics_options',
                $option_name,
                array(
                    'type'              => 'string',
                    'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
                    'default'           => '',
                )
            );
        }
```

- [ ] **Step 2: Add the four checkbox fields**

In `register_fields()`, add:

```php
        add_settings_field(
            'for_you_google_analytics_event_tracking',
            'Event Tracking',
            array(__CLASS__, 'event_tracking_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );
```

Add the combined field callback (one field row rendering all four checkboxes, since they're one logical group):

```php
    public static function event_tracking_field() {
        $options = array(
            'for_you_google_analytics_track_outbound'  => __('Outbound link clicks', 'for-you-google-analytics'),
            'for_you_google_analytics_track_downloads' => __('File download clicks', 'for-you-google-analytics'),
            'for_you_google_analytics_track_scroll'    => __('Scroll depth', 'for-you-google-analytics'),
            'for_you_google_analytics_track_forms'     => __('Form submissions', 'for-you-google-analytics'),
        );

        foreach ($options as $option_name => $label) {
            $value = get_option($option_name);
            echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="' . esc_attr($option_name) . '" value="1" ' . checked('1', $value, false) . ' /> ';
            echo esc_html($label) . '</label>';
        }
    }
```

- [ ] **Step 3: Add cleanup to uninstall.php**

```php
delete_option( 'for_you_google_analytics_track_outbound' );
delete_option( 'for_you_google_analytics_track_downloads' );
delete_option( 'for_you_google_analytics_track_scroll' );
delete_option( 'for_you_google_analytics_track_forms' );
```

- [ ] **Step 4: Manually verify**

Confirm all four option names in `event_tracking_field()`'s `$options` array match exactly the four registered in Step 1's `$checkbox_options` array (a typo mismatch here would silently break saving for that checkbox — WordPress would register a setting under one name but render a checkbox with a different `name` attribute, so the form would submit a value that never gets validated/saved by `register_setting`).

- [ ] **Step 5: Commit**

```bash
git add includes/class-settings.php uninstall.php
git commit -m "Add event tracking checkboxes to settings page"
```

---

### Task 8: Event tracking JS enqueue (PHP side)

**Files:**
- Create: `includes/class-event-tracking.php`
- Modify: `Easy Google Analytics.php` (require + init)

**Interfaces:**
- Consumes: the four `for_you_google_analytics_track_*` options (Task 7), `EGA_Tracking_Output::is_configured()` (Task 4)
- Produces: enqueues `assets/tracking.js` (Task 9) with localized JS object `easyGA4TrackingConfig` — Task 9's JS reads this exact global name and its four boolean keys plus `downloadExtensions` array, matching the spec's config shape exactly.

- [ ] **Step 1: Create `includes/class-event-tracking.php`**

```php
<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Event_Tracking {

    const DOWNLOAD_EXTENSIONS = array('pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'mp4', 'csv');

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
    }

    public static function any_module_enabled() {
        return get_option('for_you_google_analytics_track_outbound') === '1'
            || get_option('for_you_google_analytics_track_downloads') === '1'
            || get_option('for_you_google_analytics_track_scroll') === '1'
            || get_option('for_you_google_analytics_track_forms') === '1';
    }

    public static function enqueue() {
        if (!EGA_Tracking_Output::is_configured() || !self::any_module_enabled()) {
            return;
        }

        wp_enqueue_script(
            'ega-tracking',
            EGA_PLUGIN_URL . 'assets/tracking.js',
            array(),
            '2.0',
            true
        );

        wp_localize_script('ega-tracking', 'easyGA4TrackingConfig', array(
            'outbound'           => get_option('for_you_google_analytics_track_outbound') === '1',
            'downloads'          => get_option('for_you_google_analytics_track_downloads') === '1',
            'scroll'             => get_option('for_you_google_analytics_track_scroll') === '1',
            'forms'              => get_option('for_you_google_analytics_track_forms') === '1',
            'downloadExtensions' => self::DOWNLOAD_EXTENSIONS,
        ));
    }
}
```

- [ ] **Step 2: Wire into loader**

In `Easy Google Analytics.php`, add after the `class-consent.php` require:

```php
require_once EGA_PLUGIN_DIR . 'includes/class-event-tracking.php';
```

and after `EGA_Consent::init();`:

```php
EGA_Event_Tracking::init();
```

- [ ] **Step 3: Manually verify**

Confirm `wp_localize_script`'s array keys (`outbound`, `downloads`, `scroll`, `forms`, `downloadExtensions`) match exactly what the spec's "Event tracking (tracking.js)" section defines as the config shape — this is the contract Task 9's JS is written against. Confirm `enqueue()` bails when no module is enabled, so `tracking.js` never loads with all-false config (avoids a pointless network request).

- [ ] **Step 4: Commit**

```bash
git add "Easy Google Analytics.php" includes/class-event-tracking.php
git commit -m "Add event tracking JS enqueue with localized config"
```

---

### Task 9: Event tracking JS (outbound, downloads, scroll, forms)

**Files:**
- Create: `assets/tracking.js`

**Interfaces:**
- Consumes: global `easyGA4TrackingConfig` object (Task 8: `{outbound, downloads, scroll, forms, downloadExtensions}`); global `gtag` function (Task 4); cookie `easygoogleanalytics_consent` (Task 6) — read directly here rather than via a shared helper, since this is a separately-enqueued script with no shared module system.

- [ ] **Step 1: Create `assets/tracking.js`**

```js
(function () {
    'use strict';

    if (typeof easyGA4TrackingConfig === 'undefined') {
        return;
    }

    var config = easyGA4TrackingConfig;

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function consentGranted() {
        return getCookie('easygoogleanalytics_consent') === 'granted';
    }

    function fireEvent(name, params) {
        if (typeof window.gtag !== 'function' || !consentGranted()) {
            return;
        }
        window.gtag('event', name, params);
    }

    function getExtension(url) {
        try {
            var pathname = new URL(url, window.location.href).pathname;
            var match = pathname.match(/\.([a-zA-Z0-9]+)$/);
            return match ? match[1].toLowerCase() : null;
        } catch (e) {
            return null;
        }
    }

    if (config.outbound || config.downloads) {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link) {
                return;
            }

            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (e) {
                return;
            }

            var isOutbound = url.hostname !== window.location.hostname;

            if (config.downloads) {
                var ext = getExtension(link.href);
                if (ext && config.downloadExtensions.indexOf(ext) !== -1) {
                    fireEvent('file_download', {
                        file_extension: ext,
                        link_url: link.href
                    });
                    return;
                }
            }

            if (config.outbound && isOutbound) {
                fireEvent('click', {
                    link_url: link.href,
                    link_domain: url.hostname,
                    outbound: true
                });
            }
        });
    }

    if (config.scroll) {
        var milestones = [25, 50, 75, 90];
        var fired = {};
        var ticking = false;

        function checkScroll() {
            ticking = false;
            var scrollTop = window.scrollY || document.documentElement.scrollTop;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight <= 0) {
                return;
            }
            var percent = (scrollTop / docHeight) * 100;

            milestones.forEach(function (milestone) {
                if (!fired[milestone] && percent >= milestone) {
                    fired[milestone] = true;
                    fireEvent('scroll', { percent_scrolled: milestone });
                }
            });
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(checkScroll);
                ticking = true;
            }
        });
    }

    if (config.forms) {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.tagName !== 'FORM') {
                return;
            }
            fireEvent('form_submit', { form_id: form.id || null });
        });
    }
})();
```

- [ ] **Step 2: Manually verify against the spec's event-tracking section**

Re-read spec section "Event tracking (tracking.js)" and confirm each of the four modules matches: event names (`click`, `file_download`, `scroll`, `form_submit`), parameter shapes, the download-check-before-outbound-check ordering (a download link to another domain fires `file_download`, not both events — matches the `return` after firing `file_download` inside the click handler), and that `consentGranted()` gates every `fireEvent()` call (Global Constraint: no event fires while `analytics_storage` is denied).

- [ ] **Step 3: Browser-based manual test**

In a local WP install with GA4 configured, consent already granted (accept the banner first), and all four event-tracking checkboxes enabled:
1. Click a link to an external site. Confirm a `click` event with `outbound: true` appears in the Network tab's `collect` request or GA4 DebugView.
2. Click a link to a `.pdf` file (can be a dummy link). Confirm a `file_download` event fires instead of a duplicate outbound `click` event.
3. Scroll to the bottom of a long page. Confirm `scroll` events fire at each of the 25/50/75/90 milestones, each only once.
4. Submit a plain HTML form on the page (or a test page with one). Confirm a `form_submit` event fires.
5. Reject consent (clear the `easygoogleanalytics_consent` cookie and set it to `denied`, or click Reject on the banner) and repeat steps 1-4. Confirm no events fire.

- [ ] **Step 4: Commit**

```bash
git add assets/tracking.js
git commit -m "Add outbound/download/scroll/form event tracking JS"
```

---

### Task 10: Final uninstall.php review and version bump verification

**Files:**
- Modify: `uninstall.php` (final consolidation pass)

**Interfaces:**
- Consumes: the full list of options introduced across Tasks 1-9.
- Produces: nothing further downstream — this is the last task.

- [ ] **Step 1: Read the full current `uninstall.php` and confirm all 7 options are present**

The expected final contents:

```php
<?php
 /* For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       #
 * @since      1.0.0
 *
 * @package    Plugin_Name
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'for_you_google_analytics_ga4_code' );
delete_option( 'for_you_google_analytics_gtm_id' );
delete_option( 'for_you_google_analytics_consent_banner_enabled' );
delete_option( 'for_you_google_analytics_track_outbound' );
delete_option( 'for_you_google_analytics_track_downloads' );
delete_option( 'for_you_google_analytics_track_scroll' );
delete_option( 'for_you_google_analytics_track_forms' );
```

If any line is missing (each prior task added its own line — this step is a consolidation check, not new code), add it now.

- [ ] **Step 2: Confirm plugin header version matches actual scope**

Read `Easy Google Analytics.php` and confirm the header comment says `Version: 2.0` (set in Task 1, Step 2) — this is a major feature addition (consent mode, GTM, event tracking) over the 1.2 baseline, so the major version bump is correct per semver conventions for user-facing new functionality.

- [ ] **Step 3: Full manual regression pass**

Using a local WP install:
1. Activate the plugin fresh. Confirm no PHP warnings/errors in `debug.log`.
2. Visit Settings → Google Analytics (GA4). Confirm all fields render: GA4 code, GTM ID, consent banner checkbox, four event-tracking checkboxes.
3. Save with only GA4 set. Confirm `wp_head` outputs Consent Mode defaults + gtag.js, no GTM snippet.
4. Add a GTM ID and save. Confirm both GTM and gtag.js snippets now appear.
5. Enable the consent banner, all four event checkboxes. Reload as a fresh visitor, accept consent, verify events fire (per Task 9 Step 3).
6. Deactivate and uninstall the plugin. Query the `wp_options` table (`wp option list | grep for_you_google_analytics` via WP-CLI, or check via phpMyAdmin) and confirm zero rows remain with that prefix.

- [ ] **Step 4: Commit**

```bash
git add uninstall.php
git commit -m "Finalize uninstall cleanup for v2.0 options"
```

---

## Self-Review Notes

**Spec coverage:** GTM field (Task 2) ✓, consent banner checkbox (Task 3) ✓, Consent Mode v2 defaults (Task 4) ✓, CMP detection + fallback banner (Tasks 5-6) ✓, four event-tracking checkboxes (Task 7) ✓, event-tracking JS enqueue + config (Task 8) ✓, four event modules (Task 9) ✓, uninstall cleanup for all 7 new/existing options (Task 10, incrementally in Tasks 2/3/7) ✓. File structure from spec matches Tasks 1/4/5/6/8/9 exactly (`includes/class-settings.php`, `class-consent.php`, `class-tracking-output.php`, `class-event-tracking.php`, `assets/tracking.js`, `assets/consent-banner.js`, `assets/consent-banner.css`).

**Placeholder scan:** No TBD/TODO markers. Every step has complete, runnable code. Manual-verification steps describe concrete checks (specific cookie names, specific DOM IDs, specific event names) rather than vague "test it" instructions, since no automated test framework exists for this plugin (confirmed in spec's Testing section).

**Type/naming consistency:** `easyGA4TrackingConfig` object shape (Task 8 produces, Task 9 consumes) matches key-for-key. `easygoogleanalytics_consent` cookie name matches between Task 6 (writer) and Task 9 (reader). DOM IDs `#ega-consent-banner`/`#ega-consent-accept`/`#ega-consent-reject` match between Task 5 (markup) and Task 6 (JS binding). All class names (`EGA_Settings`, `EGA_Tracking_Output`, `EGA_Consent`, `EGA_Event_Tracking`) and their `init()` methods are consistently named and wired into the loader in dependency order across Tasks 1/4/5/8.
