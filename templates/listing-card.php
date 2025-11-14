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
$filter_classes = '';
if (!is_wp_error($types) && !empty($types)) {
    $parts = [];
    foreach ($types as $t) {
        if (!empty($t->slug)) { $parts[] = 'type-' . sanitize_html_class($t->slug); }
    }
    if (!empty($parts)) { $filter_classes = ' ' . implode(' ', $parts); }
}
$categories_terms = get_the_terms($post_id, 'listings-category');
$cat_classes = '';
if (!is_wp_error($categories_terms) && !empty($categories_terms)) {
    $cparts = [];
    foreach ($categories_terms as $c) {
        if (!empty($c->slug)) { $cparts[] = 'cat-' . sanitize_html_class($c->slug); }
    }
    if (!empty($cparts)) { $cat_classes = ' ' . implode(' ', $cparts); }
}
$title_attr = strtolower(wp_strip_all_tags(get_the_title($post_id)));
$rating = get_field('tripadvisor_rating', $post_id);
$nextDate = get_field('next_occurrence', $post_id);
$price = get_field('price', $post_id);
$nextMonth = '';
$nextDay  = '';
if (!empty($nextDate)) {
    $dt = causeway_parse_occ_dt($nextDate);
    if ($dt instanceof DateTime) {
        $tz = wp_timezone();
        $ts = $dt->getTimestamp();
        $nextMonth = wp_date('M', $ts, $tz);   // e.g., Nov, Dec
        $nextDay  = wp_date('j', $ts, $tz); // e.g., 4, 23
    }
}
?>
<article <?php post_class('listing-card' . $filter_classes . $cat_classes); ?> data-title="<?php echo esc_attr($title_attr); ?>">
    <a href="<?php echo esc_url(get_permalink()); ?>" class="thumb">
        <?php if (has_post_thumbnail()) {
            the_post_thumbnail('medium_large');
        } ?>
        <div class="thumb-overlay">
            <?php if (!is_wp_error($types) && !empty($types)) : ?>
                <span class='type'><?php echo esc_html($types[0]->name); ?></span>
            <?php endif; ?>
            <?php if ($nextMonth || $nextDay) : ?>
                <span class='date'>
                    <span class="date-month"><?php echo esc_html($nextMonth); ?></span>
                    <span class="date-day"><?php echo esc_html($nextDay); ?></span>
                </span>
            <?php endif; ?>
            <?php if ($price && has_term('deals-and-packages', 'listings-category', $post_id)) : ?>
                <span class='price'>
                    <span class="date-month">FROM</span>
                    <span class="date-day">$<?php echo esc_html($price); ?></span>
                </span>
            <?php endif; ?>
        </div>
    </a>
    <div class="body">
        <h3 class="title">
            <a href="<?php echo esc_url(get_permalink()); ?>">
                <?php echo esc_html(get_the_title()); ?>
            </a>
            <?php if($rating): ?>
            <div class='ta-rating'>
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.9972 13.0566C22.4985 17.4118 17.8288 19.622 14.1622 17.0378C14.012 16.9329 13.8562 16.6807 13.6805 16.7063L12.0427 18.5424L11.8047 18.4404L10.2066 16.7148C7.29368 19.3159 2.55033 18.1967 1.26957 14.4734C0.898376 13.391 0.90688 11.7447 1.31774 10.668C1.62377 9.86608 2.15081 9.18037 2.72035 8.55416L1.03155 6.62452H4.93051C5.04385 6.62452 6.54563 5.68094 6.88565 5.53076C10.4077 3.96382 14.4455 4.131 17.8486 5.91329C18.0526 6.02097 18.8999 6.62452 18.965 6.62452H22.932L21.2432 8.49465C21.2262 8.59099 21.8666 9.29087 21.9714 9.44388C22.5098 10.2231 22.9207 11.2942 23 12.2406V13.0566H22.9972ZM16.0833 7.03255C13.4396 6.02097 10.5268 6.01247 7.88023 7.03255L9.32533 7.8571C10.7818 8.83467 11.8075 10.4158 11.9832 12.1782C12.1135 10.5008 13.0741 9.01886 14.3945 8.02145L16.0833 7.03255ZM5.96475 8.94519C1.32908 9.47789 1.90996 16.7204 6.75815 16.3719C11.6375 16.0177 11.119 8.35298 5.96475 8.94519ZM14.8337 15.2526C18.4408 18.8484 23.6829 13.2522 19.9851 9.87459C16.4602 6.65285 11.4448 11.8751 14.8337 15.2526Z"/>
                    <path d="M17.2197 10.7048C19.9853 10.3988 20.144 14.5244 17.5371 14.6122C15.1059 14.6972 14.8084 10.9711 17.2197 10.7048Z"/>
                    <path d="M6.16859 10.7048C8.58276 10.362 9.29115 13.8075 7.06682 14.5103C4.38062 15.3603 3.46539 11.0902 6.16859 10.7048Z"/>
                </svg>
                <?php echo $rating; ?>
            </div>
            <?php endif; ?>
        </h3>

        <!-- First Start/Last End -->
        <?php
        // Data is guaranteed sorted at import; use first start and last end directly.
        $occurrences = get_field('all_occurrences', $post_id);
        if (is_array($occurrences) && !empty($occurrences)) {
            $first = reset($occurrences);
            $last  = end($occurrences);

            $first_dt = !empty($first['occurrence_start']) ? causeway_parse_occ_dt((string)$first['occurrence_start']) : null;
            $last_dt  = !empty($last['occurrence_end'])   ? causeway_parse_occ_dt((string)$last['occurrence_end'])   : null;

            if ($first_dt instanceof DateTime && $last_dt instanceof DateTime) {
                $tz = wp_timezone();
                $start_label = wp_date('M j', $first_dt->getTimestamp(), $tz);
                $end_label   = wp_date('M j', $last_dt->getTimestamp(), $tz);
                $range_label = ($start_label === $end_label) ? $start_label : ($start_label . ' - ' . $end_label);
                echo '<p class="dates">' . esc_html($range_label) . '</p>';
            }
        }
        ?>
    </div>
</article>
