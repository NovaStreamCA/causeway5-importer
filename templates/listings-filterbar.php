<?php
/**
 * Filter bar for Listings Grid block.
 * Theme can override by placing a file in one of:
 * - causeway/listings-filterbar.php
 * - template-parts/causeway/listings-filterbar.php
 * - listings-filterbar.php (root)
 */
if (!defined('ABSPATH')) { exit; }

$available_filters = ['search', 'type', 'category', 'community', 'area'];
if (!isset($enabled_filters) || !is_array($enabled_filters)) {
    $enabled_filters = $available_filters;
}
$enabled_filters = array_values(array_intersect(
    $available_filters,
    array_map('sanitize_key', $enabled_filters)
));

// Only query terms for enabled taxonomy filters.
$types = in_array('type', $enabled_filters, true) ? get_terms([
    'taxonomy' => 'listing-type',
    'hide_empty' => true,
]) : [];
$cats = in_array('category', $enabled_filters, true) ? get_terms([
    'taxonomy' => 'listings-category',
    'hide_empty' => true,
    'pad_counts' => true,
]) : [];
$communities = in_array('community', $enabled_filters, true) ? get_terms([
    'taxonomy' => 'listing-communities',
    'hide_empty' => true,
]) : [];
$areas = in_array('area', $enabled_filters, true) ? get_terms([
    'taxonomy' => 'listing-areas',
    'hide_empty' => true,
]) : [];

// Group categories by parent so the dropdown reflects the taxonomy hierarchy.
$categories_by_parent = [];
if (!is_wp_error($cats) && !empty($cats)) {
    foreach ($cats as $category) {
        $categories_by_parent[(int) $category->parent][] = $category;
    }
}
$render_category_options = static function ($parent_id = 0, $depth = 0) use (&$render_category_options, $categories_by_parent) {
    if (empty($categories_by_parent[$parent_id])) { return; }

    foreach ($categories_by_parent[$parent_id] as $category) {
        $prefix = str_repeat('— ', $depth);
        printf(
            '<option value="%1$s" data-depth="%2$d">%3$s</option>',
            esc_attr($category->slug),
            (int) $depth,
            esc_html($prefix . $category->name)
        );
        $render_category_options((int) $category->term_id, $depth + 1);
    }
};
?>

<div class="causeway-filterbar" role="region" aria-label="Listings filters">
    <div class="filter-row">
        <?php if (in_array('search', $enabled_filters, true)) : ?>
            <label for="causeway-filter-search" class="screen-reader-text"><?php esc_html_e('Search listings', 'causeway'); ?></label>
            <div class="cs-input-group">
                <div class="input-group-text">
                    <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15.7549 14.255H14.9649L14.6849 13.985C15.6649 12.845 16.2549 11.365 16.2549 9.755C16.2549 6.165 13.3449 3.255 9.75488 3.255C6.16488 3.255 3.25488 6.165 3.25488 9.755C3.25488 13.345 6.16488 16.255 9.75488 16.255C11.3649 16.255 12.8449 15.665 13.9849 14.685L14.2549 14.965V15.755L19.2549 20.745L20.7449 19.255L15.7549 14.255ZM9.75488 14.255C7.26488 14.255 5.25488 12.245 5.25488 9.755C5.25488 7.26501 7.26488 5.255 9.75488 5.255C12.2449 5.255 14.2549 7.26501 14.2549 9.755C14.2549 12.245 12.2449 14.255 9.75488 14.255Z"/>
                    </svg>
                </div>
                <input id="causeway-filter-search" type="search" placeholder="<?php echo esc_attr__('Search listings…', 'causeway'); ?>" data-role="search" />
            </div>
        <?php endif; ?>

        <?php if (in_array('type', $enabled_filters, true)) : ?>
            <label for="causeway-filter-type" class="screen-reader-text"><?php esc_html_e('Filter by type', 'causeway'); ?></label>
            <select id="causeway-filter-type" data-role="select-type">
                <option value=""><?php esc_html_e('All Types', 'causeway'); ?></option>
                <?php if (!is_wp_error($types) && !empty($types)) : ?>
                    <?php foreach ($types as $t) : ?>
                        <option value="<?php echo esc_attr($t->slug); ?>"><?php echo esc_html($t->name); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        <?php endif; ?>

        <?php if (in_array('category', $enabled_filters, true)) : ?>
            <label for="causeway-filter-cat" class="screen-reader-text"><?php esc_html_e('Filter by category', 'causeway'); ?></label>
            <select id="causeway-filter-cat" data-role="select-cat">
                <option value=""><?php esc_html_e('All Categories', 'causeway'); ?></option>
                <?php $render_category_options(); ?>
            </select>
        <?php endif; ?>

        <?php if (in_array('community', $enabled_filters, true)) : ?>
            <label for="causeway-filter-community" class="screen-reader-text"><?php esc_html_e('Filter by community', 'causeway'); ?></label>
            <select id="causeway-filter-community" data-role="select-community">
                <option value=""><?php esc_html_e('All Communities', 'causeway'); ?></option>
                <?php if (!is_wp_error($communities) && !empty($communities)) : ?>
                    <?php foreach ($communities as $community) : ?>
                        <option value="<?php echo esc_attr($community->slug); ?>"><?php echo esc_html($community->name); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        <?php endif; ?>

        <?php if (in_array('area', $enabled_filters, true)) : ?>
            <label for="causeway-filter-area" class="screen-reader-text"><?php esc_html_e('Filter by area', 'causeway'); ?></label>
            <select id="causeway-filter-area" data-role="select-area">
                <option value=""><?php esc_html_e('All Areas', 'causeway'); ?></option>
                <?php if (!is_wp_error($areas) && !empty($areas)) : ?>
                    <?php foreach ($areas as $area) : ?>
                        <option value="<?php echo esc_attr($area->slug); ?>"><?php echo esc_html($area->name); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        <?php endif; ?>
    </div>
    <!-- You can add client-side pagination controls later here -->
</div>
