# Tabbed Settings + Consent Banner Design System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the settings page into "Settings" and "Banner Design" tabs, and let the site owner customize the consent banner's colors (via 5 presets + custom override), layout (bar/corner), wording, and privacy-policy link, with an instant live preview.

**Architecture:** Two tab panels render unconditionally inside the existing single `<form>`/`options.php` submission (pure client-side `hidden`-attribute toggling, no AJAX, no data loss switching tabs). A new `EGA_Consent::get_palettes()` array is the single source of truth for the 5 presets, consumed by both the PHP sanitize callback (server-side authority) and JS (client-side instant preview, via `wp_localize_script`). The frontend banner reads resolved colors/wording/layout into CSS custom properties + a layout class on its root element; `consent-banner.css` switches from hardcoded values to `var(--ega-banner-*, <today's-default>)` so behavior is unchanged for every existing install until the site owner visits the new Design tab.

**Tech Stack:** PHP (WordPress Settings API), vanilla JS + jQuery (already a dependency), CSS custom properties, no build step, no automated test framework (manual verification against local WP, matching this plugin's existing convention).

**Spec:** `docs/superpowers/specs/2026-09-01-banner-design-tabs-design.md`

## Global Constraints

- All new options are registered in the existing `for_you_google_analytics_options` settings group — one Save Changes button continues to save everything across both tabs.
- Text domain for all user-facing strings: `for-you-google-analytics`.
- Color sanitization always falls back to the option's last-saved value (never to a blank/empty string) plus `add_settings_error()`, matching the existing `sanitize_ga4_code`/`sanitize_gtm_id` pattern — never reuse WP core's `sanitize_hex_color()` directly, since it returns `''` on invalid input with no fallback mechanism.
- Wording fields (`banner_message`, `banner_accept_label`, `banner_reject_label`) store `''` when left blank — never pre-filled with English defaults at save time. The English default is applied only at render time (both the live preview's JS and the frontend PHP render), never written to the database, so future default-text fixes apply automatically to every site that never customized it.
- Every new option's registered default reproduces today's exact banner appearance/wording with zero visible change on upgrade: `banner_palette` defaults to `'dark'`, the four color options default to the Dark palette's values, `banner_layout` defaults to `'bar'`, wording/privacy-url options default to `''`.
- `banner_palette` submitted as `custom` (or any unrecognized value) means "use the four color fields as individually submitted" — the sanitize callback only overwrites the four color options when the submitted palette name matches one of the 5 known preset keys exactly.
- No new files are created for CSS/JS — all changes land in the existing `assets/admin.css`, `assets/admin.js`, `assets/consent-banner.css` files, and existing `includes/class-settings.php`, `includes/class-consent.php` classes.
- Both tab panels always render server-side on every page load — switching tabs is pure client-side attribute toggling, never conditional PHP output, so no data is lost switching tabs before saving.

---

### Task 1: Palette data model, sanitize callbacks, and option registration

**Files:**
- Modify: `includes/class-consent.php`
- Modify: `includes/class-settings.php`

**Interfaces:**
- Consumes: nothing from other tasks (first task)
- Produces: `EGA_Consent::get_palettes()` (public static, no args, returns associative array keyed by palette slug — each value an associative array with keys `bg`, `text`, `accept`, `reject`, `reject_style`) — consumed by Task 2 (settings-page rendering + JS localization) and Task 4 (frontend banner rendering). Also produces the 9 new registered options (see table below), each readable via `get_option('<name>', '<default>')` — consumed by Task 2 (rendering the Design tab fields) and Task 4 (frontend banner rendering).

- [ ] **Step 1: Add `get_palettes()` to `EGA_Consent`**

In `includes/class-consent.php`, add as the first method inside the class (before `init()`):

```php
    public static function get_palettes() {
        return array(
            'dark' => array(
                'bg'           => '#1e1e1e',
                'text'         => '#ffffff',
                'accept'       => '#2271b1',
                'reject'       => '#ffffff',
                'reject_style' => 'outline',
            ),
            'light' => array(
                'bg'           => '#ffffff',
                'text'         => '#1e1e1e',
                'accept'       => '#2271b1',
                'reject'       => '#f0f0f1',
                'reject_style' => 'filled',
            ),
            'minimal' => array(
                'bg'           => '#f8f9fa',
                'text'         => '#3c434a',
                'accept'       => '#3c434a',
                'reject'       => '#3c434a',
                'reject_style' => 'outline',
            ),
            'brand-blue' => array(
                'bg'           => '#0f172a',
                'text'         => '#e2e8f0',
                'accept'       => '#3b82f6',
                'reject'       => '#93c5fd',
                'reject_style' => 'outline',
            ),
            'high-contrast' => array(
                'bg'           => '#000000',
                'text'         => '#ffffff',
                'accept'       => '#ffcc00',
                'reject'       => '#ffffff',
                'reject_style' => 'filled',
            ),
        );
    }
```

- [ ] **Step 2: Add hex-color sanitizer to `EGA_Settings`**

In `includes/class-settings.php`, add after `sanitize_checkbox()`:

```php
    public static function sanitize_hex_color($input, $option_name, $fallback) {
        $input = is_string($input) ? trim($input) : '';

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $input)) {
            return strtolower($input);
        }

        add_settings_error(
            $option_name,
            'invalid_hex_color',
            __('Invalid color value. Colors must be a 6-digit hex code (e.g. #1e1e1e).', 'for-you-google-analytics')
        );

        return $fallback;
    }
```

Note: this signature takes `$option_name` and `$fallback` as extra arguments beyond what `register_setting`'s `sanitize_callback` normally passes (WordPress only ever passes the raw `$input` value). Task 1 Step 3 below wires each color option through a small per-option closure/wrapper so each call site supplies its own option name and current-value fallback — see Step 3's exact registration code, which resolves this.

- [ ] **Step 3: Add `sanitize_banner_palette()` to `EGA_Settings`**

Add after `sanitize_hex_color()`:

```php
    public static function sanitize_banner_palette($input) {
        $input = is_string($input) ? trim($input) : '';
        $palettes = EGA_Consent::get_palettes();

        if (!array_key_exists($input, $palettes)) {
            return 'custom';
        }

        $preset = $palettes[$input];
        update_option('for_you_google_analytics_banner_bg_color', $preset['bg']);
        update_option('for_you_google_analytics_banner_text_color', $preset['text']);
        update_option('for_you_google_analytics_banner_accept_color', $preset['accept']);
        update_option('for_you_google_analytics_banner_reject_color', $preset['reject']);

        return $input;
    }
```

Note: this callback calls `update_option()` directly on the four color options rather than returning a value for them, because `register_setting`'s sanitize callback can only transform the value of the option it is registered for — it has no mechanism to also set sibling options. Directly calling `update_option()` here has the desired synchronous effect (the four color options are already updated by the time `options.php`'s own save of `for_you_google_analytics_banner_palette` completes) and matches the spec's requirement that a recognized preset selection authoritatively overwrites the four color fields server-side. This runs during the same `options.php` request that also independently sanitizes the four color options via their own callbacks (Step 4) — order does not matter here since both end up writing the same preset values when a known palette is selected, and Step 4's own sanitizer only ever runs on whatever was actually submitted in that field (which the browser-side JS, added in Task 3, also updates to match before submit).

- [ ] **Step 4: Register the 9 new options in `register_settings()`**

In `includes/class-settings.php`, add at the end of `register_settings()` (after the existing `$checkbox_options` foreach loop):

```php
        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_palette',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_banner_palette'),
                'default'           => 'dark',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_bg_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_bg_color', get_option('for_you_google_analytics_banner_bg_color', '#1e1e1e'));
                },
                'default'           => '#1e1e1e',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_text_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_text_color', get_option('for_you_google_analytics_banner_text_color', '#ffffff'));
                },
                'default'           => '#ffffff',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_accept_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_accept_color', get_option('for_you_google_analytics_banner_accept_color', '#2271b1'));
                },
                'default'           => '#2271b1',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_reject_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_reject_color', get_option('for_you_google_analytics_banner_reject_color', '#ffffff'));
                },
                'default'           => '#ffffff',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_layout',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_banner_layout'),
                'default'           => 'bar',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_message',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_accept_label',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_reject_label',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_privacy_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_banner_privacy_url'),
                'default'           => '',
            )
        );
```

- [ ] **Step 5: Add `sanitize_banner_layout()` and `sanitize_banner_privacy_url()` to `EGA_Settings`**

Add after `sanitize_banner_palette()`:

```php
    public static function sanitize_banner_layout($input) {
        return ($input === 'corner') ? 'corner' : 'bar';
    }

    public static function sanitize_banner_privacy_url($input) {
        $input = is_string($input) ? trim($input) : '';

        if ($input === '') {
            return '';
        }

        $sanitized = esc_url_raw($input);

        return $sanitized !== '' ? $sanitized : '';
    }
```

- [ ] **Step 6: Manually verify**

Read the final `includes/class-consent.php` and confirm `get_palettes()` returns exactly 5 keys (`dark`, `light`, `minimal`, `brand-blue`, `high-contrast`), each with exactly the 5 sub-keys (`bg`, `text`, `accept`, `reject`, `reject_style`), and that `reject_style` is `'filled'` for exactly `light` and `high-contrast`, `'outline'` for the other three — matching the spec's palette table exactly. Read the final `includes/class-settings.php` and confirm: all 9 new `register_setting()` calls use the exact option names this task's Interfaces section promises (character-for-character, since Task 2 and Task 4 read these names via `get_option()`); `sanitize_banner_palette()` returns `'custom'` for any input not matching one of the 5 known keys (never errors, never adds a settings_error, per the spec's Error Handling section); the four color sanitize closures each reference the correct sibling option name in both their `get_option()` fallback call and their `sanitize_hex_color()` `$option_name` argument (a copy-paste mismatch here would misattribute a validation error to the wrong field). Run `php -l includes/class-consent.php` and `php -l includes/class-settings.php`.

- [ ] **Step 7: Commit**

```bash
git add includes/class-consent.php includes/class-settings.php
git commit -m "Add banner palette data model, color sanitizers, and option registration"
```

---

### Task 2: Tab structure and Banner Design tab markup

**Files:**
- Modify: `includes/class-settings.php`

**Interfaces:**
- Consumes: the 9 options and `EGA_Consent::get_palettes()` from Task 1
- Produces: tab markup with element IDs `ega-tab-trigger-settings`, `ega-tab-trigger-design`, `ega-tab-panel-settings`, `ega-tab-panel-design` (consumed by Task 3's tab-switching JS); Design-tab form field `name` attributes exactly matching the 9 option names from Task 1 (consumed by `options.php` on submit — no other task reads these names, but they must match Task 1's registered names exactly for the form to save correctly); a `wp_localize_script` JS global `egaBannerDesign` containing `palettes` (the same array as `get_palettes()`) and `defaults` (the English default wording strings) — consumed by Task 3's live-preview and palette-swatch JS.

- [ ] **Step 1: Add tab triggers above `.ega-grid`, and wrap the existing content in the Settings panel**

In `includes/class-settings.php`, inside `render_page()`, locate:

```php
            <!-- Main Form -->
            <form method="post" action="options.php" class="ega-settings-form">
                <?php settings_fields('for_you_google_analytics_options'); ?>

                <div class="ega-grid">
```

Replace with:

```php
            <!-- Main Form -->
            <form method="post" action="options.php" class="ega-settings-form">
                <?php settings_fields('for_you_google_analytics_options'); ?>

                <!-- Tabs -->
                <div class="ega-tabs-nav" role="tablist">
                    <button type="button" id="ega-tab-trigger-settings" class="ega-tab-trigger is-active" role="tab" aria-selected="true" aria-controls="ega-tab-panel-settings">
                        <?php esc_html_e('Settings', 'for-you-google-analytics'); ?>
                    </button>
                    <button type="button" id="ega-tab-trigger-design" class="ega-tab-trigger" role="tab" aria-selected="false" aria-controls="ega-tab-panel-design">
                        <?php esc_html_e('Banner Design', 'for-you-google-analytics'); ?>
                    </button>
                </div>

                <div id="ega-tab-panel-settings" class="ega-tab-panel" role="tabpanel">
                <div class="ega-grid">
```

This opens both a new `.ega-tab-panel` wrapper div and keeps the existing `.ega-grid` div open immediately after it (two open tags, matched by two closing tags in Step 2) — the existing Settings-tab content (Cards 1-4, sidebar) needs no further changes in this step, it now simply renders one level deeper inside the new panel wrapper.

- [ ] **Step 2: Close the Settings panel after the existing `.ega-grid` closes, before the new Design panel**

Locate the end of the existing grid (the sidebar column's closing tags, immediately before `</form>`):

```php
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
```

Replace with:

```php
                    </div>
                </div>
                </div>

                <div id="ega-tab-panel-design" class="ega-tab-panel" hidden role="tabpanel">
                    <?php self::render_design_tab(); ?>
                </div>
            </form>
        </div>
        <?php
    }

    private static function render_design_tab() {
        $palette       = get_option('for_you_google_analytics_banner_palette', 'dark');
        $bg_color      = get_option('for_you_google_analytics_banner_bg_color', '#1e1e1e');
        $text_color    = get_option('for_you_google_analytics_banner_text_color', '#ffffff');
        $accept_color  = get_option('for_you_google_analytics_banner_accept_color', '#2271b1');
        $reject_color  = get_option('for_you_google_analytics_banner_reject_color', '#ffffff');
        $layout        = get_option('for_you_google_analytics_banner_layout', 'bar');
        $message       = get_option('for_you_google_analytics_banner_message', '');
        $accept_label  = get_option('for_you_google_analytics_banner_accept_label', '');
        $reject_label  = get_option('for_you_google_analytics_banner_reject_label', '');
        $privacy_url   = get_option('for_you_google_analytics_banner_privacy_url', '');

        $default_message      = __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics');
        $default_accept_label = __('Accept', 'for-you-google-analytics');
        $default_reject_label = __('Reject', 'for-you-google-analytics');
        $palettes              = EGA_Consent::get_palettes();
        ?>
        <div class="ega-grid">
            <div class="ega-main-column">

                <!-- Card: Color Palette -->
                <div class="ega-card">
                    <div class="ega-card-header">
                        <div class="ega-card-title-group">
                            <div class="ega-card-icon accent">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C22 6.012 17.461 2 12 2z"/></svg>
                            </div>
                            <div>
                                <h2 class="ega-card-title"><?php esc_html_e('Color Palette', 'for-you-google-analytics'); ?></h2>
                                <p class="ega-card-subtitle"><?php esc_html_e('Pick a preset, then fine-tune any color individually.', 'for-you-google-analytics'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="ega-card-body">
                        <input type="hidden" id="ega-banner-palette-input" name="for_you_google_analytics_banner_palette" value="<?php echo esc_attr($palette); ?>" />

                        <div class="ega-palette-swatches">
                            <?php foreach ($palettes as $key => $preset) : ?>
                                <button type="button" class="ega-palette-swatch <?php echo ($palette === $key) ? 'is-active' : ''; ?>" data-palette="<?php echo esc_attr($key); ?>">
                                    <span class="ega-palette-swatch-preview" style="background:<?php echo esc_attr($preset['bg']); ?>;">
                                        <span style="background:<?php echo esc_attr($preset['accept']); ?>;"></span>
                                        <span style="background:<?php echo esc_attr($preset['reject']); ?>;"></span>
                                    </span>
                                    <span class="ega-palette-swatch-label"><?php echo esc_html(ucwords(str_replace('-', ' ', $key))); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-bg-color"><span><?php esc_html_e('Background Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-bg-color" name="for_you_google_analytics_banner_bg_color" value="<?php echo esc_attr($bg_color); ?>" class="ega-color-input" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-text-color"><span><?php esc_html_e('Text Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-text-color" name="for_you_google_analytics_banner_text_color" value="<?php echo esc_attr($text_color); ?>" class="ega-color-input" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-accept-color"><span><?php esc_html_e('Accept Button Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-accept-color" name="for_you_google_analytics_banner_accept_color" value="<?php echo esc_attr($accept_color); ?>" class="ega-color-input" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-reject-color"><span><?php esc_html_e('Reject Button Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-reject-color" name="for_you_google_analytics_banner_reject_color" value="<?php echo esc_attr($reject_color); ?>" class="ega-color-input" />
                        </div>
                    </div>
                </div>

                <!-- Card: Layout -->
                <div class="ega-card">
                    <div class="ega-card-header">
                        <div class="ega-card-title-group">
                            <div class="ega-card-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
                            </div>
                            <div>
                                <h2 class="ega-card-title"><?php esc_html_e('Layout', 'for-you-google-analytics'); ?></h2>
                                <p class="ega-card-subtitle"><?php esc_html_e('Choose how the banner is positioned on the page.', 'for-you-google-analytics'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="ega-card-body">
                        <label class="ega-radio-option">
                            <input type="radio" name="for_you_google_analytics_banner_layout" value="bar" <?php checked('bar', $layout); ?> />
                            <?php esc_html_e('Full-width bar (bottom of screen)', 'for-you-google-analytics'); ?>
                        </label>
                        <label class="ega-radio-option">
                            <input type="radio" name="for_you_google_analytics_banner_layout" value="corner" <?php checked('corner', $layout); ?> />
                            <?php esc_html_e('Floating box (bottom-right corner)', 'for-you-google-analytics'); ?>
                        </label>
                    </div>
                </div>

                <!-- Card: Wording & Privacy Link -->
                <div class="ega-card">
                    <div class="ega-card-header">
                        <div class="ega-card-title-group">
                            <div class="ega-card-icon emerald">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <h2 class="ega-card-title"><?php esc_html_e('Wording &amp; Privacy Link', 'for-you-google-analytics'); ?></h2>
                                <p class="ega-card-subtitle"><?php esc_html_e('Leave a field blank to use the default text.', 'for-you-google-analytics'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="ega-card-body">
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-message"><span><?php esc_html_e('Banner Message', 'for-you-google-analytics'); ?></span></label>
                            <textarea id="ega-banner-message" name="for_you_google_analytics_banner_message" class="ega-text-input ega-textarea" rows="3" placeholder="<?php echo esc_attr($default_message); ?>"><?php echo esc_textarea($message); ?></textarea>
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-accept-label"><span><?php esc_html_e('Accept Button Label', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-accept-label" name="for_you_google_analytics_banner_accept_label" value="<?php echo esc_attr($accept_label); ?>" class="ega-text-input" placeholder="<?php echo esc_attr($default_accept_label); ?>" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-reject-label"><span><?php esc_html_e('Reject Button Label', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-reject-label" name="for_you_google_analytics_banner_reject_label" value="<?php echo esc_attr($reject_label); ?>" class="ega-text-input" placeholder="<?php echo esc_attr($default_reject_label); ?>" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-privacy-url"><span><?php esc_html_e('Privacy Policy URL', 'for-you-google-analytics'); ?></span></label>
                            <input type="url" id="ega-banner-privacy-url" name="for_you_google_analytics_banner_privacy_url" value="<?php echo esc_attr($privacy_url); ?>" class="ega-text-input" placeholder="<?php echo esc_attr(get_privacy_policy_url()); ?>" />
                            <p class="ega-helper-text">
                                <?php esc_html_e('Leave blank to automatically use your site\'s Privacy Policy page (Settings > Privacy), if one is set.', 'for-you-google-analytics'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ega-sidebar-column">
                <div class="ega-sidebar-card">
                    <div class="ega-sidebar-header">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <h3 class="ega-sidebar-title"><?php esc_html_e('Live Preview', 'for-you-google-analytics'); ?></h3>
                    </div>
                    <div class="ega-sidebar-body">
                        <div id="ega-design-preview" class="ega-preview-box ega-design-preview">
                            <div class="ega-preview-header">
                                <span><?php esc_html_e('Frontend Preview', 'for-you-google-analytics'); ?></span>
                            </div>
                            <div id="ega-design-preview-banner" class="ega-preview-banner ega-layout-<?php echo esc_attr($layout); ?>" data-reject-style="<?php echo esc_attr($palettes[$palette]['reject_style'] ?? 'outline'); ?>">
                                <p id="ega-design-preview-message"><?php echo esc_html($message !== '' ? $message : $default_message); ?></p>
                                <div class="ega-preview-actions">
                                    <button type="button" id="ega-design-preview-reject" class="ega-btn-preview-reject"><?php echo esc_html($reject_label !== '' ? $reject_label : $default_reject_label); ?></button>
                                    <button type="button" id="ega-design-preview-accept" class="ega-btn-preview-accept"><?php echo esc_html($accept_label !== '' ? $accept_label : $default_accept_label); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
```

Note: `render_design_tab()` is declared `private` (not `array(__CLASS__, ...)`-called from any WordPress hook) — it is only ever invoked directly from `render_page()` in the same class, so it does not need to be `public static` like the hook-registered methods elsewhere in this file.

- [ ] **Step 3: Localize palette and default-wording data for JS**

In `includes/class-settings.php`, in `enqueue_admin_assets()`, add after the existing `wp_enqueue_script('ega-admin-scripts', ...)` call and before the closing `}`:

```php
        wp_localize_script('ega-admin-scripts', 'egaBannerDesign', array(
            'palettes' => EGA_Consent::get_palettes(),
            'defaults' => array(
                'message'     => __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics'),
                'acceptLabel' => __('Accept', 'for-you-google-analytics'),
                'rejectLabel' => __('Reject', 'for-you-google-analytics'),
            ),
        ));
```

- [ ] **Step 4: Manually verify**

Read the final `includes/class-settings.php` in full. Confirm: the Settings tab's existing Cards 1-4 and sidebar are unchanged in content, only newly wrapped inside `#ega-tab-panel-settings` (diff should show only added wrapper lines around the existing block, no changes inside it); every `name="for_you_google_analytics_banner_*"` attribute in the new Design tab matches one of the 9 option names registered in Task 1 character-for-character; the hidden `#ega-banner-palette-input` field is the one and only form field with `name="for_you_google_analytics_banner_palette"` (the palette swatches themselves are `type="button"`, not radio inputs, so they never submit a value directly — Task 3's JS must write the clicked palette's key into this hidden input, which this task does not yet wire up but must leave in a state Task 3 can attach to). Run `php -l includes/class-settings.php`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-settings.php
git commit -m "Add tab structure and Banner Design tab markup"
```

---

### Task 3: Tab-switching, palette-swatch, and live-preview JS + CSS

**Files:**
- Modify: `assets/admin.js`
- Modify: `assets/admin.css`

**Interfaces:**
- Consumes: `egaBannerDesign` global (Task 2, Step 3: `{palettes: {...}, defaults: {message, acceptLabel, rejectLabel}}`); element IDs `ega-tab-trigger-settings`, `ega-tab-trigger-design`, `ega-tab-panel-settings`, `ega-tab-panel-design`, `ega-banner-palette-input`, `ega-banner-bg-color`, `ega-banner-text-color`, `ega-banner-accept-color`, `ega-banner-reject-color`, `.ega-palette-swatch` (with `data-palette` attribute), radio inputs named `for_you_google_analytics_banner_layout`, `ega-banner-message`, `ega-banner-accept-label`, `ega-banner-reject-label`, `ega-design-preview-banner`, `ega-design-preview-message`, `ega-design-preview-reject`, `ega-design-preview-accept` (all from Task 2).
- Produces: nothing consumed by later tasks (this task's JS only drives the settings-page preview, not the frontend banner — Task 4 is independent PHP/CSS work for the real frontend banner).

- [ ] **Step 1: Add tab-switching JS**

In `assets/admin.js`, inside the existing `$(document).ready(function () { ... })` block, add (after the existing `$gtmInput.on('input', validateGTM);` block, before the "Event tracking cards click handler" section):

```javascript
    // Tab switching
    const $tabTriggerSettings = $('#ega-tab-trigger-settings');
    const $tabTriggerDesign = $('#ega-tab-trigger-design');
    const $tabPanelSettings = $('#ega-tab-panel-settings');
    const $tabPanelDesign = $('#ega-tab-panel-design');

    function activateTab(name) {
      const isSettings = name === 'settings';
      $tabTriggerSettings.toggleClass('is-active', isSettings).attr('aria-selected', isSettings ? 'true' : 'false');
      $tabTriggerDesign.toggleClass('is-active', !isSettings).attr('aria-selected', !isSettings ? 'true' : 'false');
      $tabPanelSettings.prop('hidden', !isSettings);
      $tabPanelDesign.prop('hidden', isSettings);
    }

    $tabTriggerSettings.on('click', function () {
      activateTab('settings');
    });
    $tabTriggerDesign.on('click', function () {
      activateTab('design');
    });
```

- [ ] **Step 2: Run to verify tab switching works**

Since there is no automated test harness for this plugin's JS, verification here is manual: this step is completed together with Step 8's manual verification pass (loading the actual settings page). Do not skip ahead — continue to Step 3 first so the palette/preview JS exists before manual testing.

- [ ] **Step 3: Add live-preview sync function**

In `assets/admin.js`, add after the tab-switching code from Step 1:

```javascript
    // Banner Design live preview
    if (typeof egaBannerDesign !== 'undefined') {
      const $bgColor = $('#ega-banner-bg-color');
      const $textColor = $('#ega-banner-text-color');
      const $acceptColor = $('#ega-banner-accept-color');
      const $rejectColor = $('#ega-banner-reject-color');
      const $paletteInput = $('#ega-banner-palette-input');
      const $layoutRadios = $('input[name="for_you_google_analytics_banner_layout"]');
      const $message = $('#ega-banner-message');
      const $acceptLabel = $('#ega-banner-accept-label');
      const $rejectLabel = $('#ega-banner-reject-label');
      const $previewBanner = $('#ega-design-preview-banner');
      const $previewMessage = $('#ega-design-preview-message');
      const $previewReject = $('#ega-design-preview-reject');
      const $previewAccept = $('#ega-design-preview-accept');

      function currentRejectStyle() {
        const paletteKey = $paletteInput.val();
        const preset = egaBannerDesign.palettes[paletteKey];
        return preset ? preset.reject_style : 'outline';
      }

      function syncPreview() {
        $previewBanner.css({
          background: $bgColor.val(),
          color: $textColor.val()
        });
        $previewAccept.css({ background: $acceptColor.val(), color: '#ffffff' });

        const rejectStyle = currentRejectStyle();
        $previewBanner.attr('data-reject-style', rejectStyle);
        if (rejectStyle === 'filled') {
          $previewReject.css({ background: $rejectColor.val(), color: $textColor.val(), border: 'none' });
        } else {
          $previewReject.css({ background: 'transparent', color: $rejectColor.val(), border: '1px solid ' + $rejectColor.val() });
        }

        $previewBanner.removeClass('ega-layout-bar ega-layout-corner');
        $previewBanner.addClass('ega-layout-' + $layoutRadios.filter(':checked').val());

        $previewMessage.text($message.val().trim() !== '' ? $message.val() : egaBannerDesign.defaults.message);
        $previewAccept.text($acceptLabel.val().trim() !== '' ? $acceptLabel.val() : egaBannerDesign.defaults.acceptLabel);
        $previewReject.text($rejectLabel.val().trim() !== '' ? $rejectLabel.val() : egaBannerDesign.defaults.rejectLabel);
      }

      $bgColor.add($textColor).add($acceptColor).add($rejectColor).add($message).add($acceptLabel).add($rejectLabel).on('input', syncPreview);
      $layoutRadios.on('change', syncPreview);

      // Palette swatch selection
      $('.ega-palette-swatch').on('click', function () {
        const key = $(this).data('palette');
        const preset = egaBannerDesign.palettes[key];
        if (!preset) {
          return;
        }

        $('.ega-palette-swatch').removeClass('is-active');
        $(this).addClass('is-active');
        $paletteInput.val(key);

        $bgColor.val(preset.bg);
        $textColor.val(preset.text);
        $acceptColor.val(preset.accept);
        $rejectColor.val(preset.reject);

        syncPreview();
        markFormDirty();
      });

      syncPreview();
    }
```

Note: `markFormDirty()` is called here because it is already defined earlier in the same `$(document).ready(...)` closure (used by the existing event-tracking checkbox and consent-toggle handlers) — this task's code runs later in the same closure and has access to it. The generic `$('form.ega-settings-form input').on('input change', function () { markFormDirty(); });` handler that already exists at the bottom of the file also fires for every new Design-tab `<input>`/`<textarea>` automatically (jQuery selector matches by tag, not by name, and textareas are separately unaffected since only `input`/`change` events matter here, not the element type) — so individual color/text field edits already mark the form dirty without extra code; only the swatch buttons need an explicit call since they are `<button>` elements, not form inputs matched by that existing selector.

- [ ] **Step 4: Add tab CSS**

In `assets/admin.css`, add after the `/* Two-Column Grid Layout */` block (after the `@media (max-width: 1080px)` rule that closes it):

```css
/* Tabs */
.ega-tabs-nav {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  border-bottom: 1.5px solid var(--ega-border);
}

.ega-tab-trigger {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 12px 18px;
  font-size: 14px;
  font-weight: 600;
  color: var(--ega-text-secondary);
  cursor: pointer;
  transition: var(--ega-transition);
  margin-bottom: -1.5px;
}

.ega-tab-trigger:hover {
  color: var(--ega-text-main);
}

.ega-tab-trigger.is-active {
  color: var(--ega-primary);
  border-bottom-color: var(--ega-primary);
}

.ega-tab-panel[hidden] {
  display: none;
}
```

- [ ] **Step 5: Add palette swatch CSS**

In `assets/admin.css`, add after the CSS from Step 4:

```css
/* Palette Swatches */
.ega-palette-swatches {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 10px;
  margin-bottom: 22px;
}

.ega-palette-swatch {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: 1.5px solid var(--ega-border);
  border-radius: var(--ega-radius-sm);
  padding: 10px;
  cursor: pointer;
  transition: var(--ega-transition);
}

.ega-palette-swatch:hover {
  border-color: #cbd5e1;
}

.ega-palette-swatch.is-active {
  border-color: var(--ega-primary);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.ega-palette-swatch-preview {
  width: 100%;
  height: 36px;
  border-radius: 6px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 4px;
  padding-bottom: 6px;
  border: 1px solid rgba(0, 0, 0, 0.08);
}

.ega-palette-swatch-preview span {
  width: 14px;
  height: 8px;
  border-radius: 3px;
}

.ega-palette-swatch-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--ega-text-secondary);
}
```

- [ ] **Step 6: Add color-input and radio-option CSS**

In `assets/admin.css`, add after the CSS from Step 5:

```css
/* Color input (native color swatch + hex text side by side) */
.ega-admin-wrap .ega-color-input {
  width: 100%;
  max-width: 100%;
  height: 44px;
  padding: 0 14px;
  font-size: 13px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-weight: 600;
  color: var(--ega-text-main);
  background-color: #f8fafc;
  border: 1.5px solid var(--ega-border);
  border-radius: var(--ega-radius-md);
  box-sizing: border-box;
}

.ega-admin-wrap .ega-color-input:focus {
  background-color: #ffffff;
  border-color: var(--ega-border-focus);
  outline: none;
}

.ega-radio-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  margin-bottom: 8px;
  background: #f8fafc;
  border: 1.5px solid var(--ega-border);
  border-radius: var(--ega-radius-md);
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--ega-text-main);
}

