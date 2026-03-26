<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure translated listing-campaigns terms always have campaign_sections rows.
 * WPML/ACF can occasionally create translated taxonomy terms without repeater row meta.
 */
function causeway_sync_campaign_sections_rows_for_term(int $term_id): void
{
    static $running = false;

    if ($running) {
        return;
    }

    if (!function_exists('get_field') || !function_exists('update_field')) {
        return;
    }

    // WPML not active.
    if (!has_filter('wpml_object_id')) {
        return;
    }

    $taxonomy = 'listing-campaigns';
    $default_lang = apply_filters('wpml_default_language', null);
    if (!is_string($default_lang) || $default_lang === '') {
        return;
    }

    // Resolve source/default-language term for this translation group.
    $source_term_id = (int) apply_filters('wpml_object_id', $term_id, $taxonomy, true, $default_lang);
    if ($source_term_id <= 0) {
        return;
    }

    $source_key = $taxonomy . '_' . $source_term_id;
    $source_sections = get_field('campaign_sections', $source_key);

    if (!is_array($source_sections) || empty($source_sections)) {
        return;
    }

    $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
    if (!is_array($languages) || empty($languages)) {
        return;
    }

    $running = true;

    foreach ($languages as $lang_code => $lang_info) {
        if (!is_string($lang_code) || $lang_code === '') {
            continue;
        }

        $translated_term_id = (int) apply_filters('wpml_object_id', $source_term_id, $taxonomy, true, $lang_code);
        if ($translated_term_id <= 0) {
            continue;
        }

        if ($translated_term_id === $source_term_id) {
            continue;
        }

        $target_key = $taxonomy . '_' . $translated_term_id;
        $existing = get_field('campaign_sections', $target_key);

        // Only seed when target rows are missing/empty.
        if (is_array($existing) && !empty($existing)) {
            continue;
        }

        update_field('campaign_sections', $source_sections, $target_key);
    }

    $running = false;
}

/**
 * Sync immediately after term create/edit in listing-campaigns taxonomy.
 */
function causeway_wpml_campaign_term_saved($term_id): void
{
    causeway_sync_campaign_sections_rows_for_term((int) $term_id);
}
add_action('created_listing-campaigns', 'causeway_wpml_campaign_term_saved', 20, 1);
add_action('edited_listing-campaigns', 'causeway_wpml_campaign_term_saved', 20, 1);

/**
 * Backfill missing rows for existing translated campaign terms.
 */
function causeway_wpml_backfill_campaign_sections_rows(): void
{
    if (!function_exists('get_field') || !function_exists('update_field')) {
        return;
    }

    if (!has_filter('wpml_object_id')) {
        return;
    }

    $term_ids = get_terms([
        'taxonomy' => 'listing-campaigns',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);

    if (is_wp_error($term_ids) || !is_array($term_ids) || empty($term_ids)) {
        return;
    }

    foreach ($term_ids as $term_id) {
        causeway_sync_campaign_sections_rows_for_term((int) $term_id);
    }
}
add_action('init', 'causeway_wpml_backfill_campaign_sections_rows', 30);
