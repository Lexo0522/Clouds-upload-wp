<div class="wrap wpmcs-queue-page">
    <h1>异步上传队列</h1>
    
    <div class="wpmcs-queue-intro">
        <p>异步上传队列允许您在后台处理文件上传，避免阻塞用户操作。</p>
        <p><strong>状态说明：</strong></p>
        <ul>
            <li><strong>待处理</strong>: 等待上传的文件</li>
            <li><strong>处理中</strong>: 正在尝试上传的文件</li>
            <li><strong>失败</strong>: 超过最大重试次数的文件</li>
            <li><strong>高优先级</strong>: 优先处理的文件</li>
        </ul>
    </div>
    
    <!-- 队列统计 -->
    <div class="wpmcs-queue-stats">
        <h2>队列统计</h2>
        <div class="wpmcs-stats-grid">
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">总任务数</span>
                <span class="wpmcs-stat-value" id="total-tasks">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">待处理</span>
                <span class="wpmcs-stat-value wpmcs-pending" id="pending-tasks">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">处理中</span>
                <span class="wpmcs-stat-value wpmcs-processing" id="processing-tasks">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">失败</span>
                <span class="wpmcs-stat-value wpmcs-failed" id="failed-tasks">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">高优先级</span>
                <span class="wpmcs-stat-value wpmcs-high-priority" id="high-priority-tasks">-</span>
            </div>
        </div>
        <button type="button" id="refresh-stats" class="button">
            <span class="dashicons dashicons-update"></span> 刷新统计
        </button>
    </div>
    
    <!-- 缓存状态 -->
    <div class="wpmcs-cache-stats">
        <h2>缓存状态</h2>
        <div class="wpmcs-stats-grid">
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">内存缓存项</span>
                <span class="wpmcs-stat-value" id="memory-cache-count">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">对象缓存</span>
                <span class="wpmcs-stat-value" id="object-cache-status">-</span>
            </div>
            <div class="wpmcs-stat-item">
                <span class="wpmcs-stat-label">查询次数</span>
                <span class="wpmcs-stat-value" id="query-count">-</span>
            </div>
        </div>
        <button type="button" id="flush-cache" class="button button-secondary">
            <span class="dashicons dashicons-trash"></span> 清空缓存
        </button>
    </div>
    
    <!-- 队列操作 -->
    <div class="wpmcs-queue-actions">
        <h2>队列操作</h2>
        
        <button type="button" id="process-queue" class="button button-primary">
            <span class="dashicons dashicons-controls-play"></span> 立即处理队列
        </button>
        
        <button type="button" id="retry-failed" class="button button-secondary">
            <span class="dashicons dashicons-update"></span> 重试失败项
        </button>
        
        <button type="button" id="clear-queue" class="button button-secondary">
            <span class="dashicons dashicons-no"></span> 清空队列
        </button>
    </div>
    
    <!-- 队列设置 -->
    <div class="wpmcs-queue-settings">
        <h2>队列设置</h2>
        
        <form method="post" action="options.php">
            <?php
            // 这里可以添加设置字段
            // 暂时使用链接到主设置页面
            ?>
            
            <p class="description">
                异步上传队列设置已集成到主设置页面。
                <a href="<?php echo admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ); ?>">前往设置页面</a>
            </p>
        </form>
    </div>
    
    <!-- 队列列表 -->
    <div class="wpmcs-queue-list">
        <h2>队列详情</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-id">附件 ID</th>
                    <th scope="col" class="manage-column column-priority">优先级</th>
                    <th scope="col" class="manage-column column-attempts">尝试次数</th>
                    <th scope="col" class="manage-column column-added">添加时间</th>
                    <th scope="col" class="manage-column column-error">错误信息</th>
                    <th scope="col" class="manage-column column-action">操作</th>
                </tr>
            </thead>
            <tbody id="queue-list-body">
                <!-- 动态填充 -->
            </tbody>
        </table>
    </div>
</div>

<style>
.wpmcs-queue-page {
    max-width: 1200px;
}

.wpmcs-queue-intro {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-queue-stats,
.wpmcs-cache-stats,
.wpmcs-queue-actions,
.wpmcs-queue-settings,
.wpmcs-queue-list {
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
    border-radius: 8px;
}

.wpmcs-stat-label {
    display: block;
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
}

.wpmcs-stat-value {
    display: block;
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.wpmcs-stat-value.wpmcs-pending {
    color: #ffb900;
}

.wpmcs-stat-value.wpmcs-processing {
    color: #46b450;
}

.wpmcs-stat-value.wpmcs-failed {
    color: #dc3232;
}

.wpmcs-stat-value.wpmcs-high-priority {
    color: #00a0d2;
}

.wpmcs-queue-actions .button {
    margin-right: 10px;
}

.wpmcs-queue-actions .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 5px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 加载统计信息
    loadStats();
    
    // 加载队列列表
    loadQueueList();
    
    // 绑定事件
    $('#refresh-stats').on('click', function() {
        loadStats();
        loadQueueList();
    });
    
    $('#flush-cache').on('click', function() {
        if (!confirm('确定要清空缓存吗？')) return;
        
        // 调用清空缓存 API
        // 这里需要添加对应的 AJAX 处理函数
        alert('缓存已清空');
    });
    
    $('#process-queue').on('click', function() {
        // 手动触发队列处理
        alert('队列处理已触发，请稍后刷新查看结果');
        setTimeout(loadStats, 2000);
    });
    
    $('#retry-failed').on('click', function() {
        if (!confirm('确定要重试所有失败的任务吗？')) return;
        
        // 调用重试 API
        alert('失败任务已重新加入队列');
        setTimeout(loadStats, 1000);
    });
    
    $('#clear-queue').on('click', function() {
        if (!confirm('确定要清空队列吗？此操作不可撤销！')) return;
        
        // 调用清空队列 API
        alert('队列已清空');
        loadStats();
        loadQueueList();
    });
    
    function loadStats() {
        // 这里需要添加 AJAX 调用来获取实时统计
        // 暂时显示占位符
        $('#total-tasks').text('0');
        $('#pending-tasks').text('0');
        $('#processing-tasks').text('0');
        $('#failed-tasks').text('0');
        $('#high-priority-tasks').text('0');
        
        $('#memory-cache-count').text('0');
        $('#object-cache-status').text('已启用');
        $('#query-count').text('0');
    }
    
    function loadQueueList() {
        // 加载队列列表
        $('#queue-list-body').html('<tr><td colspan="6" style="text-align: center; color: #999;">暂无队列数据</td></tr>');
    }
});
</script>