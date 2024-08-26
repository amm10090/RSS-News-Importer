<?php

/**
 * Handles logging for the RSS News Importer.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Logger {

    private $log_file;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/rss-news-importer-log.txt';
    }

    public function log($message, $level = 'info') {
        $timestamp = current_time('mysql');
        $formatted_message = sprintf("[%s] [%s]: %s\n", $timestamp, strtoupper($level), $message);
        error_log($formatted_message, 3, $this->log_file);
    }
}