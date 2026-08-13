# Genuine — Recent Sales for WooCommerce

Tasteful "recently purchased" notifications built from your **real** WooCommerce orders. No fake data, ever.

Most social-proof plugins let you *type in* fake notifications. This one refuses to — every toast is a genuine, recent, paid order. That honesty is the whole point, and it's enforced: the optional **Verified** badge can only stay on while your settings stay within genuine limits.

## Features

- **Real orders only.** Pulled live from WooCommerce (HPOS-compatible). No demo/fake mode exists.
- **Three styles** — text only · text + colour swatch · text + product photo.
- **Full theming** — Inherit theme · Preset (Cream / White / Black) · Custom (Background, Text, Title, Accent colour pickers). Corner radius + font too.
- **Shows what matters** — up to 2 variation attributes (you pick which, or auto), with an optional colour dot. Simple products can fall back to price or category.
- **Privacy-safe** — first name (or just an initial) + optional town/state. Never a surname, email, address or order value.
- **Bucketed timestamps** — `< 24 hours` → `4+ days ago`. Never precise, never "2 weeks ago".
- **Collision-aware** — automatically lifts above common chat widgets, floating carts and back-to-top buttons.
- **Considered cadence** — configurable delay, on-screen time, gap, per-session cap and dismiss memory. Click a toast to open the product; click × to dismiss.
- **Cache-friendly & light** — proper enqueued assets, a cached REST feed, deferred, zero third-party JS. Excluded from cart, checkout and account pages automatically.

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

**Auto-updates:** drop [`plugin-update-checker`](https://github.com/YahnisElsts/plugin-update-checker) into `lib/plugin-update-checker/` and updates flow from this repo's GitHub releases — no WordPress.org required.

## Privacy note for store owners

This displays real customers' first names and (optionally) their town/state publicly on your storefront, and serves them from a public REST endpoint (`/wp-json/grs/v1/feed`) — the same data the toast shows. Defaults are conservative (first name + town + state, paid orders, last 14 days). Choose "Initial only" and untick town/state if your jurisdiction or policies call for it. You are responsible for your own GDPR/CCPA compliance.

## License

GPL-2.0-or-later.
