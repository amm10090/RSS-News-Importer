<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="log-viewer-root">
        <!-- React 将在这里渲染日志查看器 -->
    </div>
</div>

<?php
// 加载 React 和 ReactDOM
wp_enqueue_script('react', 'https://unpkg.com/react@17.0.2/umd/react.production.min.js', array(), '17.0.2', true);
wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17.0.2/umd/react-dom.production.min.js', array('react'), '17.0.2', true);

// 加载 log-viewer-component.js
wp_enqueue_script('rss-news-importer-log-viewer-component', plugin_dir_url(dirname(__FILE__)) . 'js/log-viewer-component.js', array('react', 'react-dom', 'jquery'), RSS_NEWS_IMPORTER_VERSION, true);

// 本地化脚本
wp_localize_script('rss-news-importer-log-viewer-component', 'rss_news_importer_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('rss_news_importer_nonce'),
    'i18n' => array(
        'confirm_clear' => __('确定要清除所有日志吗？此操作无法撤消。', 'rss-news-importer'),
        'error_loading' => __('加载日志失败', 'rss-news-importer'),
        'error_exporting' => __('导出日志失败', 'rss-news-importer'),
        'error_clearing' => __('清除日志失败', 'rss-news-importer'),
        'success_clearing' => __('所有日志已清除', 'rss-news-importer'),
        'search_logs' => __('搜索日志...', 'rss-news-importer'),
        'all_levels' => __('所有级别', 'rss-news-importer'),
        'info' => __('信息', 'rss-news-importer'),
        'debug' => __('调试', 'rss-news-importer'),
        'warning' => __('警告', 'rss-news-importer'),
        'error' => __('错误', 'rss-news-importer'),
        'load_more' => __('加载更多', 'rss-news-importer'),
        'export_csv' => __('导出 CSV', 'rss-news-importer'),
        'export_json' => __('导出 JSON', 'rss-news-importer'),
        'clear_logs' => __('清除日志', 'rss-news-importer'),
        'total_logs' => __('总日志数', 'rss-news-importer'),
        'errors' => __('错误', 'rss-news-importer'),
        'warnings' => __('警告', 'rss-news-importer'),
        'info_logs' => __('信息', 'rss-news-importer'),
        'sort_by_date' => __('按日期排序', 'rss-news-importer'),
        'ascending' => __('升序', 'rss-news-importer'),
        'descending' => __('降序', 'rss-news-importer'),
        'dark_mode' => __('暗色模式', 'rss-news-importer'),
        'light_mode' => __('亮色模式', 'rss-news-importer'),
        'refresh_logs' => __('刷新日志', 'rss-news-importer'),
        'pause_refresh' => __('暂停自动刷新', 'rss-news-importer'),
        'resume_refresh' => __('恢复自动刷新', 'rss-news-importer'),
        'log_viewer' => __('日志查看器', 'rss-news-importer'),
        'statistics' => __('统计信息', 'rss-news-importer'),
        'logs_refreshed' => __('日志已刷新', 'rss-news-importer'),
        'error_refreshing' => __('刷新日志失败', 'rss-news-importer'),
    ),
));
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    ReactDOM.render(
        React.createElement(LogViewer, {
            translations: rss_news_importer_ajax.i18n,
            ajaxUrl: rss_news_importer_ajax.ajax_url,
            nonce: rss_news_importer_ajax.nonce
        }),
        document.getElementById('log-viewer-root')
    );
});
</script>