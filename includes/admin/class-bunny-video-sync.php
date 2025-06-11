<?php
defined('ABSPATH') || exit;

class Bunny_Video_Sync {
    private $api_key;
    private $selected_library_id;
    private $stream_api_key;

    public function __construct() {
        $this->api_key = get_option('bunny_video_api_key', '');
        $this->selected_library_id = get_option('bunny_video_library_id', '');
        $this->stream_api_key = get_option('bunny_video_stream_api_key', '');
    }

    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }

    public function set_library_id($library_id) {
        $this->selected_library_id = $library_id;
    }

    public function set_stream_api_key($stream_api_key) {
        $this->stream_api_key = $stream_api_key;
    }

    public function sync_videos() {
        error_log('Bunny Video Sync: Starting sync process');
        
        if (empty($this->api_key) || empty($this->selected_library_id)) {
            error_log('Bunny Video Sync: Missing configuration - API Key or Library ID');
            return new WP_Error('missing_config', 'API key and library ID must be configured');
        }
        
        error_log('Bunny Video Sync: Configuration validated. Library ID: ' . $this->selected_library_id);
        $page = 1;
        $total_created = 0;
        $total_updated = 0;
        $processed_guids = array();
        
        do {
            error_log('Bunny Video Sync: Fetching videos from page ' . $page);
            $videos_data = $this->get_library_videos($this->selected_library_id, $page);
            
            if (is_wp_error($videos_data)) {
                error_log('Bunny Video Sync: Error fetching videos - ' . $videos_data->get_error_message());
                return $videos_data;
            }
            
            $videos = isset($videos_data['items']) ? $videos_data['items'] : array();
            error_log('Bunny Video Sync: Found ' . count($videos) . ' videos on page ' . $page);
            
            foreach ($videos as $video) {
                if (!isset($video['guid'])) {
                    continue;
                }
                
                $processed_guids[] = $video['guid'];
                $result = $this->create_or_update_video_post($video);
                
                if ($result['created']) {
                    $total_created++;
                } elseif ($result['updated']) {
                    $total_updated++;
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
        
        return "Sync completed at {$last_sync}! Created: {$total_created}, Updated: {$total_updated}";
    }

    private function get_library_videos($library_id, $page = 1, $per_page = 100) {
        if (empty($this->stream_api_key)) {
            // Try to get it from the library info
            $response = wp_remote_get('https://api.bunny.net/videolibrary', array(
                'headers' => array(
                    'AccessKey' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (is_array($data)) {
                    foreach ($data as $library) {
                        if ($library['Id'] == $library_id) {
                            $this->stream_api_key = $library['ApiKey'];
                            update_option('bunny_video_stream_api_key', $library['ApiKey']);
                            break;
                        }
                    }
                }
            }
        }
        
        if (empty($this->stream_api_key)) {
            return new WP_Error('missing_stream_api', 'Stream API key could not be retrieved');
        }

        $url = "https://video.bunnycdn.com/library/{$library_id}/videos?page={$page}&itemsPerPage={$per_page}&orderBy=date";

        $response = wp_remote_get($url, array(
            'headers' => array(
                'AccessKey' => $this->stream_api_key,
                'accept' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('api_error', 'API request failed: ' . wp_remote_retrieve_body($response));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function create_or_update_video_post($video) {
        $guid = $video['guid'];
        $title = !empty($video['title']) ? $video['title'] : $guid;
        
        $existing_posts = get_posts(array(
            'post_type' => 'video',
            'meta_key' => '_bvp_guid',
            'meta_value' => $guid,
            'posts_per_page' => 1
        ));
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => isset($video['description']) ? $video['description'] : '',
            'post_status' => 'publish',
            'post_type' => 'video'
        );
        
        if (!empty($existing_posts)) {
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
            $updated = true;
            $created = false;
        } else {
            $post_id = wp_insert_post($post_data);
            $updated = false;
            $created = true;
        }
        
        if (!is_wp_error($post_id) && $post_id > 0) {
            update_post_meta($post_id, '_bvp_guid', $guid);
            update_post_meta($post_id, '_bvp_library_id', $this->selected_library_id);
            update_post_meta($post_id, '_bvp_video_data', $video);
            
            $this->set_post_thumbnail_from_url($post_id, 
                "https://thumbnail.bunnycdn.com/{$this->selected_library_id}/{$guid}.jpg"
            );
        }
        
        return array('created' => $created, 'updated' => $updated, 'post_id' => $post_id);
    }

    public function cleanup_deleted_videos($processed_guids) {
        $existing_videos = get_posts(array(
            'post_type' => 'video',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        foreach ($existing_videos as $post_id) {
            $guid = get_post_meta($post_id, '_bvp_guid', true);
            if (!empty($guid) && !in_array($guid, $processed_guids)) {
                wp_delete_post($post_id, true);
            }
        }
    }

    private function set_post_thumbnail_from_url($post_id, $image_url) {
        if (has_post_thumbnail($post_id)) {
            return;
        }
        
        $image_data = wp_remote_get($image_url);
        if (is_wp_error($image_data) || wp_remote_retrieve_response_code($image_data) !== 200) {
            return;
        }
        
        $image_content = wp_remote_retrieve_body($image_data);
        $filename = sanitize_file_name(get_post_meta($post_id, '_bvp_guid', true) . '.jpg');
        
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
}
