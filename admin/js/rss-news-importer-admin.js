(function ($) {
    'use strict';

    $(document).ready(function () {
        // 选项卡切换功能
        $('.nav-tab-wrapper .nav-tab').on('click', function (e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.tab-content .tab-pane').removeClass('active');
            $(target).addClass('active');

            // 如果切换到日志选项卡，加载日志内容
            if (target === '#logs') {
                viewLogs();
                loadLogViewer();
            }
        });

        // 初始化已保存的RSS源
        $('#rss-feeds-list .feed-item').each(function () {
            var feedUrl = $(this).find('.feed-url').val();
            var feedName = $(this).find('.feed-name').val();
            console.log('Loaded feed:', feedUrl, feedName);
        });

        // 拖拽排序功能
        $('#rss-feeds-list').sortable({
            handle: '.handle',
            update: function (event, ui) {
                var feedOrder = $(this).sortable('toArray', { attribute: 'data-feed-url' });
                $.ajax({
                    url: rss_news_importer_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rss_news_importer_update_feed_order',
                        order: feedOrder,
                        security: rss_news_importer_ajax.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            showFeedback(response.data, 'success');
                        } else {
                            showFeedback(response.data, 'error');
                        }
                    },
                    error: function () {
                        showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                    }
                });
            }
        });

        // 添加新的RSS源
        $('#add-feed').on('click', function () {
            var feedUrl = $('#new-feed-url').val();
            var feedName = $('#new-feed-name').val();
            if (feedUrl) {
                addFeed(feedUrl, feedName);
                $('#new-feed-url').val('');
                $('#new-feed-name').val('');
            }
        });

        // 删除RSS源
        $('#rss-feeds-list').on('click', '.remove-feed', function () {
            var feedItem = $(this).closest('.feed-item');
            var feedUrl = feedItem.data('feed-url');
            removeFeed(feedUrl, feedItem);
        });

        // 预览RSS源
        $('#rss-feeds-list').on('click', '.preview-feed', function () {
            var feedUrl = $(this).closest('.feed-item').data('feed-url');
            previewFeed(feedUrl);
        });

        // 立即导入
        $('#import-now').on('click', function () {
            importNow();
        });

        // 查看日志
        $('#view-logs').on('click', function () {
            viewLogs();
        });

        // 添加RSS源的函数
        function addFeed(feedUrl, feedName) {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_add_feed',
                    feed_url: feedUrl,
                    feed_name: feedName,
                    security: rss_news_importer_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var feedItem = $('<div class="feed-item" data-feed-url="' + response.data.feed_url + '"></div>');
                        feedItem.html('<span class="dashicons dashicons-menu handle"></span>' +
                            '<input type="text" name="' + rss_news_importer_ajax.option_name + '[rss_feeds][]" value="' + response.data.feed_url + '" readonly class="feed-url">' +
                            '<input type="text" name="' + rss_news_importer_ajax.option_name + '[rss_feeds][]" value="' + (response.data.feed_name || '') + '" placeholder="Feed Name (optional)" class="feed-name">' +
                            '<button class="button remove-feed">' + rss_news_importer_ajax.i18n.remove_text + '</button>' +
                            '<button class="button preview-feed">预览</button>');
                        $('#rss-feeds-list').append(feedItem);
                        showFeedback(response.data.message, 'success');
                    } else {
                        showFeedback(response.data, 'error');
                    }
                },
                error: function () {
                    showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        }

        // 删除RSS源的函数
        function removeFeed(feedUrl, feedItem) {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_remove_feed',
                    feed_url: feedUrl,
                    security: rss_news_importer_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        feedItem.remove();
                        showFeedback(response.data, 'success');
                    } else {
                        showFeedback(response.data, 'error');
                    }
                },
                error: function () {
                    showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        }

        // 预览RSS源的函数
        function previewFeed(feedUrl) {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_preview_feed',
                    feed_url: feedUrl,
                    security: rss_news_importer_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#feed-preview').html(response.data).show();
                    } else {
                        showFeedback('预览RSS源时出错。', 'error');
                    }
                },
                error: function () {
                    showFeedback('预览RSS源时出错。', 'error');
                }
            });
        }

        // 立即导入的函数
        function importNow() {
            $('#import-now').prop('disabled', true).text(rss_news_importer_ajax.i18n.importing_text);
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_import_now',
                    security: rss_news_importer_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#import-results').html(response.data);
                    } else {
                        showFeedback(response.data, 'error');
                    }
                    $('#import-now').prop('disabled', false).text('立即导入');
                },
                error: function () {
                    showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                    $('#import-now').prop('disabled', false).text('立即导入');
                }
            });
        }

        // 查看日志的函数
        function viewLogs() {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_view_logs',
                    security: rss_news_importer_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#import-logs').html(response.data);
                    } else {
                        showFeedback('加载日志时出错。', 'error');
                    }
                },
                error: function () {
                    showFeedback('加载日志时出错。', 'error');
                }
            });
        }

        // 显示反馈信息的函数
        function showFeedback(message, type = 'success') {
            var feedback = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap.rss-news-importer-admin').prepend(feedback);
            setTimeout(function () {
                feedback.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        }

        // 保存表单
        $('#rss-news-importer-form').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();

            console.log('Form data:', formData);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function (response) {
                    console.log('Save success:', response);
                    showFeedback('设置已保存', 'success');
                },
                error: function (xhr, status, error) {
                    console.error('Save error:', error);
                    showFeedback('保存设置时出错', 'error');
                }
            });
        });

        // 加载 LogViewer React 组件
        function loadLogViewer() {
            if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined' && typeof LogViewer !== 'undefined') {
                ReactDOM.render(
                    React.createElement(LogViewer),
                    document.getElementById('log-viewer-root')
                );
            } else {
                console.error('React, ReactDOM, or LogViewer component is not loaded. Make sure to enqueue these scripts.');
                $('#log-viewer-root').html('<p class="error-message">Error: Unable to load log viewer. Please check the console for more information.</p>');
            }
        }

        // 初始化：如果默认显示日志选项卡，则加载日志
        if ($('#logs').hasClass('active')) {
            viewLogs();
            loadLogViewer();
        }
    });
})(jQuery);