<?php
/**
 * Handles the importing of RSS feeds into WordPress posts.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Post_Importer {

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Import feeds from all configured sources.
     *
     * @since    1.0.0
     */
    public function import_feeds() {
        $feed_urls = $this->get_feed_urls();
        
        foreach ( $feed_urls as $feed_url ) {
            $this->import_single_feed( $feed_url );
        }
    }

    /**
     * Get all configured feed URLs.
     *
     * @since    1.0.0
     * @return   array    An array of feed URLs.
     */
    private function get_feed_urls() {
        // In a real implementation, this would likely come from the database or plugin settings
        return array(
            'https://example.com/feed1',
            'https://example.com/feed2',
            // Add more feed URLs as needed
        );
    }

    /**
     * Import posts from a single feed.
     *
     * @since    1.0.0
     * @param    string    $feed_url    The URL of the feed to import.
     */
    private function import_single_feed( $feed_url ) {
        $rss = fetch_feed( $feed_url );

        if ( is_wp_error( $rss ) ) {
            error_log( "RSS News Importer: Error fetching feed: " . $feed_url . " - " . $rss->get_error_message() );
            return;
        }

        $max_items = $rss->get_item_quantity( 10 ); // Import up to 10 items
        $rss_items = $rss->get_items( 0, $max_items );

        foreach ( $rss_items as $item ) {
            $this->import_single_item( $item );
        }
    }

    /**
     * Import a single item from the feed.
     *
     * @since    1.0.0
     * @param    object    $item    The feed item to import.
     */
    private function import_single_item( $item ) {
        $guid = $item->get_id();
        if ( $this->post_exists( $guid ) ) {
            return; // Skip if already imported
        }

        $post_data = array(
            'post_title'   => $item->get_title(),
            'post_content' => $item->get_content(),
            'post_date'    => $item->get_date( 'Y-m-d H:i:s' ),
            'post_status'  => 'publish',
            'post_author'  => 1, // Default to the first user
            'post_type'    => 'post',
            'meta_input'   => array(
                '_rss_news_importer_guid' => $guid,
            ),
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            error_log( "RSS News Importer: Error importing post: " . $item->get_title() . " - " . $post_id->get_error_message() );
        } else {
            // Optionally set categories, tags, or featured image here
            $this->set_post_categories( $post_id, $item );
            $this->set_post_tags( $post_id, $item );
            $this->set_featured_image( $post_id, $item );
        }
    }

    /**
     * Check if a post with the given GUID already exists.
     *
     * @since    1.0.0
     * @param    string    $guid    The GUID to check.
     * @return   boolean            True if the post exists, false otherwise.
     */
    private function post_exists( $guid ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_rss_news_importer_guid' AND meta_value=%s", $guid ) );
    }

    /**
     * Set categories for the imported post.
     *
     * @since    1.0.0
     * @param    int       $post_id    The ID of the imported post.
     * @param    object    $item       The feed item.
     */
    private function set_post_categories( $post_id, $item ) {
        // Implementation depends on how categories are handled in your RSS feed
    }

    /**
     * Set tags for the imported post.
     *
     * @since    1.0.0
     * @param    int       $post_id    The ID of the imported post.
     * @param    object    $item       The feed item.
     */
    private function set_post_tags( $post_id, $item ) {
        // Implementation depends on how tags are handled in your RSS feed
    }

    /**
     * Set featured image for the imported post.
     *
     * @since    1.0.0
     * @param    int       $post_id    The ID of the imported post.
     * @param    object    $item       The feed item.
     */
    private function set_featured_image( $post_id, $item ) {
        // Implementation depends on how images are handled in your RSS feed
    }
}