<?php
/**
 * Archive template fallback for Listing post type.
 * Copy to your theme as archive-listing.php to override.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main class="causeway-archive causeway-archive-listing">
    <header class="archive-header">
        <h1>Listings</h1>
        <?php if (get_query_var('paged') > 1) : ?>
            <p class="archive-page">Page <?php echo (int) get_query_var('paged'); ?></p>
        <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="causeway-listings-grid auto-fill">
            <?php while (have_posts()) : the_post(); 
                $post_id = get_the_ID();
                $title_attr = strtolower(wp_strip_all_tags(get_the_title($post_id)));
                $nextDate = get_field('next_occurrence', $post_id);
                $occurrences = get_field('all_occurrences', $post_id);
                // Event determination: slug exactly 'event' OR contains 'event'
                $is_event = false; $type_slugs = [];
                $types = get_the_terms($post_id, 'listing-type');
                if ($types && !is_wp_error($types)) {
                    foreach ($types as $t) { 
                        if (!empty($t->slug)) { $type_slugs[] = $t->slug; }
                        if (isset($t->slug)) { 
                            $slug_l = strtolower($t->slug);
                            if ($slug_l === 'event' || strpos($slug_l, 'event') !== false) { $is_event = true; }
                        }
                    }
                }
                $sortNextTs = 9999999999;
                if ($is_event && !empty($nextDate)) {
                    $dt = function_exists('causeway_parse_occ_dt') ? causeway_parse_occ_dt($nextDate) : null;
                    if ($dt instanceof DateTime) { $sortNextTs = $dt->getTimestamp(); }
                }
                if ($is_event && $sortNextTs === 9999999999 && is_array($occurrences) && !empty($occurrences)) {
                    $nowTs = (int) current_time('timestamp');
                    foreach ($occurrences as $row) {
                        if (!empty($row['occurrence_start']) && function_exists('causeway_parse_occ_dt')) {
                            $rowDt = causeway_parse_occ_dt((string)$row['occurrence_start']);
                            if ($rowDt instanceof DateTime) {
                                $rowTs = $rowDt->getTimestamp();
                                if ($rowTs >= $nowTs) { $sortNextTs = $rowTs; break; }
                            }
                        }
                    }
                    if ($sortNextTs === 9999999999) {
                        $last = end($occurrences);
                        if (!empty($last['occurrence_end']) && function_exists('causeway_parse_occ_dt')) {
                            $endDt = causeway_parse_occ_dt((string)$last['occurrence_end']);
                            if ($endDt instanceof DateTime) { $sortNextTs = $endDt->getTimestamp(); }
                        }
                        reset($occurrences);
                    }
                }
                $types = get_the_terms($post_id, 'listing-type');
            ?>
            <article <?php post_class('listing-card'); ?> data-title="<?php echo esc_attr($title_attr); ?>" data-event="<?php echo $is_event ? '1' : '0'; ?>" data-next="<?php echo (string)(int)$sortNextTs; ?>" data-types="<?php echo esc_attr(implode(',', $type_slugs)); ?>">
                <a href="<?php the_permalink(); ?>" class="thumb">
                    <?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large'); } ?>
                </a>
                <div class="body">
                    <h2 class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p class="excerpt">
                        <?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 22)); ?>
                    </p>
                    <?php if ($types && !is_wp_error($types)) : ?>
                        <div class="type">
                            <?php echo esc_html($types[0]->name); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endwhile; ?>
        </div>

        <nav class="causeway-pagination">
            <?php
            echo paginate_links([
                'total' => $wp_query->max_num_pages,
                'current' => max(1, get_query_var('paged')),
            ]);
            ?>
        </nav>
    <?php else : ?>
        <p>No listings found.</p>
    <?php endif; ?>
</main>
<?php get_footer();
