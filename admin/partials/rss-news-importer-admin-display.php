<?php
// 安全检查
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap rss-news-importer-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="rss-news-importer-container">
        <form action="options.php" method="post">
            <?php
            settings_fields($this->plugin_name);
            do_settings_sections($this->plugin_name);
            submit_button(__('Save Settings', 'rss-news-importer'));
            ?>
        </form>
    </div>

    <div class="rss-news-importer-container">
        <h2><?php _e('Import Now', 'rss-news-importer'); ?></h2>
        <p><?php _e('Click the button below to manually import RSS feeds now.', 'rss-news-importer'); ?></p>
        <button id="import-now" class="button button-primary"><?php _e('Import Now', 'rss-news-importer'); ?></button>
        <div id="import-result"></div>
        <div id="import-progress" class="hidden">
            <div class="progress-bar"></div>
        </div>
    </div>

    <div class="rss-news-importer-container">
        <h2><?php _e('RSS Feeds', 'rss-news-importer'); ?></h2>
        <div id="rss-feeds">
            <?php
            $options = get_option($this->option_name);
            $rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
            foreach ($rss_feeds as $feed) :
            ?>
                <div class="rss-feed-item">
                    <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_url($feed); ?>" class="regular-text" readonly />
                    <button type="button" class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
                    <button type="button" class="button preview-feed" data-feed-url="<?php echo esc_url($feed); ?>"><?php _e('Preview', 'rss-news-importer'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="rss-news-importer-container">
        <h2><?php _e('Import Logs', 'rss-news-importer'); ?></h2>
        <button id="view-logs" class="button"><?php _e('View Logs', 'rss-news-importer'); ?></button>
        <div id="log-container"></div>
    </div>
</div>

<!-- 预览模态框 -->
<div id="preview-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('Feed Preview', 'rss-news-importer'); ?></h2>
        <div id="preview-content"></div>
    </div>
</div>

