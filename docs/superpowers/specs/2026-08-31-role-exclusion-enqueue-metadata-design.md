# Easy Google Analytics v2.1 — Role Exclusion, Enqueued Output, Metadata

## Problem

Four gaps remain after v2.0:

1. There is no way to exclude specific WordPress roles (most commonly
   Administrators) from being tracked, so a site owner's own admin/editor
   visits pollute their GA4 data.
2. `EGA_Tracking_Output` still emits its Consent Mode default block and its
   GA4/GTM loader as raw `echo`'d `<script>` tags in `wp_head`, rather than
   using `wp_enqueue_script`/`wp_localize_script` the way `EGA_Consent` and
   `EGA_Event_Tracking` already do — inconsistent with the rest of the
   plugin and harder to cache-bust, dequeue, or audit via WordPress's script
   registry.
3. `uninstall.php` has no trailing newline (cosmetic, flagged by common
   linters, already noted as a deferred minor in the v2.0 final review).
4. There is no `readme.txt` in WordPress.org format, and the main plugin
   file's header is missing `Requires PHP`, `Requires at least`, `License`,
   and `License URI`.

## Non-goals

- No per-user (as opposed to per-role) exclusion.
- No exclusion UI for logged-out visitors (there is no "role" to exclude —
  logged-out traffic is never excluded by this feature).
- No implicit exclusion of roles not explicitly checked (a custom role not
  ticked in settings is tracked normally — see Data model).
- No merging of the two existing `wp_head` hooks (priority 1 consent
  defaults, priority 10 GA4/GTM loader) into a single script — they stay
  separate, each independently converted to enqueue+localize.
- Not preparing this plugin for wordpress.org submission end-to-end (no
  screenshots, no `.org` assets directory) — just the `readme.txt` file and
  header metadata WP.org and most linters expect.

## File structure

New files:
```
assets/consent-defaults.js   Consent Mode v2 default() call, reads localized defaults
assets/gtag-loader.js        dataLayer/gtag bootstrap + gtag('config', ...) call, reads localized GA4/GTM ids
readme.txt                   WordPress.org-format readme
```

Modified files:
```
includes/class-settings.php        role-exclusion checkboxes + sanitizer
includes/class-tracking-output.php enqueue conversion, is_user_excluded()
includes/class-consent.php         exclusion check added to banner_enabled()
includes/class-event-tracking.php  exclusion check added to enqueue()'s gate
uninstall.php                      delete new option, add trailing newline
Easy Google Analytics.php          add Requires PHP / License headers
```

No files are removed. The GTM/GA4 `<script src="...">` tags (the external
loader tags, as opposed to the inline bootstrap logic) become registered
via `wp_enqueue_script` pointing at the same external URLs — WordPress's
supported way to enqueue a third-party script with query-string arguments.

## Data model (new option)

| Option name | Type | Notes |
|---|---|---|
| `for_you_google_analytics_excluded_roles` | array of strings | Sanitized to only role slugs that exist in `wp_roles()->get_names()` at save time; unknown/stale slugs are dropped silently (e.g. a role deleted after being excluded). Default: `array()`. |

Registered via `register_setting` with a `sanitize_callback` that:
1. Ensures the input is an array (WordPress omits unchecked checkboxes from
   `$_POST` entirely, so an empty selection arrives as `null`/absent —
   normalize to `array()`).
2. Filters it against `array_keys(wp_roles()->get_names())`, dropping any
   value not currently a registered role slug.
3. Returns the filtered array (`array_values()` to keep it a clean
   zero-indexed list for storage).

`uninstall.php` adds `delete_option('for_you_google_analytics_excluded_roles')`.

## Role exclusion

**Settings field** (`includes/class-settings.php`): a new field, rendered
by iterating `get_editable_roles()` (WP core function; equivalent to
`wp_roles()->get_names()` but respects multisite/role-editing filters and
is the conventional call for an admin-facing role list) and rendering one
checkbox per role, checked if its slug is in the stored array. Label:
"Exclude these roles from tracking". Description: "Logged-in users with
any of the checked roles will not be tracked, and will not see the consent
banner or contribute to event-tracking data."

**Check** (`includes/class-tracking-output.php`): new public static method
`EGA_Tracking_Output::is_user_excluded()`:

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

This lives on `EGA_Tracking_Output` (which already owns `is_configured()`,
the other shared precondition) rather than on `EGA_Settings`, since it is
consulted by output-decision logic, not settings-rendering logic.

**Call sites** — each of the three existing gates adds this as an explicit
second condition, rather than folding it into `is_configured()`:

- `EGA_Tracking_Output::output_consent_defaults()` and
  `output_tracking_scripts()`: `if (!self::is_configured() ||
  self::is_user_excluded()) { return; }`
- `EGA_Consent::banner_enabled()`: becomes `return
  EGA_Tracking_Output::is_configured() && !EGA_Tracking_Output::is_user_excluded()
  && get_option('for_you_google_analytics_consent_banner_enabled') === '1';`
- `EGA_Event_Tracking::enqueue()`: adds `|| EGA_Tracking_Output::is_user_excluded()`
  to its existing bailout condition alongside `!is_configured()` and
  `!any_module_enabled()`.

Rationale for not renaming/changing `is_configured()` itself: three classes
already call `is_configured()` today, verified clean by the v2.0 review.
Changing its meaning to include exclusion would be an invisible behavior
change to every existing call site with no diff marking it. An explicit
second condition at each site is more repetitive but leaves no ambiguity
about what each class is checking and why — matches how `banner_enabled()`
already combines two independent conditions.

