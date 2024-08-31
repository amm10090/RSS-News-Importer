<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_i18n {

    private $domain;
    private $logger;

    /**
     * 构造函数
     *
     * @param string $domain 文本域
     */
    public function __construct($domain = 'rss-news-importer') {
        $this->domain = $domain;
        $this->logger = new RSS_News_Importer_Logger();
    }

    /**
     * 加载插件的文本域
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            $this->domain,
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );

        $this->logger->log("Loaded text domain: {$this->domain}", 'info');
    }

    /**
     * 获取当前加载的语言
     *
     * @return string 当前语言的代码
     */
    public function get_current_language() {
        return determine_locale();
    }

    /**
     * 检查特定的翻译文件是否存在
     *
     * @param string $locale 语言代码
     * @return bool 翻译文件是否存在
     */
    public function translation_exists($locale) {
        $mofile = WP_LANG_DIR . '/plugins/' . $this->domain . '-' . $locale . '.mo';
        
        if (file_exists($mofile)) {
            $this->logger->log("Translation file found for locale: {$locale}", 'info');
            return true;
        } else {
            $this->logger->log("Translation file not found for locale: {$locale}", 'warning');
            return false;
        }
    }

    /**
     * 获取可用的翻译列表
     *
     * @return array 可用翻译的列表
     */
    public function get_available_translations() {
        $translations = [];
        $lang_dir = dirname(dirname(plugin_basename(__FILE__))) . '/languages/';
        
        if (is_dir($lang_dir)) {
            $files = scandir($lang_dir);
            foreach ($files as $file) {
                if (preg_match('/^' . $this->domain . '-(.+)\.mo$/', $file, $matches)) {
                    $translations[] = $matches[1];
                }
            }
        }

        $this->logger->log("Found " . count($translations) . " available translations", 'info');
        return $translations;
    }

    /**
     * 翻译字符串并记录未翻译的字符串
     *
     * @param string $text 要翻译的文本
     * @return string 翻译后的文本
     */
    public function translate($text) {
        $translated = __($text, $this->domain);
        
        if ($translated === $text) {
            $this->logger->log("Untranslated string: {$text}", 'warning');
        }
        
        return $translated;
    }

    /**
     * 设置文本域
     *
     * @param string $domain 新的文本域
     */
    public function set_domain($domain) {
        $this->domain = $domain;
        $this->logger->log("Text domain changed to: {$domain}", 'info');
    }

    /**
     * 获取当前文本域
     *
     * @return string 当前文本域
     */
    public function get_domain() {
        return $this->domain;
    }
}