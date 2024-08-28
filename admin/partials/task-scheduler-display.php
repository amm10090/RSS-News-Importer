<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Task Name', 'rss-news-importer'); ?></th>
                <th><?php _e('Recurrence', 'rss-news-importer'); ?></th>
                <th><?php _e('Next Run', 'rss-news-importer'); ?></th>
                <th><?php _e('Priority', 'rss-news-importer'); ?></th>
                <th><?php _e('Actions', 'rss-news-importer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $cron_manager = new RSS_News_Importer_Cron_Manager($this->plugin_name, $this->version);
            $tasks = $cron_manager->get_tasks();
            foreach ($tasks as $name => $task) :
            ?>
                <tr>
                    <td><?php echo esc_html($name); ?></td>
                    <td><?php echo esc_html($task['recurrence']); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $task['next_run'])); ?></td>
                    <td><?php echo esc_html($task['priority']); ?></td>
                    <td>
                        <button class="button run-now" data-task="<?php echo esc_attr($name); ?>"><?php _e('Run Now', 'rss-news-importer'); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>