.ega-radio-option:last-child {
  margin-bottom: 0;
}

.ega-textarea {
  height: auto !important;
  min-height: 76px !important;
  line-height: 1.5 !important;
  padding: 12px 16px !important;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  letter-spacing: normal !important;
  resize: vertical;
}

/* Design tab preview layout variants (mirrors frontend consent-banner.css) */
.ega-design-preview .ega-preview-banner.ega-layout-corner {
  max-width: 320px;
  flex-direction: column;
  align-items: flex-start;
}
```

- [ ] **Step 7: Run syntax checks**

```bash
node --check assets/admin.js
```

Expected: no output (success). There is no CSS linter configured for this project, so `assets/admin.css` has no automated check — Step 8's manual verification covers it visually.

- [ ] **Step 8: Manually verify**

Load the plugin's settings page on a local WordPress install (or state clearly if this was not possible and why). Confirm: clicking "Banner Design" shows the Design tab and hides Settings, and vice versa; clicking each of the 5 palette swatches updates all 4 color fields and the live preview instantly; manually changing a color field after selecting a palette updates only the preview (palette selection is not reset — the hidden `#ega-banner-palette-input` still holds the last-clicked palette key, meaning on save `sanitize_banner_palette()` will re-apply that preset's colors, overwriting the manual tweak — note this as an expected interaction to describe in this task's completion notes, not a bug to fix in this task); switching the layout radio updates the preview's layout class; editing message/accept/reject fields updates the preview text live, falling back to default text when a field is cleared.

