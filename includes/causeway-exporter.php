<?php

/*

Causeway Data Fetching

*/

add_action('init', function () {
    add_action('rest_api_init', function () {
        register_rest_route('causeway/v1', '/listings', [
            'methods'  => 'GET',
            'callback' => 'get_listings',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('causeway/v1', '/taxonomy/(?P<taxonomy>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => 'get_taxonomy_terms_with_acf',
            'permission_callback' => '__return_true',
        ]);
    });
});

/*
Start Listings
*/

function get_listings($request)
{
    $id        = $request->get_param('id');
    $per_page  = $request->get_param('per_page') ?: -1;
    $page      = $request->get_param('page') ?: 1;

    $args = [
        'post_type'      => 'listing',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_key'       => 'is_featured',
        'orderby'        => [
            'meta_value_num' => 'DESC',  // Featured (1) comes before non-featured (0)
            'title'          => 'ASC',   // Then order A–Z by title
        ],
    ];

    if ($id) {
        $args['p'] = intval($id);
    }

    $query = new WP_Query($args);
    $results = [];

    foreach ($query->posts as $post) {
        $id = $post->ID;
        $acf = get_fields($id) ?: [];

        $listing = [
            'id'             => $acf['causeway_id'] ?? $id,
            'name'           => html_entity_decode(get_the_title($post)),
            'slug'           => $post->post_name,
            'description'    => html_entity_decode(apply_filters('the_content', $post->post_content)),
            'highlights'     => html_entity_decode($acf['highlights']) ?? null,
            'contact_name'   => $acf['contact_name'] ?? null,
            'phone_primary'  => $acf['phone_primary'] ?? null,
            'phone_secondary' => $acf['phone_secondary'] ?? null,
            'phone_offseason' => $acf['phone_offseason'] ?? null,
            'phone_tollfree' => $acf['phone_tollfree'] ?? null,
            'opengraph_title' => $acf['opengraph_title'] ?? null,
            'opengraph_description' => $acf['opengraph_description'] ?? null,
            'email'          => $acf['email'] ?? null,
            'meta'          => $acf['metaline'] ?? null,
            'status'         => $acf['status'] ?? 'Draft',
            'provider'       => $acf['provider'] ?? 'Causeway',
            'tripadvisor_id'         => $acf['tripadvisor_id'] ?? null,
            'tripadvisor_url'        => $acf['tripadvisor_url'] ?? null,
            'tripadvisor_rating_url' => $acf['tripadvisor_rating_url'] ?? null,
            'tripadvisor_rating' => $acf['tripadvisor_rating'] ?? null,
            'tripadvisor_count'      => $acf['tripadvisor_count'] ?? null,
            'activated_at'  => $acf['activated_at'] ?? null,
            'expired_at'    => $acf['expired_at'] ?? null,
            'is_featured'   => (bool) ($acf['is_featured'] ?? false),
            'is_in_national_park'   => (bool) ($acf['is_in_national_park'] ?? false),
            'is_campaign_only'   => (bool) ($acf['is_campaign_only'] ?? false),
            'price'         => floatval($acf['price'] ?? 0),
            'types'         => get_terms_with_acf($id, 'listing-type'),
            'categories'    => get_terms_with_acf($id, 'listings-category', true),
            'amenities'     => get_terms_with_acf($id, 'listings-amenities'),
            'campaigns'     => get_terms_with_acf($id, 'listing-campaigns'),
            'seasons'       => get_terms_with_acf($id, 'listings-seasons'),
            'locations'     => format_locations($acf['location_details'] ?? [], $id),
            'websites'      => format_websites($acf['websites'] ?? []),
            'attachments'   => format_attachments($acf['attachments'] ?? [], $id),
            'dates'         => format_dates($acf['dates'] ?? []),
            'next_occurrence' => $acf['next_occurrence'] ?? null,          // string 'Y-m-d H:i:s'
            'occurrences'     => format_occurrences( is_array($acf['all_occurrences']) ? $acf['all_occurrences'] : [] ),
        ];

        $related_posts = get_field('related_listings', $id) ?: [];
        $related_ids = array_map(function ($p) {
            return get_field('causeway_id', $p) ?: $p;
        }, $related_posts);


        $listing['related'] = $related_ids;

        // 🔁 Add translations for frontend fields only
        if (function_exists('icl_object_id')) {
            $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);

            foreach ($languages as $lang_code => $lang_info) {
                $translated_post_id = apply_filters('wpml_object_id', $id, 'listing', true, $lang_code);

                if (!$translated_post_id || $translated_post_id === $id) {
                    continue;
                }

                $translated_post = get_post($translated_post_id);
                if (!$translated_post || $translated_post->post_status !== 'publish') {
                    continue;
                }

                $translated_acf = get_fields($translated_post_id);
                // $translated_title = html_entity_decode(get_the_title($translated_post));
                $translated_description = html_entity_decode(apply_filters('the_content', $translated_post->post_content));

                if ($translated_acf || $translated_description) {
                    if (! empty($translated_acf['attachments'])) {
                        add_alt_translations_to_attachments(
                            $listing['attachments'],                 // ← passes by reference
                            $translated_acf['attachments'],
                            $lang_code
                        );
                    }

                    $listing['translations'][$lang_code] = [
                        // 'name'        => $translated_title,
                        'description' => $translated_description,
                    ];

                    if ($translated_acf) {
                        if ((array_key_exists('highlights', $translated_acf))) {
                            $listing['translations'][$lang_code]['highlights'] = html_entity_decode($translated_acf['highlights']);
                        }

                        if ((array_key_exists('metaline', $translated_acf))) {
                            $listing['translations'][$lang_code]['meta'] = html_entity_decode($translated_acf['metaline']);
                        }
                    }
                } else {
                    $listing['translations'][$lang_code] = [];
                }
            }
        }

        $results[] = $listing;
    }

    $response = rest_ensure_response($results);

    // 🔹 Add headers for pagination
    $response->header('X-WP-Total', (int) $query->found_posts);
    $response->header('X-WP-TotalPages', (int) $query->max_num_pages);

    return $response;
}

