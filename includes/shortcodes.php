<?php
/**
 * Shortcode for listings grid with parity to ACF block options (client-side pagination only).
 * Supported attributes (mirrors block):
 * - count: int (default 6)
 * - columns: int (1-6, default 3)
 * - type: single listing-type slug (string)
 * - types: comma-separated listing-type slugs (overrides `type` when present)
 * - categories: comma-separated listings-category slugs
 * - orderby: date|title|menu_order (default date)
 * - order: ASC|DESC (default DESC)
 * - show_pagination: true|false (client-side JS pagination, uses per_page/page-limit data attribute)
 * - per_page: int page size for client pagination (defaults to `count` if omitted)
 * - show_filterbar: true|false (include filterbar template like block)
 *
 * Deprecated:
 * - pagination: legacy server-side flag (alias to show_pagination when true). Server-side pagination removed.
 *
 * Usage examples:
 * [causeway_listings]
 * [causeway_listings count="9" columns="3" orderby="title" order="ASC"]
 * [causeway_listings types="event,festival" categories="music,food"]
 * [causeway_listings show_filterbar="true" types="event"]
 * [causeway_listings show_pagination="true" per_page="6" count="12"]
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
            'types' => '',
            'categories' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'show_pagination' => 'false',
            'per_page' => '',
            'show_filterbar' => 'false',
            // Deprecated legacy attribute retained for compatibility:
            'pagination' => 'false',
        ], $atts, 'causeway_listings');

        // Interpret booleans (treat deprecated pagination as alias)
        $client_pagination = filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN) || filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN);
        $show_filterbar    = filter_var($atts['show_filterbar'], FILTER_VALIDATE_BOOLEAN);

        // Types (multi overrides single)
        $types_multi = [];
        if (!empty($atts['types'])) {
            $raw = explode(',', (string)$atts['types']);
            foreach ($raw as $slug) {
                $slug = trim($slug);
                if ($slug !== '') { $types_multi[] = sanitize_title($slug); }
            }
        }
        $type = '';
        if (!empty($types_multi)) {
            $type = $types_multi; // array of slugs
        } elseif (!empty($atts['type'])) {
            $type = sanitize_title($atts['type']);
        }

        // Categories multi
        $categories_multi = [];
        if (!empty($atts['categories'])) {
            $rawc = explode(',', (string)$atts['categories']);
            foreach ($rawc as $slug) {
                $slug = trim($slug);
                if ($slug !== '') { $categories_multi[] = sanitize_title($slug); }
            }
        }
        $categories = !empty($categories_multi) ? $categories_multi : '';

        // Client-side pagination page size
        $per_page = (int)($atts['per_page'] !== '' ? $atts['per_page'] : 0);
        if ($per_page < 1) { $per_page = (int)$atts['count']; }

        // Build args for shared renderer
        $loop_args = [
            'count' => (int)$atts['count'],
            'columns' => (int)$atts['columns'],
            'type' => $type,
            'orderby' => in_array($atts['orderby'], ['date','title','menu_order'], true) ? $atts['orderby'] : 'date',
            'order' => strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC',
            'category' => $categories,
        ];

        if ($client_pagination) {
            // JS pagination: supply data attribute page-limit
            $loop_args['data'] = ['page-limit' => $per_page];
        }

        ob_start();
        echo '<div class="causeway-listings-section">';

        // Optional filterbar include (same logic as block)
        if ($show_filterbar) {
            $basename   = 'listings-filterbar.php';
            $candidates = ['causeway/' . $basename, $basename, 'template-parts/causeway/' . $basename];
            $template = locate_template($candidates);
            if (!$template) { $template = dirname(__DIR__) . '/templates/' . $basename; }
            if (file_exists($template)) { include $template; }
        }

        // Render grid
        echo Causeway_Listings_Loop::render($loop_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        // Client-side pagination page list container (mirrors block output)
        if ($client_pagination) {
            echo '<div class="causeway-page-list"></div>';
        }
        echo '</div>';

        return trim(ob_get_clean());
    }
}

Causeway_Listings_Shortcodes::init();
