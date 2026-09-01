# Easy Google Analytics — Tabbed Settings + Consent Banner Design System

## Problem

The plugin's consent banner is currently fixed in appearance: hardcoded
dark colors, hardcoded English wording, no privacy-policy link, and a
single layout (full-width bottom bar). Site owners who want the banner
to match their brand, use different wording, or link to their privacy
policy have no way to do so short of editing plugin CSS/PHP directly —
which breaks on every plugin update.

Separately, the settings page has grown to four cards in a single
scrolling column (Tracking IDs, Consent Mode, Event Tracking, Role
Exclusion) with no room to add a real design/customization surface
without the page becoming unwieldy.

## Goals

1. Split the settings page into two tabs: **Settings** (everything that
   exists today) and **Banner Design** (new — appearance/wording for
   the consent banner).
2. Let the site owner customize the consent banner's background color,
   text color, accept-button color, and reject-button color — either
   via one of 5 built-in palettes (one click sets all four colors) or
   by fine-tuning each color individually afterward.
3. Let the site owner choose between two banner layouts: the current
   full-width bottom bar, or a smaller floating corner box.
4. Let the site owner customize the banner's message text, accept
   button label, and reject button label.
5. Let the site owner add a privacy-policy link into the banner
   message, auto-populated from WordPress's own Privacy Policy page
   when one is set, with a manual override field.
6. Extend the existing live-preview box so all of the above previews
   instantly (before saving) on the settings page itself.

## Non-goals

- No per-page or per-post banner variants — one set of banner settings
  site-wide, same as today.
- No additional layout shapes beyond "bar" and "corner" (no top bar,
  no modal/overlay style) — two is enough to prove the pattern; more
  can follow later if requested.
- No font-family/font-size customization — colors, wording, layout,
  and the privacy link only.
- No changes to the "Manage cookie preferences" reopen tab's own
  position/shape — it automatically inherits the chosen palette's
  colors (via shared CSS custom properties) but its own fixed
  bottom-left placement is unchanged.
- No changes to `EGA_Tracking_Output`, `EGA_Event_Tracking`, or role
  exclusion — this feature only touches settings-page structure and
  `EGA_Consent`'s rendering.
- Not building a generic "tabs" component for reuse elsewhere — this
  is the plugin's first and only tab UI; if a third tab is ever
  needed, the same lightweight pattern extends trivially, but no
  abstraction is built preemptively.

## Approach

### Tabs

Two tab triggers (`<button>` elements, not links — no page reload,
no URL hash dependency) rendered above the existing `.ega-grid`
structure. Two panel wrapper `<div>`s, each containing what is
currently rendered inline in `render_page()`:

- `#ega-tab-panel-settings` — the existing Card 1 (Tracking IDs),
  Card 2 minus banner-appearance content (Consent Mode enable toggle,
  CMP-detection callout — no color/wording/layout controls), Card 3
  (Event Tracking), Card 4 (Role Exclusion), and the sidebar column
  (Diagnostics, Checklist, About). This is a straight move of existing
  markup into a wrapper div — no behavior changes.
- `#ega-tab-panel-design` — new. Contains the palette picker, custom
  color fields, layout picker, wording fields, privacy-link field, and
  an extended live-preview box.

Both panels render unconditionally on every page load (no AJAX, no
conditional PHP) — switching tabs is pure client-side `hidden`
attribute toggling. This means: no data loss when switching tabs
before saving, the browser's native form validation still sees every
field, and a single `<form>`/`options.php` submit still saves both
tabs' fields together, exactly like today's single-tab form. Active
tab is not persisted (always opens on Settings) — this is a UI
convenience, not state worth an option or a cookie.

Sidebar column (Diagnostics/Checklist/About) stays visible only on the
Settings tab, since its content (GA4/GTM status, event-module count)
isn't relevant to banner design. The Design tab's own content is wide
enough (palette swatches + live preview) that it does not need a
sidebar.

### Data model (new options)

All new options are registered in the existing
`for_you_google_analytics_options` settings group (same group the
`for_you_google_analytics_options` form already posts to — one Save
Changes button continues to save everything).

