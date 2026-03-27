<div class="wrap wpmcs-stats-page">
    <h1>存储空间统计</h1>
    
    <div class="wpmcs-stats-intro">
        <p>查看云端存储使用量、文件数量统计和流量使用情况的详细报告。</p>
    </div>
    
    <!-- 快速概览 -->
    <div class="wpmcs-stats-overview">
        <h2>快速概览</h2>
        <div class="wpmcs-overview-grid">
            <div class="wpmcs-overview-card">
                <div class="wpmcs-overview-icon dashicons dashicons-cloud-upload"></div>
                <div class="wpmcs-overview-content">
                    <span class="wpmcs-overview-label">云端文件</span>
                    <span class="wpmcs-overview-value" id="cloud-files-count">-</span>
                </div>
            </div>
            
            <div class="wpmcs-overview-card">
                <div class="wpmcs-overview-icon dashicons dashicons-database-import"></div>
                <div class="wpmcs-overview-content">
                    <span class="wpmcs-overview-label">云端存储</span>
                    <span class="wpmcs-overview-value" id="cloud-storage-size">-</span>
                </div>
            </div>
            
            <div class="wpmcs-overview-card">
                <div class="wpmcs-overview-icon dashicons dashicons-chart-line"></div>
                <div class="wpmcs-overview-content">
                    <span class="wpmcs-overview-label">今日流量</span>
                    <span class="wpmcs-overview-value" id="today-traffic">-</span>
                </div>
            </div>
            
            <div class="wpmcs-overview-card">
                <div class="wpmcs-overview-icon dashicons dashicons-yes-alt"></div>
                <div class="wpmcs-overview-content">
                    <span class="wpmcs-overview-label">上传比例</span>
                    <span class="wpmcs-overview-value" id="upload-percentage">-</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 操作按钮 -->
    <div class="wpmcs-stats-actions">
        <button type="button" id="refresh-stats" class="button button-primary">
            <span class="dashicons dashicons-update"></span> 刷新统计
        </button>
        <span class="wpmcs-last-updated">最后更新: <span id="last-updated-time">-</span></span>
    </div>
    
    <!-- 详细统计 -->
    <div class="wpmcs-stats-details">
        <!-- 存储使用量 -->
        <div class="wpmcs-stats-section">
            <h2>存储使用量</h2>
            <div class="wpmcs-stats-grid">
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">本地存储</span>
                    <span class="wpmcs-stat-value" id="local-storage">-</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">云端存储</span>
                    <span class="wpmcs-stat-value" id="cloud-storage">-</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">节省空间</span>
                    <span class="wpmcs-stat-value" id="saved-storage">-</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">同步比例</span>
                    <span class="wpmcs-stat-value" id="sync-percentage">-</span>
                </div>
            </div>
            
            <!-- 存储使用量图表 -->
            <div class="wpmcs-chart-container">
                <canvas id="storage-chart"></canvas>
            </div>
        </div>
        
        <!-- 文件统计 -->
        <div class="wpmcs-stats-section">
            <h2>文件统计</h2>
            <div class="wpmcs-stats-grid">
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">总文件数</span>
                    <span class="wpmcs-stat-value" id="total-files">-</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">已上传</span>
                    <span class="wpmcs-stat-value wpmcs-success" id="uploaded-files">-</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">未上传</span>
                    <span class="wpmcs-stat-value wpmcs-warning" id="not-uploaded-files">-</span>
                </div>
            </div>
            
            <!-- 文件类型分布 -->
            <h3>文件类型分布</h3>
            <div id="file-types-chart" class="wpmcs-chart-container">
                <canvas id="file-types-canvas"></canvas>
            </div>
            
            <!-- 月度上传趋势 -->
            <h3>月度上传趋势</h3>
            <div class="wpmcs-chart-container">
                <canvas id="monthly-chart"></canvas>
            </div>
        </div>
        
        <!-- 流量统计 -->
        <div class="wpmcs-stats-section">
            <h2>流量统计</h2>
            <div class="wpmcs-stats-grid">
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">今日流量</span>
                    <span class="wpmcs-stat-value" id="traffic-today">-</span>
                    <span class="wpmcs-stat-sub" id="requests-today">0 次请求</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">本周流量</span>
                    <span class="wpmcs-stat-value" id="traffic-week">-</span>
                    <span class="wpmcs-stat-sub" id="requests-week">0 次请求</span>
                </div>
                <div class="wpmcs-stat-item">
                    <span class="wpmcs-stat-label">本月流量</span>
                    <span class="wpmcs-stat-value" id="traffic-month">-</span>
                    <span class="wpmcs-stat-sub" id="requests-month">0 次请求</span>
                </div>
            </div>
            
            <!-- 每日流量图表 -->
            <h3>每日流量趋势（最近 30 天）</h3>
            <div class="wpmcs-chart-container">
                <canvas id="traffic-chart"></canvas>
            </div>
            
            <!-- 流量类型分布 -->
            <h3>流量类型分布</h3>
            <div id="traffic-types-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>文件类型</th>
                            <th>流量</th>
                            <th>请求数</th>
                            <th>占比</th>
                        </tr>
                    </thead>
                    <tbody id="traffic-types-body">
                        <tr><td colspan="4" style="text-align: center;">加载中...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 服务商信息 -->
        <div class="wpmcs-stats-section">
            <h2>云服务商信息</h2>
            <table class="form-table">
                <tr>
                    <th>服务商</th>
                    <td id="provider-name">-</td>
                </tr>
                <tr>
                    <th>Bucket</th>
                    <td id="provider-bucket">-</td>
                </tr>
                <tr>
                    <th>区域</th>
                    <td id="provider-region">-</td>
                </tr>
                <tr>
                    <th>域名</th>
                    <td id="provider-domain">-</td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- 样式已在 assets/css/stats.css 中加载 -->
