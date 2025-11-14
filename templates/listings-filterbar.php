<?php
/**
 * Filter bar for Listings Grid block (Title search, Type select, Category select).
 * Theme can override by placing a file in one of:
 * - causeway/listings-filterbar.php
 * - template-parts/causeway/listings-filterbar.php
 * - listings-filterbar.php (root)
 */
if (!defined('ABSPATH')) { exit; }

// Load listing types and categories
$types = get_terms([
    'taxonomy' => 'listing-type',
    'hide_empty' => true,
]);
$cats = get_terms([
    'taxonomy' => 'listings-category',
    'hide_empty' => true,
]);
?>

<div class="causeway-filterbar" role="region" aria-label="Listings filters">
    <div class="filter-row">
        <label for="causeway-filter-search" class="screen-reader-text"><?php esc_html_e('Search listings', 'causeway'); ?></label>
        <div class="cs-input-group">
            <div class="input-group-text">
                <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15.7549 14.255H14.9649L14.6849 13.985C15.6649 12.845 16.2549 11.365 16.2549 9.755C16.2549 6.165 13.3449 3.255 9.75488 3.255C6.16488 3.255 3.25488 6.165 3.25488 9.755C3.25488 13.345 6.16488 16.255 9.75488 16.255C11.3649 16.255 12.8449 15.665 13.9849 14.685L14.2549 14.965V15.755L19.2549 20.745L20.7449 19.255L15.7549 14.255ZM9.75488 14.255C7.26488 14.255 5.25488 12.245 5.25488 9.755C5.25488 7.26501 7.26488 5.255 9.75488 5.255C12.2449 5.255 14.2549 7.26501 14.2549 9.755C14.2549 12.245 12.2449 14.255 9.75488 14.255Z"/>
                </svg>
            </div>
            <input id="causeway-filter-search" type="search" placeholder="<?php echo esc_attr__('Search listings…', 'causeway'); ?>" data-role="search" />
        </div>

        <label for="causeway-filter-type" class="screen-reader-text"><?php esc_html_e('Filter by type', 'causeway'); ?></label>
        <select id="causeway-filter-type" data-role="select-type">
            <option value=""><?php esc_html_e('All Types', 'causeway'); ?></option>
            <?php if (!is_wp_error($types) && !empty($types)) : ?>
                <?php foreach ($types as $t) : ?>
                    <option value="<?php echo esc_attr($t->slug); ?>"><?php echo esc_html($t->name); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label for="causeway-filter-cat" class="screen-reader-text"><?php esc_html_e('Filter by category', 'causeway'); ?></label>
        <select id="causeway-filter-cat" data-role="select-cat">
            <option value=""><?php esc_html_e('All Categories', 'causeway'); ?></option>
            <?php if (!is_wp_error($cats) && !empty($cats)) : ?>
                <?php foreach ($cats as $c) : ?>
                    <option value="<?php echo esc_attr($c->slug); ?>"><?php echo esc_html($c->name); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    <!-- You can add client-side pagination controls later here -->
</div>
