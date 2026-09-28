# Genuine — Recent Sales for WooCommerce

Tasteful "recently purchased" notifications built from your **real** WooCommerce orders. No fake data, ever.

Most social-proof plugins let you *type in* fake notifications. This one refuses to — every toast is a genuine, recent, paid order. That honesty is the whole point, and it's enforced: the optional **Verified** badge can only stay on while your settings stay within genuine limits.

## Features

- **Real orders only.** Pulled live from WooCommerce (HPOS-compatible). No demo/fake mode exists.
- **Three styles** — text only · text + colour swatch · text + product photo.
- **Full theming** — Match page (picks a light or dark card from the background behind it, follows a site light/dark switch) · Preset (Cream / White / Black) · Custom (Background, Text, Title, Accent colour pickers). Corner radius + font too.
- **Stays out of the way** — never covers a button, form control, filter or navigation link: it takes the other bottom corner, or waits until the visitor scrolls. Never hides while hovered or focused.
- **Shows what matters** — up to 2 variation attributes (you pick which, or auto), with an optional colour dot. Simple products can fall back to price or category.
- **Privacy-safe** — first name (or just an initial) + optional town/state. Never a surname, email, address or order value.
- **Bucketed timestamps** — `< 24 hours` → `4+ days ago`. Never precise, never "2 weeks ago".
- **Collision-aware** — automatically lifts above common chat widgets, floating carts and back-to-top buttons.
- **Considered cadence** — configurable delay, on-screen time, gap, per-session cap and dismiss memory. Click a toast to open the product; click × to dismiss.
- **Cache-friendly & light** — proper enqueued assets, a cached REST feed, deferred, zero third-party JS. Excluded from cart, checkout and account pages automatically.
- **Measurable** — optional Google Analytics 4 events for views, clicks and dismissals, sent to the GA4 property already on your site (see below).

## The "Genuine" guarantee

The **Verified purchase** badge is available **only** while your configuration stays honest:

| Setting | Genuine limit |
| --- | --- |
| Data window | ≤ 30 days |
| Per-session cap | on (1–8) |
| Gap between toasts | ≥ 20s |
| First-toast delay | ≥ 5s |
| Order statuses | paid only (Completed / Processing) |

Push any of these outside its limit and the badge auto-switches off, with a notice explaining why. Cosmetic settings (colour, style, position) never affect it. There's a one-click **Reset to genuine defaults**.

## Install

1. Download the latest release ZIP (or clone this repo) into `wp-content/plugins/genuine-recent-sales/`.
2. Activate it (requires WooCommerce, PHP 8.1+, WP 6.2+).
3. **WooCommerce → Recent Sales**, configure, tick **Enable**.

**Auto-updates work out of the box.** [`plugin-update-checker`](https://github.com/YahnisElsts/plugin-update-checker) (v5.7, MIT) is bundled in `lib/`, so new releases published here show up on your Plugins screen like any other update — no WordPress.org required.

## Google Analytics 4 events

On by default. Switch it off at **WooCommerce → Recent Sales → Analytics**. The plugin loads no analytics of its own. It sends events when the page already runs GA4 through `gtag.js`, either way:
- a `gtag('config', 'G-…')` tag, or
- a Google tag (`GT-…`, as Site Kit adds) that loads a GA4 destination.

It sends:

| Event | Fires when | Parameters |
| --- | --- | --- |
| `grs_view` | a toast is actually painted on screen (never in a background tab) | `grs_product`, `grs_seq` |
| `grs_click` | the toast is clicked through to the product (left or middle click) | `grs_product`, `grs_seq`, `link_url` |
| `grs_dismiss` | the × is clicked | `grs_product`, `grs_seq` |

- **Where events go:** each event is addressed with `send_to` to every GA4 (`G-`) destination on the page, so nothing goes to Google Ads or other gtag destinations.
- **When a view counts:** only when the card is actually rendered — never in a background tab, and never while the hide-on-mobile rule has hidden it after a rotation.
- **What `grs_seq` means:** the toast's position in the visitor's session (1, 2, 3…).
- **What is never sent:** customer data — only the product name.
- **Not sent** in admin preview mode, or when the page has no GA4 tag.
- **Tag Manager-only sites** (no `gtag` function) get no events.
- **Per-product reports:** register `grs_product` as an event-scoped custom dimension in GA4 (Admin → Custom definitions). Click-through rate = `grs_click` ÷ `grs_view`.

## Privacy note for store owners

This displays real customers' first names and (optionally) their town/state publicly on your storefront, and serves them from a public REST endpoint (`/wp-json/grs/v1/feed`) — the same data the toast shows. Defaults are conservative (first name + town + state, paid orders, last 14 days). Choose "Initial only" and untick town/state if your jurisdiction or policies call for it. You are responsible for your own GDPR/CCPA compliance.

## Changelog

- **0.4.1** — Display fixes from a live-site audit.
  - **Never covers the page's controls:** hero calls-to-action, filter sidebars, add-to-cart buttons and nav links. If its corner is busy, the toast uses the other bottom corner, or waits and retries without using up the session cap. If the visitor scrolls a control under a toast that is already showing, it fades out early.
  - **"Inherit theme" is now "Match page":** it reads the background behind the toast and shows a light or dark card, following a site's light/dark switch. The system colours it used before turned white on dark sites. The accent colour can now be set in this mode.
  - **Compact card:** unitless line-height (a theme's px line-height no longer inflates it), product names clamped to two lines, and who · when on one line.
  - **Accessibility:** the kicker text meets AA contrast (accent kept for the dot and hover), and focus rings use the card's title colour and beat theme `!important` focus rules. The whole card is the link, and it doesn't auto-hide while hovered or focused. Dismissing hands keyboard focus back.
  - **Tidier who-line:** "QLD · 4+ days ago" spacing, "Deception Bay" casing, and no state repeated when a customer typed it into the city field.
  - **Cache:** the cached feed is rebuilt once after each update.

- **0.4.0** — Optional GA4 events (`grs_view` / `grs_click` / `grs_dismiss`), on by default. A view only counts once the toast is rendered.
  - **Fixed — live sites ran in admin-preview cadence.** `wp_localize_script()` sends `0` as the string `"0"`, which JavaScript reads as true. Every enabled install was using the preview timing: first toast at 1.5 s, a 6.5 s gap, up to 24 per page, looping, with the per-session cap and the dismiss memory ignored.
  - After updating, sites use their saved delay, gap, cap and dismiss settings, so **expect far fewer toasts per visit.**
  - The *Hide on mobile* off-switch had the same bug and now works.
- **0.3.0** — Bundles plugin-update-checker v5.7, so GitHub releases auto-update.
- **0.2.0** — Initial public release. A faded toast no longer blocks clicks, and there is a *Hide on mobile* setting (client-side, cache-safe).

## License

GPL-2.0-or-later. Bundles [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) v5.7 by Jānis Elsts under the MIT license (`lib/plugin-update-checker/license.txt`).
