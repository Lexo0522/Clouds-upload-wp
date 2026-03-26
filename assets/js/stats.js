document.addEventListener('DOMContentLoaded', function() {
    // 状态管理
    var state = {
        stats: null,
        charts: {},
        loading: false
    };
    
    // DOM 元素
    var elements = {
        // 快速概览
        cloudFilesCount: document.getElementById('cloud-files-count'),
        cloudStorageSize: document.getElementById('cloud-storage-size'),
        todayTraffic: document.getElementById('today-traffic'),
        uploadPercentage: document.getElementById('upload-percentage'),
        
        // 操作按钮
        refreshBtn: document.getElementById('refresh-stats'),
        lastUpdatedTime: document.getElementById('last-updated-time'),
        
        // 存储使用量
        localStorage: document.getElementById('local-storage'),
        cloudStorage: document.getElementById('cloud-storage'),
        savedStorage: document.getElementById('saved-storage'),
        syncPercentage: document.getElementById('sync-percentage'),
        
        // 文件统计
        totalFiles: document.getElementById('total-files'),
        uploadedFiles: document.getElementById('uploaded-files'),
        notUploadedFiles: document.getElementById('not-uploaded-files'),
        
        // 流量统计
        trafficToday: document.getElementById('traffic-today'),
        requestsToday: document.getElementById('requests-today'),
        trafficWeek: document.getElementById('traffic-week'),
        requestsWeek: document.getElementById('requests-week'),
        trafficMonth: document.getElementById('traffic-month'),
        requestsMonth: document.getElementById('requests-month'),
        
        // 流量类型表格
        trafficTypesBody: document.getElementById('traffic-types-body'),
        
        // 服务商信息
        providerName: document.getElementById('provider-name'),
        providerBucket: document.getElementById('provider-bucket'),
        providerRegion: document.getElementById('provider-region'),
        providerDomain: document.getElementById('provider-domain'),
        
        // 图表 Canvas
        storageChart: document.getElementById('storage-chart'),
        fileTypesCanvas: document.getElementById('file-types-canvas'),
        monthlyChart: document.getElementById('monthly-chart'),
        trafficChart: document.getElementById('traffic-chart')
    };
    
    // 初始化
    init();
    
    function init() {
        // 先加载统计数据，避免图表库失败时整页数据被阻塞
        loadStats();

        // 图表库是增强项，晚到也可以补绘
        loadChartJS(function() {
            if (state.stats) {
                renderCharts(state.stats);
            }
        });
        
        // 绑定事件
        bindEvents();
    }
    
    function bindEvents() {
        elements.refreshBtn.addEventListener('click', refreshStats);
    }
    
    // 加载 Chart.js 库
    function loadChartJS(callback) {
        if (window.Chart) {
            callback();
            return;
        }
        
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
        script.onload = callback;
        script.onerror = function() {
            console.error('Failed to load Chart.js');
            callback();
        };
        document.head.appendChild(script);
    }
    
    // 加载统计数据
    function loadStats() {
        if (state.loading) return;
        
        state.loading = true;
        setLoadingState(true);
        
        sendRequest('wpmcs_get_storage_stats', {}, function(response) {
            state.loading = false;
            setLoadingState(false);
            
            if (response.success) {
                state.stats = response.data;
                renderStats(response.data);
            } else {
                showError(response.data.message || '加载统计数据失败');
            }
        });
    }
    
    // 刷新统计数据
    function refreshStats() {
        if (state.loading) return;
        
        elements.refreshBtn.disabled = true;
        elements.refreshBtn.classList.add('updating');
        
        sendRequest('wpmcs_refresh_storage_stats', {}, function(response) {
            elements.refreshBtn.disabled = false;
            elements.refreshBtn.classList.remove('updating');
            
            if (response.success) {
                state.stats = response.data;
                renderStats(response.data);
                showSuccess('统计数据已刷新');
            } else {
                showError(response.data.message || '刷新失败');
            }
        });
    }
    
    // 渲染统计数据
    function renderStats(stats) {
        if (!stats) return;
        
        // 更新快速概览
        updateOverview(stats);
        
        // 更新存储使用量
        updateStorageStats(stats.storage);
        
        // 更新文件统计
        updateFileStats(stats.files);
        
        // 更新流量统计
        updateTrafficStats(stats.traffic);
        
        // 更新服务商信息
        updateProviderStats(stats.providers);
        
        // 更新最后更新时间
        if (stats.updated_at) {
            elements.lastUpdatedTime.textContent = stats.updated_at;
        }
        
        // 渲染图表
        renderCharts(stats);
    }
    
    // 更新快速概览
    function updateOverview(stats) {
        if (stats.files) {
            elements.cloudFilesCount.textContent = formatNumber(stats.files.cloud);
        }
        
        if (stats.storage) {
            elements.cloudStorageSize.textContent = stats.storage.cloud_size_formatted || '0 B';
        }
        
        if (stats.traffic && stats.traffic.today) {
            elements.todayTraffic.textContent = stats.traffic.today.bytes_formatted || '0 B';
        }
        
        if (stats.files) {
            elements.uploadPercentage.textContent = stats.files.upload_percentage + '%';
        }
    }
    
    // 更新存储使用量
    function updateStorageStats(storage) {
        if (!storage) return;
        
        elements.localStorage.textContent = storage.local_size_formatted || '0 B';
        elements.cloudStorage.textContent = storage.cloud_size_formatted || '0 B';
        elements.savedStorage.textContent = storage.local_size > storage.cloud_size 
            ? sizeFormat(storage.local_size - storage.cloud_size)
            : '0 B';
        elements.syncPercentage.textContent = storage.saved_percentage + '%';
    }
    
    // 更新文件统计
    function updateFileStats(files) {
        if (!files) return;
        
        elements.totalFiles.textContent = formatNumber(files.total);
        elements.uploadedFiles.textContent = formatNumber(files.cloud);
        elements.notUploadedFiles.textContent = formatNumber(files.local);
    }
    
    // 更新流量统计
    function updateTrafficStats(traffic) {
        if (!traffic) return;
        
        // 今日流量
        if (traffic.today) {
            elements.trafficToday.textContent = traffic.today.bytes_formatted || '0 B';
            elements.requestsToday.textContent = formatNumber(traffic.today.requests) + ' 次请求';
        }
        
        // 本周流量
        if (traffic.week) {
            elements.trafficWeek.textContent = traffic.week.bytes_formatted || '0 B';
            elements.requestsWeek.textContent = formatNumber(traffic.week.requests) + ' 次请求';
        }
        
        // 本月流量
        if (traffic.month) {
            elements.trafficMonth.textContent = traffic.month.bytes_formatted || '0 B';
            elements.requestsMonth.textContent = formatNumber(traffic.month.requests) + ' 次请求';
        }
        
        // 流量类型分布表格
        renderTrafficTypes(traffic.by_type);
    }
    
    // 渲染流量类型表格
    function renderTrafficTypes(byType) {
        if (!byType || Object.keys(byType).length === 0) {
            elements.trafficTypesBody.innerHTML = '<tr><td colspan="4" style="text-align: center;">暂无数据</td></tr>';
            return;
        }
        
        var totalBytes = 0;
        for (var type in byType) {
            if (byType.hasOwnProperty(type)) {
                totalBytes += byType[type].bytes;
            }
        }
        
        var html = '';
        for (var type in byType) {
            if (byType.hasOwnProperty(type)) {
                var item = byType[type];
                var percentage = totalBytes > 0 ? ((item.bytes / totalBytes) * 100).toFixed(2) : 0;
                
                html += '<tr>';
                html += '<td>' + getTypeLabel(type) + '</td>';
                html += '<td>' + item.bytes_formatted + '</td>';
                html += '<td>' + formatNumber(item.requests) + '</td>';
                html += '<td>' + percentage + '%</td>';
                html += '</tr>';
            }
        }
        
        elements.trafficTypesBody.innerHTML = html;
    }
    
    // 更新服务商信息
    function updateProviderStats(providers) {
        if (!providers) return;
        
        elements.providerName.textContent = providers.name || '-';
        elements.providerBucket.textContent = providers.bucket || '-';
        elements.providerRegion.textContent = providers.region || '-';
        elements.providerDomain.textContent = providers.domain || '-';
    }
    
    // 渲染图表
    function renderCharts(stats) {
        if (!window.Chart) {
            console.warn('Chart.js not loaded');
            return;
        }
        
        // 存储使用量对比图
        renderStorageChart(stats.storage);
        
        // 文件类型分布图
        renderFileTypesChart(stats.files);
        
        // 月度上传趋势图
        renderMonthlyChart(stats.files);
        
        // 每日流量趋势图
        renderTrafficChart(stats.traffic);
    }
    
    // 渲染存储使用量图表
    function renderStorageChart(storage) {
        if (!storage || !elements.storageChart) return;
        
        // 销毁现有图表
        if (state.charts.storage) {
            state.charts.storage.destroy();
        }
        
        var ctx = elements.storageChart.getContext('2d');
        
        state.charts.storage = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['本地存储', '云端存储'],
                datasets: [{
                    label: '存储使用量',
                    data: [storage.local_size, storage.cloud_size],
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(240, 147, 251, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(240, 147, 251, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return sizeFormat(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return sizeFormat(value);
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 渲染文件类型分布图
    function renderFileTypesChart(files) {
        if (!files || !files.by_type || !elements.fileTypesCanvas) return;
        
        // 销毁现有图表
        if (state.charts.fileTypes) {
            state.charts.fileTypes.destroy();
        }
        
        var types = files.by_type;
        var labels = [];
        var data = [];
        var colors = [
            'rgba(102, 126, 234, 0.8)',
            'rgba(240, 147, 251, 0.8)',
            'rgba(79, 172, 254, 0.8)',
            'rgba(67, 233, 123, 0.8)',
            'rgba(250, 112, 154, 0.8)',
            'rgba(254, 225, 64, 0.8)'
        ];
        
        for (var type in types) {
            if (types.hasOwnProperty(type)) {
                labels.push(getTypeLabel(type));
                data.push(types[type]);
            }
        }
        
        var ctx = elements.fileTypesCanvas.getContext('2d');
        
        state.charts.fileTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var percentage = ((context.raw / total) * 100).toFixed(2);
                                return context.label + ': ' + formatNumber(context.raw) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 渲染月度上传趋势图
    function renderMonthlyChart(files) {
        if (!files || !files.by_month || !elements.monthlyChart) return;
        
        // 销毁现有图表
        if (state.charts.monthly) {
            state.charts.monthly.destroy();
        }
        
        var months = files.by_month;
        var labels = [];
        var data = [];
        
        // 按月份排序（升序）
        var sortedMonths = Object.keys(months).sort();
        
        sortedMonths.forEach(function(month) {
            labels.push(month);
            data.push(months[month]);
        });
        
        var ctx = elements.monthlyChart.getContext('2d');
        
        state.charts.monthly = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '上传文件数',
                    data: data,
                    fill: true,
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    // 渲染每日流量趋势图
    function renderTrafficChart(traffic) {
        if (!traffic || !traffic.daily || !elements.trafficChart) return;
        
        // 销毁现有图表
        if (state.charts.traffic) {
            state.charts.traffic.destroy();
        }
        
        var daily = traffic.daily;
        var labels = [];
        var data = [];
        
        // 按日期排序（升序）
        var sortedDates = Object.keys(daily).sort();
        
        sortedDates.forEach(function(date) {
            labels.push(date);
            data.push(daily[date].bytes);
        });
        
        var ctx = elements.trafficChart.getContext('2d');
        
        state.charts.traffic = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '流量',
                    data: data,
                    fill: true,
                    backgroundColor: 'rgba(79, 172, 254, 0.2)',
                    borderColor: 'rgba(79, 172, 254, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return sizeFormat(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return sizeFormat(value);
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 设置加载状态
    function setLoadingState(loading) {
        var loadingText = loading ? '加载中...' : '';
        
        elements.cloudFilesCount.textContent = loadingText || '-';
        elements.cloudStorageSize.textContent = loadingText || '-';
        elements.todayTraffic.textContent = loadingText || '-';
        elements.uploadPercentage.textContent = loadingText || '-';
    }
    
    // 发送 AJAX 请求
    function sendRequest(action, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', wpmcsStats.ajax_url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (callback) callback(response);
                } catch (e) {
                    console.error('Failed to parse response:', e);
                    if (callback) callback({ success: false, data: { message: '解析响应失败' } });
                }
            } else {
                if (callback) callback({ success: false, data: { message: '请求失败: HTTP ' + xhr.status } });
            }
        };
        
        xhr.onerror = function() {
            if (callback) callback({ success: false, data: { message: '网络错误' } });
        };
        
        var params = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(wpmcsStats.nonce);
        
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                params += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
            }
        }
        
        xhr.send(params);
    }
    
    // 格式化数字
    function formatNumber(num) {
        num = parseInt(num) || 0;
        return num.toLocaleString();
    }
    
    // 格式化文件大小
    function sizeFormat(bytes) {
        bytes = parseInt(bytes) || 0;
        
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var unitIndex = 0;
        
        while (bytes >= 1024 && unitIndex < units.length - 1) {
            bytes /= 1024;
            unitIndex++;
        }
        
        return bytes.toFixed(2) + ' ' + units[unitIndex];
    }
    
    // 获取文件类型标签
    function getTypeLabel(type) {
        var labels = {
            'image': '图片',
            'video': '视频',
            'audio': '音频',
            'application': '文档',
            'text': '文本',
            'other': '其他'
        };
        
        return labels[type] || type;
    }
    
    // 显示成功消息
    function showSuccess(message) {
        showNotice(message, 'success');
    }
    
    // 显示错误消息
    function showError(message) {
        showNotice(message, 'error');
    }
    
    // 显示通知
    function showNotice(message, type) {
        // 创建通知元素
        var notice = document.createElement('div');
        notice.className = 'notice notice-' + type + ' is-dismissible';
        notice.style.cssText = 'position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px;';
        
        notice.innerHTML = '<p>' + message + '</p>';
        
        document.body.appendChild(notice);
        
        // 3秒后自动移除
        setTimeout(function() {
            notice.remove();
        }, 3000);
    }
});
