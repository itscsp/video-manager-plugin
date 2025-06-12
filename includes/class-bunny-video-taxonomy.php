<?php
/**
 * Class to handle video album taxonomy registration and functionality
 */

defined('ABSPATH') || exit;

class Bunny_Video_Taxonomy {
    /**
     * Taxonomy name
     */
    const TAXONOMY = 'video_album';

    /**
     * Post type to register taxonomy for
     */
    const POST_TYPE = 'video';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_taxonomy'));
    }

    /**
     * Register the video album taxonomy
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x('Albums', 'taxonomy general name', 'bunny-video-plugin'),
            'singular_name'     => _x('Album', 'taxonomy singular name', 'bunny-video-plugin'),
            'search_items'      => __('Search Albums', 'bunny-video-plugin'),
            'all_items'         => __('All Albums', 'bunny-video-plugin'),
            'parent_item'       => __('Parent Album', 'bunny-video-plugin'),
            'parent_item_colon' => __('Parent Album:', 'bunny-video-plugin'),
            'edit_item'         => __('Edit Album', 'bunny-video-plugin'),
            'update_item'       => __('Update Album', 'bunny-video-plugin'),
            'add_new_item'      => __('Add New Album', 'bunny-video-plugin'),
            'new_item_name'     => __('New Album Name', 'bunny-video-plugin'),
            'menu_name'         => __('Albums', 'bunny-video-plugin'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'album'),
            'show_in_rest'      => true,
        );

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, $args);
    }

    /**
     * Get all video albums
     *
     * @param array $args Additional arguments to pass to get_terms()
     * @return array|WP_Error Array of WP_Term objects on success, WP_Error on failure
     */
    public function get_albums($args = array()) {
        $default_args = array(
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        );

        $args = wp_parse_args($args, $default_args);
        return get_terms($args);
    }

    /**
     * Get videos in an album
     *
     * @param int|WP_Term $album Album ID or term object
     * @param array $args Additional arguments to pass to WP_Query
     * @return WP_Query
     */
    public function get_album_videos($album, $args = array()) {
        $term_id = is_object($album) ? $album->term_id : (int) $album;

        $default_args = array(
            'post_type' => self::POST_TYPE,
            'tax_query' => array(
                array(
                    'taxonomy' => self::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        );

        $args = wp_parse_args($args, $default_args);
        return new WP_Query($args);
    }
}
