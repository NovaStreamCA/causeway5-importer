<?php

// File: causeway.php
/**
 * Plugin Name: Causeway Listings Importer
 * Description: Imports listings and taxonomies from Causeway API into WordPress with WPML and ACF integration.
 * Version: 1.0.9
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

    // If an import is already running, block unless stale or in error
    $status = get_option('causeway_import_status');
    if (is_array($status) && !empty($status['running'])) {
        $updated_at = isset($status['updated_at']) ? (int)$status['updated_at'] : 0;
        $state = isset($status['state']) ? $status['state'] : '';
        $is_stale = $updated_at > 0 ? (time() - $updated_at) > 8 * 60 : false; // 8-minute watchdog
        $is_error = ($state === 'error');
        if (!$is_stale && !$is_error) {
            wp_redirect(add_query_arg('import_running', '1', admin_url('edit.php?post_type=listing&page=causeway-importer')));
            exit;
        } else {
            error_log('[Causeway] Overriding running lock due to ' . ($is_error ? 'error' : 'stale') . ' status.');
        }
    }

    // Optimistically mark status as queued/running to prevent double-clicks
    $status = [
        'running' => true,
        'phase' => 'queued',
        'processed' => 0,
        'total' => 0,
        'percent' => 0,
        'state' => 'queued',
        'started_at' => time(),
        'updated_at' => time(),
    ];
    update_option('causeway_import_status', $status, false);

    // Try to launch the import via WP-CLI in the background to avoid HTTP/Cloudflare timeouts.
    $spawned_cli = false;
    if (apply_filters('causeway_cli_spawn_enabled', true)) {
        $spawned_cli = causeway_try_spawn_cli_import();
    }

    if ($spawned_cli) {
        error_log('🚀 Spawned Causeway import via WP-CLI in background');
    } else {
        // Fallback: schedule a one-off cron event to run the import in the background ASAP.
        // Using the existing 'causeway_cron_hook' which calls Causeway_Importer::import().
        $scheduled_for = time();
        wp_schedule_single_event($scheduled_for, 'causeway_cron_hook');
        error_log('🕑 Manual import scheduled for immediate execution via WP-Cron @ '.date('Y-m-d H:i:s',$scheduled_for));
        // Best-effort immediate cron spawn so the single event executes without waiting for next page load.
        if (function_exists('spawn_cron')) {
            spawn_cron(time());
        } else {
            error_log('⚠️ spawn_cron() unavailable; import will wait for next WP-Cron run/page load.');
        }
    }

    // Redirect back to admin with queued notice
    $args = [ 'import_queued' => '1' ];
    if ($spawned_cli) { $args['cli'] = '1'; }
    wp_redirect(add_query_arg($args, admin_url('edit.php?post_type=listing&page=causeway-importer')));
    exit;
});

// AJAX endpoint to fetch current import status
add_action('wp_ajax_causeway_import_status', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }
    $status = get_option('causeway_import_status', []);
    if (!is_array($status)) { $status = []; }
    $defaults = [
        'running' => false,
        'phase' => 'idle',
        'processed' => 0,
        'total' => 0,
        'percent' => 0,
        'state' => 'idle',
        'started_at' => null,
        'updated_at' => null,
        'error_message' => null,
    ];
    $status = array_merge($defaults, $status);
    wp_send_json_success($status);
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

// Admin-post: force reset import status (in case of stuck state)
add_action('admin_post_causeway_manual_import_reset', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    if (!isset($_POST['causeway_import_reset_nonce']) || !wp_verify_nonce($_POST['causeway_import_reset_nonce'], 'causeway_import_reset_action')) {
        wp_die('Invalid nonce');
    }
    $defaults = [
        'running' => false,
        'phase' => 'idle',
        'processed' => 0,
        'total' => 0,
        'percent' => 0,
        'state' => 'idle',
        'started_at' => null,
        'updated_at' => time(),
        'error_message' => null,
        'cancel_requested' => false,
    ];
    update_option('causeway_import_status', $defaults, false);
    error_log('[Causeway] Import status reset by admin');
    wp_redirect(add_query_arg('import_reset', '1', admin_url('edit.php?post_type=listing&page=causeway-importer')));
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
        try {
            Causeway_Importer::import();
        } catch (Throwable $e) {
            error_log('[Causeway] Import failed: ' . $e->getMessage());
            $status = get_option('causeway_import_status', []);
            if (!is_array($status)) { $status = []; }
            $status['running'] = false;
            $status['phase'] = 'error';
            $status['state'] = 'error';
            $status['error_message'] = $e->getMessage();
            $status['updated_at'] = time();
            update_option('causeway_import_status', $status, false);
        }
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
    // Also clear new structured status
    $status = get_option('causeway_import_status', []);
    if (!is_array($status)) { $status = []; }
    $status['running'] = false;
    $status['phase'] = 'idle';
    $status['percent'] = 0;
    $status['state'] = 'idle';
    update_option('causeway_import_status', $status, false);
}

/**
 * Best-effort background spawn of the importer via WP-CLI to avoid HTTP request timeouts.
 * Returns true if a CLI process was launched, false otherwise.
 */
