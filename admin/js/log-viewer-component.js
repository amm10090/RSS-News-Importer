(function (window, document, $) {
    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const { useState, useEffect } = React;

    window.LogViewer = function () {
        const [logs, setLogs] = useState([]);
        const [filteredLogs, setFilteredLogs] = useState([]);
        const [searchTerm, setSearchTerm] = useState('');
        const [filterType, setFilterType] = useState('all');
        const [sortOrder, setSortOrder] = useState('desc');

        useEffect(() => {
            fetchLogs();
        }, []);

        useEffect(() => {
            const filtered = logs.filter(log =>
                (filterType === 'all' || log.type.toLowerCase() === filterType) &&
                (log.message.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    log.date.toLowerCase().includes(searchTerm.toLowerCase()))
            );
            const sorted = [...filtered].sort((a, b) =>
                sortOrder === 'asc' ? new Date(a.date) - new Date(b.date) : new Date(b.date) - new Date(a.date)
            );
            setFilteredLogs(sorted);
        }, [logs, searchTerm, filterType, sortOrder]);

        const fetchLogs = () => {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_get_logs',
                    security: rss_news_importer_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        setLogs(response.data);
                    } else {
                        console.error('Failed to fetch logs:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Ajax request failed:', error);
                }
            });
        };

        const clearlogs = () => {
            if (confirm('Are you sure you want to delete all logs?')) {
                $.ajax({
                    url: rss_news_importer_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rss_news_importer_clear_logs',
                        security: rss_news_importer_ajax.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            setLogs([]);
                            setFilteredLogs([]);
                            alert('All logs have been deleted.');
                        } else {
                            alert('Failed to delete logs: ' + response.data);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Failed to delete logs. Please try again.');
                    }
                });
            }
        };

        const getLogTypeIcon = (type) => {
            switch (type.toLowerCase()) {
                case 'info': return 'ℹ️';
                case 'debug': return '🐞';
                case 'error': return '⚠️';
                default: return 'ℹ️';
            }
        };

        const getLogTypeColor = (type) => {
            switch (type.toLowerCase()) {
                case 'info': return '#3498db';
                case 'debug': return '#2ecc71';
                case 'error': return '#e74c3c';
                default: return '#95a5a6';
            }
        };

        const styles = {
            logViewer: {
                fontFamily: 'Arial, sans-serif',
                maxWidth: '800px',
                margin: '0 auto',
                padding: '20px',
                backgroundColor: '#f5f5f5',
                borderRadius: '8px',
                boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
            },
            controls: {
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginBottom: '20px'
            },
            input: {
                padding: '8px 12px',
                borderRadius: '4px',
                border: '1px solid #ddd',
                marginRight: '10px'
            },
            select: {
                padding: '8px 12px',
                borderRadius: '4px',
                border: '1px solid #ddd',
                marginRight: '10px'
            },
            button: {
                padding: '8px 12px',
                borderRadius: '4px',
                border: 'none',
                backgroundColor: '#3498db',
                color: 'white',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '5px',
                marginLeft: '10px'
            },
            deleteButton: {
                backgroundColor: '#e74c3c'
            },
            logList: {
                display: 'flex',
                flexDirection: 'column',
                gap: '10px'
            },
            logItem: {
                backgroundColor: 'white',
                padding: '15px',
                borderRadius: '4px',
                boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
                display: 'flex',
                alignItems: 'center',
                gap: '10px'
            },
            logDate: {
                fontSize: '0.9em',
                color: '#7f8c8d'
            },
            logType: {
                display: 'flex',
                alignItems: 'center',
                gap: '5px',
                fontWeight: 'bold',
                padding: '3px 8px',
                borderRadius: '12px',
                fontSize: '0.8em'
            },
            logMessage: {
                flex: 1
            }
        };

        return React.createElement(
            'div',
            { style: styles.logViewer },
            React.createElement(
                'div',
                { style: styles.controls },
                React.createElement(
                    'div',
                    { style: { display: 'flex', gap: '10px' } },
                    React.createElement('input', {
                        type: 'text',
                        placeholder: '搜索日志...',
                        value: searchTerm,
                        onChange: (e) => setSearchTerm(e.target.value),
                        style: styles.input
                    }),
                    React.createElement(
                        'select',
                        {
                            value: filterType,
                            onChange: (e) => setFilterType(e.target.value),
                            style: styles.select
                        },
                        React.createElement('option', { value: 'all' }, '所有类型'),
                        React.createElement('option', { value: 'info' }, '信息'),
                        React.createElement('option', { value: 'debug' }, '调试'),
                        React.createElement('option', { value: 'error' }, '错误')
                    )
                ),
                React.createElement(
                    'div',
                    { style: { display: 'flex', gap: '10px' } },
                    React.createElement(
                        'button',
                        {
                            onClick: () => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc'),
                            style: styles.button
                        },
                        sortOrder === 'asc' ? '↑ 升序' : '↓ 降序'
                    ),
                    React.createElement(
                        'button',
                        { onClick: fetchLogs, style: styles.button },
                        '🔄 刷新日志'
                    ),
                    React.createElement(
                        'button',
                        { onClick: clearlogs, style: { ...styles.button, ...styles.deleteButton } },
                        '🗑️ 删除日志'
                    )
                )
            ),
            React.createElement(
                'div',
                { style: styles.logList },
                filteredLogs.map((log, index) =>
                    React.createElement(
                        'div',
                        { key: index, style: styles.logItem },
                        React.createElement('span', { style: styles.logDate }, log.date),
                        React.createElement(
                            'span',
                            {
                                style: {
                                    ...styles.logType,
                                    backgroundColor: getLogTypeColor(log.type),
                                    color: 'white'
                                }
                            },
                            getLogTypeIcon(log.type),
                            log.type
                        ),
                        React.createElement('span', { style: styles.logMessage }, log.message)
                    )
                )
            )
        );
    };
})(window, document, jQuery);