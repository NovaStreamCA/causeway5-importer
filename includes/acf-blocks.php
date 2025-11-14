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
                        'key' => 'field_causeway_listings_orderby',
                        'label' => 'Order By',
                        'name' => 'orderby',
                        'type' => 'select',
                        'choices' => [
                            'date' => 'Date',
                            'title' => 'Title',
                            'menu_order' => 'Menu Order',
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
                        'key' => 'field_causeway_listings_show_filterbar',
                        'label' => 'Show Filterbar',
                        'name' => 'show_filterbar',
                        'type' => 'true_false',
                        'ui' => 1,
                        'default_value' => 0,
                        'instructions' => 'Displays a filter bar above the listings.'
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
        $order       = get_field('order') ?: 'DESC';
        $pagination  = (bool) get_field('show_pagination');
        $show_filter = (bool) get_field('show_filterbar');

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

        $html = Causeway_Listings_Loop::render([
            'count' => $count,
            'columns' => $columns,
            'type' => $type,
            'orderby' => $orderby,
            'order' => $order,
            'show_pagination' => $pagination,
        ]);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</div>'; // .causeway-listings-section
    }
}

Causeway_ACF_Blocks::init();
