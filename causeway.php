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

add_action('admin_init', function () {
    Causeway_Admin::init();
});

function causeway_register_listing_post_type() {
    register_post_type('listing', [
        'labels' => [
            'name' => __('Listings'),
            'singular_name' => __('Listing')
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
        'rewrite' => ['slug' => 'listings'],
    ]);
}
add_action('init', 'causeway_register_listing_post_type');

function causeway_register_taxonomies() {
    $taxonomies = [
        'listing-type' => 'Type',
        'listing-campaigns' => 'Campaign',
        'listing-areas' => 'Area',
        'listing-counties' => 'County',
        'listing-communities' => 'Community',
        'listing-regions' => 'Region',
        'listings-category' => 'Category',
        'listings-seasons' => 'Season',
        'listings-amenities' => 'Amenity',
    ];

    foreach ($taxonomies as $slug => $name) {
        register_taxonomy($slug, 'listing', [
            'labels' => [
                'name' => __($name . 's'),
                'singular_name' => __($name),
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => $slug],
        ]);
    }
}
add_action('init', 'causeway_register_taxonomies');

// Optional: Load ACF field group setup here (if not using GUI export)

// Optional: Create admin menu for manual import

// Optional: WP CLI command to trigger import (for cron or dev)

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
