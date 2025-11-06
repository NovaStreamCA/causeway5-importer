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
