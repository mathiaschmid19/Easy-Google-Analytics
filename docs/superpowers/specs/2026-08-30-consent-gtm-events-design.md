# Easy Google Analytics v2.0 — Consent Mode, GTM, Event Tracking

## Problem

The current plugin is a thin wrapper: an admin settings field that echoes a
static GA4 `gtag.js` snippet into `wp_head`. It offers no advantage over a
site owner hand-pasting the same snippet from a theme's header field, and it
tracks visitors with no consent mechanism, which is a compliance risk under
GDPR/ePrivacy for EU-facing sites.

This spec adds three capabilities that make the plugin worth using:

1. Consent-gated tracking (Google Consent Mode v2), deferring to an existing
   CMP (Complianz, Cookiebot) when present, falling back to a minimal
   built-in accept/reject banner otherwise.
2. Optional Google Tag Manager container support alongside GA4.
3. Opt-in GA4 event tracking helpers: outbound link clicks, file download
   clicks, scroll depth, and form submissions — each independently toggled.

## Non-goals

- No full CMP replacement (no granular cookie categories, no scanning, no
  geo-detection). The built-in banner is binary accept/reject.
- No automated PHPUnit test suite is introduced; verification is manual
  against a local WP install (see Testing).
- No support for CMPs other than Complianz and Cookiebot in this iteration.
- No admin-configurable download-extension list; the list is a fixed
  constant in v1.

## File structure

```
Easy-Google-Analytics/
  Easy Google Analytics.php        Thin loader: header, requires includes/*, activation/uninstall wiring
  uninstall.php                    Deletes all plugin options
  includes/
    class-settings.php             Settings page, fields, sanitize callbacks
    class-consent.php              CMP detection + fallback banner render
    class-tracking-output.php      Emits Consent Mode defaults + gtag.js/GTM snippet
    class-event-tracking.php       Enqueues tracking.js, localizes config
  assets/
    tracking.js                    Outbound link / download / scroll / form event listeners
    consent-banner.js              Accept/reject banner logic, consent cookie, gtag consent update
    consent-banner.css             Minimal banner styling
```

Each class is instantiated once from the main plugin file via its own
`init()`/hook-registration method, following the existing procedural hook
style already used in the plugin (no need to introduce a DI container or
autoloader beyond simple `require_once` calls in load order: settings →
consent → tracking-output → event-tracking).

## Data model (options)

| Option name | Type | Notes |
|---|---|---|
| `for_you_google_analytics_ga4_code` | string | existing, `^G-[A-Z0-9]+$`, unchanged |
| `for_you_google_analytics_gtm_id` | string | new, `^GTM-[A-Z0-9]+$` |
| `for_you_google_analytics_consent_banner_enabled` | bool ('1'/'') | new, checkbox |
| `for_you_google_analytics_track_outbound` | bool ('1'/'') | new, checkbox |
| `for_you_google_analytics_track_downloads` | bool ('1'/'') | new, checkbox |
| `for_you_google_analytics_track_scroll` | bool ('1'/'') | new, checkbox |
| `for_you_google_analytics_track_forms` | bool ('1'/'') | new, checkbox |

All are registered via `register_setting` with explicit `sanitize_callback`s
(booleans sanitized to `'1'` or `''` via a shared helper). `uninstall.php`
deletes all seven.

## Settings page

Extends the existing single settings page (same slug, same
`manage_options` capability, same Settings API pattern already in place —
no new page, no new capability).