function causeway_try_spawn_cli_import(): bool
{
    // Ensure exec/proc_open are permitted
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $can_exec = function_exists('exec') && !in_array('exec', $disabled, true);
    $can_proc = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    if (!$can_exec && !$can_proc) {
        return false;
    }

    // Resolve WP-CLI binary automatically
    $wp_bin = '';
    // Try to locate wp in PATH
    if ($can_exec) {
        $out = [];
        $rv = 1;
        @exec('command -v wp 2>/dev/null', $out, $rv);
        if ($rv === 0 && !empty($out[0])) {
            $wp_bin = trim($out[0]);
        }
    }
    // Fallback to common locations if still empty
    if ($wp_bin === '') {
        foreach (['/usr/local/bin/wp','/usr/bin/wp','/bin/wp'] as $candidate) {
            if (is_executable($candidate)) { $wp_bin = $candidate; break; }
        }
    }
    if ($wp_bin === '') {
        error_log('[Causeway] WP-CLI binary not found on PATH. Install WP-CLI (wp) and ensure it is executable.');
        return false;
    }
    $wp_path = apply_filters('causeway_wp_path', ABSPATH);
    $allow_root = is_callable('posix_geteuid') ? (posix_geteuid() === 0) : true;
    $allow_root_flag = $allow_root ? ' --allow-root' : '';

    // Write logs to content dir
    $log_dir = trailingslashit(WP_CONTENT_DIR);
    $log_file = $log_dir . 'causeway-import.log';

    // Compose command
    // If wp_bin contains spaces (e.g., 'php /path/wp-cli.phar'), treat as full command prefix
    $cmd_prefix = (strpos($wp_bin, ' ') !== false) ? $wp_bin : escapeshellcmd($wp_bin);
    $cmd = $cmd_prefix
        . ' --path=' . escapeshellarg($wp_path)
        . $allow_root_flag
        . ' causeway import';

    // Background with nohup; capture PID to indicate success.
    $bg = 'nohup ' . $cmd . ' >> ' . escapeshellarg($log_file) . ' 2>&1 & echo $!';

    try {
        if ($can_exec) {
            $output = [];
            $exit = 0;
            @exec($bg, $output, $exit);
            // If we got a numeric PID back or exit 0, treat as success
            if ($exit === 0 && (!empty($output) && preg_match('/^[0-9]+$/', trim(end($output))))) {
                return true;
            }
            // Some shells return 0 without echoing PID; accept exit 0 as success
            if ($exit === 0) { return true; }
        }
        if ($can_proc) {
            $descriptorspec = [0 => ['pipe', 'r'], 1 => ['file', $log_file, 'a'], 2 => ['file', $log_file, 'a']];
            $process = @proc_open($cmd, $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
            if (is_resource($process)) {
                // Detach immediately
                @proc_close($process);
                return true;
            }
        }
    } catch (Throwable $e) {
        error_log('[Causeway] CLI spawn failed: ' . $e->getMessage());
    }

    return false;
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