// Helpers

/**
 * Return the post’s terms enriched with ACF fields, relationships, translations,
 * …and now the parent term (if any) in the shape:
 *
 * 'parent' => [
 *     'id'           => int,
 *     'name'         => string,
 *     'slug'         => string,
 *     'translations' => [ <lang> => [ id, name, slug, … ] ]
 * ]
 */
function get_terms_with_acf(int $post_id, string $taxonomy, $include_parents = false): array
{
    $terms  = wp_get_post_terms($post_id, $taxonomy);
    $result = [];

    foreach ($terms as $term) {
        $result[] = build_term_tree((int) $term->term_id, $taxonomy, $include_parents);
    }

    return $result;
}


/**
 * Build a term payload and follow its parents recursively.
 *
 * @param  int      $term_id   The WP term ID.
 * @param  string   $taxonomy  The taxonomy slug.
 * @param  int[]    $seen      Internally prevents circular loops.
 * @return array|null          Null if the term can’t be loaded.
 */
function build_term_tree(int $term_id, string $taxonomy, $include_parents, array &$seen = []): ?array
{

    /* ── guard: avoid infinite loops ───────────────────────────────────────── */
    if (in_array($term_id, $seen, true)) {
        return null;
    }
    $seen[] = $term_id;

    /* ── fetch term ───────────────────────────────────────────────────────── */
    $term = get_term($term_id, $taxonomy);
    if (! $term || is_wp_error($term)) {
        return null;
    }

    /* ── base + ACF ───────────────────────────────────────────────────────── */
    $acf         = get_fields("{$taxonomy}_{$term_id}") ?: [];
    $causeway_id = $acf['causeway_id'] ?? $term_id;

    // Extract + remove relationship fields you don’t want duplicated
    $relationship_fields = format_related_acf_ids(
        [
            'related_communities' => 'listing-communities',
            'related_areas'       => 'listing-areas',
            'related_regions'     => 'listing-regions',
            'listing_type'        => 'listing-type',
        ],
        $acf
    );
    unset(
        $acf['causeway_id'],
        $acf['related_communities'],
        $acf['related_areas'],
        $acf['related_regions'],
        $acf['listing_type']
    );

    $node = [
        'id'   => (int) $causeway_id,
        'name' => html_entity_decode($term->name),
        'slug' => $term->slug,
    ] + $relationship_fields + $acf;

    /* ── translations ─────────────────────────────────────────────────────── */
    if (function_exists('icl_object_id')) {
        $langs = apply_filters('wpml_active_languages', null, [ 'skip_missing' => 0 ]);
        if ($langs) {
            foreach ($langs as $code => $info) {
                $t_id = apply_filters('wpml_object_id', $term_id, $taxonomy, true, $code);
                if (! $t_id || $t_id === $term_id) {
                    continue;
                }
                $t_term = get_term($t_id, $taxonomy);
                if (! $t_term || is_wp_error($t_term)) {
                    continue;
                }
                $t_acf      = get_fields("{$taxonomy}_{$t_id}") ?: [];
                $t_causeway = $t_acf['causeway_id'] ?? $t_id;

                $node['translations'][ $code ] = array_merge(
                    [
                        'id'   => (int) $t_causeway,
                        'name' => html_entity_decode($t_term->name),
                        'slug' => $t_term->slug,
                    ],
                    $t_acf ?: []
                );
            }
        }
    }

    /* ── recurse into the parent ──────────────────────────────────────────── */
    if ($include_parents) {
        $node['parent_object'] = $term->parent ? build_term_tree((int) $term->parent, $taxonomy, $include_parents, $seen) : null;
    }

    return $node;
}




