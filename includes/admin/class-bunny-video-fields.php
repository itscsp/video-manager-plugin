<?php
defined('ABSPATH') || exit;

class Bunny_Video_Fields {
    private $post_type;
    private $fields;

    /**
     * Constructor.
     *
     * @param string $post_type The custom post type slug.
     * @param array $fields Array of fields (each with 'label' and 'id').
     */
    public function __construct($post_type, $fields) {
        $this->post_type = $post_type;
        $this->fields = $fields;

        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
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
     */
    public function render_meta_box($post) {
        wp_nonce_field("save_{$this->post_type}_fields", "{$this->post_type}_fields_nonce");
        
        echo '<div class="bunny-video-fields">';
        foreach ($this->fields as $field) {
            $value = get_post_meta($post->ID, $field['id'], true);
            echo '<div class="bunny-video-field">';
            echo "<label for='{$field['id']}'><strong>{$field['label']}</strong></label><br>";
            echo "<input type='text' id='{$field['id']}' name='{$field['id']}' value='" . esc_attr($value) . "' class='widefat' />";
            echo '</div>';
        }
        echo '</div>';
        
        // Add some basic styling
        echo '<style>
            .bunny-video-fields { padding: 10px; }
            .bunny-video-field { margin-bottom: 15px; }
            .bunny-video-field label { margin-bottom: 5px; display: block; }
        </style>';
    }

    /**
     * Save the meta box fields.
     */
    public function save_meta_box($post_id) {
        // Security checks
        if (!isset($_POST["{$this->post_type}_fields_nonce"]) ||
            !wp_verify_nonce($_POST["{$this->post_type}_fields_nonce"], "save_{$this->post_type}_fields")) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Save each field
        foreach ($this->fields as $field) {
            if (isset($_POST[$field['id']])) {
                update_post_meta($post_id, $field['id'], sanitize_text_field($_POST[$field['id']]));
            }
        }
    }
}
