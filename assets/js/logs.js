document.addEventListener('DOMContentLoaded', function() {
    // 当前筛选条件
    var currentFilters = {
        level: '',
        type: '',
        date_from: '',
        date_to: '',
        search: '',
        per_page: 50,
        page: 1
    };
    
    // 初始化
    loadAnalysis();
    loadLogs();
    
    // 绑定事件
    document.getElementById('logs-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        updateFilters();
        loadLogs();
    });
    
    document.getElementById('reset-filters').addEventListener('click', function() {
        document.getElementById('filter-level').value = '';
        document.getElementById('filter-type').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        document.getElementById('filter-search').value = '';
        updateFilters();
        loadLogs();
    });
    
    document.getElementById('refresh-logs').addEventListener('click', function() {
        loadAnalysis();
        loadLogs();
    });
    
    document.getElementById('export-logs').addEventListener('click', function() {
        exportLogs();
    });
    
    document.getElementById('clear-logs').addEventListener('click', function() {
        if (confirm('确定要清空所有日志吗？此操作不可撤销！')) {
            clearLogs();
        }
    });
    
    // 点击日志行显示详情
    document.getElementById('logs-list-body').addEventListener('click', function(e) {
        var row = e.target.closest('.log-row');
        if (row) {
            var logData = JSON.parse(row.getAttribute('data-log'));
            showLogDetail(logData);
        }
    });
    
    // 关闭模态框
    document.querySelector('.wpmcs-modal-backdrop')?.addEventListener('click', closeLogDetail);
    document.querySelector('.wpmcs-modal-close')?.addEventListener('click', closeLogDetail);
    
    // 加载分析
    function loadAnalysis() {
        sendRequest('wpmcs_get_log_analysis', {}, function(response) {
            if (response.success) {
                renderAnalysis(response.data);
            }
        });
    }
    
    // 渲染分析
    function renderAnalysis(data) {
        var analysis = data.analysis;
        var stats = data.stats;
        
        // 渲染统计
        var statsHtml = '';
        statsHtml += '<div class="wpmcs-stat-item"><span class="wpmcs-stat-label">总日志数</span><span class="wpmcs-stat-value">' + stats.total + '</span></div>';
        
        for (var level in stats.by_level) {
            statsHtml += '<div class="wpmcs-stat-item"><span class="wpmcs-stat-label">' + level.toUpperCase() + '</span><span class="wpmcs-stat-value ' + level + '">' + stats.by_level[level] + '</span></div>';
        }
        
        statsHtml += '<div class="wpmcs-stat-item"><span class="wpmcs-stat-label">最近错误</span><span class="wpmcs-stat-value">' + stats.recent_errors + '</span></div>';
        
        document.getElementById('stats-content').innerHTML = statsHtml;
        
        // 渲染分析
        var analysisHtml = '<div class="wpmcs-analysis-summary">' + analysis.summary + '</div>';
        
        if (analysis.issues && analysis.issues.length > 0) {
            analysisHtml += '<div class="wpmcs-issues-list"><h3>发现问题</h3>';
            analysis.issues.forEach(function(issue) {
                analysisHtml += '<div class="wpmcs-issue-item ' + issue.severity + '">';
                analysisHtml += '<strong>' + issue.type.toUpperCase() + '</strong>: ' + issue.message;
                analysisHtml += '</div>';
            });
            analysisHtml += '</div>';
        }
        
        if (analysis.recommendations && analysis.recommendations.length > 0) {
            analysisHtml += '<div class="wpmcs-recommendations-list"><h3>建议措施</h3><ul>';
            analysis.recommendations.forEach(function(rec) {
                analysisHtml += '<li class="wpmcs-recommendation-item">' + rec + '</li>';
            });
            analysisHtml += '</ul></div>';
        }
        
        document.getElementById('analysis-content').innerHTML = analysisHtml;
    }
    
    // 加载日志
    function loadLogs() {
        sendRequest('wpmcs_get_logs', currentFilters, function(response) {
            if (response.success) {
                renderLogs(response.data);
            }
        });
    }
    
    // 渲染日志
    function renderLogs(data) {
        var tbody = document.getElementById('logs-list-body');
        
        if (!data.logs || data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">暂无日志</td></tr>';
            document.getElementById('logs-total').textContent = '0 条日志';
            document.getElementById('pagination-links').innerHTML = '';
            return;
        }
        
        var html = '';
        data.logs.forEach(function(log) {
            html += '<tr class="log-row" data-log="' + escapeHtml(JSON.stringify(log)) + '">';
            html += '<td class="column-time">' + log.created_at + '</td>';
            html += '<td class="column-level"><span class="log-level ' + log.level + '">' + log.level.toUpperCase() + '</span></td>';
            html += '<td class="column-type">' + log.type + '</td>';
            html += '<td class="column-message">' + escapeHtml(log.message.substring(0, 100)) + (log.message.length > 100 ? '...' : '') + '</td>';
            html += '<td class="column-attachment">' + (log.attachment_id ? '<a href="post.php?post=' + log.attachment_id + '&action=edit">' + log.attachment_id + '</a>' : '-') + '</td>';
            html += '<td class="column-user">' + (log.user_id ? log.user_id : '-') + '</td>';
            html += '</tr>';
        });
        
        tbody.innerHTML = html;
        
        // 更新分页
        document.getElementById('logs-total').textContent = data.total + ' 条日志';
        
        var paginationHtml = '';
        if (data.pages > 1) {
            if (data.page > 1) {
                paginationHtml += '<a class="prev-page" href="#" onclick="changePage(' + (data.page - 1) + '); return false;">«</a> ';
            }
            
            paginationHtml += '<span class="paging-input">' + data.page + ' / ' + data.pages + '</span>';
            
            if (data.page < data.pages) {
                paginationHtml += ' <a class="next-page" href="#" onclick="changePage(' + (data.page + 1) + '); return false;">»</a>';
            }
        }
        
        document.getElementById('pagination-links').innerHTML = paginationHtml;
    }
    
    // 显示日志详情
    function showLogDetail(log) {
        var content = '';
        
        content += '<div class="log-detail-row">';
        content += '<div class="log-detail-label">时间</div>';
        content += '<div class="log-detail-value">' + log.created_at + '</div>';
        content += '</div>';
        
        content += '<div class="log-detail-row">';
        content += '<div class="log-detail-label">级别</div>';
        content += '<div class="log-detail-value"><span class="log-level ' + log.level + '">' + log.level.toUpperCase() + '</span></div>';
        content += '</div>';
        
        content += '<div class="log-detail-row">';
        content += '<div class="log-detail-label">类型</div>';
        content += '<div class="log-detail-value">' + log.type + '</div>';
        content += '</div>';
        
        content += '<div class="log-detail-row">';
        content += '<div class="log-detail-label">消息</div>';
        content += '<div class="log-detail-value">' + escapeHtml(log.message) + '</div>';
        content += '</div>';
        
        if (log.context) {
            content += '<div class="log-detail-row">';
            content += '<div class="log-detail-label">上下文</div>';
            content += '<div class="log-detail-value"><pre>' + escapeHtml(JSON.stringify(log.context, null, 2)) + '</pre></div>';
            content += '</div>';
        }
        
        if (log.attachment_id) {
            content += '<div class="log-detail-row">';
            content += '<div class="log-detail-label">附件 ID</div>';
            content += '<div class="log-detail-value"><a href="post.php?post=' + log.attachment_id + '&action=edit">' + log.attachment_id + '</a></div>';
            content += '</div>';
        }
        
        if (log.user_id) {
            content += '<div class="log-detail-row">';
            content += '<div class="log-detail-label">用户 ID</div>';
            content += '<div class="log-detail-value">' + log.user_id + '</div>';
            content += '</div>';
        }
        
        if (log.ip_address) {
            content += '<div class="log-detail-row">';
            content += '<div class="log-detail-label">IP 地址</div>';
            content += '<div class="log-detail-value">' + log.ip_address + '</div>';
            content += '</div>';
        }
        
        if (log.request_uri) {
            content += '<div class="log-detail-row">';
            content += '<div class="log-detail-label">请求 URI</div>';
            content += '<div class="log-detail-value">' + escapeHtml(log.request_uri) + '</div>';
            content += '</div>';
        }
        
        document.getElementById('log-detail-content').innerHTML = content;
        document.getElementById('log-detail-modal').style.display = 'block';
    }
    
    // 关闭日志详情
    function closeLogDetail() {
        document.getElementById('log-detail-modal').style.display = 'none';
    }
    
    // 导出日志
    function exportLogs() {
        sendRequest('wpmcs_export_logs', currentFilters, function(response) {
            if (response.success) {
                var csv = response.data.csv;
                var blob = new Blob([csv], { type: 'text/csv' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'wpmcs-logs-' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }
        });
    }
    
    // 清空日志
    function clearLogs() {
        sendRequest('wpmcs_clear_logs', {}, function(response) {
            if (response.success) {
                alert(response.data.message);
                loadAnalysis();
                loadLogs();
            }
        });
    }
    
    // 更新筛选条件
    function updateFilters() {
        currentFilters.level = document.getElementById('filter-level').value;
        currentFilters.type = document.getElementById('filter-type').value;
        currentFilters.date_from = document.getElementById('filter-date-from').value;
        currentFilters.date_to = document.getElementById('filter-date-to').value;
        currentFilters.search = document.getElementById('filter-search').value;
        currentFilters.page = 1;
    }
    
    // 改变页码
    window.changePage = function(page) {
        currentFilters.page = page;
        loadLogs();
    };
    
    // 发送 AJAX 请求
    function sendRequest(action, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', wpmcsLogs.ajax_url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (callback) callback(response);
                } catch (e) {
                    console.error('Parse error:', e);
                }
            }
        };
        
        var params = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(wpmcsLogs.nonce);
        
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                params += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
            }
        }
        
        xhr.send(params);
    }
    
    // HTML 转义
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});