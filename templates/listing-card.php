<?php
/**
 * Listing card template part.
 * Theme may override by placing:
 *   - causeway/listing-card.php
 *   - template-parts/causeway/listing-card.php
 *   - listing-card.php (root)
 */
if (!defined('ABSPATH')) { exit; }
$post_id = get_the_ID();
$excerpt = get_the_excerpt();
if (!$excerpt) { $excerpt = wp_trim_words(wp_strip_all_tags(get_the_content()), 25); }
$types = get_the_terms($post_id, 'listing-type');
?>
<article <?php post_class('listing-card'); ?>>
    <a href="<?php echo esc_url(get_permalink()); ?>" class="thumb">
        <?php if (has_post_thumbnail()) {
            the_post_thumbnail('medium_large');
        } ?>
    </a>
    <div class="body">
        <h3 class="title">
            <a href="<?php echo esc_url(get_permalink()); ?>">
                <?php echo esc_html(get_the_title()); ?>
            </a>
        </h3>
        <p class="excerpt">
            <?php echo esc_html(wp_trim_words($excerpt, 25)); ?>
        </p>
        <?php if (!is_wp_error($types) && !empty($types)) : ?>
            <div class="type">
                <?php echo esc_html($types[0]->name); ?>
            </div>
        <?php endif; ?>
    </div>
</article>
