<?php

/**
 * Define the internationalization functionality
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_i18n {

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'rss-news-importer',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}