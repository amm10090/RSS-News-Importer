<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit;
}
?>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
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
            <!-- 常规设置选项卡内容 -->
            <div id="general" class="tab-pane active">
                <form method="post" action="options.php">
                    <?php
                    settings_fields($this->plugin_name);
                    do_settings_sections($this->plugin_name);
                    submit_button();
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
                            $options = get_option('rss_news_importer_options', array());
                            $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
                            if (empty($feeds)) {
                                echo '<p>' . __('No RSS feeds added yet.', 'rss-news-importer') . '</p>';
                            } else {
                                foreach ($feeds as $index => $feed) :
                                    $feed_url = isset($feed['url']) ? $feed['url'] : '';
                                    $feed_name = isset($feed['name']) ? $feed['name'] : '';
                                    if (empty($feed_url)) continue;
                            ?>
                                <div class="feed-item" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                                    <span class="dashicons dashicons-menu handle"></span>
                                    <input type="text" name="rss_news_importer_options[rss_feeds][<?php echo $index; ?>][url]" value="<?php echo esc_url($feed_url); ?>" readonly class="feed-url">
                                    <input type="text" name="rss_news_importer_options[rss_feeds][<?php echo $index; ?>][name]" value="<?php echo esc_attr($feed_name); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
                                    <button class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
                                    <button class="button preview-feed"><?php _e('Preview', 'rss-news-importer'); ?></button>
                                </div>
                            <?php
                                endforeach;
                            }
                            ?>
                        </div>
                        <div class="rss-feed-actions">
                            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>">
                            <input type="text" id="new-feed-name" placeholder="<?php _e('Enter feed name (optional)', 'rss-news-importer'); ?>">
                            <button id="add-feed" class="button button-primary"><?php _e('Add Feed', 'rss-news-importer'); ?></button>
                        </div>
                        <div id="feed-preview"></div>
                    </div>
                </div>
            </div>

            <!-- 手动导入选项卡内容 -->
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

            <!-- 日志选项卡内容 -->
            <div id="logs" class="tab-pane">
                <div id="log-viewer-root"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // 选项卡切换功能
        $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.tab-content .tab-pane').removeClass('active');
            $(target).addClass('active');

            // 如果切换到日志选项卡，加载日志内容
            if (target === '#logs') {
                loadLogViewer();
            }
        });

        // 加载 LogViewer 组件
        function loadLogViewer() {
            if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined' && typeof LogViewer !== 'undefined') {
                ReactDOM.render(
                    React.createElement(LogViewer),
                    document.getElementById('log-viewer-root')
                );
            } else {
                console.error('React, ReactDOM, or LogViewer is not loaded. Make sure to enqueue these scripts.');
                $('#log-viewer-root').html('<p class="error-message">Error: Unable to load log viewer. Please check the console for more information.</p>');
            }
        }

        // 如果默认显示日志选项卡，则立即加载 LogViewer
        if ($('#logs').hasClass('active')) {
            loadLogViewer();
        }
    });
</script>