<?php
/**
 * Template for displaying video albums
 */

get_header(); ?>

<div class="bunny-video-album-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <header class="page-header">
        <?php if (is_tax('video_album')): ?>
            <h1 class="page-title"><?php single_term_title(); ?></h1>
            <?php the_archive_description('<div class="taxonomy-description">', '</div>'); ?>
        <?php else: ?>
            <h1 class="page-title"><?php _e('Video Albums', 'bunny-video-plugin'); ?></h1>
        <?php endif; ?>
    </header>

    <?php if (have_posts()): ?>
        <div class="video-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php while (have_posts()): the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('video-item'); ?> style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                    <?php if (has_post_thumbnail()): ?>
                        <div class="video-thumbnail">
                            <?php the_post_thumbnail('medium', array('style' => 'width: 100%; height: 200px; object-fit: cover;')); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="video-content" style="padding: 15px;">
                        <h2 class="entry-title" style="margin: 0 0 10px;">
                            <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: inherit;">
                                <?php the_title(); ?>
                            </a>
                        </h2>
                        
                        <?php if ($creator = get_post_meta(get_the_ID(), '_bvp_creator', true)): ?>
                            <div class="video-creator" style="margin-bottom: 5px;">
                                <strong>Creator:</strong> <?php echo esc_html($creator); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($duration = get_post_meta(get_the_ID(), '_bvp_duration', true)): ?>
                            <div class="video-duration">
                                <strong>Duration:</strong> <?php echo esc_html($duration); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>

    <?php else: ?>
        <p><?php _e('No videos found in this album.', 'bunny-video-plugin'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
