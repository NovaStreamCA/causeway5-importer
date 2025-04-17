<?php

/*

Causeway Data Fetching

*/

add_action('rest_api_init', function () {
    register_rest_route('causeway/v1', '/listings', [
        'methods'  => 'GET',
        'callback' => 'get_listings', // ✅ Not an array, just the function name
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('causeway/v1', '/taxonomy/(?P<taxonomy>[a-zA-Z0-9_-]+)', [
        'methods'  => 'GET',
        'callback' => 'get_taxonomy_terms_with_acf',
        'permission_callback' => '__return_true',
    ]);
});

/*
Start Listings
*/

function get_listings($request) {
    $id        = $request->get_param('id');
    $per_page  = $request->get_param('per_page') ?: -1;
    $page      = $request->get_param('page') ?: 1;

    $args = [
        'post_type'      => 'listing',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ];

    if ($id) $args['p'] = intval($id);

    $query = new WP_Query($args);
    $results = [];

    foreach ($query->posts as $post) {
        $id = $post->ID;
        $acf = get_fields($id);

        $results[] = [
            'id'             => $acf['causeway_id'] ?? $id,
            'name'           => get_the_title($post),
            'slug'           => $post->post_name,
            'description'    => apply_filters('the_content', $post->post_content),
            'highlights'     => $acf['highlights'] ?? null,
            'contact_name'   => $acf['contact_name'] ?? null,
            'phone_primary'  => $acf['phone_primary'] ?? null,
            'phone_secondary'=> $acf['phone_secondary'] ?? null,
            'phone_offseason'=> $acf['phone_offseason'] ?? null,
            'phone_tollfree' => $acf['phone_tollfree'] ?? null,
            'email'          => $acf['email'] ?? null,
            'status'         => $acf['status'] ?? 'Draft',
            'provider'       => $acf['provider'] ?? 'Causeway',
            'tripadvisor_id'         => $acf['tripadvisor_id'] ?? null,
            'tripadvisor_url'        => $acf['tripadvisor_url'] ?? null,
            'tripadvisor_rating_url' => $acf['tripadvisor_rating_url'] ?? null,
            'tripadvisor_count'      => $acf['tripadvisor_count'] ?? null,
            'activated_at'  => $acf['activated_at'] ?? null,
            'expired_at'    => $acf['expired_at'] ?? null,
            'is_featured'   => (bool) ($acf['is_featured'] ?? false),
            'price'             => floatval($acf['price'] ?? 0),
            'types'       => get_terms_with_acf($id, 'listing-type'),
            'categories'  => get_terms_with_acf($id, 'listings-category'),
            'amenities'   => get_terms_with_acf($id, 'listings-amenities'),
            'campaigns'   => get_terms_with_acf($id, 'listing-campaigns'),
            'seasons'     => get_terms_with_acf($id, 'listings-seasons'),
            'locations'         => format_locations($acf['location_details'] ?? [], $acf['location'] ?? [], $id),
            'websites'          => format_websites($acf['websites'] ?? []),
            'attachments'       => format_attachments($acf['attachments'] ?? [], $id),
            'dates'         => format_dates($acf['dates'] ?? []),
            // 'related'           => $acf['related_listings'], //TODO related does not work currently in the importer
        ];
    }

    return rest_ensure_response($results);
}


function get_terms_with_acf($post_id, $taxonomy) {
    $terms = wp_get_post_terms($post_id, $taxonomy);
    $result = [];

    foreach ($terms as $term) {
        $acf = get_fields($taxonomy . '_' . $term->term_id);
        $causeway_id = isset($acf['causeway_id']) ? $acf['causeway_id'] : $term->term_id;

        error_log("ACF" . print_r($acf, true));

        // Handle relationship fields
        $relationship_fields = format_related_acf_ids([
            'communities' => 'listing-communities',
            'areas'       => 'listing-areas',
            'regions'     => 'listing-regions',
            'listing_type'  => 'listing-type',
        ], $acf);

        error_log('Relationship fields: ' . print_r($relationship_fields, true));

        unset($acf['causeway_id'], $acf['related_communities'], $acf['related_areas'], $acf['related_regions'], $acf['listing_type']);

        $item = [
            'id'   => (int) $causeway_id,
            'name' => $term->name,
            // 'slug' => $term->slug,
        ];

        // Add extracted relationships at root level
        foreach ($relationship_fields as $key => $value) {
            $item[$key] = $value;
        }

        if (!empty($acf)) {
            foreach ($acf as $key => $value) {
                $item[$key] = $value;
            }
        }

        $result[] = $item;
    }

    return $result;
}

function get_taxonomy_terms_with_acf($request) {
    $taxonomy = $request->get_param('taxonomy');

    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Taxonomy does not exist.', ['status' => 404]);
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    $results = [];

    foreach ($terms as $term) {
        $term_id = $term->term_id;
        $acf = get_fields($taxonomy . '_' . $term_id);
        $causeway_id = isset($acf['causeway_id']) ? (int)$acf['causeway_id'] : $term_id;

        if(!empty($acf)) {
            // Format relationship ACF fields
            $relationships = format_related_acf_ids([
                'communities' => 'listing-communities',
                'areas'       => 'listing-areas',
                'regions'     => 'listing-regions',
                'listing_type' => 'listing-type',
            ], $acf);

            // Remove the raw fields after formatting
            unset($acf['causeway_id'], $acf['related_communities'], $acf['related_areas'], $acf['related_regions'], $acf['listing_type']);
        }

        // Build the response
        $term_data = [
            'id'   => $causeway_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ];

        foreach ($relationships as $key => $val) {
            $term_data[$key] = $val;
        }

        if (!empty($acf)) {
            foreach ($acf as $key => $value) {
                $term_data[$key] = $value;
            }
        }

        $results[] = $term_data;
    }

    return rest_ensure_response($results);
}


function format_related_acf_ids(array $fieldMap, array $acf): array {
    $output = [];

    foreach ($fieldMap as $acfKey => $taxonomy) {
        if (!isset($acf[$acfKey])) continue;

        $outputKey = ($acfKey === 'listing_type') ? 'type' : $acfKey;

        // Handle multiple (array of IDs)
        if (is_array($acf[$acfKey])) {
            $formatted = [];

            foreach ($acf[$acfKey] as $term_id) {
                $term = get_term($term_id, $taxonomy);
                if (!$term || is_wp_error($term)) continue;

                $causeway_id = get_field('causeway_id', $taxonomy . '_' . $term->term_id);
                $formatted[] = [
                    'id'   => (int) ($causeway_id ?: $term->term_id),
                    'name' => $term->name,
                ];
            }

            $output[$outputKey] = $formatted;
        }
        // Handle single term ID
        elseif (is_numeric($acf[$acfKey])) {
            $term_id = $acf[$acfKey];
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $causeway_id = get_field('causeway_id', $taxonomy . '_' . $term->term_id);
                $output[$outputKey] = [
                    'id'   => (int) ($causeway_id ?: $term->term_id),
                    'name' => $term->name,
                ];
            }
        }
    }

    return $output;
}


// Helpers
function format_locations($details, $coords, $post_id) {
    $community_terms = wp_get_post_terms($post_id, 'listing-communities');
    $community = $community_terms[0] ?? null;

    $community_data = null;
    if ($community) {
        $acf = get_fields('listing-communities_' . $community->term_id);
        $areas = $acf['related_areas'] ?? [];
        $regions = $acf['related_regions'] ?? [];
        $county = wp_get_post_terms($post_id, 'listing-counties')[0] ?? null;

        $community_data = [
            'id'   => (int) ($acf['causeway_id'] ?? $community->term_id),
            'name' => $community->name,
            'areas' => map_terms($areas, 'listing-areas'),
            'regions' => map_terms($regions, 'listing-regions'),
            'county' => $county ? [
                'id' => (int) get_field('causeway_id', 'listing-counties_' . $county->term_id),
                'name' => $county->name,
            ] : null,
        ];
    }

    return [[
        'id'            => $post_id,
        'name'          => $details['name'] ?? '',
        'civic_address' => $details['civic_address'] ?? '',
        'postal_code'   => $details['postal_code'] ?? '',
        'state'         => $details['state'] ?? '',
        'country'       => $details['country'] ?? '',
        'latitude'      => floatval($details['latitude'] ?? $coords['lat'] ?? 0),
        'longitude'     => floatval($details['longitude'] ?? $coords['lng'] ?? 0),
        'place_id'      => $details['place_id'] ?? null,
        'lookup_method' => 'Civic',
        'community'     => $community_data,
    ]];
}

function map_terms($term_ids, $taxonomy) {
    $out = [];
    foreach ($term_ids as $id) {
        $term = get_term($id, $taxonomy);
        if ($term && !is_wp_error($term)) {
            $causeway_id = get_field('causeway_id', $taxonomy . '_' . $term->term_id);
            $out[] = [
                'id'   => (int) ($causeway_id ?? $term->term_id),
                'name' => $term->name,
            ];
        }
    }
    return $out;
}

function format_websites($sites) {
    $formatted = [];
    foreach ($sites as $i => $site) {
        $formatted[] = [
            'id' => $i + 1,
            'url' => $site['url'],
            'name' => $site['name'],
            'type' => [
                'id' => $i + 1,
                'name' => $site['type_name'] ?? 'General',
            ],
        ];
    }
    return $formatted;
}

function format_attachments($items, $listing_id) {
    $formatted = [];
    foreach ($items as $i => $a) {
        $url_parts = explode('/', $a['url']);
        $filename = end($url_parts);
        $formatted[] = [
            'id' => $i + 1,
            'listing_id' => $listing_id,
            'url' => $a['url'],
            'alt' => $a['alt'] ?? null,
            'category' => $a['category'] ?? null,
            'type' => 'image',
        ];
    }
    return $formatted;
}

function format_related($ids) {
    $out = [];
    foreach ($ids as $post_id) {
        $acf = get_fields($post_id);
        $out[] = [
            'id' => (int) ($acf['causeway_id'] ?? $post_id),
        ];
    }
    return $out;
}

function format_dates(array $dates): array {
    $result = [];

    foreach ($dates as $date) {
        $result[] = [
            'start_at' => $date['start_at'] ?? null,
            'end_at'   => $date['end_at'] ?? null,
            'rrule'    => $date['rrule'] ?? null,
        ];
    }

    return $result;
}
