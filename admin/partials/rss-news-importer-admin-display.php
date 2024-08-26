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
    </div>
</div>