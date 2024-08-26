<div class="wrap rss-news-importer-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="rss-importer-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'rss-news-importer'); ?></a>
            <a href="#feeds" class="nav-tab"><?php _e('RSS Feeds', 'rss-news-importer'); ?></a>
            <a href="#import" class="nav-tab"><?php _e('Import', 'rss-news-importer'); ?></a>
            <a href="#logs" class="nav-tab"><?php _e('Logs', 'rss-news-importer'); ?></a>
        </nav>

        <div class="tab-content">
            <div id="general" class="tab-pane active">
                <form method="post" action="options.php">
                    <?php
                    settings_fields($this->plugin_name);
                    do_settings_sections($this->plugin_name);
                    submit_button();
                    ?>
                </form>
            </div>

            <div id="feeds" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Manage RSS Feeds', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <div id="rss-feeds-list"></div>
                        <div class="rss-feed-actions">
                            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>">
                            <button id="add-feed" class="button button-primary"><?php _e('Add Feed', 'rss-news-importer'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="import" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Import Now', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Click the button below to manually import posts from all configured RSS feeds.', 'rss-news-importer'); ?></p>
                        <button id="import-now" class="button button-primary"><?php _e('Import Now', 'rss-news-importer'); ?></button>
                        <div id="import-results"></div>
                    </div>
                </div>
            </div>

            <div id="logs" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Import Logs', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <div id="import-logs"></div>
                        <button id="view-logs" class="button button-secondary"><?php _e('Refresh Logs', 'rss-news-importer'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>