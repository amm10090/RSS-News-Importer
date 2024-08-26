<?php

/**
 * Manages the cron jobs for RSS News Importer.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Cron_Manager {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function schedule_import() {
        if (!wp_next_scheduled('rss_news_importer_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'rss_news_importer_cron_hook');
        }
    }

    public function unschedule_import() {
        $timestamp = wp_next_scheduled('rss_news_importer_cron_hook');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rss_news_importer_cron_hook');
        }
    }
}