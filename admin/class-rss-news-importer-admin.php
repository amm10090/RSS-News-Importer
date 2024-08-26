<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/admin
 */

class RSS_News_Importer_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
    }

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
        // 在这里注册设置
    }
}