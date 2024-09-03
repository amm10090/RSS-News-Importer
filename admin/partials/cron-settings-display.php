<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 获取RSS更新管理器实例
$rss_update_manager = new RSS_News_Importer_Cron_Manager($this->plugin_name, $this->version);

// 获取当前RSS更新设置
$options = get_option('rss_news_importer_options', array());
$current_schedule = isset($options['rss_update_schedule']) ? $options['rss_update_schedule'] : 'hourly';
$next_run = $rss_update_manager->get_next_scheduled_time();
$cron_status = $rss_update_manager->get_cron_status();
$available_schedules = wp_get_schedules();
$update_method = isset($options['update_method']) ? $options['update_method'] : 'bulk';
$custom_interval = isset($options['custom_cron_interval']) ? $options['custom_cron_interval'] : '';

// 处理手动更新请求
if (isset($_POST['run_rss_update_now'])) {
    if (!check_admin_referer('rss_news_importer_run_update', 'rss_news_importer_run_nonce')) {
        wp_die(__('安全检查失败', 'rss-news-importer'));
    }

    // 执行手动更新
    $result = $rss_update_manager->manual_update();

    if ($result) {
        add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('RSS更新任务已手动触发。请查看日志以了解详情。', 'rss-news-importer'), 'updated');
    } else {
        add_settings_error('rss_news_importer_messages', 'rss_news_importer_error', __('触发RSS更新任务失败。请查看日志以了解详情。', 'rss-news-importer'), 'error');
    }
}

// 显示设置错误消息
settings_errors('rss_news_importer_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Cron设置表单 -->
    <form id="rss-cron-settings-form" method="post" action="">
        <?php wp_nonce_field('rss_news_importer_cron_settings', 'rss_news_importer_cron_nonce'); ?> <table class="form-table">
            <tr>
                <th scope="row"><label for="rss_update_schedule"><?php _e('RSS更新频率', 'rss-news-importer'); ?></label></th>
                <td>
                    <select name="rss_update_schedule" id="rss_update_schedule">
                        <?php foreach ($available_schedules as $schedule => $schedule_data) : ?>
                            <option value="<?php echo esc_attr($schedule); ?>" <?php selected($current_schedule, $schedule); ?>>
                                <?php echo esc_html($schedule_data['display']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom" <?php selected($current_schedule, 'custom'); ?>>
                            <?php _e('自定义', 'rss-news-importer'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr id="custom_interval_row" style="display: none;">
                <th scope="row"><label for="custom_cron_interval"><?php _e('自定义间隔（分钟）', 'rss-news-importer'); ?></label></th>
                <td>
                    <input type="number" name="custom_cron_interval" id="custom_cron_interval"
                        value="<?php echo esc_attr($custom_interval); ?>"
                        min="1" step="1"
                        <?php echo ($current_schedule !== 'custom') ? 'disabled' : ''; ?>>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="update_method"><?php _e('更新方法', 'rss-news-importer'); ?></label></th>
                <td>
                    <select name="update_method" id="update_method">
                        <option value="bulk" <?php selected($update_method, 'bulk'); ?>><?php _e('批量更新', 'rss-news-importer'); ?></option>
                        <option value="individual" <?php selected($update_method, 'individual'); ?>><?php _e('单独更新', 'rss-news-importer'); ?></option>
                    </select>
                    <p class="description"><?php _e('批量更新会一次性更新所有源，单独更新会逐个处理每个源。', 'rss-news-importer'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('保存RSS更新设置', 'rss-news-importer'), 'primary', 'submit_rss_cron_settings'); ?>
    </form>

    <!-- 设置消息显示区域 -->
    <div id="settings-message" style="display:none;"></div>

    <!-- RSS更新状态显示 -->
    <h2><?php _e('RSS更新状态', 'rss-news-importer'); ?></h2>
    <table class="widefat">
        <tr>
            <th><?php _e('下次计划更新', 'rss-news-importer'); ?></th>
            <td><?php echo $next_run ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)) : __('未计划', 'rss-news-importer'); ?></td>
        </tr>
        <tr>
            <th><?php _e('当前更新计划', 'rss-news-importer'); ?></th>
            <td><?php echo esc_html($available_schedules[$current_schedule]['display'] ?? __('自定义', 'rss-news-importer')); ?></td>
        </tr>
        <tr>
            <th><?php _e('上次更新时间', 'rss-news-importer'); ?></th>
            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $cron_status['last_update'])); ?></td>
        </tr>
        <tr>
            <th><?php _e('当前更新方法', 'rss-news-importer'); ?></th>
            <td><?php echo $update_method === 'bulk' ? __('批量更新', 'rss-news-importer') : __('单独更新', 'rss-news-importer'); ?></td>
        </tr>
    </table>

    <!-- 手动RSS更新表单 -->
    <h2><?php _e('手动RSS更新', 'rss-news-importer'); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field('rss_news_importer_run_update', 'rss_news_importer_run_nonce'); ?>
        <p>
            <input type="submit" name="run_rss_update_now" class="button-secondary" value="<?php _e('立即更新RSS源', 'rss-news-importer'); ?>">
            <?php _e('点击此按钮手动触发RSS更新任务。', 'rss-news-importer'); ?>
        </p>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // 切换自定义间隔字段的显示状态
        function toggleCustomIntervalField() {
            if ($('#rss_update_schedule').val() === 'custom') {
                $('#custom_interval_row').show();
            } else {
                $('#custom_interval_row').hide();
            }
        }
        //当选择"自定义"时启用自定义间隔输入
        $('#rss_update_schedule').change(function() {
            if ($(this).val() === 'custom') {
                $('#custom_cron_interval').prop('disabled', false);
            } else {
                $('#custom_cron_interval').prop('disabled', true);
            }
        });

        // 绑定更新频率选择变化事件
        $('#rss_update_schedule').change(toggleCustomIntervalField);
        toggleCustomIntervalField(); // 页面加载时执行一次

        // 处理表单提交
        $('#rss-cron-settings-form').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=save_rss_cron_settings&security=' + $('#rss_news_importer_cron_nonce').val();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        $('#settings-message').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                    } else {
                        $('#settings-message').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                    $('#settings-message').html('<div class="notice notice-error"><p><?php _e('保存设置时发生错误。请稍后重试。', 'rss-news-importer'); ?></p></div>').show();
                }
            });
        });
    });
</script>