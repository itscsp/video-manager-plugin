<?php
/**
 * Template for displaying album archive
 */

get_header(); ?>

<div class="bunny-video-albums-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <header class="page-header">
        <h1 class="page-title"><?php _e('Video Albums', 'bunny-video-plugin'); ?></h1>
    </header>

    <div class="albums-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
        <?php
        $albums = get_terms(array(
            'taxonomy' => 'video_album',
            'hide_empty' => true,
        ));

        if (!empty($albums) && !is_wp_error($albums)):
            foreach ($albums as $album):
                // Get the first video in this album to use its thumbnail
                $videos = get_posts(array(
                    'post_type' => 'video',
                    'posts_per_page' => 1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'video_album',
                            'field' => 'term_id',
                            'terms' => $album->term_id,
                        ),
                    ),
                ));
                
                $thumbnail = '';
                if (!empty($videos)) {
                    $video = $videos[0];
                    $thumbnail = get_the_post_thumbnail_url($video->ID, 'medium');
                }
                ?>
                <article class="album-item" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                    <?php if ($thumbnail): ?>
                        <div class="album-thumbnail">
                            <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($album->name); ?>"
                                 style="width: 100%; height: 200px; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    
                    <div class="album-content" style="padding: 15px;">
                        <h2 class="album-title" style="margin: 0 0 10px;">
                            <a href="<?php echo esc_url(get_term_link($album)); ?>" style="text-decoration: none; color: inherit;">
                                <?php echo esc_html($album->name); ?>
                            </a>
                        </h2>
                        
                        <div class="album-meta">
                            <span class="video-count">
                                <?php printf(_n('%s video', '%s videos', $album->count, 'bunny-video-plugin'), 
                                    number_format_i18n($album->count)); ?>
                            </span>
                        </div>
                        
                        <?php if ($album->description): ?>
                            <div class="album-description" style="margin-top: 10px;">
                                <?php echo wp_kses_post($album->description); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p><?php _e('No albums found.', 'bunny-video-plugin'); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
