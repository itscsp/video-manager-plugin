<?php
/**
 * Class for handling custom fields for video posts
 *
 * @package Video_Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Make sure we're in WordPress environment
if (!function_exists('add_action')) {
    return;
}

class Dynamic_Custom_Fields {
    private $post_type;
    private $fields;

    /**
     * Constructor.
     *
     * @param string $post_type The custom post type slug.
     * @param array  $fields    Array of fields (each with 'label' and 'id').
     */
    public function __construct($post_type, $fields) {
        $this->post_type = $post_type;
        $this->fields = $fields;
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
        
        // Register meta for REST API
        add_action('init', [$this, 'register_meta_fields']);
    }

    /**
     * Register meta fields for REST API
     */
    public function register_meta_fields() {
        foreach ($this->fields as $field) {
            register_post_meta(
                $this->post_type,
                $field['id'],
                [
                    'show_in_rest' => true,
                    'single' => true,
                    'type' => 'string',
                    'auth_callback' => function() {
                        return current_user_can('edit_posts');
                    }
                ]
            );
        }
    }

    /**
     * Register the meta box.
     */
    public function add_meta_box() {
        add_meta_box(
            "{$this->post_type}_dynamic_fields",
            'Video Details',
            [$this, 'render_meta_box'],
            $this->post_type,
            'normal',
            'default'
        );
    }

    /**
     * Render the meta box fields.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box($post) {
        wp_nonce_field("save_{$this->post_type}_fields", "{$this->post_type}_fields_nonce");
        
        echo '<div class="bunny-video-fields">';
        echo '<style>
            .bunny-video-fields p { margin: 1em 0; }
            .bunny-video-fields label { display: block; margin-bottom: 5px; }
            .bunny-video-fields input { width: 100%; padding: 8px; border: 1px solid #ddd; }
        </style>';
        
        foreach ($this->fields as $field) {
            $value = get_post_meta($post->ID, $field['id'], true);
            echo '<p>';
            echo "<label for='{$field['id']}'><strong>{$field['label']}</strong></label>";
            echo "<input type='text' id='{$field['id']}' name='{$field['id']}' value='" . esc_attr($value) . "' class='widefat' />";
            echo '</p>';
        }
        echo '</div>';
    }

    /**
     * Save the meta box fields.
     *
     * @param int $post_id The post ID.
     */
    public function save_meta_box($post_id) {
        // Security checks
        if (!isset($_POST["{$this->post_type}_fields_nonce"]) || 
            !wp_verify_nonce($_POST["{$this->post_type}_fields_nonce"], "save_{$this->post_type}_fields")) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save each field
        foreach ($this->fields as $field) {
            if (isset($_POST[$field['id']])) {
                update_post_meta($post_id, $field['id'], sanitize_text_field($_POST[$field['id']]));
            }
        }
    }
}

// Initialize the custom fields for the video post type
add_action('init', function() {
    new Dynamic_Custom_Fields('video', [
        ['label' => 'Video Creator', 'id' => '_video_creator'],
        ['label' => 'Bunny.net Video ID', 'id' => '_bunny_video_id'],
        ['label' => 'Video Album', 'id' => '_video_album'],
        ['label' => 'Video Duration', 'id' => '_video_duration'],
        ['label' => 'Video Resolution', 'id' => '_video_resolution']
    ]);
}, 20);