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

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/rss-news-importer-admin.js', array( 'jquery' ), $this->version, false );
    }

    public function add_plugin_admin_menu() {
        add_options_page(
            'RSS News Importer Settings', 
            'RSS News Importer', 
            'manage_options', 
            $this->plugin_name, 
            array( $this, 'display_plugin_setup_page' )
        );
    }

    public function add_action_links( $links ) {
        $settings_link = array(
            '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_name ) . '">' . __( 'Settings', $this->plugin_name ) . '</a>',
        );
        return array_merge( $settings_link, $links );
    }

    public function display_plugin_setup_page() {
        include_once 'partials/rss-news-importer-admin-display.php';
    }

    public function register_settings() {
        register_setting( 
            $this->plugin_name, 
            $this->plugin_name . '_feed_urls', 
            array( $this, 'validate_feed_urls' )
        );
        register_setting( 
            $this->plugin_name, 
            $this->plugin_name . '_import_frequency'
        );
        register_setting( 
            $this->plugin_name, 
            $this->plugin_name . '_post_status'
        );
        register_setting( 
            $this->plugin_name, 
            $this->plugin_name . '_category'
        );
    }

    public function validate_feed_urls( $input ) {
        $valid = array();
        foreach ( $input as $url ) {
            $valid[] = esc_url_raw( $url );
        }
        return $valid;
    }

    public function get_import_frequencies() {
        return array(
            'hourly' => __('Hourly', $this->plugin_name),
            'twicedaily' => __('Twice Daily', $this->plugin_name),
            'daily' => __('Daily', $this->plugin_name),
        );
    }

    public function get_post_statuses() {
        return array(
            'draft' => __('Draft', $this->plugin_name),
            'publish' => __('Published', $this->plugin_name),
        );
    }
}