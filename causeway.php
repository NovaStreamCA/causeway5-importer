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