- [ ] **Step 9: Commit**

```bash
git add assets/admin.js assets/admin.css
git commit -m "Add tab switching, palette swatches, and live preview JS/CSS"
```

---

### Task 4: Frontend banner rendering — colors, layout, wording, privacy link

**Files:**
- Modify: `includes/class-consent.php`
- Modify: `assets/consent-banner.css`

**Interfaces:**
- Consumes: the 9 options from Task 1 (read directly via `get_option()`, independent of Task 2/3's admin-UI work — this task only touches the public-facing banner, not the settings page)
- Produces: nothing consumed by later tasks (last task)

- [ ] **Step 1: Update `render_banner_markup()` to resolve dynamic values**

In `includes/class-consent.php`, replace the entire `render_banner_markup()` method:

```php
    public static function render_banner_markup() {
        if (!self::banner_enabled()) {
            return;
        }

        $palette_key = get_option('for_you_google_analytics_banner_palette', 'dark');
        $palettes    = self::get_palettes();
        $reject_style = isset($palettes[$palette_key]) ? $palettes[$palette_key]['reject_style'] : 'outline';

        $bg_color     = get_option('for_you_google_analytics_banner_bg_color', '#1e1e1e');
        $text_color   = get_option('for_you_google_analytics_banner_text_color', '#ffffff');
        $accept_color = get_option('for_you_google_analytics_banner_accept_color', '#2271b1');
        $reject_color = get_option('for_you_google_analytics_banner_reject_color', '#ffffff');
        $layout       = get_option('for_you_google_analytics_banner_layout', 'bar');

        $message      = get_option('for_you_google_analytics_banner_message', '');
        $message      = $message !== '' ? $message : __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics');
        $accept_label = get_option('for_you_google_analytics_banner_accept_label', '');
        $accept_label = $accept_label !== '' ? $accept_label : __('Accept', 'for-you-google-analytics');
        $reject_label = get_option('for_you_google_analytics_banner_reject_label', '');
        $reject_label = $reject_label !== '' ? $reject_label : __('Reject', 'for-you-google-analytics');

        $privacy_url = get_option('for_you_google_analytics_banner_privacy_url', '');
        if ($privacy_url === '') {
            $privacy_url = get_privacy_policy_url();
        }

        $style = sprintf(
            '--ega-banner-bg:%s;--ega-banner-text:%s;--ega-banner-accept:%s;--ega-banner-reject:%s;',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_attr($accept_color),
            esc_attr($reject_color)
        );
        ?>
        <div id="ega-consent-banner" class="ega-layout-<?php echo esc_attr($layout); ?>" data-reject-style="<?php echo esc_attr($reject_style); ?>" style="<?php echo esc_attr($style); ?>" hidden>
            <p>
                <?php echo esc_html($message); ?>
                <?php if (!empty($privacy_url)) : ?>
                    <a href="<?php echo esc_url($privacy_url); ?>"><?php esc_html_e('Learn more', 'for-you-google-analytics'); ?></a>
                <?php endif; ?>
            </p>
            <div class="ega-consent-actions">
                <button type="button" id="ega-consent-reject"><?php echo esc_html($reject_label); ?></button>
                <button type="button" id="ega-consent-accept"><?php echo esc_html($accept_label); ?></button>
            </div>
        </div>
        <button type="button" id="ega-consent-manage" style="<?php echo esc_attr($style); ?>" hidden><?php esc_html_e('Manage cookie preferences', 'for-you-google-analytics'); ?></button>
        <?php
    }
```

Note: the inline `style` attribute is applied to both `#ega-consent-banner` and `#ega-consent-manage` (rather than a single ancestor wrapper) because the two elements are rendered as CSS siblings with no shared wrapping `<div>` — this matches the spec's requirement that the reopen tab inherits the same custom properties without adding new stored color options for it. Both elements already pass through `esc_attr()` on the same `$style` string, so there is no duplicate escaping logic to maintain — if this string's construction ever changes, both usages stay in sync since they read the same PHP variable.

- [ ] **Step 2: Convert `consent-banner.css` to use CSS custom properties, with today's values as fallbacks**

In `assets/consent-banner.css`, replace the entire file:

```css
#ega-consent-banner {
    position: fixed;
    z-index: 999999;
    background: var(--ega-banner-bg, #1e1e1e);
    color: var(--ega-banner-text, #fff);
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

#ega-consent-banner.ega-layout-bar {
    bottom: 0;
    left: 0;
    right: 0;
}

#ega-consent-banner.ega-layout-corner {
    bottom: 16px;
    right: 16px;
    max-width: 380px;
    flex-direction: column;
    align-items: flex-start;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

#ega-consent-banner p {
    margin: 0;
    flex: 1 1 300px;
}

#ega-consent-banner.ega-layout-corner p {
    flex: 1 1 auto;
}

#ega-consent-banner p a {
    color: inherit;
    text-decoration: underline;
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
    background: var(--ega-banner-accept, #2271b1);
    color: #fff;
}

#ega-consent-banner[data-reject-style="filled"] #ega-consent-reject {
    background: var(--ega-banner-reject, #f0f0f1);
    color: var(--ega-banner-bg, #1e1e1e);
    border: none;
}

#ega-consent-banner[data-reject-style="outline"] #ega-consent-reject,
#ega-consent-banner:not([data-reject-style]) #ega-consent-reject {
    background: transparent;
    color: var(--ega-banner-reject, var(--ega-banner-text, #fff));
    border: 1px solid var(--ega-banner-reject, var(--ega-banner-text, #fff));
}

#ega-consent-banner[hidden] {
    display: none;
}

#ega-consent-manage {
    position: fixed;
    bottom: 16px;
    left: 16px;
    z-index: 999998;
    background: var(--ega-banner-bg, #1e1e1e);
    color: var(--ega-banner-text, #fff);
    border: none;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 12px;
    line-height: 1.4;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    opacity: 0.85;
}

#ega-consent-manage:hover,
#ega-consent-manage:focus {
    opacity: 1;
}

#ega-consent-manage[hidden] {
    display: none;
}
```

Note: the previous version's `bottom: 0; left: 0; right: 0;` on `#ega-consent-banner` (unconditional, no layout class) is now split into `.ega-layout-bar`-scoped rules — every existing install has `banner_layout` defaulting to `'bar'` (Task 1's registered default) and Step 1's render always adds `class="ega-layout-<?php echo esc_attr($layout); ?>"`, so this is not a behavior change for any existing install, only a restructuring to support the new `.ega-layout-corner` variant.

