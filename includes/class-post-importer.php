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

    private $plugin_name;
    private $version;
    private $logger;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
    }

    public function import_feeds() {
        $feed_urls = $this->get_feed_urls();
        
        foreach ($feed_urls as $feed_url) {
            $this->import_single_feed($feed_url);
        }
    }

    public function import_single_feed($feed_url) {
        $this->logger->log("Starting import for feed: " . $feed_url);
        
        try {
            $rss = fetch_feed($feed_url);

            if (is_wp_error($rss)) {
                throw new Exception($rss->get_error_message());
            }

            $max_items = $rss->get_item_quantity(10); // Import up to 10 items
            $rss_items = $rss->get_items(0, $max_items);

            foreach ($rss_items as $item) {
                $this->import_single_item($item);
            }
        } catch (Exception $e) {
            $this->logger->log("Error importing feed: " . $e->getMessage(), 'error');
        }

        $this->logger->log("Finished import for feed: " . $feed_url);
    }

    private function import_single_item($item) {
        $guid = $item->get_id();
        if ($this->post_exists($guid)) {
            return; // Skip if already imported
        }

        $post_data = array(
            'post_title'   => $item->get_title(),
            'post_content' => $item->get_content(),
            'post_date'    => $item->get_date('Y-m-d H:i:s'),
            'post_status'  => $this->get_post_status(),
            'post_author'  => 1, // Default to the first user
            'post_type'    => 'post',
            'meta_input'   => array(
                '_rss_news_importer_guid' => $guid,
            ),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->logger->log("Error importing post: " . $item->get_title() . " - " . $post_id->get_error_message(), 'error');
        } else {
            $this->set_post_category($post_id);
            $this->logger->log("Successfully imported post: " . $item->get_title());
        }
    }

    private function post_exists($guid) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_rss_news_importer_guid' AND meta_value=%s", $guid));
    }

    private function get_feed_urls() {
        $options = get_option($this->plugin_name . '_options');
        return isset($options['feed_urls']) ? $options['feed_urls'] : array();
    }

    private function get_post_status() {
        $options = get_option($this->plugin_name . '_options');
        return isset($options['post_status']) ? $options['post_status'] : 'draft';
    }

    private function set_post_category($post_id) {
        $options = get_option($this->plugin_name . '_options');
        $category_id = isset($options['category']) ? intval($options['category']) : 0;
        if ($category_id > 0) {
            wp_set_post_categories($post_id, array($category_id));
        }
    }
}