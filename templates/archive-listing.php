<?php
/**
 * Archive template fallback for Listing post type.
 * Copy to your theme as archive-listing.php to override.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main class="causeway-archive causeway-archive-listing" style="max-width:1200px;margin:40px auto;padding:0 20px;">
    <header class="archive-header" style="margin-bottom:32px;">
        <h1 style="margin:0;font-size:2rem;">Listings</h1>
        <?php if (get_query_var('paged') > 1) : ?>
            <p style="color:#666;margin:8px 0 0;">Page <?php echo (int) get_query_var('paged'); ?></p>
        <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="causeway-listings-grid" style="display:grid;gap:24px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('listing-card'); ?> style="border:1px solid #e5e5e5;border-radius:8px;overflow:hidden;background:#fff;display:flex;flex-direction:column;">
                    <a href="<?php the_permalink(); ?>" class="thumb" style="display:block;aspect-ratio:16/9;background:#f5f5f5;overflow:hidden;">
                        <?php if (has_post_thumbnail()) { the_post_thumbnail('medium_large', ['style'=>'width:100%;height:100%;object-fit:cover;display:block;']); } ?>
                    </a>
                    <div class="body" style="padding:16px;flex:1;display:flex;flex-direction:column;">
                        <h2 style="margin:0 0 8px;font-size:1.1rem;"><a style="text-decoration:none;color:inherit;" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p style="margin:0 0 12px;color:#555;font-size:0.9rem;flex:0;">
                            <?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 22)); ?>
                        </p>
                        <?php $types = get_the_terms(get_the_ID(), 'listing-type'); ?>
                        <?php if ($types && !is_wp_error($types)) : ?>
                            <div style="margin-top:auto;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;color:#777;">
                                <?php echo esc_html($types[0]->name); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <nav class="pagination" style="margin-top:40px;text-align:center;">
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
