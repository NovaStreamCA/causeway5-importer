<?php
/**
 * Single template for Causeway Listing (post type: listing)
 *
 * Themes can override by copying this file to: your-theme/single-listing.php
 */

if (!defined('ABSPATH')) { exit; }

get_header();

while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // ACF fields
    $highlights    = get_field('highlights', $post_id);
    $price         = get_field('price', $post_id);
    $is_featured   = get_field('is_featured', $post_id);
    $email         = get_field('email', $post_id);
    $phone_primary = get_field('phone_primary', $post_id);
    $phone_second  = get_field('phone_secondary', $post_id);
    $phone_off     = get_field('phone_offseason', $post_id);
    $phone_toll    = get_field('phone_tollfree', $post_id);
    $websites      = get_field('websites', $post_id) ?: [];
    $attachments   = get_field('attachments', $post_id) ?: [];

    $next_occ      = get_field('next_occurrence', $post_id);
    $occurrences   = get_field('all_occurrences', $post_id) ?: [];

    $locations     = get_field('location_details', $post_id) ?: [];

    $types         = get_the_terms($post_id, 'listing-type') ?: [];
    $categories    = get_the_terms($post_id, 'listings-category') ?: [];
    $communities   = get_the_terms($post_id, 'listing-communities') ?: [];
    $areas         = get_the_terms($post_id, 'listing-areas') ?: [];
    $regions       = get_the_terms($post_id, 'listing-regions') ?: [];
?>

