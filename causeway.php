<?php

// File: causeway.php
/**
 * Plugin Name: Causeway Listings Importer
 * Description: Imports listings and taxonomies from Causeway API into WordPress with WPML and ACF integration.
 * Version: 1.0.4
 * Author: NovaStream
 * Update URI: https://github.com/NovaStreamCA/causeway5-importer
 */

if (!defined('ABSPATH')) {
    exit;
}

// ──────────────────────────────────────────
// Composer autoload – needed for Carbon & RRule
// ──────────────────────────────────────────
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>
            Causeway Importer: run <code>composer install</code> inside the plugin to generate the autoloader.
        </p></div>';
    });
    return;
}

require_once plugin_dir_path(__FILE__) . 'admin/acf-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-causeway-importer.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-causeway-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/causeway-exporter.php';
require_once plugin_dir_path(__FILE__) . 'includes/github-updater.php';
require_once plugin_dir_path(__FILE__) . 'includes/listings-loop.php';
require_once plugin_dir_path(__FILE__) . 'includes/acf-blocks.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
// TODO - enable this
// require_once plugin_dir_path(__FILE__) . 'includes/disable-edit.php';

// Overrides acf taxonomy input to return causeway id if selected
require_once plugin_dir_path(__FILE__) . 'includes/causeway-taxonomy-acf-field.php';
require_once plugin_dir_path(__FILE__) . 'includes/causeway-postobject-acf-field.php';

// Admin-only init for admin UI
add_action('admin_init', function () {
    Causeway_Admin::init();
});

// Initialize GitHub updater early so checks also work via cron and non-admin contexts
add_action('init', function () {
    if (class_exists('Causeway_GitHub_Updater')) {
        // Instantiate only once
        static $done = false;
        if (!$done) {
            new Causeway_GitHub_Updater(__FILE__, 'NovaStreamCA', 'causeway5-importer');
            $done = true;
        }
    }
});

function causeway_register_listing_post_type()
{
    register_post_type('listing', [
        'labels' => [
            'name' => __('Causeway'),
            'singular_name' => __('Listing'),
            'add_new' => __('Add Listing'),
            'add_new_item' => __('Add New Listing'),
            'edit_item' => __('Edit Listing'),
            'new_item' => __('New Listing'),
            'view_item' => __('View Listing'),
            'search_items' => __('Search Listings'),
            'not_found' => __('No listings found'),
            'not_found_in_trash' => __('No listings found in trash'),
            'all_items' => __('All Listings'),
            'menu_name' => __('Causeway'),
            'name_admin_bar' => __('Causeway'),
        ],
        'public' => true,
        'publicly_queryable' => true,
        'query_var' => 'listing',
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
        'rewrite' => ['slug' => 'listing', 'with_front' => false,],
        'menu_icon' => 'dashicons-admin-site-alt3', // You can swap this with any Dashicon or SVG
    ]);
}
add_action('init', 'causeway_register_listing_post_type');

function causeway_register_taxonomies()
{
    $taxonomies = [
        'listing-type' => ['singular' => 'Type', 'plural' => 'Types'],
        'listing-campaigns' => ['singular' => 'Campaign', 'plural' => 'Campaigns'],
        'listing-areas' => ['singular' => 'Area', 'plural' => 'Areas'],
        'listing-counties' => ['singular' => 'County', 'plural' => 'Counties'],
        'listing-communities' => ['singular' => 'Community', 'plural' => 'Communities'],
        'listing-regions' => ['singular' => 'Region', 'plural' => 'Regions'],
        'listings-category' => ['singular' => 'Category', 'plural' => 'Categories'],
        'listings-seasons' => ['singular' => 'Season', 'plural' => 'Seasons'],
        'listings-amenities' => ['singular' => 'Amenity', 'plural' => 'Amenities'],
    ];

    foreach ($taxonomies as $slug => $labels) {
        register_taxonomy($slug, 'listing', [
            'labels' => [
                'name' => __($labels['plural']),
                'singular_name' => __($labels['singular']),
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => $slug],
        ]);
    }
}
add_action('init', 'causeway_register_taxonomies');

