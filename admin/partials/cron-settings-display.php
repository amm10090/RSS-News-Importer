<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 获取定时任务管理器实例
$cron_manager = new RSS_News_Importer_Cron_Manager($this->plugin_name, $this->version);

// 获取队列管理器实例
$queue_manager = new RSS_News_Importer_Queue();

// 获取当前定时任务设置
$current_schedule = $cron_manager->get_current_schedule();
$next_run = $cron_manager->get_next_scheduled_time();
$available_schedules = $cron_manager->get_available_schedules();

// 获取队列信息
$queue_size = $queue_manager->get_queue_size();
$max_queue_size = $queue_manager->get_max_queue_size();
$is_queue_full = $queue_manager->is_queue_full();

// 处理表单提交
if (isset($_POST['update_cron_settings'])) {
    check_admin_referer('rss_news_importer_cron_settings');
    
    $new_schedule = sanitize_text_field($_POST['cron_schedule']);
    $cron_manager->update_schedule($new_schedule);
    
    $new_max_queue_size = intval($_POST['max_queue_size']);
    $queue_manager->set_max_queue_size($new_max_queue_size);
    
    echo '<div class="updated"><p>设置已更新。</p></div>';
}

if (isset($_POST['clear_queue'])) {
    check_admin_referer('rss_news_importer_clear_queue');
    if ($queue_manager->clear_queue()) {
        echo '<div class="updated"><p>队列已清空。</p></div>';
    } else {
        echo '<div class="error"><p>清空队列失败。</p></div>';
    }
}

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('rss_news_importer_cron_settings'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="cron_schedule">导入频率</label></th>
                <td>
                    <select name="cron_schedule" id="cron_schedule">
                        <?php foreach ($available_schedules as $schedule => $display) : ?>
                            <option value="<?php echo esc_attr($schedule); ?>" <?php selected($current_schedule, $schedule); ?>>
                                <?php echo esc_html($display); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="max_queue_size">最大队列大小</label></th>
                <td>
                    <input type="number" name="max_queue_size" id="max_queue_size" value="<?php echo esc_attr($max_queue_size); ?>" min="1" step="1">
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="update_cron_settings" class="button-primary" value="保存设置">
        </p>
    </form>
    
    <h2>队列状态</h2>
    <p>当前队列大小: <?php echo esc_html($queue_size); ?></p>
    <p>队列是否已满: <?php echo $is_queue_full ? '是' : '否'; ?></p>
    
    <form method="post" action="">
        <?php wp_nonce_field('rss_news_importer_clear_queue'); ?>
        <p class="submit">
            <input type="submit" name="clear_queue" class="button-secondary" value="清空队列" onclick="return confirm('确定要清空队列吗？');">
        </p>
    </form>
    
    <h2>下次计划运行时间</h2>
    <p>
        <?php 
        if ($next_run) {
            echo '下次运行时间: ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run));
        } else {
            echo '未设置计划任务。';
        }
        ?>
    </p>
    
    <h2>手动运行导入</h2>
    <p>点击下面的按钮立即运行导入任务：</p>
    <form method="post" action="">
        <?php wp_nonce_field('rss_news_importer_run_import'); ?>
        <p class="submit">
            <input type="submit" name="run_import_now" class="button-secondary" value="立即运行导入">
        </p>
    </form>
</div>

<?php
// 处理手动运行导入
if (isset($_POST['run_import_now'])) {
    check_admin_referer('rss_news_importer_run_import');
    $cron_manager->run_import_now();
    echo '<div class="updated"><p>导入任务已手动触发。请查看日志以获取详细信息。</p></div>';
}
?>