New fields, added as new `add_settings_field` entries in the existing
section (or a second section if visually cleaner — implementer's call):

- **GTM Container ID** — text input, placeholder `GTM-XXXXXXX`, description
  noting "If set, GA4 is typically configured inside GTM instead of loading
  separately."
- **Enable built-in consent banner** — checkbox, with inline note when a
  supported CMP is detected server-side is not reliable (CMP presence is a
  runtime JS fact) — so instead the description text simply states: "Only
  shown when Complianz or Cookiebot isn't detected on the page."
- **Event tracking** — four checkboxes: Outbound link clicks, File download
  clicks, Scroll depth, Form submissions.

`settings_errors()` continues to surface sanitize-callback validation
messages (already wired for GA4; extended to GTM).

## Consent flow (Google Consent Mode v2)

1. On `wp_head` at priority 1 (before any gtag/GTM script tag), if either
   GA4 or GTM is configured, emit:
   ```js
   window.dataLayer = window.dataLayer || [];
   function gtag(){dataLayer.push(arguments);}
   gtag('consent', 'default', {
     ad_storage: 'denied',
     analytics_storage: 'denied',
     ad_user_data: 'denied',
     ad_personalization: 'denied',
     wait_for_update: 500
   });
   ```
   immediately followed by the existing gtag.js loader (and/or GTM
   container snippet if GTM ID is set). This is the standard Consent Mode
   v2 pattern: scripts always load, but start in a denied/cookieless state.

2. `consent-banner.js` runs on `DOMContentLoaded` and checks, in order:
   - Complianz: presence of `window.complianz` global / `cmplz_marketing`
     cookie category, subscribes to Complianz's own consent-change event.
   - Cookiebot: presence of `window.Cookiebot`, subscribes to
     `CookiebotOnAccept`/`CookiebotOnDecline`.
   - If either is detected, map their granted categories to the four
     Consent Mode v2 fields and call `gtag('consent','update', {...})`
     accordingly. The plugin's own banner is **not** rendered in this case,
     regardless of the "enable built-in banner" setting.

3. If neither CMP is detected and `consent_banner_enabled` is `'1'`, render
   the fallback banner (PHP outputs the markup in `wp_footer`; CSS/JS
   enqueued only when this path is active). Accept → `gtag('consent',
   'update', {analytics_storage:'granted', ...})` + set a first-party
   `easygoogleanalytics_consent=granted` cookie (1 year) so the banner
   doesn't reprompt. Reject → set
   `easygoogleanalytics_consent=denied` cookie (1 year), leave Consent Mode
   defaults as denied.

4. If neither a CMP is detected nor the built-in banner is enabled, Consent
   Mode stays at its denied defaults permanently (site owner's explicit
   choice not to gate) — tracking remains in the cookieless/modeled state
   indefinitely. This is intentional: the plugin doesn't invent consent.

## Event tracking (`tracking.js`)

Enqueued only when at least one of the four checkboxes is on, and only
after GA4/GTM is configured. Config is passed via `wp_localize_script`
(object name `easyGA4TrackingConfig`):

```js
{
  outbound: true|false,
  downloads: true|false,
  scroll: true|false,
  forms: true|false,
  downloadExtensions: ["pdf","zip","doc","docx","xls","xlsx","ppt","pptx","mp3","mp4","csv"]
}
```

All listeners check current consent state (reading the same cookie /
Consent Mode state used above) before firing `gtag('event', ...)` calls —
no event listener fires GA4 events while `analytics_storage` is denied.

- **Outbound links**: delegated `click` listener on `document`, closest
  `a[href]` ancestor, compare `link.hostname` to `location.hostname`.
  `gtag('event', 'click', {link_url, link_domain, outbound: true})`.
- **Downloads**: same delegated listener, checks the link's path extension
  against `downloadExtensions`. `gtag('event', 'file_download',
  {file_extension, link_url})`.
- **Scroll depth**: throttled (rAF-based) scroll listener, milestones
  25/50/75/90 tracked in a `Set` to fire once each per page load.
  `gtag('event', 'scroll', {percent_scrolled})`.
- **Forms**: `submit` listener on `document`, delegated to any native
  `<form>`. `gtag('event', 'form_submit', {form_id: form.id || null})`.
  Documented limitation (in code comment + readme): AJAX/page-builder forms
  that intercept `submit` and call `preventDefault` before this listener
  fires, or that never trigger a native submit event, will not be captured.

## Error handling / edge cases

- Neither GA4 nor GTM configured → nothing enqueues (no consent snippet, no
  banner, no tracking.js) — matches current behavior of "off by default."
- Both GA4 and GTM configured → both load; not blocked, UI just advises
  against it.
- GTM sanitize callback mirrors the existing GA4 one: uppercase, regex
  `^GTM-[A-Z0-9]+$`, `add_settings_error` + revert to previous value on
  mismatch.
- Banner CSS/JS only enqueued when the built-in banner path is actually
  active (CMP not detected AND setting on) — decided client-side at
  runtime, so the PHP side enqueues the banner assets whenever the setting
  is on and CMP detection then no-ops the banner render if a CMP is found.
  This trades a small unused-asset-load edge case for simplicity (avoiding
  server-side CMP detection, which isn't reliably possible in PHP).

## Testing

No existing automated test suite. Verification is manual against a local
WP install:

- Settings: save valid/invalid GA4 and GTM IDs, confirm validation errors
  display and invalid values are rejected/reverted.
- Consent: confirm Consent Mode default (`denied`) fires before gtag.js
  loads, via browser network tab / `dataLayer` inspection.
- Banner: with no CMP installed and the setting on, confirm banner renders,
  accept/reject set the cookie and issue the correct `consent update` call.
- CMP fallback: simulate Complianz/Cookiebot presence (stub the global in
  devtools console) and confirm the built-in banner does not render.
- Event tracking: enable each of the four checkboxes independently, confirm
  each fires the expected `gtag('event', ...)` call via console logging or
  GA4 DebugView, and confirm no events fire while consent is denied.
- `uninstall.php`: confirm all seven options are removed after uninstall.

## Open questions

None outstanding — all decisions in this spec were confirmed during
brainstorming (2026-08-30).
