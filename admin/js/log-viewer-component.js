(function (window, document, $) {
    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const { useState, useEffect, useCallback } = React;

    function LogViewer() {
        const [logs, setLogs] = useState([]);
        const [filteredLogs, setFilteredLogs] = useState([]);
        const [searchTerm, setSearchTerm] = useState('');
        const [filterType, setFilterType] = useState('all');
        const [sortOrder, setSortOrder] = useState('desc');
        const [currentPage, setCurrentPage] = useState(1);
        const [logsPerPage] = useState(20);

        const fetchLogs = useCallback(() => {
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
        }, []);

        useEffect(() => {
            fetchLogs();
        }, [fetchLogs]);

        useEffect(() => {
            if (Array.isArray(logs)) {
                const filtered = logs.filter(log =>
                    (filterType === 'all' || log.type.toLowerCase() === filterType) &&
                    (log.message.toLowerCase().includes(searchTerm.toLowerCase()) ||
                        log.date.toLowerCase().includes(searchTerm.toLowerCase()))
                );
                const sorted = [...filtered].sort((a, b) =>
                    sortOrder === 'asc' ? new Date(a.date) - new Date(b.date) : new Date(b.date) - new Date(a.date)
                );
                setFilteredLogs(sorted);
                setCurrentPage(1);
            }
        }, [logs, searchTerm, filterType, sortOrder]);
        const clearLogs = () => {
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
                default: return '#3498db';
            }
        };

        const paginate = (pageNumber) => setCurrentPage(pageNumber);

        const indexOfLastLog = currentPage * logsPerPage;
        const indexOfFirstLog = indexOfLastLog - logsPerPage;
        const currentLogs = filteredLogs.slice(indexOfFirstLog, indexOfLastLog);

        const styles = {
            logViewer: {
                fontFamily: 'Arial, sans-serif',
                maxWidth: '100%',
                margin: '0 auto',
                padding: '20px',
                boxSizing: 'border-box'
            },
            controlsContainer: {
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginBottom: '20px'
            },
            input: {
                padding: '8px',
                fontSize: '14px',
                border: '1px solid #ddd',
                borderRadius: '4px'
            },
            select: {
                padding: '8px',
                fontSize: '14px',
                border: '1px solid #ddd',
                borderRadius: '4px',
                backgroundColor: 'white'
            },
            button: {
                padding: '8px 16px',
                fontSize: '14px',
                border: 'none',
                borderRadius: '4px',
                backgroundColor: '#3498db',
                color: 'white',
                cursor: 'pointer'
            },
            logList: {
                border: '1px solid #ddd',
                borderRadius: '4px',
                overflow: 'hidden'
            },
            logItem: {
                padding: '10px',
                borderBottom: '1px solid #eee',
                display: 'flex',
                alignItems: 'center'
            },
            logDate: {
                flexBasis: '180px',
                color: '#7f8c8d'
            },
            logType: {
                flexBasis: '80px',
                textAlign: 'center',
                padding: '2px 6px',
                borderRadius: '4px',
                marginRight: '10px'
            },
            logMessage: {
                flex: 1
            },
            paginationContainer: {
                display: 'flex',
                justifyContent: 'center',
                marginTop: '20px'
            },
            paginationButton: {
                padding: '5px 10px',
                margin: '0 5px',
                border: '1px solid #ddd',
                borderRadius: '4px',
                cursor: 'pointer'
            },
            paginationAndControlsContainer: {
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginTop: '20px'
            }
        };

        return React.createElement(
            'div',
            { style: styles.logViewer },
            React.createElement(
                'div',
                { style: styles.controlsContainer },
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
                )
            ),
            React.createElement(
                'div',
                { style: styles.paginationAndControlsContainer },
                React.createElement(
                    'div',
                    { style: styles.paginationContainer },
                    Array.from({ length: Math.ceil(filteredLogs.length / logsPerPage) }, (_, i) =>
                        React.createElement(
                            'button',
                            {
                                key: i,
                                onClick: () => paginate(i + 1),
                                style: {
                                    ...styles.paginationButton,
                                    backgroundColor: currentPage === i + 1 ? '#3498db' : '#f0f0f0',
                                    color: currentPage === i + 1 ? 'white' : 'black'
                                }
                            },
                            i + 1
                        )
                    )
                ),
                React.createElement(
                    'div',
                    { style: { display: 'flex', gap: '5px' } },
                    React.createElement(
                        'button',
                        {
                            onClick: () => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc'),
                            style: styles.button
                        },
                        sortOrder === 'asc' ? '升序' : '降序'
                    ),
                    React.createElement(
                        'button',
                        { onClick: fetchLogs, style: styles.button },
                        '刷新日志'
                    ),
                    React.createElement(
                        'button',
                        { onClick: clearLogs, style: { ...styles.button, backgroundColor: '#e74c3c' } },
                        '删除日志'
                    )
                )
            ),
            React.createElement(
                'div',
                { style: styles.logList },
                currentLogs.map((log, index) =>
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
    }

    window.LogViewer = LogViewer;
})(window, document, jQuery);