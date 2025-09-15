<?php

add_action('acf/init', 'causeway_register_acf_fields');

function causeway_register_acf_fields() {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_sub_page([
            'page_title'  => 'Import Settings',
            'menu_title'  => 'Settings',
            'parent_slug' => 'edit.php?post_type=listing', // adds it under Listings
            'menu_slug'   => 'causeway-settings',
            'capability'  => 'manage_options',
        ]);
    }

    if( function_exists('acf_add_local_field_group') ) {
        // Listing Types
        acf_add_local_field_group([
            'key' => 'group_listing_type_meta',
            'title' => 'Listing Type Meta',
            'fields' => [
                [
                    'key' => 'field_listing_type_icon',
                    'label' => 'Icon',
                    'name' => 'icon',
                    'type' => 'text',
                    'instructions' => 'Font Awesome icon class, e.g., fa-solid fa-hotel',
                ],
                [
                    'key' => 'field_listing_type_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                    'instructions' => 'Original ID from Causeway API',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listing-type',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'active' => true,
        ]);

        // Listing Categories
        acf_add_local_field_group([
            'key' => 'group_listing_category_meta',
            'title' => 'Listing Category Meta',
            'fields' => [
                [
                    'key' => 'field_category_type',
                    'label' => 'Listing Type',
                    'name' => 'listing_type',
                    'type' => 'taxonomy',
                    'taxonomy' => 'listing-type',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ],
                [
                    'key' => 'field_category_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                ],
                [
                    'key' => 'field_category_attachments',
                    'label' => 'Attachments',
                    'name' => 'category_attachments',
                    'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_attachment_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                        ['key' => 'field_attachment_category', 'label' => 'Category', 'name' => 'category', 'type' => 'text'],
                        ['key' => 'field_attachment_alt', 'label' => 'Alt Text', 'name' => 'alt', 'type' => 'text'],
                    ],
                ],
                ['key' => 'field_category_slug', 'label' => 'Category Slug', 'name' => 'category_slug', 'type' => 'text'],
                ['key' => 'field_category_icon_light', 'label' => 'Category Icon Light', 'name' => 'category_icon_light', 'type' => 'text'],
                ['key' => 'field_category_icon_dark', 'label' => 'Category Icon Dark', 'name' => 'category_icon_dark', 'type' => 'text'],
                // ['key' => 'field_category_overview_slug', 'label' => 'Category Overview Slug', 'name' => 'category_overview_slug', 'type' => 'text'],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listings-category',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'active' => true,
        ]);

        // Listing Amenities
        acf_add_local_field_group([
            'key' => 'group_listings_amenities',
            'title' => 'Amenity Details',
            'fields' => [
                [
                    'key' => 'field_amenity_causeway_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                ],
                [
                    'key' => 'field_amenity_type',
                    'label' => 'Listing Type',
                    'name' => 'listing_type',
                    'type' => 'taxonomy',
                    'taxonomy' => 'listing-type',
                    'field_type' => 'select',
                    'return_format' => 'id',
                    'allow_null' => 1,
                ],
                ['key' => 'field_amenity_icon', 'label' => 'Amenity Icon', 'name' => 'amenity_icon', 'type' => 'text'],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listings-amenities',
                    ],
                ],
            ],
        ]);

        // Areas
        acf_add_local_field_group([
            'key' => 'group_listing_areas',
            'title' => 'Area Details',
            'fields' => [
                [
                    'key' => 'field_area_communities',
                    'label' => 'Related Communities',
                    'name' => 'related_communities',
                    'type' => 'taxonomy',
                    'taxonomy' => 'listing-communities',
                    'field_type' => 'multi_select',
                    'add_term' => false,
                    'return_format' => 'id',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_area_causeway_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                    'readonly' => 1,
                    'wrapper' => ['width' => '50'],
                ],
                [
                    'key' => 'field_area_attachments',
                    'label' => 'Attachments',
                    'name' => 'area_attachments',
                    'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_attachment_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                        ['key' => 'field_attachment_category', 'label' => 'Category', 'name' => 'category', 'type' => 'text'],
                        ['key' => 'field_attachment_alt', 'label' => 'Alt Text', 'name' => 'alt', 'type' => 'text'],
                    ],
                ],
                ['key' => 'field_area_slug', 'label' => 'Area Slug', 'name' => 'area_slug', 'type' => 'text'],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listing-areas',
                    ],
                ],
            ],
        ]);

        // Communities
        acf_add_local_field_group([
            'key' => 'group_listing_communities',
            'title' => 'Community Details',
            'fields' => [
                [
                    'key' => 'field_community_areas',
                    'label' => 'Related Areas',
                    'name' => 'related_areas',
                    'type' => 'taxonomy',
                    'taxonomy' => 'listing-areas',
                    'field_type' => 'multi_select',
                    'add_term' => false,
                    'return_format' => 'id',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_community_regions',
                    'label' => 'Related Regions',
                    'name' => 'related_regions',
                    'type' => 'taxonomy',
                    'taxonomy' => 'listing-regions',
                    'field_type' => 'multi_select',
                    'add_term' => false,
                    'return_format' => 'id',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_community_causeway_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                    'readonly' => 1,
                    'wrapper' => ['width' => '50'],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listing-communities',
                    ],
                ],
            ],
        ]);

        // Regions
        acf_add_local_field_group([
            'key' => 'group_listing_regions',
            'title' => 'Region Details',
            'fields' => [
                [
                    'key' => 'field_region_communities',
                    'label' => 'Related Communities',
                    'name' => 'related_communities',
                    'type' => 'taxonomy',
                    'taxonomy' => 'listing-communities',
                    'field_type' => 'multi_select',
                    'add_term' => false,
                    'return_format' => 'id',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_region_causeway_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                    'readonly' => 1,
                    'wrapper' => ['width' => '50'],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listing-regions',
                    ],
                ],
            ],
        ]);

        // Counties
        acf_add_local_field_group([
            'key' => 'group_listing_counties',
            'title' => 'County Details',
            'fields' => [
                [
                    'key' => 'field_county_causeway_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                    'readonly' => 1,
                    'wrapper' => ['width' => '50'],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listing-counties',
                    ],
                ],
            ],
        ]);

        // Campagins
        acf_add_local_field_group([
            'key' => 'group_listing_campaigns',
            'title' => 'Campaign Details',
            'fields' => [
                [
                    'key' => 'field_campaign_causeway_id',
                    'label' => 'Causeway ID',
                    'name' => 'causeway_id',
                    'type' => 'number',
                    'readonly' => 1,
                    'wrapper' => ['width' => '33'],
                ],
                [
                    'key' => 'field_campaign_activated_at',
                    'label' => 'Activated At',
                    'name' => 'activated_at',
                    'type' => 'date_picker',
                    'display_format' => 'Y-m-d',
                    'return_format' => 'Y-m-d',
                    'wrapper' => ['width' => '33'],
                ],
                [
                    'key' => 'field_campaign_expired_at',
                    'label' => 'Expired At',
                    'name' => 'expired_at',
                    'type' => 'date_picker',
                    'display_format' => 'Y-m-d',
                    'return_format' => 'Y-m-d',
                    'wrapper' => ['width' => '33'],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'listing-campaigns',
                    ],
                ],
            ],
        ]);

        // Listing
        acf_add_local_field_group([
            'key' => 'group_listing_details',
            'title' => 'Listing Details',
            'fields' => [
                ['key' => 'field_causeway_id', 'label' => 'Causeway ID', 'name' => 'causeway_id', 'type' => 'number'],
                ['key' => 'field_provider', 'label' => 'Provider', 'name' => 'provider', 'type' => 'text'],
                ['key' => 'field_status', 'label' => 'Status', 'name' => 'status', 'type' => 'text'],
                ['key' => 'field_highlights', 'label' => 'Highlights', 'name' => 'highlights', 'type' => 'textarea'],
                ['key' => 'field_email', 'label' => 'Email', 'name' => 'email', 'type' => 'email'],
                ['key' => 'field_metaline', 'label' => 'Meta', 'name' => 'metaline', 'type' => 'text'],
                ['key' => 'field_phone_primary', 'label' => 'Phone Primary', 'name' => 'phone_primary', 'type' => 'text'],
                ['key' => 'field_phone_secondary', 'label' => 'Phone Secondary', 'name' => 'phone_secondary', 'type' => 'text'],
                ['key' => 'field_phone_offseason', 'label' => 'Phone Offseason', 'name' => 'phone_offseason', 'type' => 'text'],
                ['key' => 'field_phone_tollfree', 'label' => 'Phone Toll-Free', 'name' => 'phone_tollfree', 'type' => 'text'],
                ['key' => 'field_price', 'label' => 'Price', 'name' => 'price', 'type' => 'number'],
                ['key' => 'field_is_featured', 'label' => 'Is Featured?', 'name' => 'is_featured', 'type' => 'true_false'],
                ['key' => 'field_opengraph_title', 'label' => 'OG Title', 'name' => 'opengraph_title', 'type' => 'text'],
                ['key' => 'field_opengraph_description', 'label' => 'OG Description', 'name' => 'opengraph_description', 'type' => 'textarea'],
                ['key' => 'field_contact_name', 'label' => 'Contact Name', 'name' => 'contact_name', 'type' => 'text'],
                ['key' => 'field_activated_at', 'label' => 'Activated At', 'name' => 'activated_at', 'type' => 'date_picker'],
                ['key' => 'field_expired_at', 'label' => 'Expired At', 'name' => 'expired_at', 'type' => 'date_picker'],
                [
                    'key' => 'field_tripadvisor_id',
                    'label' => 'TripAdvisor ID',
                    'name' => 'tripadvisor_id',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_tripadvisor_url',
                    'label' => 'TripAdvisor URL',
                    'name' => 'tripadvisor_url',
                    'type' => 'url',
                ],
                [
                    'key' => 'field_tripadvisor_rating_url',
                    'label' => 'TripAdvisor Rating URL',
                    'name' => 'tripadvisor_rating_url',
                    'type' => 'url',
                ],
                [
                    'key' => 'field_tripadvisor_rating',
                    'label' => 'TripAdvisor Rating',
                    'name' => 'tripadvisor_rating',
                    'type' => 'number',
                ],
                [
                    'key' => 'field_tripadvisor_count',
                    'label' => 'TripAdvisor Review Count',
                    'name' => 'tripadvisor_count',
                    'type' => 'number',
                ],
                [
                    'key' => 'field_websites',
                    'label' => 'Websites',
                    'name' => 'websites',
                    'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_website_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                        ['key' => 'field_website_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text'],
                        ['key' => 'field_website_type', 'label' => 'Type Name', 'name' => 'type_name', 'type' => 'text'],
                    ],
                ],
                [
                    'key' => 'field_attachments',
                    'label' => 'Attachments',
                    'name' => 'attachments',
                    'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_attachment_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                        ['key' => 'field_attachment_category', 'label' => 'Category', 'name' => 'category', 'type' => 'text'],
                        ['key' => 'field_attachment_alt', 'label' => 'Alt Text', 'name' => 'alt', 'type' => 'text'],
                    ],
                ],
                [
                    'key' => 'field_location_details',
                    'label' => 'Location Details',
                    'name' => 'location_details',
                    'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_location_state', 'label' => 'State', 'name' => 'state', 'type' => 'text'],
                        ['key' => 'field_location_country', 'label' => 'Country', 'name' => 'country', 'type' => 'text'],
                        ['key' => 'field_location_name', 'label' => 'Location Name', 'name' => 'name', 'type' => 'text'],
                        ['key' => 'field_location_address', 'label' => 'Civic Address', 'name' => 'civic_address', 'type' => 'text'],
                        ['key' => 'field_location_postal', 'label' => 'Postal Code', 'name' => 'postal_code', 'type' => 'text'],
                        ['key' => 'field_location_place_id', 'label' => 'Place ID', 'name' => 'place_id', 'type' => 'text'],
                        ['key' => 'field_location_latitude', 'label' => 'Latitude', 'name' => 'latitude', 'type' => 'text'],
                        ['key' => 'field_location_longitude', 'label' => 'Longitude', 'name' => 'longitude', 'type' => 'text'],
                        ['key' => 'field_location_community', 'label' => 'Community', 'name' => 'community', 'type' => 'number'],
                    ],
                ],
                [
                    'key' => 'field_listing_dates',
                    'label' => 'Dates',
                    'name' => 'dates',
                    'type' => 'repeater',
                    'sub_fields' => [
                        [
                            'key' => 'field_listing_date_start',
                            'label' => 'Start At',
                            'name' => 'start_at',
                            'type' => 'date_time_picker',
                        ],
                        [
                            'key' => 'field_listing_date_end',
                            'label' => 'End At',
                            'name' => 'end_at',
                            'type' => 'date_time_picker',
                        ],
                        [
                            'key' => 'field_listing_date_rrule',
                            'label' => 'Recurrence Rule',
                            'name' => 'rrule',
                            'type' => 'textarea',
                        ],
                    ],
                ],
                [
                    'key' => 'field_next_occurrence',
                    'label' => 'Next Occurrence',
                    'name' => 'next_occurrence',
                    'type' => 'date_time_picker',
                ],
                [
                    'key' => 'field_all_occurrences',
                    'label' => 'All Occurrences',
                    'name' => 'all_occurrences',
                    'type' => 'repeater',
                    'sub_fields' => [
                        [
                            'key' => 'field_occurrence_start',
                            'label' => 'Occurrence Start',
                            'name' => 'occurrence_start',
                            'type' => 'date_time_picker',
                        ],
                        [
                            'key' => 'field_occurrence_end',
                            'label' => 'Occurrence End',
                            'name' => 'occurrence_end',
                            'type' => 'date_time_picker',
                        ],
                    ],
                ],
                [
                    'key' => 'field_related_listings',
                    'label' => 'Related Listings',
                    'name' => 'related_listings',
                    'type' => 'relationship',
                    'post_type' => ['listing'],
                    'return_format' => 'id',
                    'filters' => ['search'],
                    'min' => 0,
                    'max' => '',
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'listing']]],
        ]);

        // Settings
        acf_add_local_field_group([
            'key' => 'group_causeway_settings',
            'title' => 'Causeway Import Settings',
            'fields' => [
                // [
                //     'key' => 'field_causeway_token',
                //     'label' => 'API Token',
                //     'name' => 'causeway_api_token',
                //     'type' => 'text',
                //     'instructions' => 'Paste the Causeway API token here.',
                //     'required' => 1,
                // ],
                [
                    'key' => 'field_causeway_api_url',
                    'label' => 'Causeway API URL',
                    'name' => 'causeway_api_url',
                    'type' => 'url',
                    'instructions' => 'Enter the API URL (e.g. https://api-causeway5.novastream.dev/)',
                    'required' => 1,
                ],
                [
                    'key' => 'field_causeway_public_url',
                    'label' => 'Public Website URL',
                    'name' => 'causeway_public_url',
                    'type' => 'url',
                    'instructions' => 'Enter the public-facing website URL (e.g. https://cbisland.ca)',
                    'required' => 1,
                ],
                [
                    'key' => 'field_headless_api_secret',
                    'label' => 'Headless API Secret',
                    'name' => 'headless_api_secret',
                    'type' => 'text',
                    'instructions' => 'Shared secret to authorize API calls from WordPress to the Angular site.',
                    'required' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'causeway-settings',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
        ]);


    }

    
}