function get_taxonomy_terms_with_acf($request)
{
    $taxonomy = $request->get_param('taxonomy');

    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'Taxonomy does not exist.', ['status' => 404]);
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    $indexed = [];
    $term_id_to_causeway_id = [];


    foreach ($terms as $term) {
        $term_id = $term->term_id;
        $acf = get_fields($taxonomy . '_' . $term_id) ?: [];


        $causeway_id = isset($acf['causeway_id']) ? (int)$acf['causeway_id'] : $term_id;
        $term_id_to_causeway_id[$term_id] = $causeway_id;
        $attachments = [];
        $area_slug = null;
        $category_slug = null;
        $icon_light = null;
        $icon_dark = null;
        $amenity_icon = null;
        // $category_overview_slug = null;

        #print_r($acf['area_attachments']);

        if (!empty($acf['area_attachments']) && is_array($acf['area_attachments'])) {
            $attachments = $acf['area_attachments'];
        } elseif (!empty($acf['category_attachments']) && is_array($acf['category_attachments'])) {
            $attachments = $acf['category_attachments'];
        } elseif (!empty($acf['attachments'])) {
            $attachments = $acf['attachments'];
        }

        if (!empty($acf['area_slug'])) {
            $area_slug = $acf['area_slug'];
        }

        if (!empty($acf['category_slug'])) {
            $category_slug = $acf['category_slug'];
        }

        if (!empty($acf['icon_light'])) {
            $icon_light = $acf['icon_light'];
        }

        if (!empty($acf['icon_dark'])) {
            $icon_dark = $acf['icon_dark'];
        }

        if (!empty($acf['icon'])) {
            $amenity_icon = $acf['icon'];
        }

        // if (!empty($acf['category_overview_slug'])) {
        //     $category_overview_slug = $acf['category_overview_slug'];
        // }

        $relationships = format_related_acf_ids([
            'related_communities' => 'listing-communities',
            'related_areas'       => 'listing-areas',
            'related_regions'     => 'listing-regions',
            'listing_type'        => 'listing-type',
        ], $acf);

        unset(
            $acf['causeway_id'],
            $acf['related_communities'],
            $acf['related_areas'],
            $acf['related_regions'],
            $acf['listing_type'],
            $acf['attachments'],
            $acf['area_attachments'],
            $acf['category_attachments'],
            $acf['area_slug']
        );

        $term_data = [
            'id'     => $causeway_id,
            'name'   => html_entity_decode($term->name),
            'slug' => $term->slug,
            'parent' => null, // to be set after
            'attachments' => $attachments
        ];

        if ($taxonomy === 'listing-areas') {
            $term_data['area_slug'] = $area_slug;
        }

        if ($taxonomy === 'listings-category') {
            $term_data['category_slug'] = $category_slug;
            $term_data['icon_light'] = $icon_light;
            $term_data['icon_dark'] = $icon_dark;
            // $term_data['category_overview_slug'] = $category_overview_slug;
        }

        if ($taxonomy === 'listings-amenities') {
            $term_data['icon'] = $amenity_icon;
        }

        foreach ($relationships as $key => $val) {
            $term_data[$key] = $val;
        }

        if (!empty($acf)) {
            foreach ($acf as $key => $value) {
                $term_data[$key] = $value;
            }
        }

        // Add translations
        if (function_exists('icl_object_id')) {
            $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);

            if (!empty($languages)) {
                foreach ($languages as $lang_code => $lang_info) {
                    $translated_term_id = apply_filters('wpml_object_id', $term_id, $taxonomy, true, $lang_code);

                    if (!$translated_term_id || $translated_term_id === $term_id) {
                        continue;
                    }

                    $translated_term = get_term($translated_term_id, $taxonomy);
                    if (!$translated_term || is_wp_error($translated_term)) {
                        continue;
                    }

                    $term_data['translations'][$lang_code] = [
                        'name' => html_entity_decode($translated_term->name),
                    ];
                }
            }
        }

        $indexed[$term_id] = $term_data;
    }

    // Set parent to causeway_id (not WordPress term ID)
    foreach ($terms as $term) {
        $term_id = $term->term_id;
        $wp_parent_id = $term->parent;

        if ($wp_parent_id && isset($indexed[$term_id])) {
            $indexed[$term_id]['parent'] = $term_id_to_causeway_id[$wp_parent_id] ?? null;
        }
    }

    // Return all terms, flattened
    return rest_ensure_response(array_values($indexed));
}