| Option | Type | Default | Notes |
|---|---|---|---|
| `for_you_google_analytics_banner_palette` | string | `'dark'` | One of `dark`, `light`, `minimal`, `brand-blue`, `high-contrast`, `custom`. |
| `for_you_google_analytics_banner_bg_color` | string (hex) | `#1e1e1e` (Dark palette's bg) | |
| `for_you_google_analytics_banner_text_color` | string (hex) | `#ffffff` | |
| `for_you_google_analytics_banner_accept_color` | string (hex) | `#2271b1` | |
| `for_you_google_analytics_banner_reject_color` | string (hex) | `#ffffff` (Dark palette's reject border/text color) | |
| `for_you_google_analytics_banner_layout` | string | `'bar'` | `bar` or `corner`. |
| `for_you_google_analytics_banner_message` | string (textarea) | `''` (falls back to built-in default string at render time) | |
| `for_you_google_analytics_banner_accept_label` | string | `''` (falls back to "Accept") | |
| `for_you_google_analytics_banner_reject_label` | string | `''` (falls back to "Reject") | |
| `for_you_google_analytics_banner_privacy_url` | string (URL) | `''` | Blank means "auto-resolve from `get_privacy_policy_url()`" at render time — see Privacy link section. |

Rationale for storing wording fields as `''`-by-default rather than
pre-filled with the English default text: this correctly distinguishes
"site owner hasn't customized this" from "site owner explicitly wants
different text," and means future default-wording tweaks (e.g. a typo
fix) apply automatically to every site that never customized it,
rather than being frozen into every install's database the moment the
option is first saved. Sites that already ran the old hardcoded-text
banner see no visible change on upgrade, since the fallback string
matches today's wording exactly.

### Palette presets (PHP-side source of truth)

A single associative array, defined once as a constant/method on
`EGA_Consent` (e.g. `EGA_Consent::get_palettes()`), shared by:
1. The sanitize callback for `banner_palette` — when a known preset
   name is submitted, the four color options are overwritten with that
   preset's values server-side (so a preset selection is authoritative
   even if the hidden color-input values sent alongside it were stale
   or tampered with).
2. The settings-page inline JS — the same five presets' colors are
   also localized into JS (via `wp_localize_script`, matching the
   plugin's established pattern) so clicking a palette swatch updates
   the color pickers and live preview instantly, without a form
   submit.

Selecting `custom` in the palette dropdown/swatch-row does not
overwrite the four color options — whatever is currently in the four
color fields (individually edited or left over from the last preset)
is saved as-is.

| Palette key | Background | Text | Accept | Reject |
|---|---|---|---|---|
| `dark` | `#1e1e1e` | `#ffffff` | `#2271b1` | `#ffffff` (outline style) |
| `light` | `#ffffff` | `#1e1e1e` | `#2271b1` | `#f0f0f1` (filled, dark text) |
| `minimal` | `#f8f9fa` | `#3c434a` | `#3c434a` | `#3c434a` (outline style) |
| `brand-blue` | `#0f172a` | `#e2e8f0` | `#3b82f6` | `#93c5fd` (outline style) |
| `high-contrast` | `#000000` | `#ffffff` | `#ffcc00` (black text) | `#ffffff` (filled, black text, thick border) |

The reject button's outline-vs-filled treatment and its text color
(dark-on-light for filled variants, matching-color-on-transparent for
outline variants) is derived in CSS from the same single reject-color
custom property plus a `data-style` attribute per palette (`outline`
or `filled`) rather than a second stored color — keeping the data
model at exactly 4 stored colors per palette, matching the "4 colors"
scope decided earlier. Exactly two of the five presets use `filled`
(`light`, `high-contrast`); the remaining three (`dark`, `minimal`,
`brand-blue`) use `outline`. This filled/outline choice per palette is
a fixed property of each preset (part of the same palette-definition
array, not independently configurable) — selecting `custom` defaults
to `outline` per the Error handling section.

### Color sanitization

New shared sanitize callback `sanitize_hex_color($input, $fallback)`:
strips whitespace, validates against `/^#[0-9a-fA-F]{6}$/`, returns the
validated value or `$fallback` (the option's own current stored value,
matching the existing `sanitize_ga4_code`/`sanitize_gtm_id` pattern of
falling back to the last-good value plus an `add_settings_error()`
message) if invalid. WordPress core's `sanitize_hex_color()` function
is deliberately not reused here because it returns `''` (not a
fallback) on invalid input and provides no way to surface a settings
error — this plugin's established pattern always preserves the last
valid value and tells the user why via `add_settings_error()`.

### Layout

`for_you_google_analytics_banner_layout` drives a class on the
banner's root element: `ega-layout-bar` (default, current full-width
fixed-bottom behavior, unchanged CSS) or `ega-layout-corner` (new — a
fixed-size rounded box anchored bottom-right, matching the visual
weight of the existing "Manage cookie preferences" reopen tab which
already lives bottom-left). Both layouts share the same markup
structure (message paragraph + two buttons) — only positioning/sizing
CSS differs between the two classes, no separate PHP rendering branch.

### Wording and privacy link

`render_banner_markup()` resolves final strings at render time:

```php
$message       = get_option('for_you_google_analytics_banner_message', '');
$message       = $message !== '' ? $message : __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics');
$accept_label  = get_option('for_you_google_analytics_banner_accept_label', '');
$accept_label  = $accept_label !== '' ? $accept_label : __('Accept', 'for-you-google-analytics');
$reject_label  = get_option('for_you_google_analytics_banner_reject_label', '');
$reject_label  = $reject_label !== '' ? $reject_label : __('Reject', 'for-you-google-analytics');

$privacy_url = get_option('for_you_google_analytics_banner_privacy_url', '');
if ($privacy_url === '') {
    $privacy_url = get_privacy_policy_url(); // WP core; '' if no Privacy Policy page is set
}
```