- [ ] **Step 3: Run syntax check**

```bash
php -l includes/class-consent.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Manually verify**

Read the final `includes/class-consent.php` and `assets/consent-banner.css` in full. Confirm: `render_banner_markup()` calls `self::get_palettes()` (the method Task 1 added to this same class) rather than duplicating the palette array; the `$style` string is applied identically to both `#ega-consent-banner` and `#ega-consent-manage`; the privacy link `<a>` only renders when `$privacy_url` is non-empty, using `esc_url()` on the href and `esc_html_e()` for the link text (no raw echo of unescaped data); `consent-banner.css`'s selectors correctly target `[data-reject-style="filled"]` vs `[data-reject-style="outline"]` scoped under `#ega-consent-banner`, and the fallback values in every `var(--ega-banner-*, <fallback>)` match today's pre-this-task hardcoded colors exactly (`#1e1e1e` bg, `#fff` text, `#2271b1` accept) — confirming zero visual change for any install that hasn't visited the new Design tab. On a local WordPress install with the consent banner enabled: confirm the banner renders with the Dark palette's colors by default (visually identical to before this task); switch to each of the other 4 palettes in wp-admin, save, reload the frontend, and confirm each renders with that palette's colors and the correct reject-button style (filled for Light/High Contrast, outline for the rest); switch layout to "corner" and confirm the banner renders as a bottom-right floating box instead of a full-width bar; set a custom message/accept/reject wording and confirm it appears on the frontend; set a WP Privacy Policy page under Settings > Privacy with no manual banner privacy URL, and confirm a "Learn more" link pointing to that page appears in the banner text; then set a manual privacy URL and confirm it overrides the auto-detected one.