function format_related_acf_ids(array $fieldMap, $acf): array
{
    $output = [];

    if (!is_array($acf)) {
        return $output;
    }

    foreach ($fieldMap as $acfKey => $taxonomy) {
        if (!isset($acf[$acfKey])) {
            continue;
        }

        if ($acfKey === 'listing_type') {
            $outputKey = 'type';
        } elseif ($acfKey === 'related_communities') {
            $outputKey = 'communities';
        } elseif ($acfKey === 'related_areas') {
            $outputKey = 'areas';
        } elseif ($acfKey === 'related_regions') {
            $outputKey = 'regions';
        } else {
            continue;
        }

        $format_term = function ($term_id) use ($taxonomy) {
            $term = get_term($term_id, $taxonomy);
            if (!$term || is_wp_error($term)) {
                return null;
            }

            $base = [
                'id'   => (int) (get_field('causeway_id', $taxonomy . '_' . $term->term_id) ?: $term->term_id),
                'name' => html_entity_decode($term->name),
            ];

            // Add translations
            if (function_exists('icl_object_id')) {
                $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
                if (!empty($languages)) {
                    foreach ($languages as $lang_code => $lang_info) {
                        $translated_term_id = apply_filters('wpml_object_id', $term_id, $taxonomy, true, $lang_code);

                        if (!$translated_term_id || $translated_term_id === $term_id) {
                            continue;
                        }

                        $translated_term = get_term($translated_term_id, $taxonomy);
                        if (!$translated_term || is_wp_error($translated_term)) {
                            continue;
                        }

                        $translated_causeway_id = get_field('causeway_id', $taxonomy . '_' . $translated_term_id);

                        $base['translations'][$lang_code] = [
                            'name' => html_entity_decode($translated_term->name),
                        ];
                    }
                }
            }

            return $base;
        };

        if (is_array($acf[$acfKey])) {
            $formatted = [];
            foreach ($acf[$acfKey] as $term_id) {
                $data = $format_term($term_id);
                if ($data) {
                    $formatted[] = $data;
                }
            }
            $output[$outputKey] = $formatted;
        } elseif (is_numeric($acf[$acfKey])) {
            $term_id = $acf[$acfKey];
            $data = $format_term($term_id);
            if ($data) {
                $output[$outputKey] = $data;
            }
        }
    }

    return $output;
}


