(function($) {
    'use strict';

    $(function() {
        // 添加新的RSS源
        $('#add-feed').on('click', function() {
            var feedUrl = prompt(rss_news_importer_ajax.add_feed_prompt);
            if (feedUrl) {
                $.ajax({
                    url: rss_news_importer_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rss_news_importer_add_feed',
                        security: rss_news_importer_ajax.nonce,
                        feed_url: feedUrl
                    },
                    success: function(response) {
                        if (response.success) {
                            var newFeed = $('<div class="rss-feed-item">')
                                .append($('<input type="text" name="rss_news_importer_options[rss_feeds][]">').val(response.data.feed_url).prop('readonly', true))
                                .append($('<button type="button" class="button remove-feed">').text(rss_news_importer_ajax.remove_text))
                                .append($('<button type="button" class="button preview-feed">').text('Preview').data('feed-url', response.data.feed_url));
                            $('#rss-feeds').append(newFeed);
                        } else {
                            alert(response.data);
                        }
                    },
                    error: function() {
                        alert(rss_news_importer_ajax.error_text);
                    }
                });
            }
        });

        // 删除RSS源
        $(document).on('click', '.remove-feed', function() {
            var feedItem = $(this).closest('.rss-feed-item');
            var feedUrl = feedItem.find('input').val();

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_remove_feed',
                    security: rss_news_importer_ajax.nonce,
                    feed_url: feedUrl
                },
                success: function(response) {
                    if (response.success) {
                        feedItem.remove();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert(rss_news_importer_ajax.error_text);
                }
            });
        });

        // 立即导入功能
        $('#import-now').on('click', function() {
            var button = $(this);
            button.prop('disabled', true);
            $('#import-result').text(rss_news_importer_ajax.importing_text);
            $('#import-progress').removeClass('hidden').find('.progress-bar').css('width', '0%').text('0%');

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_import_now',
                    security: rss_news_importer_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#import-result').html('<div class="notice notice-success">' + response.data + '</div>');
                    } else {
                        $('#import-result').html('<div class="notice notice-error">' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#import-result').html('<div class="notice notice-error">' + rss_news_importer_ajax.error_text + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false);
                    $('#import-progress').addClass('hidden');
                }
            });

            // 模拟进度更新
            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += 10;
                if (progress > 100) {
                    clearInterval(progressInterval);
                } else {
                    $('#import-progress .progress-bar').css('width', progress + '%').text(progress + '%');
                }
            }, 500);
        });

        // 预览RSS源
        $(document).on('click', '.preview-feed', function() {
            var feedUrl = $(this).data('feed-url');
            $('#preview-modal').show();
            $('#preview-content').html('Loading preview...');

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_preview_feed',
                    security: rss_news_importer_ajax.nonce,
                    feed_url: feedUrl
                },
                success: function(response) {
                    if (response.success) {
                        $('#preview-content').html(response.data);
                    } else {
                        $('#preview-content').html('<div class="notice notice-error">' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#preview-content').html('<div class="notice notice-error">' + rss_news_importer_ajax.error_text + '</div>');
                }
            });
        });

        // 关闭预览模态框
        $('.close').on('click', function() {
            $('#preview-modal').hide();
        });

        // 点击模态框外部关闭
        $(window).on('click', function(event) {
            if ($(event.target).is('#preview-modal')) {
                $('#preview-modal').hide();
            }
        });

        // 查看日志
        $('#view-logs').on('click', function() {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_view_logs',
                    security: rss_news_importer_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#log-container').html(response.data);
                    } else {
                        $('#log-container').html('<div class="notice notice-error">' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#log-container').html('<div class="notice notice-error">' + rss_news_importer_ajax.error_text + '</div>');
                }
            });
        });
    });
})(jQuery);