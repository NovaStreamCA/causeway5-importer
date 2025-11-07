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
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('listing-card'); ?>>
                    <a href="<?php the_permalink(); ?>" class="thumb">
                        <?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large'); } ?>
                    </a>
                    <div class="body">
                        <h2 class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p class="excerpt">
                            <?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 22)); ?>
                        </p>
                        <?php $types = get_the_terms(get_the_ID(), 'listing-type'); ?>
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
