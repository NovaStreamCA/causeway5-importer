<?php
class Causeway_Importer {
    private static $areas = [];
    private static $communities = [];
    private static $regions = [];
    private static $counties = [];
    private static $listing_map = [];
    private static $start;
    private static $baseURL = 'https://api-causeway5.novastream.dev/';

    public static function import() {
        error_log('start import');
        define('CAUSEWAY_IMPORTING', true);
        self::$start = microtime(true);

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

        self::assign_area_slugs();
        self::assign_category_slugs();

        self::import_listings();

        error_log('✅ Import Completed. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::export_listings();
    }

    private static function fetch_remote($endpoint) {
        $response = wp_remote_get(self::$baseURL . $endpoint);

        if(is_wp_error($response)) {
            error_log(`❌ WP Error ({$endpoint}): ` . $response->get_error_message());
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($data)) {
            error_log(`❌ JSON Error ({$endpoint}): ` . $data->get_error_message());
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private static function import_types() {
        error_log('Import types...');
        $data = self::fetch_remote('listing-types');
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;

        error_log('✅ Received '. $total .' types from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($data as $item) {
            $name = $item['name'];
            $icon = $item['icons'];
            $causeway_id = $item['id'];

            if (!$causeway_id) continue;

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
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            }

            $imported_ids[] = $causeway_id;
            update_field('icon', $icon, 'listing-type_' . $term_id);
            update_field('causeway_id', $causeway_id, 'listing-type_' . $term_id);
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        error_log('✅ Types Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_terms('listing-type', $imported_ids);
    }

    private static function import_categories() {
        error_log('Importing categories...');
        $data = self::fetch_remote('categories');
        $lookup = [];
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;
        
        error_log('✅ Received '. $total .' categories from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

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

            if (!$name || !$causeway_id) continue;

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
                if (is_wp_error($term)) continue;
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
        error_log('✅ Categories Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    
        self::delete_old_terms('listings-category', $imported_ids);
    }

    private static function import_amenities() {
        error_log('Importing amenities...');
        $data = self::fetch_remote('amenities');
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;
        
        error_log('✅ Received '. $total .' amenities from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($data as $item) {
            $name = $item['name'] ?? null;
            $causeway_id = $item['id'] ?? null;
            $type_id = $item['type']['id'] ?? null;

            if (!$name || !$causeway_id) continue;

            // Check if term already exists by causeway_id
            $existing = self::get_term_by_causeway_id($causeway_id, 'listings-amenities');

            if ($existing) {
                $term_id = $existing->term_id;
                wp_update_term($term_id, 'listing-amenities', [
                    'name' => $name,
                    'slug' => sanitize_title($name),
                ]);
            } else {
                $term = wp_insert_term($name, 'listings-amenities', [
                    'slug' => sanitize_title($name),
                ]);
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            }

            if (!isset($term_id)) continue;

            // Update ACF fields
            update_field('causeway_id', $causeway_id, 'listings-amenities_' . $term_id);

            // Set ACF relationship to type
            $type_term = self::get_term_by_causeway_id($type_id, 'listing-type');
            if ($type_term) {
                update_field('listing_type', $type_term->term_id, 'listings-amenities_' . $term_id);
            }

            $imported_ids[] = $causeway_id;
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        error_log('✅ Amenities Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    
        self::delete_old_terms('listings-amenities', $imported_ids);
    }

    private static function import_campaigns() {
        error_log('Importing campaigns...');
        $data = self::fetch_remote('campaigns');
        $imported_ids = [];
        $total = is_array($data) ? count($data) : 0;
        
        error_log('✅ Received '. $total .' campaigns from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($data as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];
            $activated_at = $item['activated_at'] ?? null;
            $expired_at = $item['expired_at'] ?? null;

            if (!$causeway_id) continue;

            $existing = self::get_term_by_causeway_id($causeway_id, 'listing-campaigns');

            if (!$existing) {
                $term = wp_insert_term($name, 'listing-campaigns');
                if (is_wp_error($term)) continue;
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
        error_log('✅ Campaigns Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    
        self::delete_old_terms('listing-campaigns', $imported_ids);
    }

    private static function import_seasons() {
        error_log('Importing seasons...');

        $seasons = [
            ['id' => 1, 'name' => 'Any Season'],
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
                if (is_wp_error($term)) continue;
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

        error_log('✅ Seasons imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_terms($taxonomy, $items) {
        error_log('Importing '.$taxonomy.'...');

        $imported_ids = [];
        $total = is_array($items) ? count($items) : 0;
        
        error_log('✅ Received '. $total . ' ' . $taxonomy . ' from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($items as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];

            $existing = self::get_term_by_causeway_id($causeway_id, $taxonomy);

            if (!$existing) {
                $term = wp_insert_term($name, $taxonomy);
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
                wp_update_term($term_id, 'listing-campaigns', [
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

            $imported_ids[] = (int) $causeway_id;
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        error_log('✅ '.$taxonomy.' Imported ' . $import_count . ' of ' . $total . ' @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    
        self::delete_old_terms($taxonomy, $imported_ids);
    }

    private static function assign_area_communities() {
        foreach (self::$areas as $area) {
            $term_id = self::get_term_id_by_causeway_id('listing-areas', $area['id']);
            if (!$term_id) continue;

            $related = [];
            foreach ($area['communities'] ?? [] as $community) {
                $community_id = self::get_term_id_by_causeway_id('listing-communities', $community['id']);
                if ($community_id) $related[] = $community_id;
            }

            update_field('related_communities', $related, 'listing-areas_' . $term_id);
        }
    }

    private static function assign_area_slugs(): void {
        error_log('🏷 Updating area slugs from pages...');
    
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
                error_log('❌ WP Error: ' . $response->get_error_message());
                break;
            }

            $headers = wp_remote_retrieve_headers($response);
            if ($totalPages === null) {
                $totalPages = (int) $headers['x-wp-totalpages'] ?? 0;
                if ($totalPages === 0) break;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || empty($data)) break;

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

        foreach(self::$areas as $area) {
            $term_id = self::get_term_id_by_causeway_id('listing-areas', $area['id']);
            if (!$term_id) continue;

            if(isset($slugMap[$area['id']])) {
                update_field('area_slug', $slugMap[$area['id']], 'listing-areas_' . $term_id);
            }
        }
    }

    // This one assigns seperate slugs for category overview pages and listings.php pages.
    // private static function assign_category_slugs(): void {
    //     error_log('🏷 Updating category slugs from pages...');
    
    //     $root_parent_ids = [239, 179, 260];
    //     $perPage = 100;
    //     $page = 1;
    //     $totalPages = null;
    //     $allPages = [];
    
    //     // Step 1: Fetch all pages
    //     do {
    //         $url = add_query_arg([
    //             '_fields'      => 'id,slug,template,parent',
    //             'per_page'     => $perPage,
    //             'page'         => $page,
    //             'bypass_clean' => 1,
    //         ], rest_url('wp/v2/pages'));
    
    //         $response = wp_remote_get($url);
    //         if (is_wp_error($response)) {
    //             error_log('❌ WP Error: ' . $response->get_error_message());
    //             break;
    //         }
    
    //         $headers = wp_remote_retrieve_headers($response);
    //         if ($totalPages === null) {
    //             $totalPages = (int) $headers['x-wp-totalpages'] ?? 0;
    //             if ($totalPages === 0) break;
    //         }
    
    //         $data = json_decode(wp_remote_retrieve_body($response), true);
    //         if (!is_array($data) || empty($data)) break;
    
    //         $allPages = array_merge($allPages, $data);
    //         $page++;
    //     } while ($page <= $totalPages);
    
    //     error_log('All Pages: ' . count($allPages) . print_r($allPages, true));
    
    //     // Step 2: Filter pages using listings.php or listings-landing.php
    //     $categoryPages = array_filter($allPages, function ($page) {
    //         return $page['template'] === 'listings.php' || $page['template'] === 'listings-landing.php';
    //     });
    
    //     error_log('Found category pages: ' . count($categoryPages));
    
    //     // Step 3: Build slug maps
    //     $slugMap = [];
    //     $overviewSlugMap = [];
    
    //     foreach ($categoryPages as $page) {
    //         $template = $page['template'];
    //         $page_id = $page['id'];
    //         $permalink = get_permalink($page_id);
    
    //         if ($template === 'listings.php') {
    //             $categoryId = get_field('category_filter', $page_id);
    //             if ($categoryId) {
    //                 $slugMap[(int)$categoryId] = $permalink;
    //             }
    //         }
    
    //         if ($template === 'listings-landing.php') {
    //             $overviewId = get_field('category', $page_id);
    //             if ($overviewId) {
    //                 $overviewSlugMap[(int)$overviewId] = $permalink;
    //             }
    //         }
    //     }
    
    //     error_log('Slug Map: ' . print_r($slugMap, true));
    //     error_log('Overview Slug Map: ' . print_r($overviewSlugMap, true));
    
    //     // Step 4: Update ACF fields for each term
    //     $terms = get_terms([
    //         'taxonomy' => 'listings-category',
    //         'hide_empty' => false,
    //     ]);
    
    //     foreach ($terms as $term) {
    //         $causeway_id = get_field('causeway_id', 'listings-category_' . $term->term_id);
    //         if (!$causeway_id) continue;
    
    //         $term_key = 'listings-category_' . $term->term_id;
    
    //         if (isset($slugMap[(int) $causeway_id])) {
    //             update_field('category_slug', $slugMap[(int) $causeway_id], $term_key);
    //         }
    
    //         if (isset($overviewSlugMap[(int) $causeway_id])) {
    //             update_field('category_overview_slug', $overviewSlugMap[(int) $causeway_id], $term_key);
    //         }
    //     }
    
    //     error_log('🏁 Category slugs assignment complete.');
    // }

    // This one assigns a single slug for category overview pages and listings.php pages.
    private static function assign_category_slugs(): void {
        error_log('🏷 Updating category slugs from pages...');
    
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
                error_log('❌ WP Error: ' . $response->get_error_message());
                break;
            }
    
            $headers = wp_remote_retrieve_headers($response);
            if ($totalPages === null) {
                $totalPages = (int) $headers['x-wp-totalpages'] ?? 0;
                if ($totalPages === 0) break;
            }
    
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || empty($data)) break;
    
            $allPages = array_merge($allPages, $data);
            $page++;
        } while ($page <= $totalPages);
    
        error_log('All Pages: ' . count($allPages) . print_r($allPages, true));
    
        // Step 2: Filter relevant category pages
        $categoryPages = array_filter($allPages, function ($page) {
            return $page['template'] === 'listings.php' || $page['template'] === 'listings-landing.php';
        });
    
        error_log('Found category pages: ' . count($categoryPages));
    
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
    
        error_log('Final Slug Map: ' . print_r($slugMap, true));
    
        // Step 4: Update ACF field 'category_slug' and clear 'category_overview_slug'
        $terms = get_terms([
            'taxonomy' => 'listings-category',
            'hide_empty' => false,
        ]);
    
        foreach ($terms as $term) {
            $causeway_id = get_field('causeway_id', 'listings-category_' . $term->term_id);
            if (!$causeway_id) continue;
    
            $term_key = 'listings-category_' . $term->term_id;
    
            // Set primary slug
            if (isset($slugMap[(int)$causeway_id])) {
                update_field('category_slug', $slugMap[(int)$causeway_id], $term_key);
            }
    
            // Always clear deprecated field
            update_field('category_overview_slug', null, $term_key); // Or '' if preferred
        }
    
        error_log('🏁 Category slugs assignment complete.');
    }
    
    

    private static function assign_community_areas_and_regions() {
        foreach (self::$communities as $community) {
            $term_id = self::get_term_id_by_causeway_id('listing-communities', $community['id']);
            if (!$term_id) continue;

            $area_ids = [];
            foreach ($community['areas'] ?? [] as $area) {
                $area_id = self::get_term_id_by_causeway_id('listing-areas', $area['id']);
                if ($area_id) $area_ids[] = $area_id;
            }

            $region_ids = [];
            foreach ($community['regions'] ?? [] as $region) {
                $region_id = self::get_term_id_by_causeway_id('listing-regions', $region['id']);
                if ($region_id) $region_ids[] = $region_id;
            }

            update_field('related_areas', $area_ids, 'listing-communities_' . $term_id);
            update_field('related_regions', $region_ids, 'listing-communities_' . $term_id);
        }
    }

    private static function assign_region_communities() {
        foreach (self::$regions as $region) {
            $term_id = self::get_term_id_by_causeway_id('listing-regions', $region['id']);
            if (!$term_id) continue;

            $related = [];
            foreach ($region['communities'] ?? [] as $community) {
                $community_id = self::get_term_id_by_causeway_id('listing-communities', $community['id']);
                if ($community_id) $related[] = $community_id;
            }

            update_field('related_communities', $related, 'listing-regions_' . $term_id);
        }
    }

    private static function import_listings() {
        error_log('Importing listings..');
        $imported_ids = [];
        
        // $response = wp_remote_get(self::$baseURL.'listings?search=listings.status&compare==&value=Published', [
        //     'headers' => ['Authorization' => 'Bearer ' . self::get_token()],
        //     'timeout' => 1200,
        // ]);

        $response = wp_remote_get(self::$baseURL.'listings?search=listings.status&compare==&value=Published', [
            'timeout' => 1200,
        ]);

        if (is_wp_error($response)) {
            error_log('❌ Failed to fetch listings');
            error_log(print_r($response, true));
            return;
        }

        $listings = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($listings)) {
            error_log('❌ Invalid listings data');
            error_log(print_r($listings, true));
            return;
        }

        $listings_count = is_array($listings) ? count($listings) : 0;
        error_log('✅ Received '. $listings_count .' listings from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        foreach ($listings as $item) {
            $post_title = $item['name'] ?? '';
            $slug = $item['slug'] ?? '';
            $description = $item['description'] ?? '';
            $causeway_id = $item['id'] ?? null;
            $post_status = strtolower($item['status'] ?? '') === 'published' ? 'publish' : 'draft';
            $post_id = false;
            // error_log("Starting listing " . $post_title);

            if (!$post_title || !$slug || !$causeway_id || $post_status !== 'publish') continue;

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
                error_log("Creating new listing: " . $post_title);
                $post_id = wp_insert_post([
                    'post_type' => 'listing',
                    'post_title' => $post_title,
                    'post_name' => $slug,
                    'post_content' => $description,
                    'post_status'  => $post_status,
                ]);
            } else {
                // Update post title, slug, and content
                wp_update_post([
                    'ID'           => $post_id,
                    'post_title'   => $post_title,
                    'post_name'    => $slug,
                    'post_content' => $description,
                    'post_status'  => $post_status,
                ]);
            }

            if (!$post_id || is_wp_error($post_id)) continue;

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
            update_field('opengraph_title', $item['opengraph_title'] ?? '', $post_id);
            update_field('opengraph_description', $item['opengraph_description'] ?? '', $post_id);
            update_field('activated_at', $item['activated_at'] ?? '', $post_id);
            update_field('expired_at', $item['expired_at'] ?? '', $post_id);
            update_field('tripadvisor_url', $item['tripadvisor_url'] ?? '', $post_id);
            update_field('tripadvisor_id', $item['tripadvisor_id'] ?? '', $post_id);
            update_field('tripadvisor_rating_url', $item['tripadvisor_rating_url'] ?? '', $post_id);
            update_field('tripadvisor_rating', $item['tripadvisor_rating'] ?? '', $post_id);
            update_field('tripadvisor_count', $item['tripadvisor_count'] ?? '', $post_id);

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
                if ($term) $season_ids[] = $term->term_id;
            }
            wp_set_object_terms($post_id, $season_ids, 'listings-seasons');

            // Remove old terms before addingn new ones
            wp_set_object_terms($post_id, [], 'listing-communities');
            wp_set_object_terms($post_id, [], 'listing-regions');
            wp_set_object_terms($post_id, [], 'listing-areas');

            // Assign Locations (coordinates and details)
            foreach ($item['locations'] ?? [] as $location) {

                update_field('location_details', [
                    'latitude'           => isset($location['latitude']) ? (float)$location['latitude'] : null,
                    'longitude'           => isset($location['longitude']) ? (float)$location['longitude'] : null,
                    'state'         => $location['state'] ?? '',
                    'country'       => $location['country'] ?? '',
                    'name'          => $location['name'] ?? '',
                    'civic_address' => $location['civic_address'] ?? '',
                    'postal_code'   => $location['postal_code'] ?? '',
                    'place_id'      => $location['place_id'] ?? '',
                ], $post_id);

                $community_name = $location['community']['name'] ?? null;
                if ($community_name) {
                    $term = get_term_by('name', $community_name, 'listing-communities');
                    if ($term) {
                        wp_set_object_terms($post_id, [$term->term_id], 'listing-communities', true);
                    }
                }

                // Assign regions (based on community)
                $region_names = $location['community']['regions'] ?? [];
                foreach ($region_names as $region) {
                    $region_term = get_term_by('name', $region['name'], 'listing-regions');
                    if ($region_term) {
                        wp_set_object_terms($post_id, [$region_term->term_id], 'listing-regions', true);
                    }
                }

                // Assign areas (based on community)
                $area_names = $location['community']['areas'] ?? [];
                foreach ($area_names as $area) {
                    $area_term = get_term_by('name', $area['name'], 'listing-areas');
                    if ($area_term) {
                        wp_set_object_terms($post_id, [$area_term->term_id], 'listing-areas', true);
                    }
                }
            }

            // Websites Repeater
            $websites = [];
            foreach ($item['websites'] ?? [] as $site) {
                $websites[] = [
                    'url' => $site['url'] ?? '',
                    'name' => $site['name'] ?? '',
                    'type_name' => $site['type']['name'] ?? '',
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

            // error_log("✅ Updated Listing: " . $post_title . " (ID: $post_id)");
        }

        error_log("Assigning related listings...");
        // TODO matt does not need to send the entire related listing object, just the slug OR ID
        foreach ($listings as $item) {
            $post_id = self::$listing_map[$item['slug']] ?? null;
            if (!$post_id) continue;

            $related_ids = [];

            foreach ($item['related'] ?? [] as $related) {
                if (!is_array($related)) continue;

                $related_slug = $related['slug'] ?? null;
                if (!$related_slug) continue;

                $related_post_id = self::$listing_map[$related_slug] ?? null;
                if ($related_post_id) {
                    $related_ids[] = $related_post_id;
                } else {
                    error_log("⚠️ Related listing not found: $related_slug for {$item['slug']}");
                }
            }

            if (!empty($related_ids)) {
                error_log("✅ Assigning " . count($related_ids) . " related listings to {$item['slug']} (ID: $post_id)");
                update_field('related_listings', $related_ids, $post_id);
            }
        }

        $import_count = is_array($imported_ids) ? count($imported_ids) : 0;
        error_log('✅ Imported ' . $import_count . ' of ' . $listings_count . ' listings @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

        self::delete_old_listings($imported_ids);
    }

    public static function export_listings() {
        error_log("Start Export");
        // Notify public site to re-fetch causeway data
        $public_url = get_field('causeway_public_url', 'option');
        $secret = get_field('headless_api_secret', 'option');

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
                error_log('❌ Failed to notify public site. ' . $endpoint);
                error_log(print_r($response, true));
            } else {
                error_log('✅ Public site received data at ' . $endpoint);
            }

            return;
        } else {
            error_log("❌ Public URL not set. Cannot notify.");
        }
    }

    // Helper functions
    private static function get_term_by_causeway_id($causeway_id, $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        foreach ($terms as $term) {
            $stored_id = get_field('causeway_id', $taxonomy . '_' . $term->term_id);
            if ((int) $stored_id === (int) $causeway_id) {
                return $term;
            }
        }

        return null;
    }

    private static function get_term_id_by_causeway_id($taxonomy, $causeway_id) {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        foreach ($terms as $term) {
            if ((int) get_field('causeway_id', $taxonomy . '_' . $term->term_id) === (int) $causeway_id) {
                return $term->term_id;
            }
        }
        return null;
    }

    private static function assign_listing_terms($post_id, $items, $taxonomy) {
        if (!is_array($items)) return;
        $term_ids = [];

        foreach ($items as $item) {
            $term = self::get_term_by_causeway_id($item['id'], $taxonomy);
            if ($term) $term_ids[] = $term->term_id;
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
        }
    }

    private static function delete_old_listings($imported_ids) {
        error_log("Deleting old listings...");

        if (empty($imported_ids)) {
            error_log("⚠️ No imported IDs provided. Skipping deletion.");
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
                wp_delete_post($post_id, true);
                $reason = is_null($causeway_id) ? 'no Causeway ID' : "stale Causeway ID: $causeway_id";
                error_log("🗑️ Deleted listing ID: $post_id ($reason)");
                $deleted_count++;
            }
        }
        
        error_log("🗑️ Deleted ".$deleted_count." listings");

        return;
    }

    private static function delete_old_terms($taxonomy, $imported_ids) {
        if (empty($imported_ids)) return;
    
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
                    error_log("❌ Failed to delete term: {$term->name} (ID: {$term->term_id}) from $taxonomy. Reason: " . $result->get_error_message());
                } else {
                    error_log("🗑️ Deleted stale term: {$term->name} (ID: {$term->term_id}) from $taxonomy");
                    $deleted_count++;
                }
            }
        }
    
        if ($deleted_count > 0) {
            error_log("🧹 Deleted $deleted_count stale terms from $taxonomy");
        }
    }
}
