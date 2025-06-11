<?php
/**
 * Plugin Name: Bunny Video Plugin
 * Plugin URI: https://yoursite.com/bunny-video-plugin
 * Description: Automatically creates WordPress posts from Bunny.net videos with automated syncing and custom post types.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: bunny-video-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BUNNY_VIDEO_VERSION', '1.0.0');
define('BUNNY_VIDEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUNNY_VIDEO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/admin/class-bunny-video-admin.php';
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/admin/class-bunny-video-sync.php';

class Bunny_Video_Plugin {
    
    private $api_key;
    private $selected_library_id;
    private $stream_api_key;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

    }
    
    public function init() {
        // Register custom post type
        $this->register_video_post_type();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add custom rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Handle template redirect
        add_action('template_redirect', array($this, 'handle_video_template'));
        
        // Setup cron for automatic video syncing (every 15 minutes)
        if (!wp_next_scheduled('bunny_video_sync')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'bunny_video_sync');
        }
        add_action('bunny_video_sync', array($this, 'sync_videos'));
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        
        // Load settings
        $this->api_key = get_option('bunny_video_api_key', '');
        $this->selected_library_id = get_option('bunny_video_library_id', '');
        $this->stream_api_key = get_option('bunny_video_stream_api_key', '');
        
        // Add AJAX handlers
        add_action('wp_ajax_bunny_manual_sync', array($this, 'manual_sync'));
        add_action('wp_ajax_bunny_test_connection', array($this, 'test_connection'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function activate() {
        $this->register_video_post_type();
        flush_rewrite_rules();
        
        // Schedule cron job for every 15 minutes
        if (!wp_next_scheduled('bunny_video_sync')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'bunny_video_sync');
        }
    }
    
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('bunny_video_sync');
        flush_rewrite_rules();
    }
    
    public function add_cron_interval($schedules) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 15 * 60, // 15 minutes in seconds
            'display'  => esc_html__('Every 15 Minutes', 'bunny-video-plugin')
        );
        return $schedules;
    }

    public function register_video_post_type() {
        $args = array(
            'public' => true,
            'label' => 'Videos',
            'labels' => array(
                'name' => 'Videos',
                'singular_name' => 'Video',
                'add_new' => 'Add New Video',
                'add_new_item' => 'Add New Video',
                'edit_item' => 'Edit Video',
                'new_item' => 'New Video',
                'view_item' => 'View Video',
                'search_items' => 'Search Videos',
                'not_found' => 'No videos found',
                'not_found_in_trash' => 'No videos found in trash'
            ),
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'rewrite' => array('slug' => 'video', 'with_front' => false),
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-video-alt3'
        );
        register_post_type('video', $args);
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^video/([^/]+)/?$',
            'index.php?post_type=video&video_id=$matches[1]',
            'top'
        );
        add_rewrite_tag('%video_id%', '([^&]+)');
    }
    
    public function handle_video_template() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['video_id'])) {
            $video_id = sanitize_text_field($wp_query->query_vars['video_id']);
            
            // Find post by video_id meta
            $posts = get_posts(array(
                'post_type' => 'video',
                'meta_key' => '_bvp_guid',
                'meta_value' => $video_id,
                'posts_per_page' => 1
            ));
            
            if (!empty($posts)) {
                global $post;
                $post = $posts[0];
                setup_postdata($post);
                
                // Load custom template
                $this->load_video_template();
                exit;
            } else {
                wp_redirect(home_url('/404'));
                exit;
            }
        }
    }
    
    public function load_video_template() {
        global $post;
        
        $library_id = get_post_meta($post->ID, '_bvp_library_id', true);
        $video_guid = get_post_meta($post->ID, '_bvp_guid', true);
        $thumbnail_url = "https://thumbnail.bunnycdn.com/{$library_id}/{$video_guid}.jpg";
        $embed_url = "https://iframe.mediadelivery.net/embed/{$library_id}/{$video_guid}";
        
        // Get suggested videos
        $suggested_videos = $this->get_suggested_videos($post->ID);
        
        get_header();
        ?>
        <div class="bunny-video-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
            <article class="bunny-video-post">
                <header>
                    <h1 class="bunny-video-title"><?php echo esc_html($post->post_title); ?></h1>
                </header>
                
                <div class="bunny-video-player" style="position: relative; padding-bottom: 56.25%; height: 0; margin-bottom: 30px;">
                    <iframe 
                        src="<?php echo esc_url($embed_url); ?>" 
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                        allowfullscreen>
                    </iframe>
                </div>
                
                <?php if (!empty($post->post_content)): ?>
                <div class="bunny-video-description" style="margin-bottom: 30px;">
                    <?php echo wpautop($post->post_content); ?>
                </div>
                <?php endif; ?>
                
                <div class="bunny-suggested-videos">
                    <h3>Suggested Videos</h3>
                    <div class="suggested-videos-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <?php foreach ($suggested_videos as $suggested): ?>
                        <div class="suggested-video-item" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                            <a href="<?php echo home_url('/video/' . get_post_meta($suggested->ID, '_bvp_guid', true)); ?>" style="text-decoration: none; color: inherit;">
                                <img src="<?php echo esc_url("https://thumbnail.bunnycdn.com/" . get_post_meta($suggested->ID, '_bvp_library_id', true) . "/" . get_post_meta($suggested->ID, '_bvp_guid', true) . ".jpg"); ?>" 
                                     alt="<?php echo esc_attr($suggested->post_title); ?>"
                                     style="width: 100%; height: 140px; object-fit: cover;">
                                <div style="padding: 15px;">
                                    <h4 style="margin: 0; font-size: 14px; line-height: 1.4;"><?php echo esc_html($suggested->post_title); ?></h4>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        </div>
        <?php
        get_footer();
    }
    
    public function get_suggested_videos($current_post_id, $limit = 5) {
        return get_posts(array(
            'post_type' => 'video',
            'posts_per_page' => $limit,
            'post__not_in' => array($current_post_id),
            'orderby' => 'rand'
        ));
    }
    
    public function add_admin_menu() {
        // Add main menu item for Videos
        add_menu_page(
            'Bunny Videos',
            'Bunny Videos',
            'manage_options',
            'bunny-videos',
            array($this, 'videos_page'),
            'dashicons-video-alt3'
        );

        // Add submenu items
        add_submenu_page(
            'bunny-videos',
            'Add New Video',
            'Add New Video',
            'manage_options',
            'bunny-video-new',
            array($this, 'add_video_page')
        );

        // Add settings as submenu
        add_submenu_page(
            'bunny-videos',
            'Bunny Video Settings',
            'Settings',
            'manage_options',
            'bunny-video-settings',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // Load scripts on both settings page and main videos page
        if ($hook !== 'settings_page_bunny-video-settings' && $hook !== 'toplevel_page_bunny-videos') {
            return;
        }
        
        // Debug: Check if we're on the right page
        error_log('Bunny Video Plugin: Enqueuing admin scripts on hook: ' . $hook);
        
        wp_enqueue_script('bunny-video-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.0.0', true);
        wp_localize_script('bunny-video-admin', 'bunny_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bunny_video_nonce')
        ));
        
        // Debug: Log the AJAX object
        error_log('Bunny Video Plugin: AJAX URL: ' . admin_url('admin-ajax.php'));
        error_log('Bunny Video Plugin: Nonce: ' . wp_create_nonce('bunny_video_nonce'));
    }
    

public function admin_page() {
    if (isset($_POST['submit'])) {
        check_admin_referer('bunny_video_settings');
        
        // Save all settings
        $main_api_key = sanitize_text_field($_POST['api_key']);
        $library_id = sanitize_text_field($_POST['library_id']);
        
        update_option('bunny_video_api_key', $main_api_key);
        update_option('bunny_video_library_id', $library_id);
        
        // Get stream API key from library info
        $libraries = $this->get_video_libraries();
        if (!is_wp_error($libraries)) {
            foreach ($libraries as $library) {
                if ($library['Id'] == $library_id) {
                    update_option('bunny_video_stream_api_key', $library['ApiKey']);
                    $this->stream_api_key = $library['ApiKey'];
                    break;
                }
            }
        }
        
        $this->api_key = $main_api_key;
        $this->selected_library_id = $library_id;
        
        echo '<div class="notice notice-success"><p>Settings saved! Stream API key automatically configured.</p></div>';
    }
    
    // Get libraries for dropdown
    $libraries = $this->get_video_libraries();
    ?>
    <div class="wrap">
        <h1>Bunny Video Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('bunny_video_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">API Key</th>
                    <td>
                        <input type="text" name="api_key" value="<?php echo esc_attr($this->api_key); ?>" class="regular-text" required />
                        <p class="description">Your Bunny.net Management API key (from Account > API Keys)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Video Library</th>
                    <td>
                        <div class="library-select-container">
                            <select name="library_id" id="bunny-library-select" class="regular-text" required>
                                <option value="">Select a library...</option>
                                <?php if ($libraries && !is_wp_error($libraries)): ?>
                                    <?php foreach ($libraries as $library): ?>
                                        <option value="<?php echo esc_attr($library['Id']); ?>" 
                                                <?php selected($this->selected_library_id, $library['Id']); ?>>
                                            <?php echo esc_html($library['Name'] . ' (ID: ' . $library['Id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" id="refresh-libraries" class="button">Refresh Libraries</button>
                        </div>
                        <p class="description">Stream API key will be automatically configured when you select a library</p>
                        <?php if (is_wp_error($libraries)): ?>
                            <div class="notice notice-error inline">
                                <p><?php echo esc_html($libraries->get_error_message()); ?></p>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2>Connection Status</h2>
        <table class="form-table">
            <tr>
                <th>Management API Key:</th>
                <td><?php echo !empty($this->api_key) ? '✅ Configured' : '❌ Not configured'; ?></td>
            </tr>
            <tr>
                <th>Stream API Key:</th>
                <td><?php echo !empty($this->stream_api_key) ? '✅ Configured' : '❌ Not configured'; ?></td>
            </tr>
            <tr>
                <th>Selected Library:</th>
                <td><?php echo !empty($this->selected_library_id) ? '✅ Selected (ID: ' . esc_html($this->selected_library_id) . ')' : '❌ Not selected'; ?></td>
            </tr>
        </table>
        
        <!-- Rest of your existing admin page code -->
    </div>
    <?php
}
    public function test_connection() {
        // Add error logging
        error_log('Bunny Video Plugin: test_connection called');
        
        check_ajax_referer('bunny_video_nonce', 'nonce');
        
        // Reload API key in case it was just saved
        $this->api_key = get_option('bunny_video_api_key', '');
        
        if (empty($this->api_key)) {
            error_log('Bunny Video Plugin: API key is empty');
            wp_send_json_error('API key is required. Please save your settings first.');
            return;
        }
        
        error_log('Bunny Video Plugin: Testing connection with API key: ' . substr($this->api_key, 0, 10) . '...');
        
        $libraries = $this->get_video_libraries();
        
        if (is_wp_error($libraries)) {
            $error_message = 'Connection failed: ' . $libraries->get_error_message();
            error_log('Bunny Video Plugin: ' . $error_message);
            wp_send_json_error($error_message);
            return;
        }
        
        $success_message = 'Connection successful! Found ' . count($libraries) . ' video libraries.';
        error_log('Bunny Video Plugin: ' . $success_message);
        wp_send_json_success($success_message);
    }
    
    public function manual_sync() {
        error_log('Bunny Video Plugin: manual_sync called');
        
        check_ajax_referer('bunny_video_nonce', 'nonce');
        
        // Reload settings in case they were just saved
        $this->api_key = get_option('bunny_video_api_key', '');
        $this->selected_library_id = get_option('bunny_video_library_id', '');
        
        $result = $this->sync_videos();
        
        if (is_wp_error($result)) {
            $error_message = 'Sync failed: ' . $result->get_error_message();
            error_log('Bunny Video Plugin: ' . $error_message);
            wp_send_json_error($error_message);
            return;
        }
        
        error_log('Bunny Video Plugin: Sync completed - ' . $result);
        wp_send_json_success($result);
    }
public function get_video_libraries() {
    if (empty($this->api_key)) {
        return new WP_Error('no_api_key', 'API key not configured');
    }
    
    $response = wp_remote_get('https://api.bunny.net/videolibrary', array(
        'headers' => array(
            'AccessKey' => $this->api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Bunny Video Plugin: Library API Error: ' . $response->get_error_message());
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    error_log('Bunny Video Plugin: Library API Response: ' . $body);
    
    if ($status_code !== 200) {
        return new WP_Error('api_error', 'API request failed: ' . $body);
    }
    
    // The API returns a direct array, not an object with Items
    return is_array($data) ? $data : array();
}


public function get_library_videos($library_id, $page = 1, $per_page = 100) {
    if (empty($this->stream_api_key)) {
        // Try to get it from the library info
        $libraries = $this->get_video_libraries();
        if (!is_wp_error($libraries)) {
            foreach ($libraries as $library) {
                if ($library['Id'] == $library_id) {
                    $this->stream_api_key = $library['ApiKey'];
                    update_option('bunny_video_stream_api_key', $library['ApiKey']);
                    break;
                }
            }
        }
        
        if (empty($this->stream_api_key)) {
            error_log('Bunny Video Plugin: Stream API key not found');
            return new WP_Error('missing_stream_api', 'Stream API key could not be retrieved');
        }
    }

    $url = "https://video.bunnycdn.com/library/{$library_id}/videos?page={$page}&itemsPerPage={$per_page}&orderBy=date";
    error_log('Bunny Video Plugin: Fetching videos from: ' . $url);

    $response = wp_remote_get($url, array(
        'headers' => array(
            'AccessKey' => $this->stream_api_key,
            'accept' => 'application/json'
        ),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        error_log('Bunny Video Plugin: API Error: ' . $response->get_error_message());
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
        error_log('Bunny Video Plugin: API Error: ' . $body);
        return new WP_Error('api_error', 'API request failed: ' . $body);
    }

    $data = json_decode($body, true);
    error_log('Bunny Video Plugin: Found ' . count($data['items']) . ' videos');
    return $data;
}
    
    public function sync_videos() {
        if (empty($this->api_key) || empty($this->selected_library_id)) {
            error_log('Bunny Video Plugin: Missing API key or library ID');
            return new WP_Error('missing_config', 'API key and library ID must be configured');
        }
        
        error_log('Bunny Video Plugin: Starting video sync...');
        
        $page = 1;
        $total_created = 0;
        $total_updated = 0;
        $processed_guids = array();
        
        do {
            $videos_data = $this->get_library_videos($this->selected_library_id, $page);
            
            if (is_wp_error($videos_data)) {
                error_log('Bunny Video Plugin: Error fetching videos: ' . $videos_data->get_error_message());
                return $videos_data;
            }
            
            $videos = isset($videos_data['items']) ? $videos_data['items'] : array();
            
            foreach ($videos as $video) {
                if (!isset($video['guid'])) {
                    error_log('Bunny Video Plugin: Invalid video data - missing GUID');
                    continue;
                }
                
                $processed_guids[] = $video['guid'];
                $result = $this->create_or_update_video_post($video);
                
                if ($result['created']) {
                    $total_created++;
                    error_log('Bunny Video Plugin: Created new post for video ' . $video['guid']);
                } elseif ($result['updated']) {
                    $total_updated++;
                    error_log('Bunny Video Plugin: Updated post for video ' . $video['guid']);
                }
            }
            
            $page++;
            $has_more = isset($videos_data['totalItems']) && 
                       isset($videos_data['currentPage']) && 
                       isset($videos_data['itemsPerPage']) &&
                       ($videos_data['currentPage'] * $videos_data['itemsPerPage']) < $videos_data['totalItems'];
            
        } while ($has_more && !empty($videos));
        
        // Clean up deleted videos
        $this->cleanup_deleted_videos($processed_guids);
        
        $last_sync = current_time('mysql');
        update_option('bunny_video_last_sync', $last_sync);
        
        $result = "Sync completed at {$last_sync}! Created: {$total_created}, Updated: {$total_updated}";
        error_log('Bunny Video Plugin: ' . $result);
        return $result;
    }
    
    public function create_or_update_video_post($video) {
        $guid = $video['guid'];
        $title = !empty($video['title']) ? $video['title'] : $guid;
        
        // Check if post already exists
        $existing_posts = get_posts(array(
            'post_type' => 'video',
            'meta_key' => '_bvp_guid',
            'meta_value' => $guid,
            'posts_per_page' => 1
        ));
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => isset($video['summary']) ? $video['summary'] : '',
            'post_status' => 'publish',
            'post_type' => 'video'
        );
        
        if (!empty($existing_posts)) {
            // Update existing post
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
            $updated = true;
            $created = false;
        } else {
            // Create new post
            $post_id = wp_insert_post($post_data);
            $updated = false;
            $created = true;
        }
        
        if (!is_wp_error($post_id) && $post_id > 0) {
            // Update meta fields
            update_post_meta($post_id, '_bvp_guid', $guid);
            update_post_meta($post_id, '_bvp_library_id', $this->selected_library_id);
            update_post_meta($post_id, '_bvp_video_data', $video);
            
            // Set thumbnail if available
            $thumbnail_url = "https://thumbnail.bunnycdn.com/{$this->selected_library_id}/{$guid}.jpg";
            $this->set_post_thumbnail_from_url($post_id, $thumbnail_url);
        }
        
        return array('created' => $created, 'updated' => $updated, 'post_id' => $post_id);
    }
    
    public function set_post_thumbnail_from_url($post_id, $image_url) {
        // Check if thumbnail already exists
        if (has_post_thumbnail($post_id)) {
            return;
        }
        
        // Download image
        $image_data = wp_remote_get($image_url);
        if (is_wp_error($image_data) || wp_remote_retrieve_response_code($image_data) !== 200) {
            return;
        }
        
        $image_content = wp_remote_retrieve_body($image_data);
        $filename = sanitize_file_name(get_post_meta($post_id, '_bvp_guid', true) . '.jpg');
        
        // Upload to media library
        $upload = wp_upload_bits($filename, null, $image_content);
        
        if (!$upload['error']) {
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title' => get_the_title($post_id),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            
            if (!is_wp_error($attach_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                set_post_thumbnail($post_id, $attach_id);
            }
        }
    }
    
    public function videos_page() {
        // Get current video posts
        $videos = get_posts(array(
            'post_type' => 'video',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Videos</h1>
            <a href="<?php echo admin_url('admin.php?page=bunny-video-new'); ?>" class="page-title-action">Add New</a>
            <button id="sync-bunny-videos" class="page-title-action">Sync Videos from Bunny.net</button>
            <div id="sync-status" style="display: none; margin-top: 10px;" class="notice"></div>
            
            <hr class="wp-header-end">
            
            <?php if (!empty($videos)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Thumbnail</th>
                        <th>GUID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video): ?>
                    <?php 
                        $guid = get_post_meta($video->ID, '_bvp_guid', true);
                        $library_id = get_post_meta($video->ID, '_bvp_library_id', true);
                        $embed_url = "https://iframe.mediadelivery.net/embed/{$library_id}/{$guid}";
                        $thumbnail_url = "https://thumbnail.bunnycdn.com/{$library_id}/{$guid}.jpg";
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($video->post_title); ?></strong>
                        </td>
                        <td>
                            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="Thumbnail" style="max-width: 100px; height: auto;">
                        </td>
                        <td><?php echo esc_html($guid); ?></td>
                        <td>
                            <a href="<?php echo get_permalink($video->ID); ?>" target="_blank">View</a> |
                            <a href="<?php echo get_edit_post_link($video->ID); ?>">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No videos found. <a href="<?php echo admin_url('admin.php?page=bunny-video-new'); ?>">Add your first video</a>.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function add_video_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('bunny_add_video');
            
            $title = sanitize_text_field($_POST['title']);
            $guid = sanitize_text_field($_POST['guid']);
            $description = wp_kses_post($_POST['description']);
            
            // Create post
            $post_data = array(
                'post_title' => $title,
                'post_content' => $description,
                'post_status' => 'publish',
                'post_type' => 'video'
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (!is_wp_error($post_id)) {
                // Save video meta
                update_post_meta($post_id, '_bvp_guid', $guid);
                update_post_meta($post_id, '_bvp_library_id', $this->selected_library_id);
                
                // Set thumbnail
                $thumbnail_url = "https://thumbnail.bunnycdn.com/{$this->selected_library_id}/{$guid}.jpg";
                $this->set_post_thumbnail_from_url($post_id, $thumbnail_url);
                
                echo '<div class="notice notice-success"><p>Video added successfully! <a href="' . get_permalink($post_id) . '" target="_blank">View video</a></p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Add New Video</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('bunny_add_video'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Title</th>
                        <td>
                            <input type="text" name="title" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Video GUID</th>
                        <td>
                            <input type="text" name="guid" class="regular-text" required />
                            <p class="description">Enter the video GUID from Bunny.net (e.g., 12a3b456-c7d8-90e1-f234-gh5678901ij)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Description</th>
                        <td>
                            <?php 
                            wp_editor('', 'description', array(
                                'media_buttons' => true,
                                'textarea_rows' => 10
                            ));
                            ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Add Video'); ?>
            </form>
        </div>
        <?php
    }
}

// Helper function to check if we're in the admin area
function is_admin_page() {
    return is_admin();
}

// Initialize the plugin
$bunny_video_plugin = new Bunny_Video_Plugin();

// Add custom query vars
function bunny_video_add_query_vars($vars) {
    $vars[] = 'video_id';
    return $vars;
}
add_filter('query_vars', 'bunny_video_add_query_vars');

// Flush rewrite rules on plugin activation
function bunny_video_flush_rewrites() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bunny_video_flush_rewrites');
?>