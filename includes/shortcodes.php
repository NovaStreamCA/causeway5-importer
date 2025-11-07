<?php
/**
 * Shortcode for listings grid.
 * Usage: [causeway_listings count="6" columns="3" type="event" orderby="date" order="DESC" pagination="false"]
 */

if (!defined('ABSPATH')) { exit; }

class Causeway_Listings_Shortcodes {
    public static function init() {
        add_shortcode('causeway_listings', [__CLASS__, 'shortcode']);
    }

    public static function shortcode($atts, $content = ''): string {
        $atts = shortcode_atts([
            'count' => 6,
            'columns' => 3,
            'type' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'pagination' => 'false',
        ], $atts, 'causeway_listings');

        $pagination = filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN);

        $html = Causeway_Listings_Loop::render([
            'count' => (int)$atts['count'],
            'columns' => (int)$atts['columns'],
            'type' => sanitize_text_field($atts['type']),
            'orderby' => sanitize_key($atts['orderby']),
            'order' => strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC',
            'show_pagination' => $pagination,
        ]);

        return $html; // Already escaped in renderer.
    }
}

Causeway_Listings_Shortcodes::init();
