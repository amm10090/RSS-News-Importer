<?php

class RSS_News_Importer_Admin {

    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery'), $this->version, false);
    }

    public function add_plugin_admin_menu() {
        add_options_page(
            'RSS News Importer Settings', 
            'RSS News Importer', 
            'manage_options', 
            $this->plugin_name, 
            array($this, 'display_plugin_setup_page')
        );
    }

    public function display_plugin_setup_page() {
        include_once 'partials/rss-news-importer-admin-display.php';
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name, array($this, 'validate_options'));

        add_settings_section(
            'rss_news_importer_general',
            __('General Settings', 'rss-news-importer'),
            array($this, 'rss_news_importer_general_cb'),
            $this->plugin_name
        );

        add_settings_field(
            'rss_feeds',
            __('RSS Feeds', 'rss-news-importer'),
            array($this, 'rss_feeds_cb'),
            $this->plugin_name,
            'rss_news_importer_general'
        );

        add_settings_field(
            'update_frequency',
            __('Update Frequency', 'rss-news-importer'),
            array($this, 'update_frequency_cb'),
            $this->plugin_name,
            'rss_news_importer_general'
        );
    }

    public function validate_options($input) {
        $valid = array();
        $valid['rss_feeds'] = isset($input['rss_feeds']) ? $this->sanitize_rss_feeds($input['rss_feeds']) : array();
        $valid['update_frequency'] = isset($input['update_frequency']) ? sanitize_text_field($input['update_frequency']) : 'hourly';
        return $valid;
    }

    public function rss_news_importer_general_cb() {
        echo '<p>' . __('Configure your RSS News Importer settings here.', 'rss-news-importer') . '</p>';
    }

    public function rss_feeds_cb() {
        $options = get_option($this->option_name);
        $rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        echo '<div id="rss-feeds">';
        foreach ($rss_feeds as $feed) {
            echo '<p><input type="text" name="' . $this->option_name . '[rss_feeds][]" value="' . esc_url($feed) . '" class="regular-text" />';
            echo ' <button type="button" class="button remove-feed">Remove</button></p>';
        }
        echo '</div>';
        echo '<button type="button" class="button" id="add-feed">Add New Feed</button>';
    }

    public function update_frequency_cb() {
        $options = get_option($this->option_name);
        $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly';
        $frequencies = array(
            'hourly' => __('Hourly', 'rss-news-importer'),
            'twicedaily' => __('Twice Daily', 'rss-news-importer'),
            'daily' => __('Daily', 'rss-news-importer'),
        );
        echo '<select name="' . $this->option_name . '[update_frequency]">';
        foreach ($frequencies as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($frequency, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }

    private function sanitize_rss_feeds($feeds) {
        return array_map('esc_url_raw', $feeds);
    }
}