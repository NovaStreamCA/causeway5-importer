<?php
use Carbon\Carbon;
use RRule\RRule;

class Causeway_Importer
{
    private static $areas = [];
    private static $communities = [];
    private static $regions = [];
    private static $counties = [];
    private static $listing_map = [];
    private static $start;
    private static $baseURL = 'https://api-causeway5.novastream.dev/';
    private static $term_cache = [];
    
    private static function occ_log($message): void {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        error_log('[Causeway:occ] ' . $message);
    }

    public static function import()
    {
        self::log('start import');
        
        $baseURL = get_field('causeway_api_url', 'option');

        if ($baseURL) {
            self::$baseURL = $baseURL;
        }

        /* ── FORCE ENGLISH ───────────────────────── */
        $orig_lang = apply_filters('wpml_current_language', null);
        do_action('wpml_switch_language', 'en');        // or your real default, e.g. 'en_CA'
        /* ────────────────────────────────────────── */

        define('CAUSEWAY_IMPORTING', true);
        self::$start = microtime(true);

         /* ── PERFORMANCE GUARD-RAILS ───────────────────────────── */
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('wp_suspend_cache_addition')) {
            wp_suspend_cache_addition(true);
        }
        wp_defer_term_counting(true);           // no recounts on every insert

        // Preload and cache calls that would be used multiple times
        self::$areas = self::fetch_remote('areas');
        self::$communities = self::fetch_remote('communities');
        self::$regions = self::fetch_remote('regions');
        self::$counties = self::fetch_remote('counties');

        //Import Taxonomies
        self::import_types();
        self::import_categories();
        self::import_amenities();
        self::import_campaigns();
        self::import_seasons();
        self::import_terms('listing-counties', self::$counties);

        // Complex taxonomies
        self::import_terms('listing-areas', self::$areas);
        self::import_terms('listing-communities', self::$communities);
        self::import_terms('listing-regions', self::$regions);

        self::assign_area_communities();
        self::assign_community_areas_and_regions();
        self::assign_region_communities();

        // Only assign slugs when in headless mode
        if (get_field('is_headless', 'option')) {
            self::assign_area_slugs();
            self::assign_category_slugs();
        } else {
            self::log('Skipping area/category slug assignments: site not headless');
        }

        self::import_listings();

        self::log('✅ Import Completed. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::export_listings();

        wp_defer_term_counting(false);
        wp_suspend_cache_addition(false);
    }

    public static function export() {
        self::export_listings();
    }

