<?php

/**
 * The dashboard functionality of the plugin.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.3.1
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Dashboard {

    /**
     * The ID of this plugin.
     *
     * @since    1.3.1
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.3.1
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.1
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Display the RSS feeds dashboard.
     *
     * @since    1.3.1
     */
    public function display_dashboard() {
        $feeds = get_option('rss_news_importer_feeds', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Feed URL', 'rss-news-importer'); ?></th>
                        <th><?php _e('Last Update', 'rss-news-importer'); ?></th>
                        <th><?php _e('Success Rate', 'rss-news-importer'); ?></th>
                        <th><?php _e('Status', 'rss-news-importer'); ?></th>
                        <th><?php _e('Actions', 'rss-news-importer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feeds as $feed_url => $feed_data) : ?>
                        <tr>
                            <td><?php echo esc_url($feed_url); ?></td>
                            <td><?php echo esc_html($feed_data['last_update']); ?></td>
                            <td><?php echo esc_html($feed_data['success_rate']); ?>%</td>
                            <td><?php echo esc_html($feed_data['status']); ?></td>
                            <td>
                                <button class="button import-now" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                                    <?php _e('Import Now', 'rss-news-importer'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Update the status of a feed.
     *
     * @since    1.3.1
     * @param    string    $feed_url    The URL of the feed.
     * @param    bool      $success     Whether the import was successful.
     */
    public function update_feed_status($feed_url, $success) {
        $feeds = get_option('rss_news_importer_feeds', array());
        if (!isset($feeds[$feed_url])) {
            $feeds[$feed_url] = array(
                'last_update' => '',
                'success_count' => 0,
                'fail_count' => 0,
                'total_count' => 0,
                'status' => 'Active',
                'success_rate' => 0
            );
        }

        $feeds[$feed_url]['last_update'] = current_time('mysql');
        $feeds[$feed_url]['total_count']++;
        if ($success) {
            $feeds[$feed_url]['success_count']++;
            $feeds[$feed_url]['fail_count'] = 0; // Reset fail count on success
        } else {
            $feeds[$feed_url]['fail_count']++;
            if ($feeds[$feed_url]['fail_count'] >= 3) {
                $this->send_alert($feed_url);
            }
        }

        $feeds[$feed_url]['success_rate'] = ($feeds[$feed_url]['success_count'] / $feeds[$feed_url]['total_count']) * 100;
        $feeds[$feed_url]['status'] = ($feeds[$feed_url]['fail_count'] >= 3) ? 'Error' : 'Active';

        update_option('rss_news_importer_feeds', $feeds);
    }

    /**
     * Send an alert for a failing feed.
     *
     * @since    1.3.1
     * @param    string    $feed_url    The URL of the failing feed.
     */
    private function send_alert($feed_url) {
        $to = get_option('admin_email');
        $subject = __('RSS Feed Import Failure Alert', 'rss-news-importer');
        $message = sprintf(
            __('The RSS feed at %s has failed to import 3 times in a row. Please check the feed and your import settings.', 'rss-news-importer'),
            $feed_url
        );
        wp_mail($to, $subject, $message);
    }

    /**
     * Handle the AJAX request for immediate feed import.
     *
     * @since    1.3.1
     */
    public function handle_import_now_ajax() {
        check_ajax_referer('rss_news_importer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'rss-news-importer'));
        }

        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error(__('Invalid feed URL.', 'rss-news-importer'));
        }

        $importer = new RSS_News_Importer_Post_Importer();
        $result = $importer->import_feed($feed_url);

        if ($result === false) {
            wp_send_json_error(__('Failed to import feed.', 'rss-news-importer'));
        } else {
            wp_send_json_success(sprintf(__('Successfully imported %d items.', 'rss-news-importer'), $result));
        }
    }
}