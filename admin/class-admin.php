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
    private $option_name;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->option_name = $this->plugin_name . '_options';

        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
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

    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('options-general.php?page=' . $this->plugin_name) . '">' . __('Settings', $this->plugin_name) . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    public function display_plugin_setup_page() {
        include_once 'partials/rss-news-importer-admin-display.php';
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name, array($this, 'validate_options'));

        add_settings_section(
            $this->option_name . '_general',
            __('General Settings', $this->plugin_name),
            array($this, 'settings_section_callback'),
            $this->plugin_name
        );

        add_settings_field(
            'feed_urls',
            __('Feed URLs', $this->plugin_name),
            array($this, 'feed_urls_field_callback'),
            $this->plugin_name,
            $this->option_name . '_general'
        );

        add_settings_field(
            'import_frequency',
            __('Import Frequency', $this->plugin_name),
            array($this, 'import_frequency_field_callback'),
            $this->plugin_name,
            $this->option_name . '_general'
        );

        add_settings_field(
            'post_status',
            __('Post Status', $this->plugin_name),
            array($this, 'post_status_field_callback'),
            $this->plugin_name,
            $this->option_name . '_general'
        );

        add_settings_field(
            'category',
            __('Category', $this->plugin_name),
            array($this, 'category_field_callback'),
            $this->plugin_name,
            $this->option_name . '_general'
        );
    }

    public function validate_options($input) {
        $valid = array();
        $valid['feed_urls'] = isset($input['feed_urls']) ? array_map('esc_url_raw', $input['feed_urls']) : array();
        $valid['import_frequency'] = isset($input['import_frequency']) ? sanitize_text_field($input['import_frequency']) : 'hourly';
        $valid['post_status'] = isset($input['post_status']) ? sanitize_text_field($input['post_status']) : 'draft';
        $valid['category'] = isset($input['category']) ? absint($input['category']) : 0;
        return $valid;
    }

    public function settings_section_callback() {
        echo '<p>' . __('Configure your RSS News Importer settings here.', $this->plugin_name) . '</p>';
    }

    public function feed_urls_field_callback() {
        $options = get_option($this->option_name);
        $feed_urls = isset($options['feed_urls']) ? $options['feed_urls'] : array();
        echo '<div id="feed-urls">';
        foreach ($feed_urls as $url) {
            echo '<p><input type="text" name="' . $this->option_name . '[feed_urls][]" value="' . esc_url($url) . '" class="regular-text" /> <button type="button" class="button remove-url">Remove</button></p>';
        }
        echo '</div>';
        echo '<button type="button" class="button" id="add-url">Add New URL</button>';
    }

    public function import_frequency_field_callback() {
        $options = get_option($this->option_name);
        $current = isset($options['import_frequency']) ? $options['import_frequency'] : 'hourly';
        $frequencies = $this->get_import_frequencies();
        echo '<select name="' . $this->option_name . '[import_frequency]">';
        foreach ($frequencies as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function post_status_field_callback() {
        $options = get_option($this->option_name);
        $current = isset($options['post_status']) ? $options['post_status'] : 'draft';
        $statuses = $this->get_post_statuses();
        echo '<select name="' . $this->option_name . '[post_status]">';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function category_field_callback() {
        $options = get_option($this->option_name);
        $current = isset($options['category']) ? $options['category'] : 0;
        wp_dropdown_categories(array(
            'name' => $this->option_name . '[category]',
            'selected' => $current,
            'show_option_none' => __('Select a category', $this->plugin_name),
            'option_none_value' => '0',
        ));
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