<?php
class Causeway_Importer {
    private static $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJhcGktY2F1c2V3YXk1Lm5vdmFzdHJlYW0uZGV2IiwiaWF0IjoxNzQ0ODk3MjQxLCJleHAiOjE3NDQ5ODM2NDEsInVpZCI6M30.8TWuKhpelk66edanpYwk8_cevbb1m9mURc9390SQqlI';
    private static $areas = [];
    private static $communities = [];
    private static $regions = [];
    private static $counties = [];
    private static $listing_map = [];
    private static $start;

    public static function import() {
        error_log('start import');
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

        self::import_listings();

        error_log('✅ Import Completed. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function fetch_remote($endpoint) {
        $url = 'https://api-causeway5.novastream.dev/' . $endpoint;
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
            ],
        ]);

        if (is_wp_error($response)) return [];
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : [];
    }

    private static function import_types() {
        error_log('Import types...');
        $response = wp_remote_get('https://api-causeway5.novastream.dev/listing-types', [
            'headers' => [
                'Authorization' => 'Bearer '. self::$token,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('ERROR', print_r($response, true));
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            error_log('ERROR - data not array', $data);
            return;
        }

        foreach ($data as $item) {
            $name = $item['name'];
            $icon = $item['icons'];
            $causeway_id = $item['id'];

            if (!$name) continue;

            // Check if term exists
            $existing = get_term_by('name', $name, 'listing-type');

            if ($existing) {
                $term_id = $existing->term_id;
                update_field('icon', $icon, 'listing-type_' . $term_id);
                update_field('causeway_id', $causeway_id, 'listing-type_' . $term_id);
            } else {
                // Insert new term
                $term = wp_insert_term($name, 'listing-type');

                if (!is_wp_error($term)) {
                    $term_id = $term['term_id'];
                    update_field('icon', $icon, 'listing-type_' . $term_id);
                    update_field('causeway_id', $causeway_id, 'listing-type_' . $term_id);
                }
            }
        }

        error_log('✅ Types imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_categories() {
        error_log('Importing categories...');

        $response = wp_remote_get('https://api-causeway5.novastream.dev/categories', [
            'headers' => [
                'Authorization' => 'Bearer '. self::$token,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('Error fetching categories');
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) return;

        // First pass: track all categories by causeway_id
        $lookup = [];

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

                // If no parent was set before, we can update
                if (!isset($existing->parent) || $existing->parent === 0) {
                    wp_update_term($term_id, 'listings-category', $args);
                }
            } else {
                $term = wp_insert_term($name, 'listings-category', $args);
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            }

            // Save ACF fields
            if (isset($term_id)) {
                update_field('causeway_id', $causeway_id, 'listings-category_' . $term_id);

                $type_term = self::get_term_by_causeway_id($type_id, 'listing-type');
                if ($type_term) {
                    update_field('listing_type', $type_term->term_id, 'listings-category_' . $term_id);
                }
            }
        }

        error_log('✅ Categories imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_amenities() {
        error_log('Importing amenities...');

        $response = wp_remote_get('https://api-causeway5.novastream.dev/amenities', [
            'headers' => [
                'Authorization' => 'Bearer '. self::$token,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('❌ Failed to fetch amenities');
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            error_log('❌ Invalid amenities data');
            return;
        }

        foreach ($data as $item) {
            $name = $item['name'] ?? null;
            $causeway_id = $item['id'] ?? null;
            $type_id = $item['type']['id'] ?? null;

            if (!$name || !$causeway_id) continue;

            // Check if term already exists by causeway_id
            $existing = self::get_term_by_causeway_id($causeway_id, 'listings-amenities');

            if ($existing) {
                $term_id = $existing->term_id;
            } else {
                $term = wp_insert_term($name, 'listings-amenities', [
                    'slug' => sanitize_title($name),
                ]);

                if (is_wp_error($term)) {
                    error_log("Error adding term: $name");
                    error_log(print_r($term, true));
                }

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
        }

        error_log('✅ Amenities imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_campaigns() {
        error_log('Importing campaigns...');

        $response = wp_remote_get('https://api-causeway5.novastream.dev/campaigns', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
            ],
        ]);

        if (is_wp_error($response)) return;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) return;

        foreach ($data as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];
            $activated_at = $item['activated_at'] ?? null;
            $expired_at = $item['expired_at'] ?? null;

            if (!$name) continue;

            $existing = get_term_by('name', $name, 'listing-campaigns');

            if (!$existing) {
                $term = wp_insert_term($name, 'listing-campaigns');
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
            }

            $acf_key = 'listing-campaigns_' . $term_id;

            update_field('causeway_id', $causeway_id, $acf_key);
            update_field('activated_at', $activated_at, $acf_key);
            update_field('expired_at', $expired_at, $acf_key);
        }

        error_log('✅ Campaigns imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_seasons() {
        error_log('Importing seasons...');

        $seasons = [
            ['id' => 1, 'name' => 'Spring'],
            ['id' => 2, 'name' => 'Summer'],
            ['id' => 3, 'name' => 'Fall'],
            ['id' => 4, 'name' => 'Winter'],
        ];

        foreach ($seasons as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];

            $existing = get_term_by('name', $name, 'listings-seasons');

            if (!$existing) {
                $term = wp_insert_term($name, 'listings-seasons');
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
            }

            update_field('causeway_id', $causeway_id, 'listings-seasons_' . $term_id);
        }

        error_log('✅ Seasons imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
    }

    private static function import_terms($taxonomy, $items) {
        error_log('Importing '.$taxonomy.'...');

        foreach ($items as $item) {
            $name = $item['name'];
            $causeway_id = $item['id'];

            $existing = get_term_by('name', $name, $taxonomy);

            if (!$existing) {
                $term = wp_insert_term($name, $taxonomy);
                if (is_wp_error($term)) continue;
                $term_id = $term['term_id'];
            } else {
                $term_id = $existing->term_id;
            }

            update_field('causeway_id', $causeway_id, $taxonomy . '_' . $term_id);
        }

        error_log('✅ '.$taxonomy.' imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
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
        
        $response = wp_remote_get('https://api-causeway5.novastream.dev/listings', [
            'headers' => ['Authorization' => 'Bearer ' . self::$token],
            'timeout' => 1200,
        ]);
        error_log('✅ Received listings from API. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');

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

        foreach ($listings as $item) {
            $post_title = $item['name'] ?? '';
            $slug = $item['slug'] ?? '';
            $description = $item['description'] ?? '';
            $causeway_id = $item['id'] ?? null;

            // error_log("Starting listing " . $post_title);

            if (!$post_title || !$slug || !$causeway_id) continue;

            // Check if post already exists by slug
            $existing = get_page_by_path($slug, OBJECT, 'listing');
            $post_id = $existing ? $existing->ID : null;

            if (!$post_id) {
                $post_id = wp_insert_post([
                    'post_type' => 'listing',
                    'post_status' => 'publish',
                    'post_title' => $post_title,
                    'post_name' => $slug,
                    'post_content' => $description,
                ]);
            }

            if (!$post_id || is_wp_error($post_id)) continue;

            // Store for later reference
            self::$listing_map[$slug] = $post_id;

            // ACF Meta
            update_field('causeway_id', $causeway_id, $post_id);
            update_field('provider', $item['provider'] ?? '', $post_id);
            update_field('status', $item['status'] ?? '', $post_id);
            update_field('highlights', $item['highlights'] ?? '', $post_id);
            update_field('email', $item['email'] ?? '', $post_id);
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
            update_field('tripadvisor_count', $item['tripadvisor_count'] ?? '', $post_id);

            // Assign Taxonomies
            self::assign_listing_terms($post_id, $item['types'], 'listing-type');
            self::assign_listing_terms($post_id, $item['categories'], 'listings-category');
            self::assign_listing_terms($post_id, $item['amenities'], 'listings-amenities');
            self::assign_listing_terms($post_id, $item['campaigns'], 'listing-campaigns');

            // Assign Seasons
            $season_ids = [];
            foreach ($item['seasons'] ?? [] as $season) {
                $term = get_term_by('name', $season, 'listings-seasons');
                if ($term) $season_ids[] = $term->term_id;
            }
            wp_set_object_terms($post_id, $season_ids, 'listings-seasons');

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

            // error_log("✅ Added Listing: " . $post_title . " (ID: $post_id)");
        }

        // error_log("Assigning related listings...");
        // TODO Assign related listings (matt not currently sending them via api)
        // foreach ($listings as $item) {
        //     $post_id = self::$listing_map[$item['slug']] ?? null;
        //     if (!$post_id) continue;

        //     $related_ids = [];
        //     foreach ($item['related'] ?? [] as $related) {
        //         $related_slug = $related['slug'] ?? null;
        //         $related_post_id = self::$listing_map[$related_slug] ?? null;
        //         if ($related_post_id) {
        //             $related_ids[] = $related_post_id;
        //         }
        //     }

        //     if (!empty($related_ids)) {
        //         update_field('related_listings', $related_ids, $post_id);
        //     }
        // }

        error_log('✅ Listings imported. @ ' . round(microtime(true) - self::$start, 2) . ' seconds');
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
    
}
