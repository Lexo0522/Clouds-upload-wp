document.addEventListener('DOMContentLoaded', function() {
    var migrationState = {
        isRunning: false,
        isPaused: false,
        progressTimer: null,
        batchTimer: null
    };
    
    // DOM 元素
    var elements = {
        totalAttachments: document.getElementById('total-attachments'),
        uploadedAttachments: document.getElementById('uploaded-attachments'),
        notUploaded: document.getElementById('not-uploaded'),
        failedAttachments: document.getElementById('failed-attachments'),
        totalSize: document.getElementById('total-size'),
        
        startBtn: document.getElementById('start-migration'),
        cancelBtn: document.getElementById('cancel-migration'),
        retryBtn: document.getElementById('retry-migration'),
        refreshBtn: document.getElementById('refresh-stats'),
        clearLogBtn: document.getElementById('clear-log'),
        
        incrementalCheckbox: document.getElementById('incremental-migration'),
        retryFailedCheckbox: document.getElementById('retry-failed'),
        forceReuploadCheckbox: document.getElementById('force-reupload'),
        
        progressSection: document.querySelector('.wpmcs-migration-progress'),
        progressBarFill: document.querySelector('.wpmcs-progress-bar-fill'),
        progressText: document.querySelector('.wpmcs-progress-text'),
        progressTotal: document.getElementById('progress-total'),
        progressProcessed: document.getElementById('progress-processed'),
        progressSuccess: document.getElementById('progress-success'),
        progressFailed: document.getElementById('progress-failed'),
        progressStatus: document.getElementById('progress-status'),
        
        logContainer: document.getElementById('migration-log'),
        failedSection: document.querySelector('.wpmcs-migration-failed'),
        failedListBody: document.getElementById('failed-list-body')
    };
    
    // 初始化
    init();
    
    function init() {
        // 加载统计信息
        loadStats();
        
        // 检查是否有正在进行的迁移
        checkProgress();
        
        // 绑定事件
        bindEvents();
    }
    
    function bindEvents() {
        elements.startBtn.addEventListener('click', startMigration);
        elements.cancelBtn.addEventListener('click', cancelMigration);
        elements.retryBtn.addEventListener('click', retryFailed);
        elements.refreshBtn.addEventListener('click', loadStats);
        elements.clearLogBtn.addEventListener('click', clearLog);
        
        // 复选框互斥
        elements.incrementalCheckbox.addEventListener('change', function() {
            if (this.checked) {
                elements.retryFailedCheckbox.checked = false;
                elements.forceReuploadCheckbox.checked = false;
            }
        });
        
        elements.retryFailedCheckbox.addEventListener('change', function() {
            if (this.checked) {
                elements.incrementalCheckbox.checked = false;
                elements.forceReuploadCheckbox.checked = false;
            }
        });

        elements.forceReuploadCheckbox.addEventListener('change', function() {
            if (this.checked) {
                elements.incrementalCheckbox.checked = false;
                elements.retryFailedCheckbox.checked = false;
            }
        });
    }
    
    // 加载统计信息
    function loadStats() {
        sendRequest('wpmcs_get_migration_stats', {}, function(response) {
            if (response.success) {
                var stats = response.data;
                elements.totalAttachments.textContent = stats.total_attachments;
                elements.uploadedAttachments.textContent = stats.uploaded_attachments;
                elements.notUploaded.textContent = stats.not_uploaded;
                elements.failedAttachments.textContent = stats.failed_attachments;
                elements.totalSize.textContent = stats.total_size;
            }
        });
    }
    
    // 开始迁移
    function startMigration() {
        var incremental = elements.incrementalCheckbox.checked;
        var retryFailed = elements.retryFailedCheckbox.checked;
        var forceReupload = elements.forceReuploadCheckbox.checked;
        
        addLog('正在启动迁移...', 'info');
        
        elements.startBtn.disabled = true;
        
        sendRequest('wpmcs_start_migration', {
            incremental: incremental,
            retry_failed: retryFailed,
            force_reupload: forceReupload
        }, function(response) {
            if (response.success) {
                addLog('迁移已启动', 'success');
                migrationState.isRunning = true;
                
                // 显示进度区域
                elements.progressSection.style.display = 'block';
                elements.cancelBtn.style.display = 'inline-block';
                
                // 开始处理批次
                processBatch();
            } else {
                addLog('启动失败: ' + response.data.message, 'error');
                elements.startBtn.disabled = false;
            }
        });
    }
    
    // 处理批次
    function processBatch() {
        if (!migrationState.isRunning) {
            return;
        }
        
        sendRequest('wpmcs_process_migration_batch', {
            batch_size: 5
        }, function(response) {
            if (response.success) {
                var status = response.data;
                
                // 更新进度
                updateProgress(status);
                
                // 添加日志
                if (status.processed > 0) {
                    addLog('已处理 ' + status.processed + ' / ' + status.total + ' 个附件', 'info');
                }
                
                // 检查是否完成
                if (status.status === 'completed') {
                    migrationState.isRunning = false;
                    addLog('迁移完成！成功: ' + status.success + ', 失败: ' + status.failed, 'success');
                    
                    // 显示重试按钮
                    if (status.failed > 0) {
                        elements.retryBtn.style.display = 'inline-block';
                        showFailedList(status.failed_ids);
                    }
                    
                    elements.cancelBtn.style.display = 'none';
                    elements.startBtn.disabled = false;
                    
                    // 刷新统计
                    setTimeout(loadStats, 1000);
                    
                } else if (status.status === 'cancelled') {
                    migrationState.isRunning = false;
                    addLog('迁移已取消', 'error');
                    elements.cancelBtn.style.display = 'none';
                    elements.startBtn.disabled = false;
                    
                } else {
                    // 继续处理下一批次
                    migrationState.batchTimer = setTimeout(processBatch, 500);
                }
            } else {
                addLog('处理失败: ' + response.data.message, 'error');
                migrationState.isRunning = false;
                elements.startBtn.disabled = false;
            }
        });
    }
    
    // 取消迁移
    function cancelMigration() {
        if (!confirm('确定要取消迁移吗？')) {
            return;
        }
        
        sendRequest('wpmcs_cancel_migration', {}, function(response) {
            if (response.success) {
                migrationState.isRunning = false;
                addLog('正在取消迁移...', 'info');
                
                // 清除定时器
                if (migrationState.batchTimer) {
                    clearTimeout(migrationState.batchTimer);
                }
                if (migrationState.progressTimer) {
                    clearTimeout(migrationState.progressTimer);
                }
            }
        });
    }
    
    // 重试失败项
    function retryFailed() {
        addLog('正在重试失败的迁移项...', 'info');
        
        elements.retryBtn.disabled = true;
        
        sendRequest('wpmcs_retry_failed', {}, function(response) {
            if (response.success) {
                addLog('开始重试', 'success');
                migrationState.isRunning = true;
                
                // 隐藏失败列表
                elements.failedSection.style.display = 'none';
                elements.retryBtn.style.display = 'none';
                
                // 开始处理
                processBatch();
            } else {
                addLog('重试失败: ' + response.data.message, 'error');
                elements.retryBtn.disabled = false;
            }
        });
    }
    
    // 检查进度
    function checkProgress() {
        sendRequest('wpmcs_get_migration_progress', {}, function(response) {
            if (response.success) {
                var progress = response.data;
                
                if (progress.status === 'running') {
                    migrationState.isRunning = true;
                    elements.progressSection.style.display = 'block';
                    elements.cancelBtn.style.display = 'inline-block';
                    elements.startBtn.disabled = true;
                    
                    updateProgress(progress);
                    
                    // 继续处理
                    processBatch();
                }
            }
        });
    }
    
    // 更新进度
    function updateProgress(status) {
        var percentage = status.total > 0 ? Math.round((status.processed / status.total) * 100) : 0;
        
        elements.progressBarFill.style.width = percentage + '%';
        elements.progressText.textContent = percentage + '%';
        elements.progressTotal.textContent = status.total;
        elements.progressProcessed.textContent = status.processed;
        elements.progressSuccess.textContent = status.success;
        elements.progressFailed.textContent = status.failed;
        
        var statusText = '处理中';
        if (status.status === 'completed') {
            statusText = '已完成';
        } else if (status.status === 'cancelled') {
            statusText = '已取消';
        }
        elements.progressStatus.textContent = statusText;
    }
    
    // 显示失败列表
    function showFailedList(failedIds) {
        if (!failedIds || failedIds.length === 0) {
            return;
        }
        
        elements.failedSection.style.display = 'block';
        elements.failedListBody.innerHTML = '';
        
        failedIds.forEach(function(item) {
            var row = document.createElement('tr');
            
            // ID 列
            var idCell = document.createElement('td');
            idCell.textContent = item.id;
            row.appendChild(idCell);
            
            // 错误列
            var errorCell = document.createElement('td');
            errorCell.textContent = item.error;
            row.appendChild(errorCell);
            
            // 操作列
            var actionCell = document.createElement('td');
            var viewLink = document.createElement('a');
            viewLink.href = '/wp-admin/post.php?post=' + item.id + '&action=edit';
            viewLink.textContent = '查看附件';
            viewLink.target = '_blank';
            actionCell.appendChild(viewLink);
            row.appendChild(actionCell);
            
            elements.failedListBody.appendChild(row);
        });
    }
    
    // 添加日志
    function addLog(message, type) {
        var entry = document.createElement('div');
        entry.className = 'wpmcs-log-entry ' + (type || '');
        
        var timestamp = new Date().toLocaleTimeString();
        entry.textContent = '[' + timestamp + '] ' + message;
        
        elements.logContainer.appendChild(entry);
        
        // 滚动到底部
        elements.logContainer.scrollTop = elements.logContainer.scrollHeight;
    }
    
    // 清空日志
    function clearLog() {
        elements.logContainer.innerHTML = '';
    }
    
    // 发送 AJAX 请求
    function sendRequest(action, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', wpmcsMigration.ajax_url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (callback) callback(response);
                } catch (e) {
                    addLog('解析响应失败: ' + e.message, 'error');
                }
            } else {
                addLog('请求失败: HTTP ' + xhr.status, 'error');
            }
        };
        
        xhr.onerror = function() {
            addLog('网络错误', 'error');
        };
        
        var params = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(wpmcsMigration.nonce);
        
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                params += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
            }
        }
        
        xhr.send(params);
    }
});