- [ ] **Step 5: Commit**

```bash
git add includes/class-consent.php assets/consent-banner.css
git commit -m "Render consent banner with dynamic colors, layout, wording, and privacy link"
```

---

## Self-Review Notes

**Spec coverage:** Tabs split (Task 2) ✓, 5 presets + custom color override (Task 1 data model, Task 2 fields, Task 3 swatch JS) ✓, independent accept/reject colors (Task 1, all tasks) ✓, layout bar/corner (Task 1 sanitizer, Task 2 radio field, Task 4 CSS) ✓, wording fields with blank-falls-back-to-default behavior (Task 1 registration, Task 2 fields with placeholder text, Task 4 render-time fallback) ✓, privacy link auto-resolving from `get_privacy_policy_url()` with manual override (Task 1 sanitizer, Task 2 field, Task 4 render logic) ✓, live preview (Task 3) ✓, "Manage cookie preferences" tab inherits palette colors (Task 4 Step 1's shared `$style` string) ✓. File structure matches the spec's File Changes list exactly: `includes/class-settings.php`, `includes/class-consent.php`, `assets/admin.css`, `assets/admin.js`, `assets/consent-banner.css` — no new files, matching the spec's explicit "No new files" statement.

**Placeholder scan:** No TBD/TODO markers. Every step has complete, runnable code. Task 3 Step 2 explicitly defers to Step 8's manual verification rather than inventing a fake automated-test step, since the spec states no automated test framework exists for this plugin's JS — this is a real constraint, not a placeholder.

**Type/naming consistency:** `EGA_Consent::get_palettes()` is defined once (Task 1) and called identically by name from three places: `EGA_Settings::sanitize_banner_palette()` (Task 1), `EGA_Settings::enqueue_admin_assets()`'s `wp_localize_script` call (Task 2), and `EGA_Consent::render_banner_markup()` via `self::get_palettes()` (Task 4) — all three read the same 5 keys and 5 sub-keys per palette. All 9 option names (`for_you_google_analytics_banner_palette`, `_bg_color`, `_text_color`, `_accept_color`, `_reject_color`, `_layout`, `_message`, `_accept_label`, `_reject_label`, `_privacy_url`) are introduced once in Task 1's `register_settings()` and referenced identically (character-for-character) by Task 2's field `name` attributes and Task 4's `get_option()` calls — no task invents a different name for the same option. Element IDs introduced in Task 2 (`ega-tab-trigger-settings`, `ega-tab-trigger-design`, `ega-tab-panel-settings`, `ega-tab-panel-design`, `ega-banner-palette-input`, `ega-banner-bg-color`, `ega-banner-text-color`, `ega-banner-accept-color`, `ega-banner-reject-color`, `ega-design-preview-banner`, `ega-design-preview-message`, `ega-design-preview-reject`, `ega-design-preview-accept`) are consumed by Task 3's JS using the identical IDs — verified by cross-reading Task 2's Step 2 markup against Task 3's Step 1/3 selectors during this plan's authoring. The `reject_style` values (`'outline'`/`'filled'`) are used identically across Task 1's data (as literal strings), Task 3's JS (`currentRejectStyle()` reading `preset.reject_style`), and Task 4's PHP (`$palettes[$palette_key]['reject_style']`) and CSS (`data-reject-style="filled"` / `"outline"` attribute selectors) — same two string values throughout, no synonym drift (e.g. never `'solid'` in one place and `'filled'` in another).
