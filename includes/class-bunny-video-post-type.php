<?php
/**
 * Class to handle video post type registration and functionality
 */

defined('ABSPATH') || exit;

// Make sure we have access to WordPress functions
if (!function_exists('add_action')) {
    return;
}

class Bunny_Video_Post_Type {
    /**
     * Post type name
     */
    const POST_TYPE = 'video';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_meta'));
        add_filter('rest_prepare_' . self::POST_TYPE, array($this, 'add_meta_to_rest'), 10, 3);
    }

    /**
     * Register the video post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x('Videos', 'post type general name', 'bunny-video-plugin'),
            'singular_name'      => _x('Video', 'post type singular name', 'bunny-video-plugin'),
            'menu_name'          => _x('Videos', 'admin menu', 'bunny-video-plugin'),
            'name_admin_bar'     => _x('Video', 'add new on admin bar', 'bunny-video-plugin'),
            'add_new'            => _x('Add New', 'video', 'bunny-video-plugin'),
            'add_new_item'       => __('Add New Video', 'bunny-video-plugin'),
            'new_item'           => __('New Video', 'bunny-video-plugin'),
            'edit_item'          => __('Edit Video', 'bunny-video-plugin'),
            'view_item'          => __('View Video', 'bunny-video-plugin'),
            'all_items'          => __('All Videos', 'bunny-video-plugin'),
            'search_items'       => __('Search Videos', 'bunny-video-plugin'),
            'parent_item_colon'  => __('Parent Videos:', 'bunny-video-plugin'),
            'not_found'          => __('No videos found.', 'bunny-video-plugin'),
            'not_found_in_trash' => __('No videos found in Trash.', 'bunny-video-plugin')
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'video'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-video-alt3'
        );

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register meta fields
     */
    public function register_meta() {
        register_post_meta(self::POST_TYPE, 'bunny_video_id', array(
            'type' => 'string',
            'description' => 'Bunny.net Video ID',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }

    /**
     * Add meta to REST API response
     */
    public function add_meta_to_rest($response, $post, $request) {
        $video_id = get_post_meta($post->ID, 'bunny_video_id', true);
        
        // Add meta to response
        $response->data['meta'] = array(
            'bunny_video_id' => $video_id
        );
        
        return $response;
    }
}
