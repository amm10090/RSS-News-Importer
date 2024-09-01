<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option($this->option_name);
$error_retry = isset($options['error_retry']) ? intval($options['error_retry']) : 3;
$error_notify = isset($options['error_notify']) ? sanitize_email($options['error_notify']) : '';
?>

<p>
    <label for="error_retry"><?php _e('错误重试次数:', 'rss-news-importer'); ?></label>
    <input type="number" id="error_retry" name="<?php echo $this->option_name; ?>[error_retry]" value="<?php echo esc_attr($error_retry); ?>" min="0" max="10" step="1">
</p>
<p class="description"><?php _e('设置导入失败时的重试次数。', 'rss-news-importer'); ?></p>

<p>
    <label for="error_notify"><?php _e('错误通知邮箱:', 'rss-news-importer'); ?></label>
    <input type="email" id="error_notify" name="<?php echo $this->option_name; ?>[error_notify]" value="<?php echo esc_attr($error_notify); ?>">
</p>
<p class="description"><?php _e('设置接收错误通知的邮箱地址。留空表示不发送通知。', 'rss-news-importer'); ?></p>

<?php
// 如果您的插件有查看错误日志的功能，可以在这里添加一个查看日志的链接
?>
<p>
    <a href="#" id="view_error_log" class="button"><?php _e('查看错误日志', 'rss-news-importer'); ?></a>
</p>
<div id="error_log_content" style="display:none;">
    <textarea readonly style="width:100%; height:200px;">
        <?php
        // 这里添加获取和显示错误日志的代码
        echo "错误日志内容将显示在这里";
        ?>
    </textarea>
</div>
<script>
    jQuery(document).ready(function($) {
        $('#view_error_log').on('click', function(e) {
            e.preventDefault();
            $('#error_log_content').toggle();
        });
    });
</script>