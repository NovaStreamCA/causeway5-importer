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
            echo '<div class="causeway-listings-grid" style="display:grid;gap:24px;grid-template-columns:repeat(' . esc_attr($cols) . ',1fr);">';
            while ($q->have_posts()) { $q->the_post();
                echo '<article class="listing-card" style="border:1px solid #e5e5e5;border-radius:8px;overflow:hidden;background:#fff;">';
                echo '<a href="' . esc_url(get_permalink()) . '" class="thumb" style="display:block;aspect-ratio:16/9;background:#f5f5f5;overflow:hidden;">';
                if (has_post_thumbnail()) {
                    the_post_thumbnail('medium_large', ['style' => 'width:100%;height:100%;object-fit:cover;display:block;']);
                }
                echo '</a>';
                echo '<div class="body" style="padding:16px;">';
                echo '<h3 class="title" style="margin:0 0 8px;font-size:1.1rem;"><a style="text-decoration:none;color:inherit;" href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
                $excerpt = get_the_excerpt();
                if (!$excerpt) { $excerpt = wp_trim_words(wp_strip_all_tags(get_the_content()), 25); }
                echo '<p class="excerpt" style="margin:0 0 10px;color:#555;font-size:0.9rem;">' . esc_html(wp_trim_words($excerpt, 25)) . '</p>';
                $types = get_the_terms(get_the_ID(), 'listing-type');
                if (!is_wp_error($types) && !empty($types)) {
                    echo '<div class="type" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#777;">' . esc_html($types[0]->name) . '</div>';
                }
                echo '</div>';
                echo '</article>';
            }
            echo '</div>';
            if (!empty($args['show_pagination'])) {
                echo '<div class="causeway-pagination" style="margin-top:32px;">';
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
}
