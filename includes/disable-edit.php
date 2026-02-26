<?php

// Disable editing of listings
add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {
    // Allow importer to save
    if (defined('CAUSEWAY_IMPORTING') && CAUSEWAY_IMPORTING) return null;

    $route = $request->get_route();
    $method = $request->get_method();

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) return null;
    
    // Block listings
    if (str_starts_with($route, '/wp/v2/listing/')) {
        error_log("🚫 Blocking REST modification to listing: $route");
        return new WP_Error('rest_forbidden', 'Modifying Causeway listings is not allowed.', ['status' => 403]);
    }

    return null; // Proceed normally
}, 10, 3);

// Style fields as disabled
add_action('acf/input/admin_head', function () {
    global $post;
    $screen = get_current_screen();

    $causeway_taxonomies = [
        'listing-type',
        'listing-campaigns',
        'listing-areas',
        'listing-counties',
        'listing-communities',
        'listing-regions',
        'listings-category',
        'listings-seasons',
        'listings-amenities',
    ];

    $is_listing = $post && $post->post_type === 'listing';
    $is_taxonomy_screen =
            $screen &&
            in_array($screen->taxonomy ?? '', $causeway_taxonomies, true) &&
            in_array($screen->base, ['term', 'edit-tags'], true);

    if ($is_listing || $is_taxonomy_screen) {
        echo '<style>
            /* Lock ACF fields */
            .acf-field input,
            .acf-field textarea,
            .acf-field select,
            .acf-field .acf-image-uploader,
            .acf-field .acf-file-uploader,
            .acf-field .acf-gallery {
                pointer-events: none !important;
                background: #f5f5f5 !important;
                opacity: 0.6 !important;
            }
            .acf-actions,
            .acf-input .button,
            .acf-field .acf-icon,
            .acf-field .acf-button,
            .acf-field .acf-gallery-add {
                display: none !important;
            }

            /* Lock native taxonomy fields */
            #name,
            #tag-name,
            #slug,
            #tag-slug,
            #tag-description,
            select#parent,
            .term-description-wrap textarea,
            .edit-tag-actions .submit,
            .submit input[type="submit"],
            .submit input[type="button"] {
                pointer-events: none !important;
                background: #f5f5f5 !important;
                opacity: 0.6 !important;
            }

            .edit-tag-actions .submit input,
            input[type="submit"],
            #delete-link,
            .editor-post-publish-button {
                display: none !important;
            }
        </style>';
    }
});



// Disable editing of Causeway taxonomies
add_action('admin_init', function () {
    if (!isset($_POST['taxonomy']) || defined('CAUSEWAY_IMPORTING')) {
        return;
    }

    $blocked_taxonomies = [
        'listing-type',
        'listing-campaigns',
        'listing-areas',
        'listing-counties',
        'listing-communities',
        'listing-regions',
        'listings-category',
        'listings-seasons',
        'listings-amenities',
    ];

    $taxonomy = sanitize_key($_POST['taxonomy']);

    if (!in_array($taxonomy, $blocked_taxonomies, true)) {
        return;
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'editedtag':
            error_log("🚫 Attempted edit of term in $taxonomy");
            wp_die('Editing Causeway taxonomy terms is not allowed.');
            break;

        case 'add-tag':
            error_log("🚫 Attempted creation of term in $taxonomy");
            wp_die('Creating Causeway taxonomy terms is not allowed.');
            break;

        case 'delete-tag':
        case 'delete':
            error_log("🚫 Attempted deletion of term in $taxonomy");
            wp_die('Deleting Causeway taxonomy terms is not allowed.');
            break;
    }
});

add_action('admin_menu', function () {
    // This removes the "Add New" submenu under Listings
    remove_submenu_page('edit.php?post_type=listing', 'post-new.php?post_type=listing');
}, 999);
