<?php
/**
 * Single template for Causeway Listing (post type: listing)
 *
 * Themes can override by copying this file to: your-theme/single-listing.php
 *
 * Spotlight lightbox integration: images wrapped in anchors with data-spotlight="listing-gallery".
 * Script enqueued conditionally in plugin (see causeway.php). Remove or adjust group name to customize.
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
    $tripadvisor_rating_url = get_field('tripadvisor_rating_url', $post_id);
    $tripadvisor_url = get_field('tripadvisor_url', $post_id);
    $tripadvisor_rating = get_field('tripadvisor_rating', $post_id);
    $tripadvisor_count = get_field('tripadvisor_count', $post_id);

    $general_site  = null;
    $social_sites  = [];
    if (!empty($websites) && is_array($websites)) {
        foreach ($websites as $w) {
            $type_id = isset($w['type_id']) ? (int)$w['type_id'] : 0;
            if ($type_id === 2 && !empty($w['url'])) { // general website
                $general_site = $w;
                // don't break; still gather social links
            }
            // Collect social media links excluding general website (id 2)
            if ($type_id !== 2 && !empty($w['url'])) {
                $social_sites[] = $w;
            }
        }
    }
    $attachments   = get_field('attachments', $post_id) ?: [];

    $next_occ      = get_field('next_occurrence', $post_id);
    $occurrences   = get_field('all_occurrences', $post_id) ?: [];

    $locations     = get_field('location_details', $post_id) ?: [];

    $types         = get_the_terms($post_id, 'listing-type') ?: [];
    $categories    = get_the_terms($post_id, 'listings-category') ?: [];
    $communities   = get_the_terms($post_id, 'listing-communities') ?: [];
    $amenities       = get_the_terms($post_id, 'listings-amenities') ?: [];

    // Build Google Maps URL from first location (name, civic_address, community name, state)
    $map_url = null;
    if (!empty($locations) && is_array($locations)) {
        $loc = $locations[0];
        $parts = [];
        if (!empty($loc['name']))           { $parts[] = (string)$loc['name']; }
        if (!empty($loc['civic_address']))  { $parts[] = (string)$loc['civic_address']; }
        // Prefer per-row community term if present; else fall back to first assigned community
        $community_name = '';
        if (!empty($loc['community'])) {
            $cterm = get_term((int)$loc['community'], 'listing-communities');
            if ($cterm && !is_wp_error($cterm)) {
                $community_name = $cterm->name;
            }
        } elseif (!empty($communities) && !is_wp_error($communities)) {
            $community_name = $communities[0]->name ?? '';
        }
        if ($community_name !== '') { $parts[] = $community_name; }
        if (!empty($loc['state']))           { $parts[] = (string)$loc['state']; }

        if (!empty($parts)) {
            $query = implode(' ', array_filter($parts));
            $map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
        }
    }
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

                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <?php foreach ($categories as $cat) : ?>
                        <span class="causeway-chip chip-type">
                            <?php echo esc_html($cat->name); ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- <?php if ($is_featured) : ?>
                    <span class="causeway-chip featured">Featured</span>
                <?php endif; ?>
                <?php if (!empty($price)) : ?>
                    <span class="causeway-chip chip-price">
                        <?php echo esc_html(is_numeric($price) ? ('$' . number_format((float)$price, 2)) : (string)$price); ?>
                    </span>
                <?php endif; ?> -->
            </div>
        </header>

        <!-- Hero media -->
        <div class="hero hero-layout <?php if(empty($attachments[1]['url'])) echo 'single-image'; ?>">
            <div class="main-image">
                <?php if(!empty($attachments[0]['url'])): ?>
                <a class="img-wrap spotlight" href="<?php echo esc_url($attachments[0]['url']); ?>" data-spotlight="listing-gallery">
                    <img src="<?php echo esc_url($attachments[0]['url']); ?>" alt="Listing Image">
                </a>
                <?php endif; ?>
            </div>
            <?php if(!empty($attachments[1]['url'])): ?>
            <div class="secondary-image">
                <a class="img-wrap spotlight" href="<?php echo esc_url($attachments[1]['url']); ?>" data-spotlight="listing-gallery">
                    <img src="<?php echo esc_url($attachments[1]['url']); ?>" alt="Listing Image">
                </a>
                <?php if(!empty($attachments[2]['url'])): ?>
                <a class="img-wrap spotlight" href="<?php echo esc_url($attachments[2]['url']); ?>" data-spotlight="listing-gallery">
                    <img src="<?php echo esc_url($attachments[2]['url']); ?>" alt="Listing Image">
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="entry-content content-layout">
            <div class="content-main">

                <!-- Dates -->
                <?php
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
                            echo '<p class="dates-tagline"><svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20ZM19 8H5V6H19V8ZM9 14H7V12H9V14ZM13 14H11V12H13V14ZM17 14H15V12H17V14ZM9 18H7V16H9V18ZM13 18H11V16H13V18ZM17 18H15V16H17V18Z"/>
                            </svg>
                            ' . esc_html($range_label) . '</p>';
                        }
                    }
                ?>

                <!-- Price -->
                <?php if(!empty($price)): ?>
                    <p class="subtext">From $<?php echo esc_html($price); ?></p>
                <?php endif; ?>

                <!-- Trip Advisor -->
                <?php if($tripadvisor_rating_url): ?>
                    <a href="<?php echo $tripadvisor_url ?>" target="_blank" class="trip-advisor">
                        <p class='text'>TripAdvisor Traveler Rating</p>
                        <div class='rating'>
                            <img class='rating-svg' src="<?php echo $tripadvisor_rating_url; ?>">
                            <p class='rating-text'>Based on <?php echo $tripadvisor_count; ?> reviews</p>
                        </div>
                    </a>
                <?php endif; ?>

                <p class="sub-heading">About</p>

                <div class="the-content">
                    <?php the_content(); ?>
                </div>

                <?php if (!empty($attachments) && count($attachments) > 1) : ?>
                    <section class="gallery">
                        <p class="sub-heading" style='padding-top:1rem;'>Gallery</p>
                        <div class="gallery-grid">
                            <?php foreach ($attachments as $i => $att) : if (empty($att['url'])) continue; ?>
                                <?php if ($i === 0) continue; // skip hero already shown ?>
                                <figure>
                                    <a href="<?php echo esc_url($att['url']); ?>" class='spotlight'>
                                    <img src="<?php echo esc_url($att['url']); ?>" alt="<?php echo esc_attr($att['alt'] ?? ''); ?>" />
                                    </a>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Amenities -->
                <?php if (!empty($amenities) && !is_wp_error($amenities)) : ?>
                    <section class="amenities">
                        <p class="sub-heading" style='padding-top:1rem;'>Amenities</p>
                        <ul class="amenities-list">
                            <?php foreach ($amenities as $amenity) : 
                                $icon = get_field('amenity_icon', 'listings-amenities_' . $amenity->term_id);
                            ?>
                                <li class="amenity">
                                    <?php if($icon): ?>
                                        <img src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_html($amenity->name); ?>">
                                    <?php else: ?>
                                        <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <g clip-path="url(#clip0_1559_25076)">
                                                <path d="M8.79995 15.9L4.59995 11.7L3.19995 13.1L8.79995 18.7L20.8 6.69999L19.4 5.29999L8.79995 15.9Z"/>
                                            </g>
                                            <defs>
                                                <clipPath id="clip0_1559_25076">
                                                    <rect width="100%" height="100%" fill="currentColor"/>
                                                </clipPath>
                                            </defs>
                                        </svg>
                                    <?php endif; ?>
                                    <?php echo esc_html($amenity->name); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="sidebar">
                <!-- Buttons -->

                <div class="buttons">
                    <?php if ($general_site) : ?>
                        <a href="<?php echo esc_url($general_site['url']); ?>" target="_blank" rel="noopener" class="btn btn-primary">Visit Website</a>
                    <?php endif; ?>
                    <?php if (!empty($map_url)) : ?>
                        <a href="<?php echo esc_url($map_url); ?>" target="_blank" rel="noopener" class="btn btn-primary-outline">Get Directions</a>
                    <?php endif; ?>
                </div>

                <p class="side-sub-heading">Location & Contact</p>

                <!-- Addresses -->

                <ul class="address-list">
                    <?php foreach ($locations as $loc) :
                        $civic = isset($loc['civic_address']) ? trim((string)$loc['civic_address']) : '';
                        $state = isset($loc['state']) ? trim((string)$loc['state']) : '';
                        if ($civic === '' || $state === '') { continue; }

                        // Resolve community name from per-row taxonomy term if set; fallback to first assigned community
                        $community_name = '';
                        if (!empty($loc['community'])) {
                            $cterm = get_term((int)$loc['community'], 'listing-communities');
                            if ($cterm && !is_wp_error($cterm)) { $community_name = $cterm->name; }
                        } elseif (!empty($communities) && !is_wp_error($communities)) {
                            $community_name = $communities[0]->name ?? '';
                        }

                        $line = $civic;
                        if ($community_name !== '') { $line .= ', ' . $community_name; }
                        $line .= ', ' . $state;
                    ?>
                        <li class="location">
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12C10.9 12 10 11.1 10 10C10 8.9 10.9 8 12 8C13.1 8 14 8.9 14 10C14 11.1 13.1 12 12 12ZM18 10.2C18 6.57 15.35 4 12 4C8.65 4 6 6.57 6 10.2C6 12.54 7.95 15.64 12 19.34C16.05 15.64 18 12.54 18 10.2ZM12 2C16.2 2 20 5.22 20 10.2C20 13.52 17.33 17.45 12 22C6.67 17.45 4 13.52 4 10.2C4 5.22 7.8 2 12 2Z"/>
                            </svg>
                            <?php echo esc_html($line); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Contact List -->

                <ul class="contact-list">
                    <?php if ($phone_primary) : ?>
                        <li>
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.54 5C6.6 5.89 6.75 6.76 6.99 7.59L5.79 8.79C5.38 7.59 5.12 6.32 5.03 5H6.54ZM16.4 17.02C17.25 17.26 18.12 17.41 19 17.47V18.96C17.68 18.87 16.41 18.61 15.2 18.21L16.4 17.02ZM7.5 3H4C3.45 3 3 3.45 3 4C3 13.39 10.61 21 20 21C20.55 21 21 20.55 21 20V16.51C21 15.96 20.55 15.51 20 15.51C18.76 15.51 17.55 15.31 16.43 14.94C16.33 14.9 16.22 14.89 16.12 14.89C15.86 14.89 15.61 14.99 15.41 15.18L13.21 17.38C10.38 15.93 8.06 13.62 6.62 10.79L8.82 8.59C9.1 8.31 9.18 7.92 9.07 7.57C8.7 6.45 8.5 5.25 8.5 4C8.5 3.45 8.05 3 7.5 3Z"/>
                            </svg>
                            <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_primary)); ?>"><?php echo esc_html($phone_primary); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($phone_second) : ?>
                        <li>
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.54 5C6.6 5.89 6.75 6.76 6.99 7.59L5.79 8.79C5.38 7.59 5.12 6.32 5.03 5H6.54ZM16.4 17.02C17.25 17.26 18.12 17.41 19 17.47V18.96C17.68 18.87 16.41 18.61 15.2 18.21L16.4 17.02ZM7.5 3H4C3.45 3 3 3.45 3 4C3 13.39 10.61 21 20 21C20.55 21 21 20.55 21 20V16.51C21 15.96 20.55 15.51 20 15.51C18.76 15.51 17.55 15.31 16.43 14.94C16.33 14.9 16.22 14.89 16.12 14.89C15.86 14.89 15.61 14.99 15.41 15.18L13.21 17.38C10.38 15.93 8.06 13.62 6.62 10.79L8.82 8.59C9.1 8.31 9.18 7.92 9.07 7.57C8.7 6.45 8.5 5.25 8.5 4C8.5 3.45 8.05 3 7.5 3Z"/>
                            </svg>
                            <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_second)); ?>"><?php echo esc_html($phone_second); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($phone_off) : ?>
                        <li>
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.54 5C6.6 5.89 6.75 6.76 6.99 7.59L5.79 8.79C5.38 7.59 5.12 6.32 5.03 5H6.54ZM16.4 17.02C17.25 17.26 18.12 17.41 19 17.47V18.96C17.68 18.87 16.41 18.61 15.2 18.21L16.4 17.02ZM7.5 3H4C3.45 3 3 3.45 3 4C3 13.39 10.61 21 20 21C20.55 21 21 20.55 21 20V16.51C21 15.96 20.55 15.51 20 15.51C18.76 15.51 17.55 15.31 16.43 14.94C16.33 14.9 16.22 14.89 16.12 14.89C15.86 14.89 15.61 14.99 15.41 15.18L13.21 17.38C10.38 15.93 8.06 13.62 6.62 10.79L8.82 8.59C9.1 8.31 9.18 7.92 9.07 7.57C8.7 6.45 8.5 5.25 8.5 4C8.5 3.45 8.05 3 7.5 3Z"/>
                            </svg>
                            <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_off)); ?>"><?php echo esc_html($phone_off); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($phone_toll) : ?>
                        <li>
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.54 5C6.6 5.89 6.75 6.76 6.99 7.59L5.79 8.79C5.38 7.59 5.12 6.32 5.03 5H6.54ZM16.4 17.02C17.25 17.26 18.12 17.41 19 17.47V18.96C17.68 18.87 16.41 18.61 15.2 18.21L16.4 17.02ZM7.5 3H4C3.45 3 3 3.45 3 4C3 13.39 10.61 21 20 21C20.55 21 21 20.55 21 20V16.51C21 15.96 20.55 15.51 20 15.51C18.76 15.51 17.55 15.31 16.43 14.94C16.33 14.9 16.22 14.89 16.12 14.89C15.86 14.89 15.61 14.99 15.41 15.18L13.21 17.38C10.38 15.93 8.06 13.62 6.62 10.79L8.82 8.59C9.1 8.31 9.18 7.92 9.07 7.57C8.7 6.45 8.5 5.25 8.5 4C8.5 3.45 8.05 3 7.5 3Z"/>
                            </svg>
                            <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_toll)); ?>"><?php echo esc_html($phone_toll); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($email) : ?>
                        <li>
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 6C22 4.9 21.1 4 20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6ZM20 6L12 11L4 6H20ZM20 18H4V8L12 13L20 8V18Z"/>
                            </svg>
                            <a href="mailto:<?php echo antispambot($email); ?>"><?php echo antispambot($email); ?></a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Social Media -->

                <?php if (!empty($social_sites)) : ?>
                    <p class="side-sub-heading" style='padding-top:1rem;'>Social Media</p>
                    <ul class="links-list">
                        <?php foreach ($social_sites as $site) :
                            $url = $site['url'] ?? '';
                            if (!$url) continue;
                            $type = $site['type_id'] ?? 0;
                        ?>
                            <li>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                    <?php if($type == 1): ?>
                                        <svg width="32px" height="32px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M22 12.0372C22 6.49324 17.5234 2 12 2C6.47656 2 2 6.49324 2 12.0372C2 16.7422 5.23047 20.6944 9.58594 21.7804V15.1033H7.52344V12.0372H9.58594V10.7159C9.58594 7.30092 11.125 5.71692 14.4688 5.71692C15.1016 5.71692 16.1953 5.84238 16.6445 5.96785V8.74378C16.4102 8.72025 16 8.70457 15.4883 8.70457C13.8477 8.70457 13.2148 9.32797 13.2148 10.9473V12.0372H16.4805L15.918 15.1033H13.2109V22C18.1641 21.4001 22 17.1696 22 12.0372Z"/>
                                        </svg>
                                    <?php elseif($type == 3): ?>
                                        <svg width="32px" height="32px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.0022 6.87225C9.16453 6.87225 6.87563 9.16166 6.87563 12C6.87563 14.8383 9.16453 17.1277 12.0022 17.1277C14.8399 17.1277 17.1288 14.8383 17.1288 12C17.1288 9.16166 14.8399 6.87225 12.0022 6.87225ZM12.0022 15.3337C10.1684 15.3337 8.66927 13.8387 8.66927 12C8.66927 10.1613 10.164 8.6663 12.0022 8.6663C13.8405 8.6663 15.3352 10.1613 15.3352 12C15.3352 13.8387 13.836 15.3337 12.0022 15.3337ZM18.5343 6.6625C18.5343 7.32746 17.9989 7.85853 17.3385 7.85853C16.6737 7.85853 16.1428 7.32299 16.1428 6.6625C16.1428 6.00201 16.6782 5.46647 17.3385 5.46647C17.9989 5.46647 18.5343 6.00201 18.5343 6.6625ZM21.9297 7.87638C21.8539 6.27424 21.488 4.85507 20.3146 3.68582C19.1456 2.51657 17.7267 2.15062 16.1249 2.07029C14.4741 1.97657 9.52593 1.97657 7.87507 2.07029C6.27775 2.14616 4.8589 2.51211 3.68544 3.68136C2.51199 4.85061 2.15059 6.26978 2.07027 7.87192C1.97658 9.52315 1.97658 14.4724 2.07027 16.1236C2.14612 17.7258 2.51199 19.1449 3.68544 20.3142C4.8589 21.4834 6.27328 21.8494 7.87507 21.9297C9.52593 22.0234 14.4741 22.0234 16.1249 21.9297C17.7267 21.8538 19.1456 21.4879 20.3146 20.3142C21.4835 19.1449 21.8494 17.7258 21.9297 16.1236C22.0234 14.4724 22.0234 9.52761 21.9297 7.87638ZM19.797 17.8953C19.449 18.7701 18.7752 19.4439 17.8963 19.7965C16.58 20.3186 13.4568 20.1981 12.0022 20.1981C10.5477 20.1981 7.41997 20.3142 6.1082 19.7965C5.23369 19.4484 4.55996 18.7745 4.20747 17.8953C3.68544 16.5788 3.80591 13.4549 3.80591 12C3.80591 10.5451 3.68991 7.41671 4.20747 6.10465C4.55549 5.22995 5.22922 4.55606 6.1082 4.2035C7.42443 3.68136 10.5477 3.80185 12.0022 3.80185C13.4568 3.80185 16.5845 3.68582 17.8963 4.2035C18.7708 4.5516 19.4445 5.22548 19.797 6.10465C20.319 7.42118 20.1985 10.5451 20.1985 12C20.1985 13.4549 20.319 16.5833 19.797 17.8953Z"/>
                                        </svg>
                                    <?php elseif($type == 4): ?>
                                        <svg width="32px" height="32px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M15.4992 2H12.145V15.6232C12.145 17.2464 10.855 18.5797 9.24958 18.5797C7.64418 18.5797 6.35413 17.2464 6.35413 15.6232C6.35413 14.029 7.61552 12.7246 9.1636 12.6667V9.24639C5.75211 9.30433 3 12.1159 3 15.6232C3 19.1594 5.80944 22 9.27826 22C12.747 22 15.5565 19.1304 15.5565 15.6232V8.63767C16.8179 9.56522 18.3659 10.1159 20 10.1449V6.72464C17.4773 6.63768 15.4992 4.55072 15.4992 2Z"/>
                                        </svg>
                                    <?php elseif($type == 5): ?>
                                        <svg width="32px" height="32px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M17.7497 2.96045H20.8179L14.1165 10.618L22 21.0395H15.8288L10.9917 14.7206L5.46371 21.0395H2.39113L9.55758 12.8475L2 2.96045H8.32768L12.6953 8.7362L17.7497 2.96045ZM16.6719 19.2056H18.3711L7.402 4.69882H5.57671L16.6719 19.2056Z" fill="currentColor"/>
                                        </svg>
                                    <?php elseif($type == 6): ?>
                                        <svg width="32px" height="32px" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M22.5408 6.34766C22.2871 5.42187 21.5419 4.69531 20.5954 4.44922C18.8795 4 12 4 12 4C12 4 5.12047 4 3.40461 4.44922C2.45807 4.69531 1.71293 5.42187 1.45917 6.34766C1 8.02344 1 11.5156 1 11.5156C1 11.5156 1 15.0078 1.45917 16.6836C1.71293 17.6094 2.45807 18.3047 3.40461 18.5508C5.12047 19 12 19 12 19C12 19 18.8795 19 20.5954 18.5508C21.5419 18.3047 22.2871 17.6055 22.5408 16.6836C23 15.0078 23 11.5156 23 11.5156C23 11.5156 23 8.02344 22.5408 6.34766ZM9.75247 14.6875V8.34375L15.5002 11.5156L9.75247 14.6875Z"/>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Date List -->
                 <?php if (count($occurrences) > 1) : ?>
                    <section class="occurrences">
                        <p class="side-sub-heading">All Dates</p>
                        <div class='dates-list' id='<?php echo esc_attr("dates-list-" . $post_id); ?>'>
                            <?php 
                            // Setup counts and comparison helpers
                            $visible_count = 6;
                            $total_count   = is_array($occurrences) ? count($occurrences) : 0;
                            $has_more      = $total_count > $visible_count;
                            $remaining     = max(0, $total_count - $visible_count);
                            $list_uid      = 'dates-list-' . $post_id;

                            // Parse next occurrence once for comparison
                            $next_dt_cmp = !empty($next_occ) ? causeway_parse_occ_dt($next_occ) : null;

                            foreach ($occurrences as $i => $row) :
                                if ($i === $visible_count && $has_more) {
                                    echo '<div class="dates-more" id="' . esc_attr($list_uid . '-more') . '" style="display:none">';
                                }
                                $start_raw = $row['occurrence_start'] ?? '';
                                $start_dt  = causeway_parse_occ_dt($start_raw);
                                if (!$start_dt) continue; // skip invalid
                                $tz     = isset($tz) ? $tz : wp_timezone();
                                $label  = wp_date('M j g:i A', $start_dt->getTimestamp(), $tz);
                                $is_next = ($next_dt_cmp && $start_dt && $start_dt->getTimestamp() === $next_dt_cmp->getTimestamp());
                                $classes = 'date-item' . ($is_next ? ' next' : '');
                            ?>
                                <span class='<?php echo esc_attr($classes); ?>'><?php echo esc_html($label); ?></span>
                            <?php endforeach; ?>
                            <?php if ($has_more) echo '</div>'; // close .dates-more when present ?>
                        </div>
                        <?php if ($has_more) : ?>
                            <button type="button"
                                    class="dates-toggle"
                                    data-target="<?php echo esc_attr($list_uid . '-more'); ?>"
                                    data-remaining="<?php echo esc_attr((string)$remaining); ?>"
                                    aria-expanded="false"
                                    aria-controls="<?php echo esc_attr($list_uid . '-more'); ?>">
                                <?php echo esc_html('+ ' . $remaining . ' more'); ?>
                            </button>
                            <noscript>
                                <style>#<?php echo esc_attr($list_uid); ?> .dates-more{display:flex !important;}</style>
                            </noscript>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </article>
</main>

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
        <section class="related-listings extra-listings">
            <p class="section-title">Related Listings</p>
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

<script>
    // Used for the show more/less toggle on date lists
(function(){
    if (window.__causewayDatesToggleInit) return;
    window.__causewayDatesToggleInit = true;
    document.addEventListener('click', function(e){
        var btn = e.target.closest && e.target.closest('.dates-toggle');
        if (!btn) return;
        var id = btn.getAttribute('data-target');
        var more = document.getElementById(id);
        if (!more) return;
        var isHidden = more.style.display === '' || more.style.display === 'none';
        if (isHidden) {
            more.style.display = 'flex';
            btn.setAttribute('aria-expanded','true');
            btn.textContent = 'Show less';
        } else {
            more.style.display = 'none';
            btn.setAttribute('aria-expanded','false');
            var remaining = btn.getAttribute('data-remaining') || '';
            btn.textContent = remaining ? ('+ ' + remaining + ' more') : '+ more';
        }
    });
})();
</script>

<?php endwhile; // end of the loop. ?>

<?php get_footer();
