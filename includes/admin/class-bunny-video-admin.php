<?php
defined('ABSPATH') || exit;

class Bunny_Video_Admin {
    private $api_key;
    private $selected_library_id;
    private $stream_api_key;

    public function __construct() {
        $this->api_key = get_option('bunny_video_api_key', '');
        $this->selected_library_id = get_option('bunny_video_library_id', '');
        $this->stream_api_key = get_option('bunny_video_stream_api_key', '');
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_bunny_manual_sync', array($this, 'manual_sync'));
        add_action('wp_ajax_bunny_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_bunny_get_libraries', array($this, 'ajax_get_libraries'));
    }

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('settings_page_bunny-video-settings', 'toplevel_page_bunny-videos'))) {
            return;
        }

        wp_enqueue_style(
            'bunny-video-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            BUNNY_VIDEO_VERSION
        );

        wp_enqueue_script(
            'bunny-video-admin',
            plugins_url('js/admin.js', dirname(dirname(__FILE__))),
            array('jquery'),
            BUNNY_VIDEO_VERSION,
            true
        );

        wp_localize_script('bunny-video-admin', 'bunny_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bunny_video_nonce')
        ));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Bunny Videos',
            'Bunny Videos',
            'manage_options',
            'bunny-videos',
            array($this, 'videos_page'),
            'dashicons-video-alt3'
        );

        add_submenu_page(
            'bunny-videos',
            'Settings',
            'Settings',
            'manage_options',
            'bunny-video-settings',
            array($this, 'settings_page')
        );
    }

    public function ajax_get_libraries() {
        check_ajax_referer('bunny_video_nonce', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key']);
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }

        $libraries = $this->get_libraries($api_key);
        if (is_wp_error($libraries)) {
            wp_send_json_error($libraries->get_error_message());
        }

        wp_send_json_success($libraries);
    }

    private function get_libraries($api_key) {
        $response = wp_remote_get('https://api.bunny.net/videolibrary', array(
            'headers' => array(
                'AccessKey' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('api_error', 'API request failed: ' . $body);
        }

        return isset($data['Items']) ? $data['Items'] : array();
    }

    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('bunny_video_settings');
            
            $api_key = sanitize_text_field($_POST['api_key']);
            $library_id = sanitize_text_field($_POST['library_id']);
            
            update_option('bunny_video_api_key', $api_key);
            update_option('bunny_video_library_id', $library_id);
            
            // Get stream API key for the selected library
            $libraries = $this->get_libraries($api_key);
            if (!is_wp_error($libraries)) {
                foreach ($libraries as $library) {
                    if ($library['Id'] == $library_id) {
                        update_option('bunny_video_stream_api_key', $library['ApiKey']);
                        break;
                    }
                }
            }
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $libraries = $this->get_libraries($this->api_key);
        ?>
        <div class="wrap bunny-video-settings">
            <h1>Bunny Video Settings</h1>
            
            <form method="post" action="" id="bunny-settings-form">
                <?php wp_nonce_field('bunny_video_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Management API Key</th>
                        <td>
                            <input type="text" 
                                   name="api_key" 
                                   id="bunny-api-key"
                                   value="<?php echo esc_attr($this->api_key); ?>" 
                                   class="regular-text bunny-video-api-key" 
                                   required />
                            <p class="description">Your Bunny.net Management API key (from Account > API Keys)</p>
                            <button type="button" id="refresh-libraries" class="button">Refresh Libraries</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Video Library</th>
                        <td>
                            <select name="library_id" id="bunny-library-select" class="bunny-video-library-select" required>
                                <option value="">Select a library...</option>
                                <?php if (!is_wp_error($libraries)): ?>
                                    <?php foreach ($libraries as $library): ?>
                                        <option value="<?php echo esc_attr($library['Id']); ?>"
                                                <?php selected($this->selected_library_id, $library['Id']); ?>>
                                            <?php echo esc_html($library['Name'] . ' (ID: ' . $library['Id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description">Select your Bunny.net video library</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <div class="api-status">
                <h2>Connection Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Management API Key:</th>
                        <td><?php echo !empty($this->api_key) ? '✅ Configured' : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <th>Selected Library:</th>
                        <td><?php echo !empty($this->selected_library_id) ? '✅ Selected (ID: ' . esc_html($this->selected_library_id) . ')' : '❌ Not selected'; ?></td>
                    </tr>
                    <tr>
                        <th>Stream API Key:</th>
                        <td><?php echo !empty($this->stream_api_key) ? '✅ Configured' : '❌ Not configured'; ?></td>
                    </tr>
                </table>
                <div id="connection-test-result"></div>
            </div>
        </div>
        <?php
    }

    public function test_connection() {
        check_ajax_referer('bunny_video_nonce', 'nonce');
        
        $libraries = $this->get_libraries($this->api_key);
        
        if (is_wp_error($libraries)) {
            wp_send_json_error('Connection failed: ' . $libraries->get_error_message());
            return;
        }
        
        wp_send_json_success('Connection successful! Found ' . count($libraries) . ' video libraries.');
    }

    public function manual_sync() {
        error_log('Bunny Video: Manual sync initiated');
        
        try {
            check_ajax_referer('bunny_video_nonce', 'nonce');
            error_log('Bunny Video: Nonce verification passed');
            
            error_log('Bunny Video: Checking configuration - API Key: ' . (empty($this->api_key) ? 'Missing' : 'Present') . 
                     ', Library ID: ' . (empty($this->selected_library_id) ? 'Missing' : $this->selected_library_id));
            
            if (empty($this->api_key) || empty($this->selected_library_id)) {
                error_log('Bunny Video: Missing configuration');
                wp_send_json_error('API key and library ID must be configured in settings.');
                return;
            }
            
            error_log('Bunny Video: Creating sync manager instance');
            $sync_manager = new Bunny_Video_Sync();
            error_log('Bunny Video: Starting sync process');
            $result = $sync_manager->sync_videos();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
        } catch (Exception $e) {
            error_log('Bunny Video: Error during sync - ' . $e->getMessage());
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }
}
