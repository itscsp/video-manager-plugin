<?php
defined('ABSPATH') || exit;

class Bunny_Video_Settings {
    private $api_key;
    private $selected_library_id;
    private $stream_api_key;
    
    public function __construct() {
        // Load settings
        add_action('init', array($this, 'load_settings'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_video-manager/video-manager.php', array($this, 'add_settings_link'));

        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Setup cron for automatic video syncing
        if (!wp_next_scheduled('bunny_video_sync')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'bunny_video_sync');
        }
        add_action('bunny_video_sync', array($this, 'sync_videos'));
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_interval'));

        // Initialize AJAX handlers
        $this->init_ajax_handlers();
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Bunny Video Manager', 'bunny-video-plugin'),
            __('Bunny Videos', 'bunny-video-plugin'),
            'manage_options',
            'bunny-video-manager',
            array($this, 'render_settings_page'),
            'dashicons-video-alt3'
        );

        add_submenu_page(
            'bunny-video-manager',
            __('Settings', 'bunny-video-plugin'),
            __('Settings', 'bunny-video-plugin'),
            'manage_options',
            'bunny-video-manager',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'bunny-video-manager',
            __('Sync Videos', 'bunny-video-plugin'),
            __('Sync Videos', 'bunny-video-plugin'),
            'manage_options',
            'bunny-video-sync',
            array($this, 'render_sync_page')
        );
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form was submitted
        if (isset($_POST['submit']) && check_admin_referer('bunny_video_settings')) {
            // API Settings
            $this->api_key = sanitize_text_field($_POST['api_key']);
            $this->selected_library_id = sanitize_text_field($_POST['library_id']);
            $this->stream_api_key = sanitize_text_field($_POST['stream_api_key']);
            
            update_option('bunny_video_api_key', $this->api_key);
            update_option('bunny_video_library_id', $this->selected_library_id);
            update_option('bunny_video_stream_api_key', $this->stream_api_key);
            
            // Additional Settings
            update_option('bunny_video_pull_zone', sanitize_text_field($_POST['video_pull_zone']));
            update_option('bunny_video_auto_sync', isset($_POST['auto_sync']) ? '1' : '0');
            update_option('bunny_video_auto_thumbnails', isset($_POST['auto_thumbnails']) ? '1' : '0');
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'bunny-video-plugin') . '</p></div>';
        }

        // Render settings form
        include BUNNY_VIDEO_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render the sync page
     */
    public function render_sync_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle manual sync
        if (isset($_POST['sync_videos']) && check_admin_referer('bunny_video_sync')) {
            $this->sync_videos();
            echo '<div class="notice notice-success"><p>' . __('Videos synced successfully.', 'bunny-video-plugin') . '</p></div>';
        }

        include BUNNY_VIDEO_PLUGIN_DIR . 'templates/admin/sync.php';
    }

    /**
     * Add custom cron schedule
     */
    public function add_cron_interval($schedules) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => __('Every 15 minutes', 'bunny-video-plugin')
        );
        return $schedules;
    }

    /**
     * Sync videos from Bunny.net
     */
    public function sync_videos() {
        if (empty($this->api_key) || empty($this->selected_library_id)) {
            return;
        }

        $sync = new Bunny_Video_Sync(
            $this->api_key, 
            $this->selected_library_id, 
            $this->stream_api_key
        );

        $result = $sync->sync_videos();
        
        if ($result) {
            update_option('bunny_video_last_sync', current_time('mysql'));
        }
        
        return $result;
    }

    /**
     * Load settings from WordPress options
     */
    public function load_settings() {
        $this->api_key = get_option('bunny_video_api_key', '');
        $this->selected_library_id = get_option('bunny_video_library_id', '');
        $this->stream_api_key = get_option('bunny_video_stream_api_key', '');
    }

    /**
     * Add settings link to plugin listing
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=bunny-video-manager'),
            __('Settings', 'bunny-video-plugin')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Check if settings are configured
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->selected_library_id);
    }

    /**
     * Get API key
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Get library ID
     */
    public function get_library_id() {
        return $this->selected_library_id;
    }

    /**
     * Get stream API key
     */
    public function get_stream_api_key() {
        return $this->stream_api_key;
    }

    /**
     * Initialize AJAX handlers
     */
    private function init_ajax_handlers() {
        add_action('wp_ajax_fetch_bunny_libraries', array($this, 'ajax_fetch_libraries'));
        add_action('wp_ajax_fetch_stream_api_key', array($this, 'ajax_fetch_stream_api_key'));
    }

    /**
     * AJAX handler for fetching Bunny.net libraries
     */
    public function ajax_fetch_libraries() {
        check_ajax_referer('bunny_video_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'bunny-video-plugin')]);
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API key is required.', 'bunny-video-plugin')]);
        }

        $response = wp_remote_get('https://api.bunny.net/videolibrary', [
            'headers' => [
                'AccessKey' => $api_key,
                'accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $libraries = json_decode($body);

        if (!is_array($libraries)) {
            wp_send_json_error(['message' => __('Invalid response from Bunny.net', 'bunny-video-plugin')]);
        }

        wp_send_json_success(['libraries' => $libraries]);
    }

    /**
     * AJAX handler for fetching Stream API key
     */
    public function ajax_fetch_stream_api_key() {
        check_ajax_referer('bunny_video_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'bunny-video-plugin')]);
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        $library_id = sanitize_text_field($_POST['library_id']);

        if (empty($api_key) || empty($library_id)) {
            wp_send_json_error(['message' => __('API key and Library ID are required.', 'bunny-video-plugin')]);
        }

        $response = wp_remote_get("https://api.bunny.net/videolibrary/{$library_id}", [
            'headers' => [
                'AccessKey' => $api_key,
                'accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $library = json_decode($body);

        if (!is_object($library) || !isset($library->ApiKey)) {
            wp_send_json_error(['message' => __('Invalid response from Bunny.net', 'bunny-video-plugin')]);
        }

        wp_send_json_success([
            'streamApiKey' => $library->ApiKey,
            'pullZone' => "https://video.bunnycdn.com/library/{$library_id}"
        ]);
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our settings pages
        if (!in_array($hook, ['toplevel_page_bunny-video-manager', 'bunny-videos_page_bunny-video-sync'])) {
            return;
        }

        wp_enqueue_script(
            'bunny-video-admin',
            BUNNY_VIDEO_PLUGIN_URL . 'js/admin.js',
            array('jquery'),
            BUNNY_VIDEO_VERSION,
            true
        );

        wp_localize_script('bunny-video-admin', 'bunnyVideoSettings', array(
            'nonce' => wp_create_nonce('bunny_video_settings'),
            'selectLibrary' => __('Select a library...', 'bunny-video-plugin'),
            'errorMessage' => __('An error occurred while communicating with Bunny.net', 'bunny-video-plugin')
        ));
    }
}