    // Run only taxonomy imports (types, categories, amenities, seasons, campaigns, areas/communities/regions/counties)
    public static function import_taxonomies_only(): void
    {
        self::log('start taxonomy-only import');

        $baseURL = get_field('causeway_api_url', 'option');
        if ($baseURL) {
            self::$baseURL = $baseURL;
        }

        /* FORCE ENGLISH */
        $orig_lang = apply_filters('wpml_current_language', null);
        do_action('wpml_switch_language', 'en');

        define('CAUSEWAY_IMPORTING', true);
        self::$start = microtime(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('wp_suspend_cache_addition')) {
            wp_suspend_cache_addition(true);
        }
        wp_defer_term_counting(true);

        // Preload
        self::$areas = self::fetch_remote('areas');
        self::$communities = self::fetch_remote('communities');
        self::$regions = self::fetch_remote('regions');
        self::$counties = self::fetch_remote('counties');

        // Core taxonomies
        self::import_types();
        self::import_categories();
        self::import_amenities();
        self::import_campaigns();
        self::import_seasons();
        self::import_terms('listing-counties', self::$counties);
        self::import_terms('listing-areas', self::$areas);
        self::import_terms('listing-communities', self::$communities);
        self::import_terms('listing-regions', self::$regions);

        self::assign_area_communities();
        self::assign_community_areas_and_regions();
        self::assign_region_communities();

        if (get_field('is_headless', 'option')) {
            self::assign_area_slugs();
            self::assign_category_slugs();
        }

        self::log('✅ Taxonomy import completed @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        wp_defer_term_counting(false);
        wp_suspend_cache_addition(false);
    }

    private static function fetch_remote($endpoint)
    {
        $response = wp_remote_get(
            self::$baseURL . $endpoint,
            array(
                'timeout'     => 120,
                'httpversion' => '1.1',
            )
        );

        if (is_wp_error($response)) {
            self::log('❌ WP Error (' . $endpoint . '): ' . $response->get_error_message());
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($data)) {
            self::log('❌ JSON Error (' . $endpoint . '): ' . $data->get_error_message());
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private static function import_types()
    {
        self::log('Import types...');
        $data = self::fetch_remote('listing-types');
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;

        self::log('✅ Received ' . $total . ' types from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($data as $item) {
            $name = $item['name'];
            $icon = $item['icons'];
            $causeway_id = $item['id'];

            if (!$causeway_id) {
                continue;
            }

            // Check if term exists
            $existing = self::get_term_by_causeway_id($causeway_id, 'listing-type');


            if ($existing) {
                $term_id = $existing->term_id;
                wp_update_term($term_id, 'listing-type', [
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ]);
            } else {
                // Insert new term
                $term = wp_insert_term($name, 'listing-type');
                if (is_wp_error($term)) {
                    continue;
                }
                $term_id = $term['term_id'];
            }

            $imported_ids[] = $causeway_id;
            update_field('icon', $icon, 'listing-type_' . $term_id);
            update_field('causeway_id', $causeway_id, 'listing-type_' . $term_id);
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        self::log('✅ Types Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_terms('listing-type', $imported_ids);
    }

    private static function import_categories()
    {
        self::log('Importing categories...');
        $data = self::fetch_remote('categories');
        $lookup = [];
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;

        self::log('✅ Received ' . $total . ' categories from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
        foreach ($data as $item) {
            $causeway_id = $item['id'];
            $lookup[$causeway_id] = $item;
        }

        // Second pass: create/update terms with parent handling
        foreach ($lookup as $item) {
            $name = $item['name'];
            $icon = $item['type']['icons'] ?? null;
            $type_id = $item['type']['id'] ?? null;
            $causeway_id = $item['id'];
            $parent_causeway_id = $item['parent']['id'] ?? null;

            if (!$name || !$causeway_id) {
                continue;
            }

            // Check if term exists
            $existing = self::get_term_by_causeway_id($causeway_id, 'listings-category');

            $args = [
                'slug' => sanitize_title($name),
            ];

            // If parent exists, lookup its WP term ID
            if ($parent_causeway_id) {
                $parent_term = self::get_term_by_causeway_id($parent_causeway_id, 'listings-category');
                if ($parent_term) {
                    $args['parent'] = $parent_term->term_id;
                }
            }

            if ($existing) {
                $term_id = $existing->term_id;
                $update_args = array_merge($args, [
                    'name' => $name,
                ]);

                // Update parent if different
                $current_parent_id = (int) $existing->parent;
                $new_parent_id = isset($args['parent']) ? (int) $args['parent'] : 0;

                if ($current_parent_id !== $new_parent_id) {
                    $update_args['parent'] = $new_parent_id;
                }

                wp_update_term($term_id, 'listings-category', $update_args);
            } else {
                $term = wp_insert_term($name, 'listings-category', $args);
                if (is_wp_error($term)) {
                    continue;
                }
                $term_id = $term['term_id'];
            }

            // Save ACF fields
            if (isset($term_id)) {
                update_field('causeway_id', $causeway_id, 'listings-category_' . $term_id);
                update_field('category_icon_light', $item['icon_light'], 'listings-category_' . $term_id);
                update_field('category_icon_dark', $item['icon_dark'], 'listings-category_' . $term_id);

                $type_term = self::get_term_by_causeway_id($type_id, 'listing-type');
                if ($type_term) {
                    update_field('listing_type', $type_term->term_id, 'listings-category_' . $term_id);
                }

                // asd attachments Repeater
                if (!empty($item['attachments']) && is_array($item['attachments'])) {
                    // Attachments Repeater
                    $attachments = [];
                    foreach ($item['attachments'] ?? [] as $a) {
                        $attachments[] = [
                            'url' => $a['url'] ?? '',
                            'category' => $a['category'] ?? '',
                            'alt' => $a['alt'] ?? '',
                        ];
                    }
                    update_field('category_attachments', $attachments, 'listings-category_' . $term_id);
                }
            }

            $imported_ids[] = $causeway_id;
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        self::log('✅ Categories Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_terms('listings-category', $imported_ids);
    }

    private static function import_amenities()
    {
        self::log('Importing amenities...');
        $data = self::fetch_remote('amenities');
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;

        self::log('✅ Received ' . $total . ' amenities from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
        foreach ($data as $item) {
            $name = $item['name'] ?? null;
            $causeway_id = $item['id'] ?? null;
            $type_id = $item['type']['id'] ?? null;

            if (!$name || !$causeway_id) {
                continue;
            }

            // Check if term already exists by causeway_id
            $existing = self::get_term_by_causeway_id($causeway_id, 'listings-amenities');

            if ($existing) {
                $term_id = $existing->term_id;
                wp_update_term($term_id, 'listings-amenities', [
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ]);
            } else {
                $term = wp_insert_term($name, 'listings-amenities', [
                    'slug' => sanitize_title($name),
                ]);
                if (is_wp_error($term)) {
                    continue;
                }
                $term_id = $term['term_id'];
            }

            if (!isset($term_id)) {
                continue;
            }

            // Update ACF fields
            update_field('causeway_id', $causeway_id, 'listings-amenities_' . $term_id);
            update_field('amenity_icon', $item['icon'], 'listings-amenities_' . $term_id);

            // Set ACF relationship to type
            $type_term = self::get_term_by_causeway_id($type_id, 'listing-type');
            if ($type_term) {
                update_field('listing_type', $type_term->term_id, 'listings-amenities_' . $term_id);
            }

            $imported_ids[] = $causeway_id;
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        self::log('✅ Amenities Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_terms('listings-amenities', $imported_ids);
    }

    private static function import_campaigns()
    {
        self::log('Importing campaigns...');
        $data = self::fetch_remote('campaigns');
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;
        self::log('✅ Received ' . $total . ' campaigns from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($data as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];
            $activated_at = $item['activated_at'] ?? null;
            $expired_at = $item['expired_at'] ?? null;

            if (!$causeway_id) {
                continue;
            }

            $existing = self::get_term_by_causeway_id($causeway_id, 'listing-campaigns');

            if (!$existing) {
                $term = wp_insert_term($name, 'listing-campaigns');
                if (is_wp_error($term)) {
                    continue;
                }
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
                wp_update_term($term_id, 'listing-campaigns', [
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ]);
            }

            $acf_key = 'listing-campaigns_' . $term_id;

            update_field('causeway_id', $causeway_id, $acf_key);
            update_field('activated_at', $activated_at, $acf_key);
            update_field('expired_at', $expired_at, $acf_key);

            $imported_ids[] = $causeway_id;
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        self::log('✅ Campaigns Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_terms('listing-campaigns', $imported_ids);
    }

    private static function import_seasons()
    {
        self::log('Importing seasons...');

        $seasons = [
            ['id' => 1, 'name' => 'All Seasons'],
            ['id' => 2, 'name' => 'Spring'],
            ['id' => 3, 'name' => 'Summer'],
            ['id' => 4, 'name' => 'Fall'],
            ['id' => 5, 'name' => 'Winter'],
        ];

        foreach ($seasons as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];
            $existing = self::get_term_by_causeway_id($causeway_id, 'listings-seasons');

            if (!$existing) {
                $term = wp_insert_term($name, 'listings-seasons');
                if (is_wp_error($term)) {
                    continue;
                }
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
                wp_update_term($term_id, 'listings-seasons', [
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ]);
            }

            update_field('causeway_id', $causeway_id, 'listings-seasons_' . $term_id);
        }

        self::log('✅ Seasons imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_terms($taxonomy, $items)
    {
        self::log('Importing ' . $taxonomy . '...');

        $imported_ids = [];
        $total = is_array($items) ? count($items) : 0;

        self::log('✅ Received ' . $total . ' ' . $taxonomy . ' from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($items as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];

            $existing = self::get_term_by_causeway_id($causeway_id, $taxonomy);

            if (!$existing) {
                $term = wp_insert_term($name, $taxonomy);
                if (is_wp_error($term)) {
                    continue;
                }
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
                wp_update_term($term_id, $taxonomy, [
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ]);
            }


            update_field('causeway_id', $causeway_id, $taxonomy . '_' . $term_id);

            if (!empty($item['attachments']) && is_array($item['attachments'])) {
                // Attachments Repeater
                $attachments = [];
                foreach ($item['attachments'] ?? [] as $a) {
                    $attachments[] = [
                        'url' => $a['url'] ?? '',
                        'category' => $a['category'] ?? '',
                        'alt' => $a['alt'] ?? '',
                    ];
                }
                update_field('area_attachments', $attachments, $taxonomy . '_' . $term_id);
            }

            $imported_ids[] = $causeway_id;
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        self::log('✅ Imported ' . $import_count . ' of ' . $total . ' for ' . $taxonomy . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_terms($taxonomy, $imported_ids);
    }

    private static function assign_area_communities()
    {
        self::log('Assigning area communities...');

        foreach (self::$areas as $area) {
            $term_id = self::get_term_id_by_causeway_id('listing-areas', $area['id']);
            if (!$term_id) {
                continue;
            }

            $related = [];
            foreach ($area['communities'] ?? [] as $community) {
                $community_id = self::get_term_id_by_causeway_id('listing-communities', $community['id']);
                if ($community_id) {
                    $related[] = $community_id;
                }
            }

            update_field('related_communities', $related, 'listing-areas_' . $term_id);
        }

        self::log('✅ Area communities assigned. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function assign_area_slugs(): void
    {
        self::log('🏷 Updating area slugs from pages...');

        // Fetch all pages with a community ACF field
        $totalPages = null;
        $page = 1;
        $perPage = 100;
        $allPages = [];

        do {
            $url = add_query_arg([
                '_fields'      => 'id,slug',
                'per_page'     => $perPage,
                'page'         => $page,
                'parent'       => 343,
                'bypass_clean' => 1,
            ], rest_url('wp/v2/pages'));

            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                self::log('❌ WP Error: ' . $response->get_error_message());
                break;
            }

            $headers = wp_remote_retrieve_headers($response);
            if ($totalPages === null) {
                $totalPages = (int) $headers['x-wp-totalpages'] ?? 0;
                if ($totalPages === 0) {
                    break;
                }
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || empty($data)) {
                break;
            }

            $allPages = array_merge($allPages, $data);
            $page++;
        } while ($page <= $totalPages);

        // Map community ID to page slug using get_field
        $slugMap = [];
        foreach ($allPages as $page) {
            $communityId = get_field('community', $page['id']);
            if ($communityId) {
                $slugMap[(int)$communityId] = $page['slug'];
            }
        }

        foreach (self::$areas as $area) {
            $term_id = self::get_term_id_by_causeway_id('listing-areas', $area['id']);
            if (!$term_id) {
                continue;
            }

            if (isset($slugMap[$area['id']])) {
                update_field('area_slug', $slugMap[$area['id']], 'listing-areas_' . $term_id);
            }
        }
    }

    // This one assigns a single slug for category overview pages and listings.php pages.
    private static function assign_category_slugs(): void
    {
        self::log('🏷 Updating category slugs from pages...');

        $root_parent_ids = [239, 179, 260];
        $perPage = 100;
        $page = 1;
        $totalPages = null;
        $allPages = [];

        // Step 1: Fetch all pages
        do {
            $url = add_query_arg([
                '_fields'      => 'id,slug,template,parent',
                'per_page'     => $perPage,
                'page'         => $page,
                'bypass_clean' => 1,
            ], rest_url('wp/v2/pages'));

            $response = wp_remote_get($url);
            if (is_wp_error($response)) {
                self::log('❌ WP Error: ' . $response->get_error_message());
                break;
            }

            $headers = wp_remote_retrieve_headers($response);
            if ($totalPages === null) {
                $totalPages = (int) $headers['x-wp-totalpages'] ?? 0;
                if ($totalPages === 0) {
                    break;
                }
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || empty($data)) {
                break;
            }

            $allPages = array_merge($allPages, $data);
            $page++;
        } while ($page <= $totalPages);

        // error_log('All Pages: ' . count($allPages) . print_r($allPages, true));

        // Step 2: Filter relevant category pages
        $categoryPages = array_filter($allPages, function ($page) {
            return $page['template'] === 'listings.php' || $page['template'] === 'listings-landing.php';
        });

        self::log('Found category pages: ' . count($categoryPages));

        // Step 3: Build slug map with listings-landing.php taking precedence
        $slugMap = [];

        foreach ($categoryPages as $page) {
            $template = $page['template'];
            $page_id = $page['id'];
            $permalink = get_permalink($page_id);

            if ($template === 'listings.php') {
                $categoryId = get_field('category_filter', $page_id);
                if ($categoryId && !isset($slugMap[(int)$categoryId])) {
                    $slugMap[(int)$categoryId] = $permalink;
                }
            }

            if ($template === 'listings-landing.php') {
                $overviewId = get_field('category', $page_id);
                if ($overviewId) {
                    // Override any existing entry from listings.php
                    $slugMap[(int)$overviewId] = $permalink;
                }
            }
        }

        // error_log('Final Slug Map: ' . print_r($slugMap, true));

        // Step 4: Update ACF field 'category_slug' and clear 'category_overview_slug'
        $terms = get_terms([
            'taxonomy' => 'listings-category',
            'hide_empty' => false,
        ]);

        foreach ($terms as $term) {
            $causeway_id = get_field('causeway_id', 'listings-category_' . $term->term_id);
            if (!$causeway_id) {
                continue;
            }

            $term_key = 'listings-category_' . $term->term_id;

            // Set primary slug
            if (isset($slugMap[(int)$causeway_id])) {
                update_field('category_slug', $slugMap[(int)$causeway_id], $term_key);
            }

            // Always clear deprecated field
            update_field('category_overview_slug', null, $term_key); // Or '' if preferred
        }

        self::log('🏁 Category slugs assignment complete.');
    }



    private static function assign_community_areas_and_regions()
    {
        self::log('Assigning community areas and regions...');

        foreach (self::$communities as $community) {
            $term_id = self::get_term_id_by_causeway_id('listing-communities', $community['id']);
            if (!$term_id) {
                continue;
            }

            $area_ids = [];
            foreach ($community['areas'] ?? [] as $area) {
                $area_id = self::get_term_id_by_causeway_id('listing-areas', $area['id']);
                if ($area_id) {
                    $area_ids[] = $area_id;
                }
            }

            $region_ids = [];
            foreach ($community['regions'] ?? [] as $region) {
                $region_id = self::get_term_id_by_causeway_id('listing-regions', $region['id']);
                if ($region_id) {
                    $region_ids[] = $region_id;
                }
            }

            update_field('related_areas', $area_ids, 'listing-communities_' . $term_id);
            update_field('related_regions', $region_ids, 'listing-communities_' . $term_id);
            self::log("Assigned community {$community['name']} (ID: {$community['id']}) to areas: " . implode(', ', $area_ids) . " and regions: " . implode(', ', $region_ids));
        }

        self::log('✅ Community areas and regions assigned. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function assign_region_communities()
    {
        self::log('Assigning region communities...');
        foreach (self::$regions as $region) {
            $term_id = self::get_term_id_by_causeway_id('listing-regions', $region['id']);
            if (!$term_id) {
                continue;
            }

            $related = [];
            foreach ($region['communities'] ?? [] as $community) {
                $community_id = self::get_term_id_by_causeway_id('listing-communities', $community['id']);
                if ($community_id) {
                    $related[] = $community_id;
                }
            }

            update_field('related_communities', $related, 'listing-regions_' . $term_id);
            self::log("Assigned region {$region['name']} (ID: {$region['id']}) to communities: " . implode(', ', $related));
        }
        self::log('✅ Region communities assigned. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_listings()
    {
        self::log('Importing listings..');
        $imported_ids = [];

        // Build base endpoint with status filter
        // $endpoint = self::$baseURL . 'listings?search=listings.status&compare==&value=Published';
        $endpoint = self::$baseURL . 'listings?search=listings.id&compare==&value=1368';

        // Apply optional filters (Areas / Communities) from settings page
        $area_ids = get_field('import_filter_areas', 'option') ?: [];
        $community_ids = get_field('import_filter_communities', 'option') ?: [];

        // Convert WP term IDs -> Causeway IDs (stored as term meta causeway_id)
        $area_causeway_ids = [];
        foreach ((array)$area_ids as $tid) {
            $cid = (int) get_field('causeway_id', 'listing-areas_' . $tid);
            if ($cid) $area_causeway_ids[] = $cid;
        }
        $community_causeway_ids = [];
        foreach ((array)$community_ids as $tid) {
            $cid = (int) get_field('causeway_id', 'listing-communities_' . $tid);
            if ($cid) $community_causeway_ids[] = $cid;
        }

        // If filters selected, append repeated search/compare/value triples as required by API
        foreach ((array)$community_causeway_ids as $cid) {
            $endpoint .= '&search=locations.community_id&compare==&value=' . rawurlencode((string)$cid);
        }
        foreach ((array)$area_causeway_ids as $aid) {
            // API expects communities_areas.area_id per spec
            $endpoint .= '&search=communities_areas.area_id&compare==&value=' . rawurlencode((string)$aid);
        }

        // Allow last-minute customization via filter
        $endpoint = apply_filters('causeway_import_listings_endpoint', $endpoint, [
            'areas' => $area_causeway_ids,
            'communities' => $community_causeway_ids,
        ]);

        self::log('Listings endpoint: ' . $endpoint);

        $response = wp_remote_get($endpoint, [
            'timeout' => 1200,
        ]);

        // $response = wp_remote_get(self::$baseURL . 'listings?search=listings.id&compare==&value=1122', [
        //     'timeout' => 1200,
        // ]);

        if (is_wp_error($response)) {
            self::log('❌ Failed to fetch listings');
            // self::log($response);
            return;
        }

        $listings = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($listings)) {
            self::log('❌ Invalid listings data');
            // self::log($listings);
            return;
        }

        // Safety net: if filters were provided, enforce client-side filtering as well
        if (!empty($area_causeway_ids) || !empty($community_causeway_ids)) {
            $pre_count = count($listings);
            $listings = array_values(array_filter($listings, function ($item) use ($area_causeway_ids, $community_causeway_ids) {
                // No filters -> accept all
                if (empty($area_causeway_ids) && empty($community_causeway_ids)) return true;

                foreach ((array)($item['locations'] ?? []) as $loc) {
                    // Community match
                    $comm_id = isset($loc['community']['id']) ? (int)$loc['community']['id'] : 0;
                    if ($comm_id && in_array($comm_id, $community_causeway_ids, true)) return true;

                    // Area match under the community areas array
                    foreach ((array)($loc['community']['areas'] ?? []) as $ar) {
                        $aid = isset($ar['id']) ? (int)$ar['id'] : 0;
                        if ($aid && in_array($aid, $area_causeway_ids, true)) return true;
                    }
                }

                return false; // no match found
            }));
            $post_count = count($listings);
            self::log("🔎 Applied filters: areas=" . json_encode($area_causeway_ids) . ", communities=" . json_encode($community_causeway_ids) . " | kept {$post_count}/{$pre_count}");
        }

        $listings_count = is_array($listings) ? count($listings) : 0;
        self::log('✅ Received ' . $listings_count . ' listings from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($listings as $item) {
            $post_title = $item['name'] ?? '';
            $slug = $item['slug'] ?? '';
            $description = $item['description'] ?? '';
            $causeway_id = $item['id'] ?? null;
            $post_status = strtolower($item['status'] ?? '') === 'published' ? 'publish' : 'draft';
            $post_id = false;

            if (!$post_title || !$slug || !$causeway_id || $post_status !== 'publish') {
                self::log("Incorrect required information or unpublished");
                continue;
            }

            // Check if post already exists by causeway_id
            $existing = get_posts([
                'post_type'  => 'listing',
                'meta_key'   => 'causeway_id',
                'meta_value' => $causeway_id,
                'numberposts' => 1,
                'fields' => 'ids',
                'lang' => 'en',
            ]);

            if (!empty($existing)) {
                $post_id = $existing[0];
            }

            if (!$post_id) {
                self::log("Creating new listing: " . $post_title);
                $post_id = wp_insert_post([
                    'post_type' => 'listing',
                    'post_title' => $post_title,
                    'post_name' => $slug,
                    'post_content' => $description,
                    'post_status'  => $post_status,
                ]);
            } else {
                self::log("Updating existing listing: " . $post_title);
                // Update post title, slug, and content
                wp_update_post([
                    'ID'           => $post_id,
                    'post_title'   => $post_title,
                    'post_name'    => $slug,
                    'post_content' => $description,
                    'post_status'  => $post_status,
                ]);
            }

            if (!$post_id || is_wp_error($post_id)) {
                self::log("Error creating or updating listing: $post_title");
                continue;
            }

            self::$listing_map[$slug] = $post_id;

            // Save ID of imported listing
            $imported_ids[] = $causeway_id;


            // ACF Meta
            update_field('causeway_id', $causeway_id, $post_id);
            update_field('provider', $item['provider'] ?? '', $post_id);
            update_field('status', $item['status'] ?? '', $post_id);
            update_field('highlights', $item['highlights'] ?? '', $post_id);
            update_field('email', $item['email'] ?? '', $post_id);
            update_field('metaline', $item['meta'] ?? '', $post_id);
            update_field('contact_name', $item['contact_name'] ?? '', $post_id);
            update_field('phone_primary', $item['phone_primary'] ?? '', $post_id);
            update_field('phone_secondary', $item['phone_secondary'] ?? '', $post_id);
            update_field('phone_offseason', $item['phone_offseason'] ?? '', $post_id);
            update_field('phone_tollfree', $item['phone_tollfree'] ?? '', $post_id);
            update_field('price', $item['price'] ?? 0, $post_id);
            update_field('is_featured', $item['is_featured'] ?? false, $post_id);
            update_field('is_in_national_park', $item['is_in_national_park'] ?? false, $post_id);
            update_field('is_campaign_only', $item['is_campaign_only'] ?? false, $post_id);
            update_field('opengraph_title', $item['opengraph_title'] ?? '', $post_id);
            update_field('opengraph_description', $item['opengraph_description'] ?? '', $post_id);
            update_field('activated_at', $item['activated_at'] ?? '', $post_id);
            update_field('expired_at', $item['expired_at'] ?? '', $post_id);
            update_field('tripadvisor_url', $item['tripadvisor_url'] ?? '', $post_id);
            update_field('tripadvisor_id', $item['tripadvisor_id'] ?? '', $post_id);
            update_field('tripadvisor_rating_url', $item['tripadvisor_rating_url'] ?? '', $post_id);
            update_field('tripadvisor_rating', $item['tripadvisor_rating'] ?? '', $post_id);
            update_field('tripadvisor_count', $item['tripadvisor_count'] ?? '', $post_id);

            // Remove old terms before addingn new ones
            wp_set_object_terms($post_id, [], 'listing-type');
            wp_set_object_terms($post_id, [], 'listings-category');
            wp_set_object_terms($post_id, [], 'listings-amenities');
            wp_set_object_terms($post_id, [], 'listing-campaigns');

            // Assign Taxonomies
            self::assign_listing_terms($post_id, $item['types'], 'listing-type');
            self::assign_listing_terms($post_id, $item['categories'], 'listings-category');
            self::assign_listing_terms($post_id, $item['amenities'], 'listings-amenities');
            self::assign_listing_terms($post_id, $item['campaigns'], 'listing-campaigns');

            // Assign Seasons
            wp_set_object_terms($post_id, [], 'listings-seasons');
            $season_ids = [];
            foreach ($item['seasons'] ?? [] as $season) {
                $term = get_term_by('name', $season, 'listings-seasons');
                if ($term) {
                    $season_ids[] = $term->term_id;
                }
            }
            wp_set_object_terms($post_id, $season_ids, 'listings-seasons');

            // Remove old terms before addingn new ones
            wp_set_object_terms($post_id, [], 'listing-communities');
            wp_set_object_terms($post_id, [], 'listing-regions');
            wp_set_object_terms($post_id, [], 'listing-areas');

            // Assign Locations (coordinates and details)
            self::assign_locations($item['locations'] ?? [], $post_id);

            // Websites Repeater
            $websites = [];
            foreach ($item['websites'] ?? [] as $site) {
                $websites[] = [
                    'url' => $site['url'] ?? '',
                    'name' => $site['name'] ?? '',
                    'type_name' => $site['type']['name'] ?? '',
                    'type_id' => $site['type']['id'] ?? '',
                ];
            }
            update_field('websites', $websites, $post_id);

            // Attachments Repeater
            $attachments = [];
            foreach ($item['attachments'] ?? [] as $a) {
                $attachments[] = [
                    'url' => $a['url'] ?? '',
                    'category' => $a['category'] ?? '',
                    'alt' => $a['alt'] ?? '',
                ];
            }
            update_field('attachments', $attachments, $post_id);

            // Set featured image based on attachments (Primary first, else first)
            self::maybe_set_featured_image($post_id, $attachments, $post_title);

            // Dates Repeater
            $dates = [];
            foreach ($item['dates'] ?? [] as $date) {
                $dates[] = [
                    'start_at' => $date['start_at'] ?? '',
                    'end_at'   => $date['end_at'] ?? '',
                    'rrule'    => $date['rrule'] ?? '',
                ];
            }
            update_field('dates', $dates, $post_id);

            // ②  Derive and store occurrences + next upcoming
            [$rows, $next] = self::build_occurrences_for_acf($dates);

            update_field('all_occurrences', $rows, $post_id);     // repeater
            update_field('next_occurrence',  $next, $post_id);    // single date_time_picker

            // error_log("✅ Updated Listing: " . $post_title . " (ID: $post_id)");
        }

        self::log("Assigning related listings...");
        // TODO matt does not need to send the entire related listing object, just the slug OR ID
        foreach ($listings as $item) {
            $post_id = self::$listing_map[$item['slug']] ?? null;
            if (!$post_id) {
                continue;
            }

            $related_ids = [];

            foreach ($item['related'] ?? [] as $related) {
                if (!is_array($related)) {
                    continue;
                }

                $related_slug = $related['slug'] ?? null;
                if (!$related_slug) {
                    continue;
                }

                $related_post_id = self::$listing_map[$related_slug] ?? null;
                if ($related_post_id) {
                    $related_ids[] = $related_post_id;
                } else {
                    self::log("⚠️ Related listing not found: $related_slug for {$item['slug']}");
                }
            }

            if (!empty($related_ids)) {
                self::log("✅ Assigning " . count($related_ids) . " related listings to {$item['slug']} (ID: $post_id)");
                update_field('related_listings', $related_ids, $post_id);
            } else {
                delete_field('related_listings', $post_id);
            }
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        self::log('✅ Imported ' . $import_count . ' of ' . $listings_count . ' listings @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_listings($imported_ids);
    }

    private static function assign_locations($locations, $post_id) {
        // ---- Simple per-request cache (taxonomy + normalized name) ----
        static $term_cache = [
            'listing-communities' => [],
            'listing-regions'     => [],
            'listing-areas'       => [],
        ];

        // Normalize a name for cache keys (lowercase + decoded + trimmed)
        $normalize = static function (?string $name): string {
            if ($name === null) return '';
            $n = trim(html_entity_decode($name));
            return mb_strtolower($n);
        };

        // Lookup with cache + slug fallback
        $lookup_term_by_name = static function (string $taxonomy, ?string $name) use (&$term_cache, $normalize) {
            $key = $normalize($name);
            if ($key === '') return null;

            if (array_key_exists($key, $term_cache[$taxonomy])) {
                return $term_cache[$taxonomy][$key]; // WP_Term|null
            }

            // Primary: by name
            $term = get_term_by('name', $name, $taxonomy);
            if (!$term || is_wp_error($term)) {
                // Fallback: by slug derived from the name
                $slug = sanitize_title($name);
                $term = get_term_by('slug', $slug, $taxonomy);
                if ($term && is_wp_error($term)) {
                    $term = null;
                }
            }

            // Cache result (even nulls) to avoid repeat DB hits
            $term_cache[$taxonomy][$key] = $term ?: null;
            return $term_cache[$taxonomy][$key];
        };
        // ---------------------------------------------------------------

        $rows          = [];
        $community_ids = [];
        $region_ids    = [];
        $area_ids      = [];

        foreach (($locations ?? []) as $location) {
            // Resolve community term for this row (by name from payload)
            $community_name = $location['community']['name'] ?? null;
            $community_term = $community_name
                ? $lookup_term_by_name('listing-communities', $community_name)
                : null;

            if ($community_term) {
                $community_ids[] = (int) $community_term->term_id;
            }

            // Regions (array on the community)
            foreach ((array)($location['community']['regions'] ?? []) as $region) {
                if (!empty($region['name'])) {
                    $term = $lookup_term_by_name('listing-regions', $region['name']);
                    if ($term) $region_ids[] = (int) $term->term_id;
                }
            }

            // Areas (array on the community)
            foreach ((array)($location['community']['areas'] ?? []) as $area) {
                if (!empty($area['name'])) {
                    $term = $lookup_term_by_name('listing-areas', $area['name']);
                    if ($term) $area_ids[] = (int) $term->term_id;
                }
            }

            // Build one repeater row ('' clears fields in ACF)
            $row = [
                'latitude'      => (isset($location['latitude'])  && $location['latitude']  !== '') ? (float)$location['latitude']  : '',
                'longitude'     => (isset($location['longitude']) && $location['longitude'] !== '') ? (float)$location['longitude'] : '',
                'state'         => $location['state']         ?? '',
                'country'       => $location['country']       ?? '',
                'name'          => $location['name']          ?? '',
                'civic_address' => $location['civic_address'] ?? '',
                'postal_code'   => $location['postal_code']   ?? '',
                'place_id'      => $location['place_id']      ?? '',
            ];

            // ✅ Write the per-row community term ID for the exporter
            if ($community_term) {
                $row['community'] = (int) $community_term->term_id; // ACF taxonomy subfield (Term ID)
            }

            $rows[] = $row;
        }

        // Save the repeater in one shot (REPLACES existing rows)
        // If you have the field key, prefer: update_field('field_XXXX_locations', $rows, $post_id);
        update_field('location_details', $rows, $post_id);

        // De-duplicate and set taxonomies once (replace terms)
        $community_ids = array_values(array_unique($community_ids));
        $region_ids    = array_values(array_unique($region_ids));
        $area_ids      = array_values(array_unique($area_ids));

        wp_set_object_terms($post_id, $community_ids, 'listing-communities', false);
        wp_set_object_terms($post_id, $region_ids,    'listing-regions',     false);
        wp_set_object_terms($post_id, $area_ids,      'listing-areas',       false);
    }

    public static function export_listings()
    {
        if (!get_field('is_headless', 'option')) {
            self::log('Skipping export notification: site not configured as headless');
            return;
        }
        self::log("Start Export");
        // Notify public site to re-fetch causeway data
        $public_url = get_field('causeway_public_url', 'option');
        $secret = get_field('headless_api_secret', 'option');

        if (!$public_url || !$secret) {
            self::log('Skipping export notification: Missing public URL or secret');
            return;
        }

        if ($public_url) {
            $endpoint = trailingslashit($public_url) . 'api/fetch-causeway';

            $response = wp_remote_post($endpoint, [
                'timeout' => 1200,
                'connect_timeout' => 30,
                'headers'         => [
                    'Content-Type' => 'application/json',
                    'x-causeway-secret' => $secret,
                ],
                'body' => json_encode([
                    'trigger' => 'listings_updated',
                ]),
            ]);

            if (is_wp_error($response)) {
                self::log('❌ Failed to notify public site. ' . $endpoint);
                // self::log($response);
            } else {
                self::log('✅ Public site received data at ' . $endpoint);
            }

            return;
        } else {
            self::log("❌ Public URL not set. Cannot notify.");
        }
    }

    // Helper functions
    private static function get_term_by_causeway_id(int $causeway_id, string $taxonomy)
    {
        if (! isset(self::$term_cache[ $taxonomy ])) {
            $terms  = get_terms([ 'taxonomy' => $taxonomy, 'hide_empty' => false ]);
            $map    = [];
            foreach ($terms as $t) {
                $id = (int) get_field('causeway_id', "{$taxonomy}_{$t->term_id}");
                if ($id) {
                    $map[ $id ] = $t;
                }
            }
            self::$term_cache[ $taxonomy ] = $map;
        }
        return self::$term_cache[ $taxonomy ][ $causeway_id ] ?? null;
    }

    private static function get_term_id_by_causeway_id($taxonomy, $causeway_id)
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        foreach ($terms as $term) {
            if ((int) get_field('causeway_id', $taxonomy . '_' . $term->term_id) === (int) $causeway_id) {
                return $term->term_id;
            }
        }
        return null;
    }

    private static function assign_listing_terms($post_id, $items, $taxonomy)
    {
        if (!is_array($items)) {
            return;
        }
        $term_ids = [];

        foreach ($items as $item) {
            $term = self::get_term_by_causeway_id($item['id'], $taxonomy);
            if ($term) {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
        }
    }

    private static function delete_old_listings($imported_ids)
    {
        self::log("Deleting old listings...");

        if (empty($imported_ids)) {
            self::log("⚠️ No imported IDs provided. Skipping deletion.");
            return;
        }

        // Make a fast lookup set
        $imported_ids_map = array_flip(array_map('intval', $imported_ids));
        $deleted_count = 0;

        global $wpdb;

        // Query all listings with causeway_id (can be NULL) and their post ID
        $results = $wpdb->get_results("
            SELECT p.ID, pm.meta_value as causeway_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'causeway_id'
            WHERE p.post_type = 'listing'
            AND p.post_status != 'trash'
        ");

        foreach ($results as $row) {
            $post_id = (int) $row->ID;
            $causeway_id = isset($row->causeway_id) ? (int) $row->causeway_id : null;

            // Delete if no causeway_id OR causeway_id not in imported_ids
            if (is_null($causeway_id) || !isset($imported_ids_map[$causeway_id])) {
                // Remove featured image attachment if safe to do so (non-headless only)
                if (!get_field('is_headless', 'option')) {
                    $thumb_id = get_post_thumbnail_id($post_id);
                    if ($thumb_id) {
                        // Allow override via filter
                        $should_delete = apply_filters('causeway_delete_listing_featured_image', true, $post_id, $thumb_id);
                        if ($should_delete) {
                            // Check if any other post uses this as its thumbnail
                            $others = get_posts([
                                'post_type'   => 'any',
                                'meta_key'    => '_thumbnail_id',
                                'meta_value'  => $thumb_id,
                                'fields'      => 'ids',
                                'numberposts' => 2, // we only need to know if there's more than 1 total
                                'post_status' => 'any',
                            ]);
                            // $others includes this post; if count <= 1, safe to remove
                            if (count($others) <= 1) {
                                wp_delete_attachment($thumb_id, true);
                                self::log("🧽 Deleted featured image attachment {$thumb_id} for stale listing ID: {$post_id}");
                            } else {
                                self::log("ℹ️ Skipped deleting attachment {$thumb_id}: used by other posts");
                            }
                        }
                    }
                }

                wp_delete_post($post_id, true);
                $reason = is_null($causeway_id) ? 'no Causeway ID' : "stale Causeway ID: $causeway_id";
                self::log("🗑️ Deleted listing ID: $post_id ($reason)");
                $deleted_count++;
            }
        }

        self::log("🗑️ Deleted " . $deleted_count . " listings");

        return;
    }

    private static function delete_old_terms($taxonomy, $imported_ids)
    {
        if (empty($imported_ids)) {
            return;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        $deleted_count = 0;
        foreach ($terms as $term) {
            $stored_id = get_field('causeway_id', $taxonomy . '_' . $term->term_id);
            if (!$stored_id || !in_array((int) $stored_id, $imported_ids, true)) {
                $result = wp_delete_term($term->term_id, $taxonomy);
                if (is_wp_error($result)) {
                    self::log("❌ Failed to delete term: {$term->name} (ID: {$term->term_id}) from $taxonomy. Reason: " . $result->get_error_message());
                } else {
                    self::log("🗑️ Deleted stale term: {$term->name} (ID: {$term->term_id}) from $taxonomy");
                    $deleted_count++;
                }
            }
        }

        if ($deleted_count > 0) {
            self::log("🧹 Deleted $deleted_count stale terms from $taxonomy");
        }
    }

    /**
     * Turn the raw API “dates” repeater into:
     *   • $rows → ready for the ACF repeater “all_occurrences”
     *   • $next → string for the single date_time_picker “next_occurrence”
     *
     * @return array [$rows, $nextString]
     */
    private static function build_occurrences_for_acf(array $dates, ?int $forceYear = null, ?string $tzName = null): array {
        // Option C: interpret DTSTART timezone if present (TZID or Z) for local wall-time, then store UTC.
        // Force behavior: allow treating DTSTART with trailing 'Z' (UTC) as local wall-time by default
        // so weekly rules keep a constant local clock across DST and only UTC shifts.
    $defaultSiteTz = get_option('timezone_string') ?: 'UTC';
    $fallbackTzName = $tzName ?: apply_filters('causeway_default_source_timezone', $defaultSiteTz);
        $treatZAsLocal = apply_filters('causeway_treat_rrule_z_as_local', true);
    $utcTz = new DateTimeZone('UTC');
        $year  = $forceYear ?: (int) Carbon::now($utcTz)->year; // window year in UTC
        $format = 'Y-m-d H:i:s'; // original API format
        $rows   = [];

    self::occ_log("build_occurrences_for_acf: year={$year} siteTz={$defaultSiteTz} fallbackTz={$fallbackTzName} treatZAsLocal=" . ($treatZAsLocal ? '1' : '0'));

        // Filter window in UTC
        $windowStart = Carbon::create($year, 1, 1, 0, 0, 0, $utcTz);
        $windowEnd   = Carbon::create($year, 12, 31, 23, 59, 59, $utcTz);

        // Helper: extract source timezone from RRULE/DTSTART, else fallback (optionally treat Z as local)
        $extractTz = static function (string $rrule, string $fallback, bool $treatZAsLocal): string {
            if (preg_match('/DTSTART;TZID=([^:]+):/i', $rrule, $m)) {
                return $m[1];
            }
            if (preg_match('/DTSTART:[0-9T]+Z(\n|$)/i', $rrule)) {
                return $treatZAsLocal ? $fallback : 'UTC';
            }
            return $fallback;
        };

        foreach ($dates as $entry) {
            $entryRows = [];
            $start = null;
            $end   = null;
            $rrule_string = (string)($entry['rrule'] ?? '');
            $sourceTzName = $rrule_string ? $extractTz($rrule_string, $fallbackTzName, $treatZAsLocal) : $fallbackTzName;
            try { $sourceTz = new DateTimeZone($sourceTzName); } catch (\Throwable $e) { $sourceTz = $utcTz; $sourceTzName = 'UTC'; }

            if (!empty($entry['start_at'])) {
                try { $start = Carbon::createFromFormat($format, $entry['start_at'], $sourceTz); } catch (\Throwable $e) { $start = null; }
            }
            if (!empty($entry['end_at'])) {
                try { $end = Carbon::createFromFormat($format, $entry['end_at'], $sourceTz); } catch (\Throwable $e) { $end = null; }
            }

            if (!$start || !$end) {
                self::log('Invalid date: ' . json_encode($entry));
                continue;
            }

            $has_rrule = !empty($entry['rrule']);
            self::occ_log('Entry: tzDetected=' . $sourceTzName . ' hasRRule=' . ($has_rrule ? '1' : '0') . ' start_at=' . ($entry['start_at'] ?? '') . ' end_at=' . ($entry['end_at'] ?? ''));

            // Compute a sane single-occurrence duration.
            // When RRULE is present (esp. with BYDAY), treat end_at as a time-of-day.
            // If end time is <= start time, assume it wraps to next day.
            if ($has_rrule) {
                $start_minutes = (int)$start->format('H') * 60 + (int)$start->format('i');
                $end_minutes   = (int)$end->format('H')   * 60 + (int)$end->format('i');
                $duration      = $end_minutes - $start_minutes;
                if ($duration <= 0) {
                    $duration += 24 * 60; // cross-midnight
                }
            } else {
                // One-off event: use the real span
                $duration = max(0, $end->diffInMinutes($start));
            }

            if ($has_rrule) {
                try {
                    $hasDtstart = stripos($rrule_string, 'DTSTART') !== false;
                    $hasDtstartZ = (bool) preg_match('/DTSTART:[0-9T]+Z(\n|$)/i', $rrule_string);
                    $startForRRule = $start; // default seed

                    // If DTSTART is UTC (Z) but we are treating Z as local, derive the local wall time from DTSTART
                    if ($hasDtstart && $hasDtstartZ && $treatZAsLocal) {
                        if (preg_match('/DTSTART:(\d{8}T\d{6})Z/i', $rrule_string, $m)) {
                            try {
                                $dtStartUtc = Carbon::createFromFormat('Ymd\THis', $m[1], $utcTz);
                                $startForRRule = Carbon::instance($dtStartUtc)->setTimezone($sourceTz);
                            } catch (\Throwable $e) {
                                // fall back to original $start
                                $startForRRule = $start;
                            }
                        }
                    }

                    if ($hasDtstart && $hasDtstartZ && $treatZAsLocal) {
                        // Strip DTSTART line so we can seed local dtstart explicitly for DST-aware expansion
                        $rrule_no_dtstart = preg_replace('/^DTSTART[^\n]*\n/m', '', $rrule_string);
                        self::occ_log('Using RRULE without DTSTART; seedLocal=' . ($startForRRule ? $startForRRule->format('Y-m-d H:i:s') : 'null') . ' tz=' . $sourceTzName);
                        $rr = new RRule($rrule_no_dtstart, $startForRRule);
                    } elseif ($hasDtstart) {
                        self::occ_log('Using RRULE with embedded DTSTART (kept as-is).');
                        $rr = new RRule($rrule_string);
                    } else {
                        self::occ_log('Using RRULE with explicit seed; seedLocal=' . ($startForRRule ? $startForRRule->format('Y-m-d H:i:s') : 'null') . ' tz=' . $sourceTzName);
                        $rr = new RRule($rrule_string, $startForRRule);
                    }
                    $occurrences = $rr->getOccurrencesBetween($windowStart, $windowEnd);
                    self::occ_log('Generated occurrences this entry: ' . count($occurrences));
                    foreach ($occurrences as $occ) {
                        // Work in local/source timezone for duration math, then convert to UTC
                        $occ_start_local = Carbon::instance($occ)->setTimezone($sourceTz);
                        $occ_end_local   = (clone $occ_start_local)->addMinutes($duration);
                        $occ_start_utc   = (clone $occ_start_local)->setTimezone($utcTz);
                        $occ_end_utc     = (clone $occ_end_local)->setTimezone($utcTz);
                        $entryRows[] = [
                            'occurrence_start' => $occ_start_utc->format('Y-m-d H:i:s'),
                            'occurrence_end'   => $occ_end_utc->format('Y-m-d H:i:s'),
                        ];
                    }
                } catch (\Throwable $e) {
                    self::log('RRULE parse/fetch failed: ' . $e->getMessage() . ' | ' . $rrule_string);
                }
            } else {
                // One-off: convert to UTC for storage and include only if within UTC window
                $startUtc = (clone $start)->setTimezone($utcTz);
                $endUtc   = (clone $end)->setTimezone($utcTz);
                if ($startUtc->betweenIncluded($windowStart, $windowEnd)) {
                    $entryRows[] = [
                        'occurrence_start' => $startUtc->format('Y-m-d H:i:s'),
                        'occurrence_end'   => $endUtc->format('Y-m-d H:i:s'),
                    ];
                }
            }

            // Per-entry sampling: first 2, first >= Nov 1, last 2
            if (!empty($entryRows)) {
                $sample = [];
                $sample = array_merge($sample, array_slice($entryRows, 0, 2));
                $nov1 = sprintf('%04d-11-01 00:00:00', $year);
                foreach ($entryRows as $r) {
                    if ($r['occurrence_start'] >= $nov1) { $sample[] = $r; break; }
                }
                $tail = array_slice($entryRows, -2);
                $sample = array_merge($sample, $tail);
                self::occ_log('Entry samples: ' . json_encode($sample));
            }

            // accumulate
            $rows = array_merge($rows, $entryRows);
        }

        usort($rows, fn($a,$b) => strcmp($a['occurrence_start'], $b['occurrence_start']));

        // First future start relative to current UTC time
        $now  = Carbon::now($utcTz)->format('Y-m-d H:i:s');
        $next = null;
        foreach ($rows as $r) {
            if ($r['occurrence_start'] >= $now) { $next = $r['occurrence_start']; break; }
        }

        return [$rows, $next];
    }

    /**
     * Public debug helper used by WP-CLI tester to exercise the private builder
     * without changing import-time visibility.
     *
     * @param array      $dates   Dates payload (array of {start_at,end_at,rrule})
     * @param int|null   $year    Force window year
     * @param string|null $tzName Fallback timezone when RRULE lacks TZ info
     * @return array{rows: array<int, array{occurrence_start:string,occurrence_end:string}>, next: string|null}
     */
    public static function debug_build_occurrences(array $dates, ?int $year = null, ?string $tzName = null): array {
        [$rows, $next] = self::build_occurrences_for_acf($dates, $year, $tzName);
        return [ 'rows' => $rows, 'next' => $next ];
    }

    private static function log($message): void
    {
        static $enabled = null;
        if ($enabled === null) {
            $enabled = (bool) get_field('enable_import_logs', 'option');
        }
        if (!$enabled) {
            return;
        }
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        error_log('[Causeway] ' . $message);
    }

    /**
     * Set the post featured image using the first attachment with category 'Primary' (case-insensitive),
     * falling back to the first attachment. Downloads the image to the media library if needed.
     * Caches by original URL in post meta to avoid re-downloading when unchanged.
     */
    private static function maybe_set_featured_image(int $post_id, array $attachments, string $post_title = ''): void
    {
        // In headless mode, don't manage media
        if (get_field('is_headless', 'option')) {
            return;
        }
        if (empty($attachments)) {
            return;
        }

        // Pick candidate URL
        $primary = null;
        foreach ($attachments as $att) {
            if (!empty($att['url']) && isset($att['category']) && strcasecmp(trim($att['category']), 'Primary') === 0) {
                $primary = $att;
                break;
            }
        }
        $selected = $primary ?: (isset($attachments[0]) ? $attachments[0] : null);
        if (!$selected || empty($selected['url'])) {
            return;
        }

        $url = esc_url_raw($selected['url']);
        $current_source = get_post_meta($post_id, '_causeway_featured_image_source', true);
        $current_thumb  = get_post_thumbnail_id($post_id);

        // If already set with same source, skip.
        if ($current_thumb && $current_source === $url) {
            return;
        }

        $desc = $post_title ?: 'Listing image';
        $attachment_id = self::sideload_attachment($url, $post_id, $desc);
        if (is_wp_error($attachment_id)) {
            self::log('❌ Failed to sideload featured image for post ' . $post_id . ' (' . $attachment_id->get_error_message() . ') URL: ' . $url);
            return;
        }

        // Set alt text if provided and not already set
        if (!empty($selected['alt'])) {
            $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!$existing_alt) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags($selected['alt']));
            }
        }

        set_post_thumbnail($post_id, $attachment_id);
        update_post_meta($post_id, '_causeway_featured_image_source', $url);
        self::log('🖼️ Set featured image for listing ' . $post_id . ' from URL: ' . $url . ' (attachment ' . $attachment_id . ')');
    }

    /**
     * Robust sideloader that supports modern formats (e.g., AVIF) and avoids
     * the legacy extension regex in media_sideload_image(). Returns attachment ID or WP_Error.
     */
    private static function sideload_attachment(string $url, int $post_id, string $desc = '')
    {
        // Core media helpers
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Fetch to a temp file
        $tmp = download_url($url, 300);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Derive a sane filename from the path (ignore query string)
        $path = parse_url($url, PHP_URL_PATH);
        $basename = $path ? basename($path) : 'image';
        // Fallback if somehow no extension
        if (strpos($basename, '.') === false) {
            $basename .= '.jpg';
        }

        $file_array = [
            'name'     => $basename,
            'tmp_name' => $tmp,
        ];

        // Defer to WP to move file into uploads and create attachment
        $attach_id = media_handle_sideload($file_array, $post_id, $desc);

        if (is_wp_error($attach_id)) {
            @unlink($tmp);
            return $attach_id;
        }

        return $attach_id;
    }
}
