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
        <label for="causeway-filter-search" class="screen-reader-text"><?php esc_html_e('Search by title', 'causeway'); ?></label>
        <input id="causeway-filter-search" type="search" placeholder="<?php echo esc_attr__('Search by title…', 'causeway'); ?>" data-role="search" />

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
