<?php
/**
 * Shortcode for listings grid with parity to ACF block options (client-side pagination only).
 * Supported attributes (mirrors block):
 * - count: int (default 6)
 * - columns: int (1-6, default 3)
 * - type: single listing-type slug (string)
 * - types: comma-separated listing-type slugs (overrides `type` when present)
 * - categories: comma-separated listings-category slugs
 * - communities: comma-separated listing-communities slugs
 * - areas: comma-separated listing-areas slugs
 * - orderby: date|title|menu_order|rand (default date)
 * - order: ASC|DESC (default DESC)
 * - show_pagination: true|false (client-side JS pagination, uses per_page/page-limit data attribute)
 * - per_page: int page size for client pagination (defaults to `count` if omitted)
 * - show_filterbar: true|false (include filterbar template like block)
 * - filters: comma-separated controls: search,type,category,community,area
 *
 * Deprecated:
 * - pagination: legacy server-side flag (alias to show_pagination when true). Server-side pagination removed.
 *
 * Usage examples:
 * [causeway_listings]
 * [causeway_listings count="9" columns="3" orderby="title" order="ASC"]
 * [causeway_listings count="12" orderby="rand" show_pagination="true" per_page="6"]
 * [causeway_listings types="event,festival" categories="music,food" communities="charlottetown"]
 * [causeway_listings show_filterbar="true" filters="search,community,area" types="event"]
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
            'communities' => '',
            'areas' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'show_pagination' => 'false',
            'per_page' => '',
            'show_filterbar' => 'false',
            'filters' => 'search,type,category,community,area',
            // Deprecated legacy attribute retained for compatibility:
            'pagination' => 'false',
        ], $atts, 'causeway_listings');

        // Interpret booleans (treat deprecated pagination as alias)
        $client_pagination = filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN) || filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN);
        $show_filterbar    = filter_var($atts['show_filterbar'], FILTER_VALIDATE_BOOLEAN);

        // Filterbar controls.
        $allowed_filters = ['search', 'type', 'category', 'community', 'area'];
        $enabled_filters = [];
        foreach (explode(',', (string)$atts['filters']) as $filter) {
            $filter = sanitize_key(trim($filter));
            if (in_array($filter, $allowed_filters, true)) {
                $enabled_filters[] = $filter;
            }
        }
        $enabled_filters = array_values(array_unique($enabled_filters));

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

        // Communities and areas multi.
        $parse_taxonomy_slugs = static function ($value) {
            $slugs = [];
            foreach (explode(',', (string)$value) as $slug) {
                $slug = sanitize_title(trim($slug));
                if ($slug !== '') { $slugs[] = $slug; }
            }
            return !empty($slugs) ? array_values(array_unique($slugs)) : '';
        };
        $communities = $parse_taxonomy_slugs($atts['communities']);
        $areas       = $parse_taxonomy_slugs($atts['areas']);

        // Client-side pagination page size
        $per_page = (int)($atts['per_page'] !== '' ? $atts['per_page'] : 0);
        if ($per_page < 1) { $per_page = (int)$atts['count']; }

        $orderby = sanitize_key(strtolower((string)$atts['orderby']));
        $orderby = in_array($orderby, ['date', 'title', 'menu_order', 'rand'], true) ? $orderby : 'date';

        // Build args for shared renderer
        $loop_args = [
            'count' => (int)$atts['count'],
            'columns' => (int)$atts['columns'],
            'type' => $type,
            'orderby' => $orderby,
            'order' => strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC',
            'category' => $categories,
            'community' => $communities,
            'area' => $areas,
        ];

        if ($client_pagination) {
            // JS pagination: supply data attribute page-limit
            $loop_args['data'] = ['page-limit' => $per_page];
        }
        if ($orderby === 'rand') {
            // Preserve WP_Query's randomized DOM order when MixItUp initializes.
            $loop_args['data']['initial-sort'] = 'default:asc';
        }

        ob_start();
        if ($show_filterbar || $client_pagination) {
            causeway_enqueue_mixitup_scripts();
        }
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