/**
 * Export locations from ACF repeater `locations`, resolving community per row.
 *
 * @param array $rows     Repeater rows. Expected subfields:
 *                        name, civic_address, postal_code, state, country,
 *                        latitude, longitude, place_id, community (Term ID or WP_Term)
 * @param int   $post_id  Listing post ID
 * @return array<int, array<string, mixed>>
 */
function format_locations($rows, $post_id) {

    if (!is_array($rows)) {
        $rows = [];
    }

    error_log('Formatting locations for post ID: ' . $post_id);
    // Fallback community (listing's first, used only if a row lacks its own)
    $primary_communities = wp_get_post_terms($post_id, 'listing-communities');
    $fallback_community  = $primary_communities[0] ?? null;

    // County is per-listing
    $county_term = (wp_get_post_terms($post_id, 'listing-counties')[0] ?? null);

    // Small caches for speed
    static $community_term_cache = []; // [term_id => WP_Term|null]
    static $community_data_cache = []; // [term_id => array|null]

    $resolve_community_term = function ($val) use (&$community_term_cache) {
        // Accept Term ID, WP_Term, or string (slug/name)
        if ($val instanceof WP_Term) {
            return $val;
        }
        if (is_numeric($val)) {
            $id = (int) $val;
            if (!array_key_exists($id, $community_term_cache)) {
                $t = get_term($id, 'listing-communities');
                $community_term_cache[$id] = ($t && !is_wp_error($t)) ? $t : null;
            }
            return $community_term_cache[$id];
        }
        if (is_string($val) && $val !== '') {
            $t = get_term_by('slug', $val, 'listing-communities');
            if (!$t) $t = get_term_by('name', $val, 'listing-communities');
            return ($t && !is_wp_error($t)) ? $t : null;
        }
        return null;
    };

    $build_community_data = function ($community_term) use (&$community_data_cache, $county_term) {
        if (!$community_term) return null;

        $cid = (int) $community_term->term_id;
        if (isset($community_data_cache[$cid])) {
            return $community_data_cache[$cid];
        }

        // ACF on the community term
        $cacf    = get_fields('listing-communities_' . $cid) ?: [];
        $areas   = $cacf['related_areas']   ?? [];
        $regions = $cacf['related_regions'] ?? [];

        $data = [
            'id'      => (int) ($cacf['causeway_id'] ?? $cid),
            'name'    => html_entity_decode($community_term->name),
            'areas'   => map_terms($areas, 'listing-areas'),
            'regions' => map_terms($regions, 'listing-regions'),
            'county'  => $county_term ? [
                'id'   => (int) get_field('causeway_id', 'listing-counties_' . $county_term->term_id),
                'name' => html_entity_decode($county_term->name),
            ] : null,
        ];

        $community_data_cache[$cid] = $data;
        return $data;
    };

    $out = [];
    foreach ($rows as $row) {
        // Coordinates (accept alt keys lat/lng if present)
        $lat = $row['latitude']  ?? $row['lat']  ?? null;
        $lng = $row['longitude'] ?? $row['lng'] ?? null;

        // Resolve per-row community (preferred), else fallback
        $row_comm_val  = $row['community'] ?? null; // Term ID (preferred) or WP_Term/string
        $row_comm_term = $resolve_community_term($row_comm_val) ?: $fallback_community;
        $community     = $build_community_data($row_comm_term);

        $out[] = [
            'id'            => (int) $post_id,
            'name'          => isset($row['name'])          ? html_entity_decode((string) $row['name'])          : '',
            'civic_address' => isset($row['civic_address']) ? html_entity_decode((string) $row['civic_address']) : '',
            'postal_code'   => isset($row['postal_code'])   ? (string) $row['postal_code']   : '',
            'state'         => isset($row['state'])         ? (string) $row['state']         : '',
            'country'       => isset($row['country'])       ? (string) $row['country']       : '',
            'latitude'      => ($lat === '' || $lat === null) ? 0.0 : (float) $lat,
            'longitude'     => ($lng === '' || $lng === null) ? 0.0 : (float) $lng,
            'place_id'      => isset($row['place_id']) && $row['place_id'] !== '' ? (string) $row['place_id'] : null,
            'lookup_method' => 'Civic',
            'community'     => $community,
        ];
    }

    return $out;
}