If `$privacy_url` resolves to a non-empty string, the banner message
paragraph appends a link (`esc_url`, `esc_html__('Learn more', ...)`
as link text, opening in the same tab per standard privacy-policy-link
convention — no `target="_blank"`) after the message text. If it
resolves empty (no manual URL and no WP Privacy Policy page set), no
link is rendered — this is not an error state, just an absent optional
feature, matching how GTM being unset today simply omits the GTM
script block rather than showing a warning.

### CSS custom properties (frontend rendering)

`render_banner_markup()`'s root banner `<div>` gets inline custom
properties reflecting the resolved colors:

```php
$style = sprintf(
    '--ega-banner-bg:%s;--ega-banner-text:%s;--ega-banner-accept:%s;--ega-banner-reject:%s;',
    esc_attr($bg_color), esc_attr($text_color), esc_attr($accept_color), esc_attr($reject_color)
);
```

`assets/consent-banner.css` replaces its hardcoded color values with
`var(--ega-banner-bg, #1e1e1e)` etc. (fallback value = today's
hardcoded default, so the CSS file remains valid/sensible even if
somehow loaded without the inline custom properties present — e.g. a
cached page snapshot). The reject button's outline-vs-filled style is
controlled by a `data-reject-style="outline|filled"` attribute (set
from the palette's or custom selection's style, stored as a 6th tiny
piece of derived state — not a stored option, computed at render time
from which named palette is active, or defaulting to `outline` when
`custom`).

The "Manage cookie preferences" reopen tab (`#ega-consent-manage`)
inherits the same custom properties by being a CSS-sibling under the
same properties scope (defined at `:root` or on a shared ancestor via
inline style on a wrapping element) — no separate color options needed
for it, addressing the design goal that it visually matches the
banner it reopens.

### Live preview

The existing `#ega-consent-preview` box (currently a static markup
snapshot that only toggles visibility with the enable/disable switch)
is extended with a small inline script: on `input`/`change` of any
Design-tab field (palette swatch click, individual color picker,
layout radio, wording fields), update the preview box's inline styles
and text content to match — using the same CSS custom property
mechanism as the real banner, so the preview is visually identical to
what will actually render, not a re-implementation. This reuses
jQuery (already a dependency of `admin.js`) rather than introducing a
new admin script file, since it is a small addition to existing
preview logic already in `admin.js`.

### File changes

Modified:
```
includes/class-settings.php   tab markup, Design-tab fields, palette JS localization
includes/class-consent.php    get_palettes(), sanitize callbacks, dynamic banner markup
assets/admin.css              tab styles, palette swatch styles, corner-layout preview styles
assets/admin.js               tab switching, palette-swatch click handler, live-preview sync
assets/consent-banner.css     CSS custom properties, corner-layout ruleset, reject outline/filled variants
```

No new files. `assets/consent-banner.js` (visitor-facing accept/reject/
reopen logic) is unaffected — it manipulates the same element IDs/
classes regardless of chosen colors or layout, since those are purely
CSS-driven.

## Error handling / edge cases

- Invalid hex color submitted directly (e.g. via a non-JS form post or
  a tampered request): falls back to the option's last-saved value,
  with `add_settings_error()` feedback — same UX as the existing GA4/
  GTM ID validation.
- `banner_palette` submitted as an unrecognized string (not one of the
  6 known keys): treated as `custom` — the four color fields are used
  as-submitted (still individually sanitized), no error shown, since
  an unrecognized-but-harmless value is not user-facing-error-worthy
  the way a malformed GA4 code is.
- No WP Privacy Policy page set AND no manual URL entered: banner
  renders with no privacy link, silently — not a warning state.
- Existing installs upgrading to this version: every new option's
  registered default (`'dark'` palette, dark palette's colors, `'bar'`
  layout, empty wording fields) reproduces today's exact banner
  appearance and wording with zero visible change until the site owner
  visits the new Design tab and changes something.
- Switching tabs with unsaved changes on the other tab: no data loss,
  since both panels are part of the same always-rendered form (see
  Tabs section) — there is nothing to warn about.

## Testing

No automated test suite exists for this plugin (unchanged from
previous versions). Verification is: `php -l` / `node --check` on
every touched file, manual code trace confirming palette selection
correctly overwrites all four color options server-side, and a written
checklist for human QA on a live WordPress install covering: switching
tabs preserves other tab's unsaved field values; each of the 5
palettes visually applies correctly to both the settings-page preview
and the actual frontend banner; custom color overrides after selecting
a palette persist correctly; both layouts render correctly on the
frontend; a configured WP Privacy Policy page's URL appears
automatically in the banner with no manual URL set; a manually-entered
privacy URL overrides the auto-detected one; blanking a wording field
and saving falls back to default text, not a blank/broken banner.

## Open questions

None outstanding — all decisions confirmed during brainstorming
(2026-09-01).