// Allow modern image formats to be uploaded/sideloaded (AVIF/HEIC/HEIF)
add_filter('upload_mimes', function ($mimes) {
    $mimes['avif'] = 'image/avif';
    // Optional extras if your content may include these
    $mimes['heic'] = 'image/heic';
    $mimes['heif'] = 'image/heif';
    return $mimes;
});

add_action('admin_post_causeway_manual_import', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['causeway_import_nonce']) || !wp_verify_nonce($_POST['causeway_import_nonce'], 'causeway_import_action')) {
        wp_die('Invalid nonce');
    }

    // Schedule a one-off cron event to run the import in the background ASAP
    // Using the existing 'causeway_cron_hook' which calls Causeway_Importer::import()
    if (!wp_next_scheduled('causeway_cron_hook')) {
        // No import currently scheduled; schedule one to run immediately
        wp_schedule_single_event(time() + 1, 'causeway_cron_hook');
    } else {
        // Even if a recurring import exists, still add a single event to run ASAP
        wp_schedule_single_event(time() + 1, 'causeway_cron_hook');
    }

    // Optionally try to spawn cron right away (best-effort, harmless if it fails)
    if (function_exists('spawn_cron')) {
        spawn_cron(time());
    }

    // Redirect back to admin with queued notice
    wp_redirect(add_query_arg('import_queued', '1', admin_url('edit.php?post_type=listing&page=causeway-importer')));
    exit;
});

add_action('admin_post_causeway_manual_export', function () {
    if (!current_user_can('manage_options') || !check_admin_referer('causeway_export_action', 'causeway_export_nonce')) {
        wp_die('Unauthorized or nonce check failed.');
    }

    // Gate export logic for non-headless installations
    if (!get_field('is_headless', 'option')) {
        wp_die('Export is disabled: this site is not configured as headless.');
    }

    if (class_exists('Causeway_Importer')) {
        Causeway_Importer::export_listings();
    }

    wp_redirect(add_query_arg('exported', '1', wp_get_referer()));
    exit;
});

// Register CRON on plugin activation
register_activation_hook(__FILE__, function () {
    // Ensure registration happens before flush so rules exist
    causeway_register_listing_post_type();
    causeway_register_taxonomies();
    if (!wp_next_scheduled('causeway_cron_hook')) {
        wp_schedule_event(time(), 'twicedaily', 'causeway_cron_hook');
    }
    if (get_field('is_headless', 'option')) {
        if (!wp_next_scheduled('causeway_cron_export_hook')) {
            wp_schedule_event(time() + 60 * 30, 'twicedaily', 'causeway_cron_export_hook');
        }
    } else {
        // Ensure export cron is not scheduled
        $ts = wp_next_scheduled('causeway_cron_export_hook');
        if ($ts) {
            wp_unschedule_event($ts, 'causeway_cron_export_hook');
        }
    }
    if (!wp_next_scheduled('causeway_clear_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'causeway_clear_cron_hook');
    }

    // Attempt an immediate taxonomy-only import on fresh activation (best-effort)
    if (class_exists('Causeway_Importer')) {
        try {
            Causeway_Importer::import_taxonomies_only();
        } catch (Throwable $e) {
            error_log('[Causeway] Taxonomy-only import on activation failed: ' . $e->getMessage());
        }
    }

    // Flush rewrites so /listing/* and archive work immediately
    flush_rewrite_rules();
});

// Safety fallback in case plugin was already active
add_action('init', function () {
    if (!wp_next_scheduled('causeway_cron_hook')) {
        wp_schedule_event(time(), 'twicedaily', 'causeway_cron_hook');
    }
    if (get_field('is_headless', 'option')) {
        if (!wp_next_scheduled('causeway_cron_export_hook')) {
            wp_schedule_event(time() + 60 * 30, 'twicedaily', 'causeway_cron_export_hook');
        }
    }
    if (!wp_next_scheduled('causeway_clear_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'causeway_clear_cron_hook');
    }
});

// Last-resort: auto-flush rewrites once if listing rules absent (helps when code updated without reactivation)
add_action('init', function () {
    $rules = get_option('rewrite_rules');
    if (!is_array($rules)) { return; }
    $has_listing = false;
    foreach ($rules as $regex => $query) {
        if (strpos($regex, 'listing/') !== false) { $has_listing = true; break; }
    }
    if (!$has_listing && !get_transient('causeway_rewrite_autoflush')) {
        // Ensure CPT/tax are registered before flush
        causeway_register_listing_post_type();
        causeway_register_taxonomies();
        flush_rewrite_rules(false);
        set_transient('causeway_rewrite_autoflush', 1, 300);
        error_log('[Causeway] Auto-flushed rewrite rules for listing CPT');
    }
}, 20);

// Deactivate CRON on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('causeway_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'causeway_cron_hook');
    }

    $timestamp2 = wp_next_scheduled('causeway_cron_export_hook');
    if ($timestamp2) {
        wp_unschedule_event($timestamp2, 'causeway_cron_export_hook');
    }

    flush_rewrite_rules();
});

