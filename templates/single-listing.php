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
    $provider      = get_field('provider', $post_id);
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

<main id="primary" class="site-main causeway-single-listing" style="max-width:1100px;margin:40px auto;padding:0 16px;">
    <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class(); ?>>
        <header class="entry-header" style="margin-bottom:24px;">
            <h1 class="entry-title" style="margin:0 0 8px;"><?php the_title(); ?></h1>
            <div class="listing-meta" style="display:flex;flex-wrap:wrap;gap:8px;color:#666;font-size:0.9rem;">
                <?php if (!empty($types) && !is_wp_error($types)) : ?>
                    <?php foreach ($types as $t) : ?>
                        <span class="chip chip-type" style="border:1px solid #ddd;padding:4px 8px;border-radius:999px;"><?php echo esc_html($t->name); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($is_featured) : ?>
                    <span class="chip chip-featured" style="border:1px solid #ffd54f;background:#fff8e1;padding:4px 8px;border-radius:999px;color:#8a6d00;">Featured</span>
                <?php endif; ?>
                <?php if (!empty($price)) : ?>
                    <span class="chip chip-price" style="border:1px solid #ddd;padding:4px 8px;border-radius:999px;">
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
            echo '<div class="listing-hero" style="margin-bottom:24px;">' . $hero_output . '</div>';
        }
        ?>

        <div class="entry-content" style="display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start;">
            <div class="content-main">
                <?php if (!empty($highlights)) : ?>
                    <div class="highlights" style="background:#f8fafc;border:1px solid #e8eef3;border-radius:8px;padding:16px;margin-bottom:16px;">
                        <strong style="display:block;margin-bottom:8px;">Highlights</strong>
                        <div><?php echo wp_kses_post(wpautop($highlights)); ?></div>
                    </div>
                <?php endif; ?>

                <div class="the-content">
                    <?php the_content(); ?>
                </div>

                <?php if (!empty($attachments) && count($attachments) > 1) : ?>
                    <section class="gallery" style="margin-top:24px;">
                        <h2 style="font-size:1.1rem;margin:0 0 12px;">Gallery</h2>
                        <div class="gallery-grid" style="display:grid;gap:12px;grid-template-columns:repeat(3,1fr);">
                            <?php foreach ($attachments as $i => $att) : if (empty($att['url'])) continue; ?>
                                <?php if ($i === 0) continue; // skip hero already shown ?>
                                <figure style="margin:0;">
                                    <img src="<?php echo esc_url($att['url']); ?>" alt="<?php echo esc_attr($att['alt'] ?? ''); ?>" style="width:100%;height:160px;object-fit:cover;border-radius:8px;" />
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($occurrences)) : ?>
                    <section class="occurrences" style="margin-top:24px;">
                        <h2 style="font-size:1.1rem;margin:0 0 12px;">Dates</h2>
                        <?php if (!empty($next_occ)) : ?>
                            <p style="margin:0 0 8px;">Next: <strong><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($next_occ))); ?></strong></p>
                        <?php endif; ?>
                        <ul style="list-style:disc;margin:0 0 0 18px;padding:0;">
                            <?php foreach ($occurrences as $row) :
                                $start = $row['occurrence_start'] ?? '';
                                $end   = $row['occurrence_end'] ?? '';
                                if (!$start) continue;
                                $label = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start));
                                if ($end) {
                                    $label .= ' – ' . date_i18n(get_option('time_format'), strtotime($end));
                                }
                            ?>
                                <li><?php echo esc_html($label); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="sidebar" style="position:sticky;top:24px;align-self:start;">
                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <div class="sidebar-box" style="margin-bottom:16px;">
                        <h3 style="font-size:1rem;margin:0 0 8px;">Categories</h3>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($categories as $cat) : ?>
                                <a href="<?php echo esc_url(get_term_link($cat)); ?>" style="text-decoration:none;border:1px solid #eee;padding:4px 8px;border-radius:999px;color:inherit;">
                                    <?php echo esc_html($cat->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($communities) || !empty($areas) || !empty($regions)) : ?>
                    <div class="sidebar-box" style="margin-bottom:16px;">
                        <h3 style="font-size:1rem;margin:0 0 8px;">Location</h3>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ([$communities, $areas, $regions] as $group) :
                                if (empty($group) || is_wp_error($group)) continue;
                                foreach ($group as $term) : ?>
                                    <a href="<?php echo esc_url(get_term_link($term)); ?>" style="text-decoration:none;border:1px solid #eee;padding:4px 8px;border-radius:999px;color:inherit;">
                                        <?php echo esc_html($term->name); ?>
                                    </a>
                                <?php endforeach; endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($websites)) : ?>
                    <div class="sidebar-box" style="margin-bottom:16px;">
                        <h3 style="font-size:1rem;margin:0 0 8px;">Websites</h3>
                        <ul style="list-style:none;margin:0;padding:0;">
                            <?php foreach ($websites as $site) :
                                $url = $site['url'] ?? '';
                                if (!$url) continue;
                                $name = $site['name'] ?? parse_url($url, PHP_URL_HOST);
                            ?>
                                <li style="margin:0 0 6px;"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" style="text-decoration:none;color:#1a73e8;">
                                    <?php echo esc_html($name); ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="sidebar-box" style="margin-bottom:16px;">
                    <h3 style="font-size:1rem;margin:0 0 8px;">Contact</h3>
                    <ul style="list-style:none;margin:0;padding:0;color:#444;">
                        <?php if ($provider) : ?><li><strong>Provider:</strong> <?php echo esc_html($provider); ?></li><?php endif; ?>
                        <?php if ($email) : ?><li><strong>Email:</strong> <a href="mailto:<?php echo antispambot($email); ?>"><?php echo antispambot($email); ?></a></li><?php endif; ?>
                        <?php if ($phone_primary) : ?><li><strong>Phone:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_primary)); ?>"><?php echo esc_html($phone_primary); ?></a></li><?php endif; ?>
                        <?php if ($phone_second) : ?><li><strong>Phone (Secondary):</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_second)); ?>"><?php echo esc_html($phone_second); ?></a></li><?php endif; ?>
                        <?php if ($phone_off) : ?><li><strong>Phone (Offseason):</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_off)); ?>"><?php echo esc_html($phone_off); ?></a></li><?php endif; ?>
                        <?php if ($phone_toll) : ?><li><strong>Toll-free:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_toll)); ?>"><?php echo esc_html($phone_toll); ?></a></li><?php endif; ?>
                    </ul>
                </div>

                <?php if (!empty($locations)) : ?>
                    <div class="sidebar-box" style="margin-bottom:16px;">
                        <h3 style="font-size:1rem;margin:0 0 8px;">Addresses</h3>
                        <ul style="list-style:none;margin:0;padding:0;color:#444;">
                            <?php foreach ($locations as $loc) :
                                $label = trim(($loc['name'] ?? '') . ' ' . ($loc['civic_address'] ?? ''));
                                $lat   = $loc['latitude'] ?? '';
                                $lng   = $loc['longitude'] ?? '';
                                $map_q = $label ?: trim($lat . ',' . $lng);
                            ?>
                                <li style="margin:0 0 8px;">
                                    <?php echo esc_html($label ?: 'Location'); ?>
                                    <?php if ($lat && $lng) : ?>
                                        <div style="font-size:0.85rem;color:#666;">(<?php echo esc_html($lat); ?>, <?php echo esc_html($lng); ?>)</div>
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
                <section class="related-listings" style="margin-top:40px;">
                    <h2 style="font-size:1.25rem;margin:0 0 16px;">Related Listings</h2>
                    <div class="causeway-listings-grid" style="display:grid;gap:24px;grid-template-columns:repeat(3,1fr);">
                        <?php while ($rel_q->have_posts()) : $rel_q->the_post(); ?>
                            <article class="listing-card" style="border:1px solid #e5e5e5;border-radius:8px;overflow:hidden;background:#fff;">
                                <a href="<?php echo esc_url(get_permalink()); ?>" class="thumb" style="display:block;aspect-ratio:16/9;background:#f5f5f5;overflow:hidden;">
                                    <?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large', ['style' => 'width:100%;height:100%;object-fit:cover;display:block;']); } ?>
                                </a>
                                <div class="body" style="padding:16px;">
                                    <h3 class="title" style="margin:0 0 8px;font-size:1.05rem;"><a style="text-decoration:none;color:inherit;" href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h3>
                                    <p class="excerpt" style="margin:0;color:#555;font-size:0.9rem;">
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
