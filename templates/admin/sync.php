<?php
// Prevent direct access
defined('ABSPATH') || exit;

// Get statistics
$total_videos = wp_count_posts('video');
$last_sync = get_option('bunny_video_last_sync', '');
?>

<div class="wrap bunny-video-sync">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!$this->is_configured()): ?>
        <div class="bunny-video-notice notice-error">
            <p>
                <?php 
                printf(
                    __('Please <a href="%s">configure your Bunny.net API settings</a> before syncing videos.', 'bunny-video-plugin'),
                    admin_url('admin.php?page=bunny-video-manager')
                ); 
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2><?php _e('Sync Status', 'bunny-video-plugin'); ?></h2>
        <p>
            <strong><?php _e('Total Videos:', 'bunny-video-plugin'); ?></strong> 
            <?php echo esc_html($total_videos->publish); ?>
        </p>
        <p>
            <strong><?php _e('Last Sync:', 'bunny-video-plugin'); ?></strong> 
            <?php 
            if ($last_sync) {
                echo esc_html(human_time_diff(strtotime($last_sync), current_time('timestamp'))) . ' ' . __('ago', 'bunny-video-plugin');
            } else {
                _e('Never', 'bunny-video-plugin');
            }
            ?>
        </p>
    </div>

    <div class="card">
        <h2><?php _e('Manual Sync', 'bunny-video-plugin'); ?></h2>
        <p><?php _e('Click the button below to manually sync videos from your Bunny Stream library.', 'bunny-video-plugin'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('bunny_video_sync'); ?>
            <input type="submit" 
                   name="sync_videos" 
                   class="button button-primary" 
                   value="<?php esc_attr_e('Sync Videos Now', 'bunny-video-plugin'); ?>">
        </form>
    </div>

    <?php if (get_option('bunny_video_auto_sync', '1')): ?>
        <div class="card">
            <h2><?php _e('Automatic Sync', 'bunny-video-plugin'); ?></h2>
            <p>
                <?php _e('Automatic sync is enabled. The plugin will check for new videos every 15 minutes.', 'bunny-video-plugin'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=bunny-video-manager')); ?>">
                    <?php _e('Change settings', 'bunny-video-plugin'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
</style>
