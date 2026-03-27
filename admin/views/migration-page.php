<div class="wrap wpmcs-migration-page">
    <h1>批量迁移到云存储</h1>
    
    <div class="wpmcs-migration-intro">
        <p>此工具可以将现有的本地附件批量迁移到云存储。支持增量迁移和失败重试。</p>
        <p><strong>注意：</strong>迁移过程可能需要较长时间，请勿关闭浏览器窗口。建议在网站访问量较低时执行。</p>
    </div>
    
    <!-- 统计信息 -->
    <div class="wpmcs-migration-stats">
        <h2>迁移统计</h2>
        <div class="wpmcs-stats-grid">
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">总附件数</span>
                <span class="wpmcs-stat-value" id="total-attachments">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">已上传</span>
                <span class="wpmcs-stat-value" id="uploaded-attachments">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">未上传</span>
                <span class="wpmcs-stat-value" id="not-uploaded">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">失败</span>
                <span class="wpmcs-stat-value" id="failed-attachments">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">总大小</span>
                <span class="wpmcs-stat-value" id="total-size">-</span>
            </div>
        </div>
        <button type="button" id="refresh-stats" class="button">
            <span class="dashicons dashicons-update"></span> 刷新统计
        </button>
    </div>
    
    <!-- 迁移选项 -->
    <div class="wpmcs-migration-options">
        <h2>迁移选项</h2>
        
        <label class="wpmcs-option-label">
            <input type="checkbox" id="incremental-migration" checked>
            增量迁移（只迁移未上传的文件）
        </label>
        
        <p class="description">
            勾选此项将跳过已上传到云端的附件，只迁移未上传的文件。        </p>

        <label class="wpmcs-option-label">
            <input type="checkbox" id="force-reupload">
            强制重传已存在云端记录的附件
        </label>

        <p class="description">
            勾选后会忽略“增量迁移”的跳过逻辑，重新上传所有匹配的附件。
        </p>

        <label class="wpmcs-option-label">
            <input type="checkbox" id="retry-failed">
            重试失败的迁移
        </label>
        
        <p class="description">
            勾选此项将只重试之前迁移失败的附件。
        </p>
    </div>
    
    <!-- 迁移控制 -->
    <div class="wpmcs-migration-controls">
        <h2>迁移控制</h2>
        
        <button type="button" id="start-migration" class="button button-primary button-large">
            <span class="dashicons dashicons-upload"></span> 开始迁移
        </button>
        
        <button type="button" id="cancel-migration" class="button button-secondary" style="display: none;">
            <span class="dashicons dashicons-no"></span> 取消迁移
        </button>
        
        <button type="button" id="retry-migration" class="button button-secondary" style="display: none;">
            <span class="dashicons dashicons-update"></span> 重试失败项
        </button>
    </div>
    
    <!-- 进度显示 -->
    <div class="wpmcs-migration-progress" style="display: none;">
        <h2>迁移进度</h2>
        
        <div class="wpmcs-progress-bar-container">
            <div class="wpmcs-progress-bar">
                <div class="wpmcs-progress-bar-fill" style="width: 0%"></div>
            </div>
            <div class="wpmcs-progress-text">0%</div>
        </div>
        
        <div class="wpmcs-progress-details">
            <div class="wpmcs-progress-item">
                <span class="wpmcs-progress-label">总计：</span>
                <span class="wpmcs-progress-value" id="progress-total">0</span>
            </div>
            <div class="wpmcs-progress-item">
                <span class="wpmcs-progress-label">已处理：</span>
                <span class="wpmcs-progress-value" id="progress-processed">0</span>
            </div>
            <div class="wpmcs-progress-item">
                <span class="wpmcs-progress-label">成功：</span>
                <span class="wpmcs-progress-value wpmcs-success" id="progress-success">0</span>
            </div>
            <div class="wpmcs-progress-item">
                <span class="wpmcs-progress-label">失败：</span>
                <span class="wpmcs-progress-value wpmcs-failed" id="progress-failed">0</span>
            </div>
            <div class="wpmcs-progress-item">
                <span class="wpmcs-progress-label">状态：</span>
                <span class="wpmcs-progress-value" id="progress-status">准备中</span>
            </div>
        </div>
    </div>
    
    <!-- 迁移日志 -->
    <div class="wpmcs-migration-log">
        <h2>迁移日志</h2>
        
        <div class="wpmcs-log-container">
            <div id="migration-log"></div>
        </div>
        
        <button type="button" id="clear-log" class="button button-secondary">
            清空日志
        </button>
    </div>
    
    <!-- 失败详情 -->
    <div class="wpmcs-migration-failed" style="display: none;">
        <h2>失败详情</h2>
        
        <div class="wpmcs-failed-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-id">附件 ID</th>
                        <th scope="col" class="manage-column column-error">错误信息</th>
                        <th scope="col" class="manage-column column-action">操作</th>
                    </tr>
                </thead>
                <tbody id="failed-list-body">
                    <!-- 动态填充 -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.wpmcs-migration-page {
    max-width: 1200px;
}

.wpmcs-migration-intro {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-migration-stats {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.wpmcs-stat-item {
    text-align: center;
    padding: 20px;
    background: #f7f7f7;
    border-radius: 4px;
}

.wpmcs-stat-label {
    display: block;
    font-size: 13px;
    color: #666;
    margin-bottom: 5px;
}

.wpmcs-stat-value {
    display: block;
    font-size: 28px;
    font-weight: bold;
    color: #0073aa;
}

.wpmcs-migration-options {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-option-label {
    display: block;
    font-size: 14px;
    margin-bottom: 10px;
}

.wpmcs-migration-controls {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-migration-controls .button {
    margin-right: 10px;
}

.wpmcs-migration-progress {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-progress-bar-container {
    margin-bottom: 20px;
    position: relative;
}

.wpmcs-progress-bar {
    height: 30px;
    background: #f0f0f0;
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 10px;
}

.wpmcs-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    transition: width 0.3s ease;
}

.wpmcs-progress-text {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    color: #0073aa;
}

.wpmcs-progress-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.wpmcs-progress-item {
    text-align: center;
    padding: 10px;
    background: #f7f7f7;
    border-radius: 4px;
}

.wpmcs-progress-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.wpmcs-progress-value {
    display: block;
    font-size: 18px;
    font-weight: bold;
}

.wpmcs-progress-value.wpmcs-success {
    color: #46b450;
}

.wpmcs-progress-value.wpmcs-failed {
    color: #dc3232;
}

.wpmcs-migration-log {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-log-container {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 4px;
    max-height: 300px;
    overflow-y: auto;
    font-family: Monaco, Consolas, monospace;
    font-size: 12px;
    margin-bottom: 10px;
}

.wpmcs-log-entry {
    margin-bottom: 5px;
    padding: 2px 0;
}

.wpmcs-log-entry.success {
    color: #46b450;
}

.wpmcs-log-entry.error {
    color: #dc3232;
}

.wpmcs-log-entry.info {
    color: #00a0d2;
}

.wpmcs-migration-failed {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
}

.wpmcs-failed-list table {
    margin-top: 10px;
}
</style>
