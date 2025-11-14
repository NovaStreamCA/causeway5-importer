<?php
/**
 * Shared listing loop renderer for ACF block + shortcode.
 */

if (!defined('ABSPATH')) { exit; }

class Causeway_Listings_Loop {
    /**
     * Build WP_Query args based on attributes.
     * @param array $args Input args: count, type, order, orderby
     */
    public static function build_query_args(array $args = []): array {
        $defaults = [
            'post_type'      => 'listing',
            'posts_per_page' => (int)($args['count'] ?? 6),
            'post_status'    => 'publish',
            'orderby'        => $args['orderby'] ?? 'date',
            'order'          => $args['order'] ?? 'DESC',
            'tax_query'      => [],
        ];
        $type = $args['type'] ?? '';
        if ($type) {
            $defaults['tax_query'][] = [
                'taxonomy' => 'listing-type',
                'field'    => 'slug',
                'terms'    => (array)$type,
            ];
        }
        if (empty($defaults['tax_query'])) {
            unset($defaults['tax_query']);
        }
        return $defaults;
    }

    /**
     * Render listings grid.
     * @param array $args same as build_query_args plus layout options
     * @return string HTML
     */
    public static function render(array $args = []): string {
        $q_args = self::build_query_args($args);
        $q      = new WP_Query($q_args);
        ob_start();
        if ($q->have_posts()) {
            $cols = (int)($args['columns'] ?? 3);
            if ($cols < 1) { $cols = 1; }
            if ($cols > 6) { $cols = 6; }
            $cols_class = 'cols-' . $cols;
            // Optional data-* attributes for JS (e.g., pagination limit)
            $data_attrs = '';
            if (!empty($args['data']) && is_array($args['data'])) {
                foreach ($args['data'] as $k => $v) {
                    if ($k === '' || $v === null) continue;
                    $attr_name = 'data-' . sanitize_key((string)$k);
                    $attr_val  = esc_attr((string)$v);
                    $data_attrs .= ' ' . $attr_name . '="' . $attr_val . '"';
                }
            }
            echo '<div class="causeway-listings-grid ' . esc_attr($cols_class) . '"' . $data_attrs . '>';
            while ($q->have_posts()) { $q->the_post();
                self::include_template_part('listing-card.php');
            }
            echo '</div>';
            if (!empty($args['show_pagination'])) {
                echo '<div class="causeway-pagination">';
                // Basic pagination (doesn't persist block attrs). For block context consider JS later.
                echo paginate_links([
                    'total'   => $q->max_num_pages,
                    'current' => max(1, get_query_var('paged')), // Might clash if used inside main loop
                ]);
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<p class="no-listings">' . esc_html__('No listings found.', 'causeway') . '</p>';
        }
        return trim(ob_get_clean());
    }

    /**
     * Locate and include a template part for listings. Theme overrides are supported.
     * @param string $basename e.g., 'listing-card.php'
     * @param array $args variables to expose to the template
     */
    private static function include_template_part(string $basename, array $args = []): void {
        // Allow themes to override under common paths
        $candidates = [
            'causeway/' . $basename,
            $basename,
            'template-parts/causeway/' . $basename,
        ];
        $template = locate_template($candidates);
        if (!$template) {
            // Fallback to plugin template
            $template = dirname(__DIR__) . '/templates/' . $basename;
        }
        if (!empty($args)) {
            extract($args, EXTR_SKIP);
        }
        if (file_exists($template)) {
            include $template;
        }
    }
}
