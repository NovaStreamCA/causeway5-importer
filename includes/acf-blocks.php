<?php
/**
 * Registers ACF block for listing grid.
 */

if (!defined('ABSPATH')) { exit; }

class Causeway_ACF_Blocks {
    public static function init() {
        add_action('acf/init', [__CLASS__, 'register_block']);
    }

    public static function register_block() {
        if (!function_exists('acf_register_block_type')) { return; }

        acf_register_block_type([
            'name'            => 'causeway-listings-grid',
            'title'           => __('Listings Grid', 'causeway'),
            'description'     => __('Displays a grid of Causeway listings.', 'causeway'),
            'render_callback' => [__CLASS__, 'render_block'],
            'category'        => 'widgets',
            'icon'            => 'screenoptions',
            'keywords'        => ['listing', 'causeway', 'grid'],
            'supports'        => [
                'align' => true,
                'anchor' => true,
                'customClassName' => true,
            ],
            'mode'            => 'preview',
        ]);

        // If desired we could register ACF local field group here.
        if (function_exists('acf_add_local_field_group')) {
            acf_add_local_field_group([
                'key' => 'group_causeway_listings_block',
                'title' => 'Causeway Listings Block',
                'fields' => [
                    [
                        'key' => 'field_causeway_listings_count',
                        'label' => 'Count',
                        'name' => 'count',
                        'type' => 'number',
                        'default_value' => 6,
                        'min' => 1,
                        'max' => 60,
                    ],
                    [
                        'key' => 'field_causeway_listings_columns',
                        'label' => 'Columns',
                        'name' => 'columns',
                        'type' => 'number',
                        'default_value' => 3,
                        'min' => 1,
                        'max' => 6,
                    ],
                    [
                        'key' => 'field_causeway_listings_types_multi',
                        'label' => 'Listing Types',
                        'name' => 'types',
                        'type' => 'taxonomy',
                        'taxonomy' => 'listing-type',
                        'field_type' => 'multi_select',
                        'add_term' => 0,
                        'save_terms' => 0,
                        'load_terms' => 0,
                        'return_format' => 'id',
                        'instructions' => 'Select one or more listing types to filter. Overrides single slug above if used.',
                    ],
                    [
                        'key' => 'field_causeway_listings_categories_multi',
                        'label' => 'Listing Categories',
                        'name' => 'categories',
                        'type' => 'taxonomy',
                        'taxonomy' => 'listings-category',
                        'field_type' => 'multi_select',
                        'add_term' => 0,
                        'save_terms' => 0,
                        'load_terms' => 0,
                        'return_format' => 'id',
                        'instructions' => 'Select one or more listing categories to filter.',
                    ],
                    [
                        'key' => 'field_causeway_listings_communities_multi',
                        'label' => 'Listing Communities',
                        'name' => 'communities',
                        'type' => 'taxonomy',
                        'taxonomy' => 'listing-communities',
                        'field_type' => 'multi_select',
                        'add_term' => 0,
                        'save_terms' => 0,
                        'load_terms' => 0,
                        'return_format' => 'id',
                        'instructions' => 'Only show listings assigned to one or more selected communities.',
                    ],
                    [
                        'key' => 'field_causeway_listings_areas_multi',
                        'label' => 'Listing Areas',
                        'name' => 'areas',
                        'type' => 'taxonomy',
                        'taxonomy' => 'listing-areas',
                        'field_type' => 'multi_select',
                        'add_term' => 0,
                        'save_terms' => 0,
                        'load_terms' => 0,
                        'return_format' => 'id',
                        'instructions' => 'Only show listings assigned to one or more selected areas.',
                    ],
                    [
                        'key' => 'field_causeway_listings_orderby',
                        'label' => 'Order By',
                        'name' => 'orderby',
                        'type' => 'select',
                        'choices' => [
                            'date' => 'Date',
                            'title' => 'Title',
                            'menu_order' => 'Menu Order',
                            'rand' => 'Random',
                        ],
                        'default_value' => 'date',
                    ],
                    [
                        'key' => 'field_causeway_listings_order',
                        'label' => 'Order',
                        'name' => 'order',
                        'type' => 'select',
                        'choices' => [
                            'DESC' => 'Descending',
                            'ASC' => 'Ascending',
                        ],
                        'default_value' => 'DESC',
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_causeway_listings_orderby',
                                    'operator' => '!=',
                                    'value' => 'rand',
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => 'field_causeway_listings_pagination',
                        'label' => 'Show Pagination',
                        'name' => 'show_pagination',
                        'type' => 'true_false',
                        'ui' => 1,
                        'default_value' => 0,
                    ],
                    [
                        'key' => 'field_causeway_listings_per_page',
                        'label' => 'Items Per Page',
                        'name' => 'per_page',
                        'type' => 'number',
                        'default_value' => 6,
                        'min' => 1,
                        'max' => 60,
                        'instructions' => 'Client-side pagination page size (MixItUp).',
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_causeway_listings_pagination',
                                    'operator' => '==',
                                    'value' => 1,
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => 'field_causeway_listings_show_filterbar',
                        'label' => 'Show Filterbar',
                        'name' => 'show_filterbar',
                        'type' => 'true_false',
                        'ui' => 1,
                        'default_value' => 0,
                        'instructions' => 'Displays a filter bar above the listings.',
                    ],
                    [
                        'key' => 'field_causeway_listings_enabled_filters',
                        'label' => 'Enabled Filters',
                        'name' => 'enabled_filters',
                        'type' => 'checkbox',
                        'choices' => [
                            'search' => 'Search',
                            'type' => 'Type',
                            'category' => 'Category',
                            'community' => 'Community',
                            'area' => 'Area',
                        ],
                        'default_value' => ['search', 'type', 'category', 'community', 'area'],
                        'layout' => 'vertical',
                        'toggle' => 1,
                        'return_format' => 'value',
                        'instructions' => 'Choose which controls appear in the filter bar.',
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_causeway_listings_show_filterbar',
                                    'operator' => '==',
                                    'value' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'block',
                            'operator' => '==',
                            'value' => 'acf/causeway-listings-grid',
                        ],
                    ],
                ],
            ]);
        }
    }

    public static function render_block($block, $content = '', $is_preview = false, $post_id = 0) {
        $count       = get_field('count') ?: 6;
        $columns     = get_field('columns') ?: 3;
        $type        = get_field('type') ?: '';
        $types_multi = get_field('types');
        if (is_array($types_multi) && !empty($types_multi)) {
            $slugs = [];
            foreach ($types_multi as $term_id) {
                $term = get_term($term_id, 'listing-type');
                if ($term && !is_wp_error($term)) { $slugs[] = $term->slug; }
            }
            if (!empty($slugs)) { $type = $slugs; }
        }
        $orderby     = get_field('orderby') ?: 'date';
        $orderby     = in_array($orderby, ['date', 'title', 'menu_order', 'rand'], true) ? $orderby : 'date';
        $order       = get_field('order') ?: 'DESC';
        $pagination  = (bool) get_field('show_pagination');
        $per_page    = (int) (get_field('per_page') ?: 0);
        $show_filter = (bool) get_field('show_filterbar');
        $enabled_filters = get_field('enabled_filters');
        if (!is_array($enabled_filters) || empty($enabled_filters)) {
            $enabled_filters = ['search', 'type', 'category', 'community', 'area'];
        }
        // Categories (listings-category) multi-select support
        $categories   = '';
        $cats_multi   = get_field('categories');
        if (is_array($cats_multi) && !empty($cats_multi)) {
            $cat_slugs = [];
            foreach ($cats_multi as $term_id) {
                $term = get_term($term_id, 'listings-category');
                if ($term && !is_wp_error($term)) { $cat_slugs[] = $term->slug; }
            }
            if (!empty($cat_slugs)) { $categories = $cat_slugs; }
        }

        // Communities and areas are server-side query constraints.
        $taxonomy_slugs = static function ($field_name, $taxonomy) {
            $term_ids = get_field($field_name);
            if (!is_array($term_ids) || empty($term_ids)) { return ''; }

            $slugs = [];
            foreach ($term_ids as $term_id) {
                $term = get_term($term_id, $taxonomy);
                if ($term && !is_wp_error($term)) { $slugs[] = $term->slug; }
            }
            return !empty($slugs) ? $slugs : '';
        };
        $query_communities = $taxonomy_slugs('communities', 'listing-communities');
        $query_areas       = $taxonomy_slugs('areas', 'listing-areas');

        // Wrap filterbar + grid for scoped controls
        echo '<div class="causeway-listings-section">';

    // Optional filterbar
        if ($show_filter) {
            $basename   = 'listings-filterbar.php';
            $candidates = [
                'causeway/' . $basename,
                $basename,
                'template-parts/causeway/' . $basename,
            ];
            $template = locate_template($candidates);
            if (!$template) {
                $template = dirname(__DIR__) . '/templates/' . $basename;
            }
            if (file_exists($template)) {
                include $template;
            }
        }

        // Page list is rendered inside the grid container when pagination is enabled

        $grid_data = [];
        if ($pagination) {
            $grid_data['page-limit'] = (int)($per_page > 0 ? $per_page : $count);
        }
        if ($orderby === 'rand') {
            $grid_data['initial-sort'] = 'default:asc';
        }

        $html = Causeway_Listings_Loop::render([
            'count' => $count,
            'columns' => $columns,
            'type' => $type,
            'orderby' => $orderby,
            'order' => $order,
            // Disable server-side pagination when JS pagination is enabled
            'show_pagination' => false,
            'category' => $categories,
            'community' => $query_communities,
            'area' => $query_areas,
            'data' => $grid_data,
        ]);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        // Render MixItUp page list outside of the grid when enabled
        if ($pagination) {
            echo '<div class="causeway-page-list"></div>';
        }

        echo '</div>'; // .causeway-listings-section
    }
}

Causeway_ACF_Blocks::init();
