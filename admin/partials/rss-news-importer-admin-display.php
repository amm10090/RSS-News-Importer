<?php
// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap rss-news-importer-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="rss-importer-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#feeds" class="nav-tab nav-tab-active"><?php _e('RSS Feeds', 'rss-news-importer'); ?></a>
            <a href="#settings" class="nav-tab"><?php _e('Settings', 'rss-news-importer'); ?></a>
            <a href="#import" class="nav-tab"><?php _e('Import', 'rss-news-importer'); ?></a>
            <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'rss-news-importer'); ?></a>
        </nav>

        <div class="tab-content">
            <!-- RSS 源管理选项卡内容 -->
            <div id="feeds" class="tab-pane active">
                <div class="card">
                    <h2 class="title"><?php _e('Manage RSS Feeds', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <div id="rss-feeds-list" class="sortable-list">
                            <?php
                            $options = get_option($this->option_name, array());
                            $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
                            if (empty($feeds)) {
                                echo '<p class="no-feeds">' . __('No RSS feeds added yet.', 'rss-news-importer') . '</p>';
                            } else {
                                foreach ($feeds as $index => $feed) :
                                    $feed_url = isset($feed['url']) ? $feed['url'] : '';
                                    $feed_name = isset($feed['name']) ? $feed['name'] : '';
                                    if (empty($feed_url)) continue;
                            ?>
                                    <div class="feed-item" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                                        <div class="feed-item-content">
                                            <span class="dashicons dashicons-menu handle"></span>
                                            <input type="text" value="<?php echo esc_url($feed_url); ?>" readonly class="feed-url">
                                            <input type="text" value="<?php echo esc_attr($feed_name); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
                                            <div class="feed-actions">
                                                <button class="button button-secondary preview-feed" title="<?php _e('Preview', 'rss-news-importer'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                                <button class="button button-secondary remove-feed" title="<?php _e('Remove', 'rss-news-importer'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="feed-preview"></div>
                                    </div>
                            <?php
                                endforeach;
                            }
                            ?>
                        </div>
                        <div class="rss-feed-actions">
                            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>">
                            <input type="text" id="new-feed-name" placeholder="<?php _e('Enter feed name (optional)', 'rss-news-importer'); ?>">
                            <button id="add-feed" class="button button-primary">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php _e('Add Feed', 'rss-news-importer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 设置选项卡内容 -->
            <div id="settings" class="tab-pane">
             <form method="post" action="javascript:void(0);" id="rss-news-importer-form">
                    <?php
                    settings_fields($this->plugin_name);
                    do_settings_sections($this->plugin_name);
                    submit_button();
                    ?>
                </form>
            </div>

            <!-- 手动导入选项卡内容 -->
            <div id="import" class="tab-pane">
                <div class="card">
                    <h2 class="title"><?php _e('Import Now', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Click the button below to manually import posts from all configured RSS feeds.', 'rss-news-importer'); ?></p>
                        <button id="import-now" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Import Now', 'rss-news-importer'); ?>
                        </button>
                        <div id="import-progress" class="progress-bar" style="display:none;"></div>
                        <div id="import-results"></div>
                    </div>
                </div>
            </div>

            <!-- 高级设置选项卡内容 -->
            <div id="advanced" class="tab-pane">
                <form method="post" action="options.php" id="rss-news-importer-advanced-form">
                    <?php
                    settings_fields($this->plugin_name . '_advanced');
                    do_settings_sections($this->plugin_name . '_advanced');
                    submit_button(__('Save Advanced Settings', 'rss-news-importer'));
                    ?>
                </form>
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

        });


    });
</script>