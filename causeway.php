<?php
// File: causeway.php
/**
 * Plugin Name: Causeway Listings Importer
 * Description: Imports listings and taxonomies from Causeway API into WordPress with WPML and ACF integration.
 * Version: 1.0.0
 * Author: Dylan George
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'admin/acf-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-causeway-importer.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-causeway-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/causeway-exporter.php';
// TODO - enable this
require_once plugin_dir_path(__FILE__) . 'includes/disable-edit.php';

// Overrides acf taxonomy input to return causeway id if selected
require_once plugin_dir_path(__FILE__) . 'includes/causeway-taxonomy-acf-field.php';

add_action('admin_init', function () {
    Causeway_Admin::init();
});

function causeway_register_listing_post_type() {
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
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
        'rewrite' => ['slug' => 'listings'],
        'menu_icon' => 'dashicons-admin-site-alt3', // You can swap this with any Dashicon or SVG
    ]);
}
add_action('init', 'causeway_register_listing_post_type');

function causeway_register_taxonomies() {
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

add_action('admin_post_causeway_manual_import', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['causeway_import_nonce']) || !wp_verify_nonce($_POST['causeway_import_nonce'], 'causeway_import_action')) {
        wp_die('Invalid nonce');
    }

    // ✅ This is where import runs
    Causeway_Importer::import();

    // Redirect back to admin with success notice
    wp_redirect(add_query_arg('imported', '1', admin_url('edit.php?post_type=listing&page=causeway-importer')));
    exit;
});

add_action('admin_post_causeway_manual_export', function () {
    if (!current_user_can('manage_options') || !check_admin_referer('causeway_export_action', 'causeway_export_nonce')) {
        wp_die('Unauthorized or nonce check failed.');
    }

    if (class_exists('Causeway_Importer')) {
        Causeway_Importer::export_listings();
    }

    wp_redirect(add_query_arg('exported', '1', wp_get_referer()));
    exit;
});

// Register CRON on plugin activation
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('causeway_cron_hook')) {
        wp_schedule_event(time(), 'twicedaily', 'causeway_cron_hook');
    }
});

// Safety fallback in case plugin was already active
add_action('init', function () {
    if (!wp_next_scheduled('causeway_cron_hook')) {
        wp_schedule_event(time(), 'twicedaily', 'causeway_cron_hook');
    }
});

// Deactivate CRON on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('causeway_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'causeway_cron_hook');
    }
});

add_action('causeway_cron_hook', 'run_causeway_export');

function run_causeway_export() {
    error_log('🕑 Running Causeway export via cron @ ' . date('Y-m-d H:i:s'));
    if (class_exists('Causeway_Importer')) {
        Causeway_Importer::import();
    }
}
