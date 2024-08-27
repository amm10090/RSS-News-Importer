<div class="wrap rss-news-importer-admin">
    <!-- 插件页面标题 -->
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="rss-importer-tabs">
        <nav class="nav-tab-wrapper">
            <!-- 导航标签：常规设置 -->
            <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'rss-news-importer'); ?></a>
            <!-- 导航标签：RSS 源管理 -->
            <a href="#feeds" class="nav-tab"><?php _e('RSS Feeds', 'rss-news-importer'); ?></a>
            <!-- 导航标签：手动导入 -->
            <a href="#import" class="nav-tab"><?php _e('Import', 'rss-news-importer'); ?></a>
            <!-- 导航标签：日志 -->
            <a href="#logs" class="nav-tab"><?php _e('Logs', 'rss-news-importer'); ?></a>
        </nav>

        <div class="tab-content">
            <!-- 常规设置选项卡内容 -->
            <div id="general" class="tab-pane active">
                <form method="post" action="options.php">
                    <?php
                    settings_fields($this->plugin_name); // 插件设置字段
                    do_settings_sections($this->plugin_name); // 插件设置部分
                    submit_button(); // 提交按钮
                    ?>
                </form>
            </div>

            <!-- RSS 源管理选项卡内容 -->
            <div id="feeds" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Manage RSS Feeds', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <div id="rss-feeds-list" class="sortable-list">
                            <?php
                            $feeds = get_option('rss_news_importer_feeds', array()); // 获取 RSS 源选项
                            foreach ($feeds as $feed) :
                                $feed_url = is_array($feed) ? $feed['url'] : $feed; // 获取 RSS 源 URL
                                $feed_name = is_array($feed) && isset($feed['name']) ? $feed['name'] : ''; // 获取 RSS 源名称
                            ?>
                                <div class="feed-item" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                                    <span class="dashicons dashicons-menu handle"></span> <!-- 拖动图标 -->
                                    <input type="text" value="<?php echo esc_url($feed_url); ?>" readonly> <!-- RSS 源 URL 输入框 -->
                                    <input type="text" value="<?php echo esc_attr($feed_name); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>"> <!-- RSS 源名称输入框 -->
                                    <button class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button> <!-- 移除按钮 -->
                                    <button class="button preview-feed"><?php _e('Preview', 'rss-news-importer'); ?></button> <!-- 预览按钮 -->
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="rss-feed-actions">
                            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>"> <!-- 新 RSS 源 URL 输入框 -->
                            <input type="text" id="new-feed-name" placeholder="<?php _e('Enter feed name (optional)', 'rss-news-importer'); ?>"> <!-- 新 RSS 源名称输入框 -->
                            <button id="add-feed" class="button button-primary"><?php _e('Add Feed', 'rss-news-importer'); ?></button> <!-- 添加按钮 -->
                        </div>
                        <div id="feed-preview"></div> <!-- RSS 源预览区域 -->
                    </div>
                </div>
            </div>

            <!-- 手动导入选项卡内容 -->
            <div id="import" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Import Now', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Click the button below to manually import posts from all configured RSS feeds.', 'rss-news-importer'); ?></p> <!-- 说明文字 -->
                        <button id="import-now" class="button button-primary"><?php _e('Import Now', 'rss-news-importer'); ?></button> <!-- 手动导入按钮 -->
                        <div id="import-results"></div> <!-- 导入结果显示区域 -->
                    </div>
                </div>
            </div>

            <!-- 日志选项卡内容 -->
            <div id="logs" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Import Logs', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <div id="import-logs"></div> <!-- 导入日志显示区域 -->
                        <button id="view-logs" class="button button-secondary"><?php _e('Refresh Logs', 'rss-news-importer'); ?></button> <!-- 刷新日志按钮 -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>