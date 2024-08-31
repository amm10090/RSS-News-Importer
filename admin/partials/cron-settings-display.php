<?php
// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap" style="max-width: 800px; margin: 20px auto; font-family: Arial, sans-serif;">
    <h1 style="color: #23282d; font-size: 28px; border-bottom: 2px solid #23282d; padding-bottom: 10px;">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <div style="background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
        <form method="post" action="options.php">
            <?php
            settings_fields('rss_news_importer_cron_settings');
            do_settings_sections('rss_news_importer_cron_settings');
            ?>
            <table class="form-table" style="width: 100%;">
                <tr valign="top">
                    <th scope="row" style="padding: 20px 0;">
                        <label style="font-weight: bold; color: #23282d;"><?php _e('Update Frequency', 'rss-news-importer'); ?></label>
                    </th>
                    <td style="padding: 20px 0;">
                        <select name="rss_news_importer_cron_schedule" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <?php foreach ($available_schedules as $key => $display) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_schedule, $key); ?>><?php echo esc_html($display); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" style="padding: 20px 0;">
                        <label style="font-weight: bold; color: #23282d;"><?php _e('Next Scheduled Run', 'rss-news-importer'); ?></label>
                    </th>
                    <td style="padding: 20px 0;">
                        <span style="display: inline-block; padding: 10px; background: #f1f1f1; border-radius: 4px;">
                            <?php echo $next_run ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run) : __('Not scheduled', 'rss-news-importer'); ?>
                        </span>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Settings', 'rss-news-importer'), 'primary', 'submit', true, array('style' => 'background: #0073aa; border-color: #0073aa; box-shadow: none; text-shadow: none; transition: all 0.3s ease;')); ?>
        </form>
    </div>

    <div style="background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
        <h2 style="color: #23282d; font-size: 20px; margin-bottom: 15px;"><?php _e('Manual Import', 'rss-news-importer'); ?></h2>
        <p style="margin-bottom: 20px;"><?php _e('Click the button below to manually run the RSS import process.', 'rss-news-importer'); ?></p>
        <button id="run-import-now" class="button button-primary" style="background: #0073aa; border-color: #0073aa; box-shadow: none; text-shadow: none; padding: 10px 20px; font-size: 16px; transition: all 0.3s ease;">
            <?php _e('Run Import Now', 'rss-news-importer'); ?>
        </button>
    </div>

    <div id="import-status" style="background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px; display: none;">
        <h3 style="color: #23282d; font-size: 18px; margin-bottom: 15px;"><?php _e('Import Status', 'rss-news-importer'); ?></h3>
        <div id="import-progress" style="padding: 15px; background: #f1f1f1; border-radius: 4px; transition: all 0.3s ease;"></div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#run-import-now').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).css('opacity', '0.7').text('<?php _e('Running Import...', 'rss-news-importer'); ?>');
            $('#import-status').fadeIn(300);
            $('#import-progress').text('<?php _e('Import process started...', 'rss-news-importer'); ?>').css('background', '#fff9c4');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_run_cron_now',
                    security: '<?php echo wp_create_nonce("rss_news_importer_run_cron_now"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#import-progress').text(response.data).css('background', '#e8f5e9');
                    } else {
                        $('#import-progress').text('<?php _e('Error: ', 'rss-news-importer'); ?>' + response.data).css('background', '#ffebee');
                    }
                    button.prop('disabled', false).css('opacity', '1').text('<?php _e('Run Import Now', 'rss-news-importer'); ?>');
                },
                error: function() {
                    $('#import-progress').text('<?php _e('An error occurred while running the import.', 'rss-news-importer'); ?>').css('background', '#ffebee');
                    button.prop('disabled', false).css('opacity', '1').text('<?php _e('Run Import Now', 'rss-news-importer'); ?>');
                }
            });
        });

        $('form').on('submit', function() {
            $(this).find('input[type="submit"]').css('opacity', '0.7');
        });

        $('select, input[type="submit"], #run-import-now').hover(
            function() {
                $(this).css('transform', 'translateY(-2px)');
            },
            function() {
                $(this).css('transform', 'translateY(0)');
            }
        );
    });
</script>