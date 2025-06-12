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
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/admin/class-bunny-video-fields.php';
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/admin/class-bunny-video-settings.php';
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/class-bunny-video-post-type.php';
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/class-bunny-video-taxonomy.php';
require_once BUNNY_VIDEO_PLUGIN_DIR . 'includes/class-bunny-custom-fields.php';

class Bunny_Video_Plugin {
    private $api_key;
    private $selected_library_id;
    private $stream_api_key;
    private $post_type;
    private $taxonomy;
    private $settings;
    private static $instance = null;

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize settings
        $this->settings = new Bunny_Video_Settings();
        
        // Initialize post type and taxonomy handlers
        $this->post_type = new Bunny_Video_Post_Type();
        $this->taxonomy = new Bunny_Video_Taxonomy();
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Template loading
        add_filter('template_include', array($this, 'load_video_templates'));
    }
    
    /**
     * Initialize plugin settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Plugin activation hook
     */
    public function activate() {
        // Register post type and taxonomy to flush rules
        $this->post_type->register_post_type();
        $this->taxonomy->register_taxonomy();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Load custom templates for video and album pages
     */
    public function load_video_templates($template) {
        // For video album taxonomy archive
        if (is_tax('video_album')) {
            $taxonomy_template = BUNNY_VIDEO_PLUGIN_DIR . 'templates/taxonomy-video_album.php';
            if (file_exists($taxonomy_template)) {
                return $taxonomy_template;
            }
        }

        // For video archive
        if (is_post_type_archive('video')) {
            $archive_template = BUNNY_VIDEO_PLUGIN_DIR . 'templates/archive-video_album.php';
            if (file_exists($archive_template)) {
                return $archive_template;
            }
        }

        return $template;
    }
}

/**
 * Initialize the plugin and return the instance
 * 
 * @return Bunny_Video_Plugin
 */
function bunny_video_plugin() {
    return Bunny_Video_Plugin::get_instance();
}

// Initialize the plugin
bunny_video_plugin();

/**
 * Initialize Gutenberg block
 */
function create_block_videobunnydynamic_block_init() {
    /**
     * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
     * based on the registered block metadata.
     * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
     *
     * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
     */
    if (function_exists('wp_register_block_types_from_metadata_collection')) {
        wp_register_block_types_from_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
        return;
    }

    /**
     * Registers the block(s) metadata from the `blocks-manifest.php` file.
     * Added to WordPress 6.7 to improve the performance of block type registration.
     *
     * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
     */
    if (function_exists('wp_register_block_metadata_collection')) {
        wp_register_block_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
    }

    /**
     * Registers the block type(s) in the `blocks-manifest.php` file.
     *
     * @see https://developer.wordpress.org/reference/functions/register_block_type/
     */
    $manifest_data = require __DIR__ . '/build/blocks-manifest.php';
    foreach (array_keys($manifest_data) as $block_type) {
        register_block_type(__DIR__ . "/build/{$block_type}");
    }
}
add_action('init', 'create_block_videobunnydynamic_block_init');