add_action('causeway_cron_hook', 'run_causeway_import_export');
add_action('causeway_cron_export_hook', 'run_causeway_export');
add_action('causeway_clear_cron_hook', 'clear_causeway_status');

function run_causeway_import_export()
{
    error_log('🕑 Running Causeway import/export via cron @ ' . date('Y-m-d H:i:s'));
    if (class_exists('Causeway_Importer')) {
        Causeway_Importer::import();
    }
}

function run_causeway_export()
{
    if (!get_field('is_headless', 'option')) {
        error_log('Skipping Causeway export via cron: not headless');
        return;
    }
    error_log('🕑 Running Causeway export via cron @ ' . date('Y-m-d H:i:s'));
    if (class_exists('Causeway_Importer')) {
        Causeway_Importer::export();
    }
}

function clear_causeway_status()
{
    update_option('importing_causeway', '0');
}

if (defined('WP_CLI') && WP_CLI) {

    /**
     * Manage Causeway imports.
     */
    class Causeway_CLI_Command
    {
        /**
         * Run the full import.
         *
         * ## EXAMPLES
         *
         *     wp causeway import
         *
         * @when after_wp_load
         */
        public function import()
        {
            $start = microtime(true);

            // Lift PHP limits if you like:
            ini_set('memory_limit', '1G');
            set_time_limit(0);

            Causeway_Importer::import();

            $secs = number_format(microtime(true) - $start, 2);
            WP_CLI::success("Import finished in {$secs}s");
        }

        /**
         * Test occurrence expansion for a JSON dates payload (debug helper).
         *
         * ## OPTIONS
         *
         * [--json=<json>]
         * : Inline JSON string representing an array of date objects (same shape as API dates repeater),
         *   or an object with a top-level "dates" key.
         *
         * [--file=<path>]
         * : Path to a JSON file containing the dates array or object with "dates" key.
         *
         * [--year=<year>]
         * : Force the target window year (default = current year).
         *
         * [--tz=<timezone>]
         * : Preferred fallback timezone identifier (default UTC) when RRULE lacks TZID/"Z".
         *
         * [--all]
         * : Show all occurrences instead of the first 25.
         *
         * [--jsonout]
         * : Dump the raw occurrences array as pretty JSON after the table.
         *
         * [--format=<table|json>]
         * : Convenience output mode. --format=json is equivalent to --jsonout (table suppressed).
         *
         * [--sample=<name>]
         * : Use a built-in sample instead of providing JSON. Available: 'utc' (default fallback), 'halifax'.
         *
         * ## EXAMPLES
         *
         *     wp causeway test-occurrences --json='[{"start_at":"2025-10-17 14:00:00","end_at":"2026-02-06 21:00:00","rrule":"DTSTART:20251017T140000Z\nRRULE:INTERVAL=1;UNTIL=20260206T210000Z;FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR"}]' --year=2025
         *     wp causeway test-occurrences --file=dates.json --tz=America/Halifax --format=json
         *     wp causeway test-occurrences --sample=halifax --year=2025
         *
         * @subcommand test-occurrences
         * @when after_wp_load
         */
        public function test_occurrences($args, $assoc_args)
        {
            // Accept JSON input from multiple sources for robustness across shells/WP-CLI parsing quirks.
            $raw = null;

            // 1) Primary: --json inline string
            if (isset($assoc_args['json']) && $assoc_args['json'] !== '') {
                $raw = (string) $assoc_args['json'];
            }

            // 2) Aliases: --data or --json-input
            if ($raw === null) {
                foreach ([ 'data', 'json-input', 'json_input' ] as $alt) {
                    if (isset($assoc_args[$alt]) && $assoc_args[$alt] !== '') {
                        $raw = (string) $assoc_args[$alt];
                        break;
                    }
                }
            }

            // 3) File path via --file (or --infile alias)
            if ($raw === null) {
                $fileOpt = null;
                if (!empty($assoc_args['file'])) {
                    $fileOpt = $assoc_args['file'];
                } elseif (!empty($assoc_args['infile'])) {
                    $fileOpt = $assoc_args['infile'];
                }
                if (!empty($fileOpt)) {
                    $path = $fileOpt;
                    if (!file_exists($path)) {
                        WP_CLI::error("File not found: $path");
                        return;
                    }
                    $raw = file_get_contents($path);
                }
            }

            // 4) First positional arg as JSON string
            if ($raw === null && !empty($args) && is_string($args[0])) {
                $first = trim($args[0]);
                if ($first !== '' && ($first[0] === '{' || $first[0] === '[')) {
                    $raw = $first;
                }
            }

            $dates = null;
            // Built-in samples via --sample
            $sample = isset($assoc_args['sample']) ? strtolower($assoc_args['sample']) : null;
            if ($sample) {
                if ($sample === 'halifax') {
                    WP_CLI::log("Using sample: halifax (TZID=America/Halifax with 11:00 local wall time)");
                    $dates = [
                        [
                            'start_at' => '2025-10-17 11:00:00', // local wall time reference
                            'end_at'   => '2026-02-06 18:00:00', // 7h duration to match sample pattern
                            'rrule'    => "DTSTART;TZID=America/Halifax:20251017T110000\nRRULE:INTERVAL=1;UNTIL=20260206T210000Z;FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR",
                        ],
                    ];
                } else { // default/utc
                    WP_CLI::log("Using sample: utc (DTSTART with Z; UTC-anchored times)");
                    $dates = [
                        [
                            'start_at' => '2025-10-17 14:00:00',
                            'end_at'   => '2026-02-06 21:00:00',
                            'rrule'    => "DTSTART:20251017T140000Z\nRRULE:INTERVAL=1;UNTIL=20260206T210000Z;FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR",
                        ],
                    ];
                }
            } elseif ($raw === null) {
                // Fallback to UTC sample when nothing provided
                WP_CLI::log('No --json/--file provided; using built-in sample payload (utc).');
                $dates = [
                    [
                        'start_at' => '2025-10-17 14:00:00',
                        'end_at'   => '2026-02-06 21:00:00',
                        'rrule'    => "DTSTART:20251017T140000Z\nRRULE:INTERVAL=1;UNTIL=20260206T210000Z;FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR",
                    ],
                ];
            } else {
                $decoded = json_decode($raw, true);
                if ($decoded === null || json_last_error() !== JSON_ERROR_NONE) {
                    WP_CLI::error('Invalid JSON input: ' . json_last_error_msg());
                    return;
                }

                $dates = isset($decoded['dates']) && is_array($decoded['dates']) ? $decoded['dates'] : (is_array($decoded) ? $decoded : []);
                if (!is_array($dates)) {
                    WP_CLI::error('Dates payload must be an array or object with "dates" key');
                    return;
                }
            }

            $year   = isset($assoc_args['year']) ? (int)$assoc_args['year'] : null;
            // If --tz is not provided, pass NULL so importer uses the WordPress site timezone
            $tz     = (array_key_exists('tz', $assoc_args) && $assoc_args['tz'] !== '') ? $assoc_args['tz'] : null;
            $format = isset($assoc_args['format']) ? strtolower($assoc_args['format']) : 'table';

            if (!in_array($format, ['table','json'], true)) {
                WP_CLI::error('--format must be table or json');
            }

            if (!class_exists('Causeway_Importer')) {
                WP_CLI::error('Causeway_Importer class not loaded.');
            }

            if (!method_exists('Causeway_Importer', 'debug_build_occurrences')) {
                WP_CLI::error('debug_build_occurrences() is missing from Causeway_Importer. Please update the plugin.');
                return;
            }

            if ($tz === null) {
                $siteTz = get_option('timezone_string') ?: 'UTC';
                WP_CLI::log('Using site timezone: ' . $siteTz);
            }

            $result = Causeway_Importer::debug_build_occurrences($dates, $year, $tz);
            $rows   = $result['rows'];
            $next   = $result['next'];

            WP_CLI::log('Total occurrences: ' . count($rows));
            WP_CLI::log('Next occurrence: ' . ($next ?: '(none in future)'));

            $showAll = isset($assoc_args['all']);
            if ($format === 'table') {
                $max = $showAll ? count($rows) : min(25, count($rows));
                $table = [];
                for ($i = 0; $i < $max; $i++) {
                    $table[] = [
                        'index' => $i,
                        'start_utc' => $rows[$i]['occurrence_start'] ?? '',
                        'end_utc'   => $rows[$i]['occurrence_end'] ?? '',
                    ];
                }
                if ($table) {
                    WP_CLI\Utils\format_items('table', $table, ['index','start_utc','end_utc']);
                }
                if ($max < count($rows) && !$showAll) {
                    WP_CLI::log('… (use --all to show full list)');
                }
            }

            if ($format === 'json' || !empty($assoc_args['jsonout'])) {
                WP_CLI::log(json_encode($rows, JSON_PRETTY_PRINT));
            }
        }
    }

    WP_CLI::add_command('causeway', 'Causeway_CLI_Command');
}

