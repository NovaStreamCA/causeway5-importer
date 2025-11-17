# Causeway Listings Importer — Headless Mode

This plugin imports listings and taxonomies from the Causeway API into WordPress and can optionally operate in a “headless” mode to feed an external frontend.

## Headless mode

Toggle headless behavior in: Listings → Settings → Causeway Import Settings.

- Is this for a headless environment? (`is_headless`)
  - OFF (default): Import only. No export functionality is exposed or scheduled.
  - ON: Export features are enabled so an external frontend can fetch data.

### Settings

- Causeway API URL (`causeway_api_url`) — required
  - Example: https://api-causeway5.novastream.dev/
- Public Website URL (`causeway_public_url`) — required when headless is ON
  - The external site to notify after imports. Example: https://cbisland.ca
- Headless API Secret (`headless_api_secret`) — required when headless is ON
  - Shared secret sent as `x-causeway-secret` when notifying the external site.

### Behavior by mode

When headless is OFF:
- Manual Export button is hidden on the admin page.
- Manual export action is blocked.
- Export cron is not scheduled.
- REST endpoints below are NOT registered.
- Post-import notification to the public site is skipped.

When headless is ON:
- Manual Export button appears on the admin page (Listings → Import/Export).
- Export cron is scheduled twice daily (`causeway_cron_export_hook`).
- REST endpoints are registered:
  - GET /wp-json/causeway/v1/listings
  - GET /wp-json/causeway/v1/taxonomy/{taxonomy}
- After a successful import, the plugin notifies the external site by POSTing to:
  - {public_url}/api/fetch-causeway with `x-causeway-secret` header

## Admin usage

- Import: Listings → Import/Export → “Start Import”
- Export (headless only): Listings → Import/Export → “Start Export”
  - Triggers a notification to the public site to refresh its data.

## WP-CLI

- Import: `wp causeway import`
  - Lifts PHP limits and runs the full import.

## REST API (headless ON)

- GET /wp-json/causeway/v1/listings
  - Lists published listings with ACF-enriched fields, taxonomy data, and optional translations.
- GET /wp-json/causeway/v1/taxonomy/{taxonomy}
  - Returns all terms for a given taxonomy with ACF fields and relationships.

Notes:
- ACF is optional for the exporter payload; values are included when fields exist.
- Translations are included when WPML is active.

## Cron

- Import cron: `causeway_cron_hook` (twice daily)
- Export cron: `causeway_cron_export_hook` (twice daily, headless ON only)
- Housekeeping: `causeway_clear_cron_hook` (hourly)

## Updates via GitHub (public repo)

This plugin is configured to update directly from GitHub Releases.

How to publish an update:

1) Bump the Version in `causeway/causeway.php` (e.g., 1.1.0).
2) Create a GitHub Release with tag `v1.1.0` (or `1.1.0`).

Notes:
- No asset zip required. The updater uses GitHub's zipball and automatically renames the extracted folder to `causeway/` so the update installs cleanly.
- Tokens are NOT required when the repo is public.
- WordPress checks periodically; visiting the Plugins screen triggers a check. You can also force-check via a management plugin or a transient clear.

## Security

- The headless notification uses `x-causeway-secret` header. Ensure the public site validates it.
- REST endpoints are read-only and only registered when headless is ON.

## Troubleshooting

- “Export is disabled: this site is not configured as headless.”
  - Turn on “Is this for a headless environment?” and save settings.
- “Public URL not set. Cannot notify.”
  - Fill in `Public Website URL` and `Headless API Secret` on the settings page.
- No REST endpoints under /wp-json/causeway/v1
  - Headless may be OFF. Enable it and flush permalinks if needed.
- Import succeeds but no changes on public site
  - Check that the public site’s `/api/fetch-causeway` endpoint accepts the request and validates the `x-causeway-secret`.

## Notes

- Editing of listings and Causeway taxonomies can be locked with the included `includes/disable-edit.php` (toggle its require in `causeway.php` if needed).
- Keep ACF groups active; the plugin reads and writes many fields on posts and terms.

## Listings Shortcode

The shortcode `[causeway_listings]` renders the same listings grid as the ACF block (`Listings Grid`) and supports the block’s client-side pagination pattern.

Attributes (parity with block fields):

- `count`: Number of listings to fetch (default 6)
- `columns`: Grid columns (1–6, default 3)
- `type`: Single listing-type slug
- `types`: Comma-separated listing-type slugs (overrides `type` when present)
- `categories`: Comma-separated listings-category slugs
- `orderby`: `date`, `title`, or `menu_order` (default `date`)
- `order`: `ASC` or `DESC` (default `DESC`)
- `show_pagination`: `true|false` enable client-side pagination (adds `data-page-limit` + page list container)
- `per_page`: Page size for client-side pagination (defaults to `count` when omitted)
- `show_filterbar`: `true|false` include the filter bar template above the grid

Usage examples:

```text
[causeway_listings]
[causeway_listings count="9" columns="3" orderby="title" order="ASC"]
[causeway_listings types="event,festival" categories="music,food"]
[causeway_listings show_filterbar="true" types="event"]
[causeway_listings show_pagination="true" per_page="6" count="12"]
```

Notes:
- Prefer `types` for multiple listing-type filters; `type` remains for single.
- Client-side pagination only; server-side pagination has been removed.
- `pagination` is maintained solely for backward compatibility and maps to client-side mode.
- The filter bar template lookup mirrors the block (theme override paths: `causeway/listings-filterbar.php`, `listings-filterbar.php`, `template-parts/causeway/listings-filterbar.php`).

## Assets & SCSS

The plugin now supports authoring styles in SCSS while still distributing compiled CSS for WordPress to enqueue.

Source directory:
- `src/scss/` — SCSS partials and the single entry file `causeway.scss`.
  - `_variables.scss` — design tokens (colors, spacing, breakpoints).
  - `_shared.scss` — shared components (container, chips).
  - `_listing-archive.scss` — archive / loop styles (grid, cards, pagination).
  - `_listing-single.scss` — single listing page styles (hero, meta, sidebar, gallery).
  - `causeway.scss` — aggregates partials via `@use`.

Build output:
- `assets/css/` — compiled CSS files (e.g. `causeway.css`). Only these are loaded by WordPress.

Tooling:
- `package.json` includes scripts using the Dart Sass CLI.
  - `npm run build:css` — one-off build (compressed).
  - `npm run watch:css` — watch mode during development.

Quick start:
```bash
cd wp-content/plugins/causeway5-importer
npm install
npm run build:css
```

Recommended workflow:
1. Edit SCSS in `src/scss/` (add partials and import them in `causeway.scss`).
2. Run `npm run watch:css` while developing.
3. Commit both the updated SCSS and the compiled CSS so production sites have the asset without needing build tooling.

Notes:
- Do not enqueue SCSS directly; only enqueue the compiled CSS file.
- If you rename the entry SCSS (`causeway.scss`), keep the output filename `causeway.css` (or update any enqueue references accordingly).
- You can extend variables in `_variables.scss` for color, spacing, breakpoints.