<main id="primary" class="site-main causeway-single-listing causeway-container">
    <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
            <div class="listing-meta">
                <?php if (!empty($types) && !is_wp_error($types)) : ?>
                    <?php foreach ($types as $t) : ?>
                        <span class="causeway-chip chip-type"><?php echo esc_html($t->name); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($is_featured) : ?>
                    <span class="causeway-chip featured">Featured</span>
                <?php endif; ?>
                <?php if (!empty($price)) : ?>
                    <span class="causeway-chip chip-price">
                        <?php echo esc_html(is_numeric($price) ? ('$' . number_format((float)$price, 2)) : (string)$price); ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>

        <?php
        // Hero media: featured image, else first attachment URL
        $hero_output = '';
        if (has_post_thumbnail($post_id)) {
            $hero_output = get_the_post_thumbnail($post_id, 'large', ['style' => 'width:100%;height:auto;border-radius:12px;display:block;']);
        } elseif (!empty($attachments) && !empty($attachments[0]['url'])) {
            $hero_output = '<img src="' . esc_url($attachments[0]['url']) . '" alt="" style="width:100%;height:auto;border-radius:12px;display:block;" />';
        }
        if ($hero_output) {
            echo '<div class="listing-hero">' . $hero_output . '</div>';
        }
        ?>

        <div class="entry-content content-layout">
            <div class="content-main">
                <?php if (!empty($highlights)) : ?>
                    <div class="highlights">
                        <strong class="highlights-heading">Highlights</strong>
                        <div><?php echo wp_kses_post(wpautop($highlights)); ?></div>
                    </div>
                <?php endif; ?>

                <div class="the-content">
                    <?php the_content(); ?>
                </div>

                <?php if (!empty($attachments) && count($attachments) > 1) : ?>
                    <section class="gallery">
                        <h2>Gallery</h2>
                        <div class="gallery-grid">
                            <?php foreach ($attachments as $i => $att) : if (empty($att['url'])) continue; ?>
                                <?php if ($i === 0) continue; // skip hero already shown ?>
                                <figure>
                                    <img src="<?php echo esc_url($att['url']); ?>" alt="<?php echo esc_attr($att['alt'] ?? ''); ?>" />
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($occurrences)) : ?>
                    <section class="occurrences">
                        <h2>Dates</h2>
                        <?php if (!empty($next_occ)) : ?>
                            <?php $next_dt = causeway_parse_occ_dt($next_occ); $tz = wp_timezone(); ?>
                            <p class="next-occurrence">Next: <strong><?php echo esc_html($next_dt ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_dt->getTimestamp(), $tz) : $next_occ); ?></strong></p>
                        <?php endif; ?>
                        <ul>
                            <?php foreach ($occurrences as $row) :
                                $start_raw = $row['occurrence_start'] ?? '';
                                $start_dt = causeway_parse_occ_dt($start_raw);
                                if (!$start_dt) continue; // skip invalid
                                $tz = isset($tz) ? $tz : wp_timezone();
                                $label = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $start_dt->getTimestamp(), $tz);
                            ?>
                                <li><?php echo esc_html($label); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="sidebar">
                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <div class="sidebar-box">
                        <h3>Categories</h3>
                        <div class="chip-group">
                            <?php foreach ($categories as $cat) : ?>
                                <a class="causeway-chip" href="<?php echo esc_url(get_term_link($cat)); ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($communities) || !empty($areas) || !empty($regions)) : ?>
                    <div class="sidebar-box">
                        <h3>Location</h3>
                        <div class="chip-group">
                            <?php foreach ([$communities, $areas, $regions] as $group) :
                                if (empty($group) || is_wp_error($group)) continue;
                                foreach ($group as $term) : ?>
                                    <a class="causeway-chip" href="<?php echo esc_url(get_term_link($term)); ?>">
                                        <?php echo esc_html($term->name); ?>
                                    </a>
                                <?php endforeach; endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($websites)) : ?>
                    <div class="sidebar-box">
                        <h3>Websites</h3>
                        <ul class="links-list">
                            <?php foreach ($websites as $site) :
                                $url = $site['url'] ?? '';
                                if (!$url) continue;
                                $name = $site['name'] ?? parse_url($url, PHP_URL_HOST);
                            ?>
                                <li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($name); ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="sidebar-box">
                    <h3>Contact</h3>
                    <ul class="contact-list">
                        <?php if ($email) : ?><li><strong>Email:</strong> <a href="mailto:<?php echo antispambot($email); ?>"><?php echo antispambot($email); ?></a></li><?php endif; ?>
                        <?php if ($phone_primary) : ?><li><strong>Phone:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_primary)); ?>"><?php echo esc_html($phone_primary); ?></a></li><?php endif; ?>
                        <?php if ($phone_second) : ?><li><strong>Phone (Secondary):</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_second)); ?>"><?php echo esc_html($phone_second); ?></a></li><?php endif; ?>
                        <?php if ($phone_off) : ?><li><strong>Phone (Offseason):</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_off)); ?>"><?php echo esc_html($phone_off); ?></a></li><?php endif; ?>
                        <?php if ($phone_toll) : ?><li><strong>Toll-free:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_toll)); ?>"><?php echo esc_html($phone_toll); ?></a></li><?php endif; ?>
                    </ul>
                </div>

                <?php if (!empty($locations)) : ?>
                    <div class="sidebar-box">
                        <h3>Addresses</h3>
                        <ul class="address-list">
                            <?php foreach ($locations as $loc) :
                                $label = trim(($loc['name'] ?? '') . ' ' . ($loc['civic_address'] ?? ''));
                                $lat   = $loc['latitude'] ?? '';
                                $lng   = $loc['longitude'] ?? '';
                                $map_q = $label ?: trim($lat . ',' . $lng);
                            ?>
                                <li>
                                    <?php echo esc_html($label ?: 'Location'); ?>
                                    <?php if ($lat && $lng) : ?>
                                        <div class="coords">(<?php echo esc_html($lat); ?>, <?php echo esc_html($lng); ?>)</div>
                                    <?php endif; ?>
                                    <div><a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($map_q); ?>">View on map</a></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </aside>
        </div>

        <?php
        // Related listings grid
        $related = get_field('related_listings', $post_id) ?: [];
        if (!empty($related)) :
            $rel_q = new WP_Query([
                'post_type' => 'listing',
                'post__in' => array_map('intval', $related),
                'orderby' => 'post__in',
                'posts_per_page' => 6,
            ]);
            if ($rel_q->have_posts()) : ?>
                <section class="related-listings">
                    <h2>Related Listings</h2>
                    <div class="causeway-listings-grid cols-3">
                        <?php while ($rel_q->have_posts()) : $rel_q->the_post(); ?>
                            <article class="listing-card">
                                <a href="<?php echo esc_url(get_permalink()); ?>" class="thumb">
                                    <?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large'); } ?>
                                </a>
                                <div class="body">
                                    <h3 class="title"><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                                    <p class="excerpt">
                                        <?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 20)); ?>
                                    </p>
                                </div>
                            </article>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; endif; ?>
    </article>
</main>

<?php endwhile; // end of the loop. ?>

<?php get_footer();
