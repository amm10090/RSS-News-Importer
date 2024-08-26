<?php
class RSS_News_Importer_Logger {
    private $log_file;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/rss-news-importer-log.txt';
    }

    public function log( $message, $level = 'info' ) {
        $timestamp = current_time( 'mysql' );
        $formatted_message = sprintf( "[%s] [%s]: %s\n", $timestamp, strtoupper( $level ), $message );
        error_log( $formatted_message, 3, $this->log_file );
    }

    public function get_log_contents() {
        if ( file_exists( $this->log_file ) ) {
            return file_get_contents( $this->log_file );
        }
        return 'Log file is empty.';
    }

    public function clear_log() {
        if ( file_exists( $this->log_file ) ) {
            unlink( $this->log_file );
        }
    }
}