function map_terms($term_ids, $taxonomy)
{
    $out = [];

    if (!is_array($term_ids)) {
        return $formatted;
    }

    foreach ($term_ids as $id) {
        $term = get_term($id, $taxonomy);
        if ($term && !is_wp_error($term)) {
            $causeway_id = get_field('causeway_id', $taxonomy . '_' . $term->term_id);
            $out[] = [
                'id'   => (int) ($causeway_id ?? $term->term_id),
                'name' => html_entity_decode($term->name),
            ];
        }
    }
    return $out;
}

function format_websites($sites)
{
    $formatted = [];

    if (!is_array($sites)) {
        return $formatted;
    }

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

function format_attachments($items, $listing_id)
{
    $formatted = [];

    if (!is_array($items)) {
        return $formatted;
    }

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

function format_related($ids)
{
    $out = [];

    if (!is_array($ids)) {
        return $formatted;
    }

    foreach ($ids as $post_id) {
        $acf = get_fields($post_id) ?: [];
        $out[] = [
            'id' => (int) ($acf['causeway_id'] ?? $post_id),
        ];
    }
    return $out;
}

function format_dates($dates): array
{

    $result = [];

    if (!is_array($dates)) {
        return $result;
    }

    foreach ($dates as $date) {
        $result[] = [
            'start_at' => $date['start_at'] ?? null,
            'end_at'   => $date['end_at'] ?? null,
            'rrule'    => $date['rrule'] ?? null,
        ];
    }

    return $result;
}

function format_occurrences(array $rows): array {
    $out = [];

    foreach ($rows as $row) {
        $out[] = [
            'start_at' => $row['occurrence_start'] ?? null,
            'end_at'   => $row['occurrence_end']   ?? null,
        ];
    }

    return $out;
}

/**
 * Inject "translations" → "<lang>" → "alt" into each attachment row.
 *
 * @param array &$base     Base-language attachments already produced by format_attachments()
 * @param array $srcRows   Attachments repeater ACF from the translated post
 * @param string $lang     Language code, e.g. 'fr'
 */
function add_alt_translations_to_attachments(array &$base, array $srcRows, string $lang): void
{
    foreach ($base as $idx => &$att) {
        // Ensure the translations container exists
        if (! isset($att['translations'])) {
            $att['translations'] = [];
        }

        /* ── Priority 1 : same-row repeater field ─────────────────── */
        if (! empty($srcRows[ $idx ]['alt'])) {
            $att['translations'][ $lang ] = [
                'alt' => html_entity_decode($srcRows[ $idx ]['alt']),
            ];
            continue;
        }

        /* ── Priority 2 : WPML media translation (fallback) ───────── */
        if (! empty($att['id'])) {
            $translated_media_id = apply_filters('wpml_object_id', $att['id'], 'attachment', true, $lang);
            if ($translated_media_id && $translated_media_id !== $att['id']) {
                $alt = get_post_meta($translated_media_id, '_wp_attachment_image_alt', true);
                if ($alt) {
                    $att['translations'][ $lang ] = [ 'alt' => html_entity_decode($alt) ];
                }
            }
        }
    }
}
