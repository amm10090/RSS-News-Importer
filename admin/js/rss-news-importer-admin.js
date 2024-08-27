console.log('JS文件已加载');
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

    // 拖拽排序功能
    $('#rss-feeds-list').sortable({
        handle: '.handle',
        update: function(event, ui) {
            var feedOrder = $(this).sortable('toArray', {attribute: 'data-feed-url'});
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_update_feed_order',
                    order: feedOrder,
                    security: rss_news_importer_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showFeedback(response.data, 'success');
                    } else {
                        showFeedback(response.data, 'error');
                    }
                },
                error: function() {
                    showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        }
    });

    // 添加新的RSS源
    $('#add-feed').on('click', function() {
        var feedUrl = $('#new-feed-url').val();
        var feedName = $('#new-feed-name').val();
        if (feedUrl) {
            addFeed(feedUrl, feedName);
            $('#new-feed-url').val('');
            $('#new-feed-name').val('');
        }
    });

    // 删除RSS源
    $('#rss-feeds-list').on('click', '.remove-feed', function() {
        var feedItem = $(this).closest('.feed-item');
        var feedUrl = feedItem.data('feed-url');
        removeFeed(feedUrl, feedItem);
    });

    // 预览RSS源
    $('#rss-feeds-list').on('click', '.preview-feed', function() {
        var feedUrl = $(this).closest('.feed-item').data('feed-url');
        previewFeed(feedUrl);
    });

    // 立即导入
    $('#import-now').on('click', function() {
        importNow();
    });

    // 查看日志
    $('#view-logs').on('click', function() {
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
            success: function(response) {
                if (response.success) {
                    var feedItem = $('<div class="feed-item" data-feed-url="' + response.data.feed_url + '"></div>');
                    feedItem.html('<span class="dashicons dashicons-menu handle"></span>' +
                        '<input type="text" name="' + rss_news_importer_ajax.option_name + '[rss_feeds][]" value="' + response.data.feed_url + '" readonly>' +
                        '<input type="text" name="' + rss_news_importer_ajax.option_name + '[rss_feeds][]" value="' + (response.data.feed_name || '') + '" placeholder="Feed Name (optional)">' +
                        '<button class="button remove-feed">' + rss_news_importer_ajax.i18n.remove_text + '</button>' +
                        '<button class="button preview-feed">预览</button>');
                    $('#rss-feeds-list').append(feedItem);
                    showFeedback(response.data.message, 'success');
                } else {
                    showFeedback(response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
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
            success: function(response) {
                if (response.success) {
                    feedItem.remove();
                    showFeedback(response.data, 'success');
                } else {
                    showFeedback(response.data, 'error');
                }
            },
            error: function() {
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
            success: function(response) {
                if (response.success) {
                    $('#feed-preview').html(response.data).show();
                } else {
                    showFeedback('预览RSS源时出错。', 'error');
                }
            },
            error: function() {
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
            success: function(response) {
                if (response.success) {
                    $('#import-results').html(response.data);
                } else {
                    showFeedback(response.data, 'error');
                }
                $('#import-now').prop('disabled', false).text('立即导入');
            },
            error: function() {
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
            success: function(response) {
                if (response.success) {
                    $('#import-logs').html(response.data);
                } else {
                    showFeedback('加载日志时出错。', 'error');
                }
            },
            error: function() {
                showFeedback('加载日志时出错。', 'error');
            }
        });
    }

    // 显示反馈信息的函数
    function showFeedback(message, type = 'success') {
        var feedback = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap.rss-news-importer-admin').prepend(feedback);
        setTimeout(function() {
            feedback.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
});
$('#rss-news-importer-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    
    // 添加调试日志
    console.log('Form data:', formData);

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        success: function(response) {
            // 处理成功响应
            console.log('Save success:', response);
        },
        error: function(xhr, status, error) {
            // 处理错误
            console.error('Save error:', error);
        }
    });
});