// Provide a fallback single template for the 'listing' post type from this plugin
add_filter('single_template', function ($single) {
    if (is_singular('listing')) {
        // Allow themes to override by providing single-listing.php
        $theme_template = locate_template(['single-listing.php']);
        if ($theme_template) {
            return $theme_template;
        }
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-listing.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $single;
});

// Provide an archive template fallback for listing archive
add_filter('archive_template', function ($archive) {
    if (is_post_type_archive('listing')) {
        $theme_template = locate_template(['archive-listing.php']);
        if ($theme_template) {
            return $theme_template;
        }
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/archive-listing.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $archive;
});

// Enqueue frontend styles conditionally
add_action('wp_enqueue_scripts', function () {
    $is_listing_screen = is_singular('listing') || is_post_type_archive('listing');
    // Heuristic: listing grid usage via block/shortcode – always enqueue on singular pages to avoid FOUC
    if ($is_listing_screen || has_block('acf/causeway-listings-grid') || is_page()) {
        wp_enqueue_style('causeway-listings', plugin_dir_url(__FILE__) . 'assets/css/causeway.css', [], '1.0.0');
    }
});

// Editor styles (block editor) for listing block previews
add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_style('causeway-listings-editor', plugin_dir_url(__FILE__) . 'assets/css/causeway.css', [], '1.0.0');
});