## Enqueue refactor

**`assets/consent-defaults.js`** (new):
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

`EGA_Tracking_Output::output_consent_defaults()` becomes:
```php
public static function output_consent_defaults() {
    if (!self::is_configured() || self::is_user_excluded()) {
        return;
    }
    wp_enqueue_script('ega-consent-defaults', EGA_PLUGIN_URL . 'assets/consent-defaults.js', array(), '2.1', false);
    wp_localize_script('ega-consent-defaults', 'easyGA4ConsentDefaults', array(
        'ad_storage'          => 'denied',
        'analytics_storage'   => 'denied',
        'ad_user_data'        => 'denied',
        'ad_personalization'  => 'denied',
        'wait_for_update'     => 500,
    ));
}
```
Hooked the same as today — `wp_head` priority 1 — except the hook now calls
`wp_enqueue_script`/`wp_localize_script` instead of echoing. `in_footer` is
`false` (the whole point of Consent Mode defaults is that they must load
before any tag fires, which for a `wp_head`-priority-1 hook means keeping
it in the head, not deferring to the footer).

**`assets/gtag-loader.js`** (new):
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

`EGA_Tracking_Output::output_tracking_scripts()` becomes: register/enqueue
the external `https://www.googletagmanager.com/gtm.js?id=...` (GTM) and
`https://www.googletagmanager.com/gtag/js?id=...` (GA4) tags via
`wp_enqueue_script` (each a real handle pointing at the external URL, async
via the `wp_script_add_data($handle, 'async', true)` core API rather than
manually printing `async` — this is the standard WordPress way to enqueue
an async external script), then enqueue `assets/gtag-loader.js` as a
dependency of the GA4 handle so it runs after gtag.js loads, localized with
`easyGA4Loader = {ga4Code: '<sanitized G-XXXXXXXXXX>'}`. GTM needs no
separate bootstrap script — GTM's own loader snippet already does
everything after the container script tag loads; only the tiny IIFE that
pushes the initial `gtm.start` event remains genuinely inline-only-viable
(GTM's own documented snippet is that IIFE, not a URL), so that one small
bootstrap stays as a `wp_add_inline_script` attached to the GTM handle
(this is the officially-supported WordPress pattern for "small script that
must run inline alongside an enqueued one" — not a regression back to raw
`echo`, since it's registered through the script API and subject to the
same dependency ordering, deregistration, and localization tooling as
everything else).

Both hooks stay at their existing priorities (1 and 10) and existing
`wp_head` action — only the emission mechanism changes, not the timing
contract other tasks/tests may rely on.

## Metadata

**`uninstall.php`**: add the one new `delete_option` line; ensure the file
ends with a trailing newline (many editors strip trailing newlines on
save — the fix step must explicitly verify the byte is present, not just
assume the edit tool added it).

**`Easy Google Analytics.php` header**, adding to the existing block:
```
Requires at least: 5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

**`readme.txt`** (new, WordPress.org format):
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

Adds Google Analytics (GA4) and Google Tag Manager tracking to your site,
with Google Consent Mode v2, a built-in consent banner, and optional
event tracking.

== Description ==

[feature list mirroring the v2.0/v2.1 feature set]

== Installation ==

[standard upload/activate/configure steps]

== Changelog ==

= 2.1 =
* Added role-based tracking exclusion
* Converted inline tracking scripts to wp_enqueue_script/wp_localize_script
* Added readme.txt and plugin header metadata

= 2.0 =
* Added Google Consent Mode v2 with Complianz/Cookiebot detection and
  built-in fallback banner
* Added Google Tag Manager support
* Added event tracking (outbound links, downloads, scroll depth, forms)
```
Exact changelog wording for pre-2.0 versions is not fabricated — the
Changelog section starts at 2.0 (the only versions this project's own
history can attest to) rather than inventing 1.x release notes.

## Error handling / edge cases

- A role deleted after being excluded: `is_user_excluded()` reads whatever
  is stored; if the sanitizer already dropped stale slugs at save time,
  this only matters if a role is deleted *after* saving without re-saving
  settings — harmless, `array_intersect` against a since-deleted role slug
  in a user's `roles` array (which WordPress itself cleans up when a role
  is deleted) simply never matches.
- No excluded roles configured (default state): `is_user_excluded()` short
  circuits via `empty($excluded)` — zero performance cost added to the
  common case.
- Logged-out visitors: always `is_user_logged_in() === false`, so the new
  check is a no-op for the vast majority of traffic — matches existing
  plugin behavior of never touching anonymous-visitor logic here.
- GTM inline bootstrap via `wp_add_inline_script`: if GTM is not configured
  (`empty($gtm_id)`), no GTM handle is registered, so nothing is
  short-circuited to check — the existing `if (!empty($gtm_id))` guard is
  preserved unchanged, just wrapping enqueue calls instead of echo.

## Testing

No automated test suite exists for this plugin (unchanged from v2.0). Per
task, verification is: `php -l` on every touched PHP file, `node --check`
on every new/touched JS file, manual code-trace confirming the exclusion
check reaches all three consuming call sites identically, and a written
checklist for later human QA on a live WordPress install covering:
checking an "Administrator" exclusion box while logged in as an admin and
confirming no gtag/GTM network requests fire; confirming a non-excluded
role is tracked normally; confirming the enqueued scripts appear in
"View Page Source" with the same effective behavior as the old inline
tags (Consent Mode default fires before the GA4/GTM loader, in the same
relative order).

## Open questions

None outstanding — all decisions confirmed during brainstorming
(2026-08-31).
