<div class="wrap wpmcs-logs-page">
    <h1>云存储日志</h1>
    
    <!-- 错误分析 -->
    <div class="wpmcs-analysis-section">
        <h2>错误分析</h2>
        <div id="analysis-content">
            <p>加载中...</p>
        </div>
    </div>
    
    <!-- 统计信息 -->
    <div class="wpmcs-stats-section">
        <h2>日志统计（最近 7 天）</h2>
        <div id="stats-content" class="wpmcs-stats-grid">
            <p>加载中...</p>
        </div>
    </div>
    
    <!-- 筛选器 -->
    <div class="wpmcs-filters-section">
        <h2>筛选日志</h2>
        <form id="logs-filter-form" class="wpmcs-filter-form">
            <select name="level" id="filter-level">
                <option value="">所有级别</option>
                <option value="debug">调试</option>
                <option value="info">信息</option>
                <option value="warning">警告</option>
                <option value="error">错误</option>
                <option value="critical">严重</option>
            </select>
            
            <select name="type" id="filter-type">
                <option value="">所有类型</option>
                <option value="upload">上传</option>
                <option value="delete">删除</option>
                <option value="migration">迁移</option>
                <option value="queue">队列</option>
                <option value="cache">缓存</option>
                <option value="system">系统</option>
            </select>
            
            <input type="date" name="date_from" id="filter-date-from" placeholder="开始日期">
            <input type="date" name="date_to" id="filter-date-to" placeholder="结束日期">
            
            <input type="text" name="search" id="filter-search" placeholder="搜索日志内容">
            
            <button type="submit" class="button">
                <span class="dashicons dashicons-search"></span> 筛选
            </button>
            
            <button type="button" id="reset-filters" class="button">
                重置
            </button>
        </form>
    </div>
    
    <!-- 操作按钮 -->
    <div class="wpmcs-actions-section">
        <button type="button" id="export-logs" class="button button-secondary">
            <span class="dashicons dashicons-download"></span> 导出日志
        </button>
        
        <button type="button" id="clear-logs" class="button button-secondary">
            <span class="dashicons dashicons-trash"></span> 清空日志
        </button>
        
        <button type="button" id="refresh-logs" class="button">
            <span class="dashicons dashicons-update"></span> 刷新
        </button>
    </div>
    
    <!-- 日志列表 -->
    <div class="wpmcs-logs-section">
        <h2>日志列表</h2>
        <div id="logs-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-time">时间</th>
                        <th scope="col" class="column-level">级别</th>
                        <th scope="col" class="column-type">类型</th>
                        <th scope="col" class="column-message">消息</th>
                        <th scope="col" class="column-attachment">附件</th>
                        <th scope="col" class="column-user">用户</th>
                    </tr>
                </thead>
                <tbody id="logs-list-body">
                    <tr>
                        <td colspan="6" style="text-align: center;">加载中...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- 分页 -->
        <div id="logs-pagination" class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num" id="logs-total">0 条日志</span>
                <span class="pagination-links" id="pagination-links"></span>
            </div>
        </div>
    </div>
    
    <!-- 日志详情模态框 -->
    <div id="log-detail-modal" style="display: none;">
        <div class="wpmcs-modal-backdrop"></div>
        <div class="wpmcs-modal-content">
            <div class="wpmcs-modal-header">
                <h3>日志详情</h3>
                <button type="button" class="wpmcs-modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="wpmcs-modal-body" id="log-detail-content">
                <!-- 动态填充 -->
            </div>
        </div>
    </div>
</div>

<style>
.wpmcs-logs-page {
    max-width: 1400px;
}

.wpmcs-analysis-section,
.wpmcs-stats-section,
.wpmcs-filters-section,
.wpmcs-actions-section,
.wpmcs-logs-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.wpmcs-stat-item {
    text-align: center;
    padding: 15px;
    background: #f7f7f7;
    border-radius: 4px;
}

.wpmcs-stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.wpmcs-stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
}

.wpmcs-stat-value.debug { color: #82878c; }
.wpmcs-stat-value.info { color: #0073aa; }
.wpmcs-stat-value.warning { color: #ffb900; }
.wpmcs-stat-value.error { color: #dc3232; }
.wpmcs-stat-value.critical { color: #a00; }

.wpmcs-filter-form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.wpmcs-filter-form select,
.wpmcs-filter-form input[type="date"],
.wpmcs-filter-form input[type="text"] {
    max-width: 200px;
}

.wpmcs-actions-section .button {
    margin-right: 10px;
}

.wpmcs-actions-section .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 5px;
}

.wpmcs-logs-section table {
    margin-top: 10px;
}

.column-time { width: 150px; }
.column-level { width: 80px; }
.column-type { width: 100px; }
.column-attachment { width: 80px; }
.column-user { width: 100px; }

.log-level {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.log-level.debug { background: #f0f0f0; color: #82878c; }
.log-level.info { background: #e5f5ff; color: #0073aa; }
.log-level.warning { background: #fff8e5; color: #ffb900; }
.log-level.error { background: #fdecea; color: #dc3232; }
.log-level.critical { background: #fce4ec; color: #a00; }

.log-row {
    cursor: pointer;
}

.log-row:hover {
    background: #f9f9f9;
}

/* 模态框 */
.wpmcs-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
}

.wpmcs-modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
    z-index: 100001;
    max-width: 800px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
}

.wpmcs-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
}

.wpmcs-modal-header h3 {
    margin: 0;
}

.wpmcs-modal-close {
    border: none;
    background: none;
    cursor: pointer;
    padding: 0;
}

.wpmcs-modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: 60vh;
}

.log-detail-row {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.log-detail-row:last-child {
    border-bottom: none;
}

.log-detail-label {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.log-detail-value {
    color: #333;
}

.log-detail-value pre {
    background: #f7f7f7;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}

/* 分析部分 */
.wpmcs-analysis-summary {
    font-size: 16px;
    padding: 15px;
    background: #f7f7f7;
    border-radius: 4px;
    margin-bottom: 15px;
}

.wpmcs-issues-list,
.wpmcs-recommendations-list {
    margin-top: 10px;
}

.wpmcs-issue-item,
.wpmcs-recommendation-item {
    padding: 10px;
    margin-bottom: 10px;
    border-left: 4px solid #0073aa;
    background: #f7f7f7;
}

.wpmcs-issue-item.high {
    border-left-color: #dc3232;
    background: #fdecea;
}

.wpmcs-issue-item.medium {
    border-left-color: #ffb900;
    background: #fff8e5;
}
</style>