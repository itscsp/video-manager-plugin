<?php
// Prevent direct access
defined('ABSPATH') || exit;

// Get current values
$api_key = get_option('bunny_video_api_key', '');
$library_id = get_option('bunny_video_library_id', '');
$stream_api_key = get_option('bunny_video_stream_api_key', '');
?>

<div class="wrap bunny-video-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!$this->is_configured()): ?>
        <div class="bunny-video-notice notice-warning">
            <p><?php _e('Please configure your Bunny.net API settings below to start using the plugin.', 'bunny-video-plugin'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('bunny_video_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('API Key', 'bunny-video-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="api_key" 
                           name="api_key" 
                           value="<?php echo esc_attr($api_key); ?>" 
                           class="regular-text"
                           required>
                    <p class="description">
                        <?php _e('Enter your Bunny.net API key. You can find this in your Bunny.net dashboard.', 'bunny-video-plugin'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="library_id"><?php _e('Library', 'bunny-video-plugin'); ?></label>
                </th>
                <td>
                    <select id="library_id" 
                            name="library_id" 
                            class="regular-text" 
                            required>
                        <option value=""><?php _e('Select a library...', 'bunny-video-plugin'); ?></option>
                        <?php if ($library_id): ?>
                            <option value="<?php echo esc_attr($library_id); ?>" selected>
                                <?php echo esc_html(get_option('bunny_video_library_name', $library_id)); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <span class="libraries-loading" style="display:none;">
                        <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="Loading...">
                        <?php _e('Loading libraries...', 'bunny-video-plugin'); ?>
                    </span>
                    <p class="description">
                        <?php _e('Select your Bunny Stream library. Libraries will load automatically after entering your API key.', 'bunny-video-plugin'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="stream_api_key"><?php _e('Stream API Key', 'bunny-video-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="stream_api_key" 
                           name="stream_api_key" 
                           value="<?php echo esc_attr($stream_api_key); ?>" 
                           class="regular-text"
                           readonly>
                    <span class="api-key-loading" style="display:none;">
                        <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="Loading...">
                        <?php _e('Loading API key...', 'bunny-video-plugin'); ?>
                    </span>
                    <p class="description">
                        <?php _e('Your Bunny Stream API key will be loaded automatically when you select a library.', 'bunny-video-plugin'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="video_pull_zone"><?php _e('Video Pull Zone', 'bunny-video-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="video_pull_zone" 
                           name="video_pull_zone" 
                           value="<?php echo esc_attr(get_option('bunny_video_pull_zone', '')); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Enter your Bunny Stream pull zone URL (optional). Example: https://video.bunnycdn.com/play/{library_id}', 'bunny-video-plugin'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Sync Settings', 'bunny-video-plugin'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="auto_sync" 
                                   value="1" 
                                   <?php checked(get_option('bunny_video_auto_sync', '1')); ?>>
                            <?php _e('Enable automatic video syncing', 'bunny-video-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, the plugin will automatically sync videos from your Bunny Stream library every 15 minutes.', 'bunny-video-plugin'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Thumbnail Settings', 'bunny-video-plugin'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="auto_thumbnails" 
                                   value="1" 
                                   <?php checked(get_option('bunny_video_auto_thumbnails', '1')); ?>>
                            <?php _e('Automatically set video thumbnails', 'bunny-video-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, the plugin will automatically set the video thumbnail as the featured image for video posts.', 'bunny-video-plugin'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'bunny-video-plugin')); ?>
    </form>
</div>
