# 错误处理和日志系统

## 概述

WP Multi Cloud Storage 插件提供了完整的错误处理和日志系统，帮助您监控插件运行状态、排查问题和优化性能。

## 核心功能

### 1. 日志记录

#### 日志级别

系统支持 5 个日志级别：

- **DEBUG**: 调试信息，用于开发调试
- **INFO**: 一般信息，记录正常操作
- **WARNING**: 警告信息，需要注意但不影响运行
- **ERROR**: 错误信息，影响功能但系统仍可运行
- **CRITICAL**: 严重错误，影响核心功能

#### 日志类型

按功能模块分类：

- **upload**: 上传相关日志
- **delete**: 删除相关日志
- **migration**: 迁移相关日志
- **queue**: 队列相关日志
- **cache**: 缓存相关日志
- **system**: 系统相关日志

#### 记录的信息

每条日志包含以下信息：

- 级别和类型
- 消息内容
- 上下文数据（JSON）
- 关联附件 ID
- 用户 ID
- IP 地址
- User Agent
- 请求 URI
- 创建时间

### 2. 错误通知

#### 邮件通知

当发生 ERROR 或 CRITICAL 级别的错误时，系统可以自动发送邮件通知。

**配置方法**：
1. 进入设置 → WP Multi Cloud Storage
2. 启用「错误通知」选项
3. 保存设置

邮件将发送到网站管理员邮箱。

### 3. 错误分析

系统提供智能错误分析功能：

#### 自动分析

- 统计最近 7 天的错误数量
- 分析错误类型和模式
- 提供问题诊断
- 给出修复建议

#### 分析报告

系统会生成以下分析内容：

**总结**：
- 错误数量统计
- 严重程度评估

**问题识别**：
- 上传失败频繁
- 队列处理异常
- 最近错误激增

**修复建议**：
- 检查配置
- 优化设置
- 联系支持

### 4. 日志查看界面

#### 访问方式

进入 WordPress 后台 → 设置 → 云存储日志

#### 功能特性

**筛选功能**：
- 按级别筛选
- 按类型筛选
- 按日期范围筛选
- 搜索日志内容

**操作功能**：
- 查看日志详情
- 导出日志（CSV）
- 清空日志
- 刷新日志

**分页显示**：
- 每页 50 条（可调整）
- 显示总数和页码
- 快速翻页

### 5. 日志清理

#### 自动清理

系统每天自动清理过期日志，默认保留 30 天。

**配置保留天数**：
在设置页面修改「日志保留天数」。

#### 手动清理

在日志查看页面点击「清空日志」按钮，可以立即清空所有日志。

**注意**：此操作不可撤销，请谨慎使用。

## 使用场景

### 场景 1: 排查上传失败

1. 进入日志页面
2. 筛选类型：upload
3. 筛选级别：error
4. 查看错误详情
5. 根据建议修复问题

### 场景 2: 监控系统运行

1. 定期查看统计信息
2. 关注错误趋势
3. 查看分析报告
4. 及时处理问题

### 场景 3: 性能优化

1. 筛选类型：queue
2. 分析队列处理情况
3. 识别性能瓶颈
4. 调整配置参数

### 场景 4: 迁移问题诊断

1. 筛选类型：migration
2. 查看迁移日志
3. 识别失败原因
4. 重试或修复

## API 使用

### 记录日志

```php
// 获取日志管理器实例
$logger = WPMCS_Plugin::instance()->get_logger();

// 记录不同级别的日志
$logger->debug( 'upload', '调试信息', array( 'file' => 'test.jpg' ) );
$logger->info( 'upload', '上传成功', array( 'url' => $cloud_url ), $attachment_id );
$logger->warning( 'cache', '缓存未命中', array( 'key' => $cache_key ) );
$logger->error( 'upload', '上传失败', array( 'error' => $error_message ), $attachment_id );
$logger->critical( 'system', '严重错误', array( 'details' => $error_details ) );
```

### 查询日志

```php
// 获取最近错误
$result = $logger->get_logs( array(
    'level' => 'error',
    'per_page' => 10,
) );

foreach ( $result['logs'] as $log ) {
    echo $log->created_at . ': ' . $log->message . "\n";
}
```

### 获取统计

```php
// 获取最近 7 天统计
$stats = $logger->get_stats( 7 );

echo '总日志数: ' . $stats['total'] . "\n";
echo '错误数: ' . $stats['by_level']['error'] . "\n";
```

### 分析错误

```php
// 获取错误分析和建议
$analysis = $logger->analyze_errors();

echo $analysis['summary'] . "\n";

foreach ( $analysis['issues'] as $issue ) {
    echo '问题: ' . $issue['message'] . "\n";
}

foreach ( $analysis['recommendations'] as $rec ) {
    echo '建议: ' . $rec . "\n";
}
```

## 数据库结构

日志存储在自定义数据库表中：

```sql
CREATE TABLE wp_wpmcs_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    level varchar(20) NOT NULL DEFAULT 'info',
    type varchar(50) NOT NULL DEFAULT 'system',
    message text NOT NULL,
    context longtext,
    attachment_id bigint(20) unsigned DEFAULT NULL,
    user_id bigint(20) unsigned DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    user_agent varchar(255) DEFAULT NULL,
    request_uri text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY level (level),
    KEY type (type),
    KEY attachment_id (attachment_id),
    KEY created_at (created_at)
);
```

## 最佳实践

### 1. 定期查看日志

- 每周至少查看一次
- 关注错误趋势
- 及时处理警告

### 2. 启用错误通知

- 配置邮件通知
- 确保管理员邮箱有效
- 及时响应通知

### 3. 合理设置保留期

- 根据网站活跃度调整
- 平衡存储空间和历史数据
- 重要日志及时导出

### 4. 分析而非仅查看

- 使用错误分析功能
- 关注建议措施
- 优化系统配置

### 5. 日志导出

- 定期导出重要日志
- 归档历史数据
- 便于离线分析

## 故障排除

### 问题 1: 日志表不存在

**症状**: 查看日志页面报错

**解决方案**:
1. 重新激活插件
2. 手动创建表（见上方 SQL）
3. 检查数据库权限

### 问题 2: 日志记录过多

**症状**: 数据库快速增长

**解决方案**:
1. 减少日志保留天数
2. 禁用 DEBUG 级别日志
3. 定期清理旧日志

### 问题 3: 邮件通知未发送

**症状**: 错误发生但未收到邮件

**解决方案**:
1. 检查邮件通知是否启用
2. 验证 WordPress 邮件功能
3. 检查管理员邮箱地址
4. 查看垃圾邮件文件夹

### 问题 4: 日志页面加载慢

**症状**: 日志列表加载时间过长

**解决方案**:
1. 添加适当的数据库索引
2. 减少每页显示数量
3. 使用筛选功能缩小范围
4. 清理过期日志

## 安全考虑

### 数据保护

- 不记录敏感信息（密钥、密码等）
- IP 地址和 User Agent 用于诊断
- 用户信息用于问题追踪

### 访问控制

- 只有管理员可以查看日志
- AJAX 请求需要权限验证
- Nonce 验证防止 CSRF

### 数据清理

- 自动清理过期数据
- 支持手动清空
- 导出时不包含敏感信息

## 性能影响

### 记录性能

- 单条日志记录: ~1-2ms
- 使用批量插入优化
- 异步处理减少影响

### 存储影响

- 每条日志约 500 字节
- 1000 条日志 ≈ 0.5MB
- 一个月日志 ≈ 15MB（估算）

### 查询性能

- 使用索引优化查询
- 分页显示减少加载
- 缓存常用查询结果

## 高级配置

### 自定义日志级别

```php
// 仅记录错误和严重错误
add_filter( 'wpmcs_log_level', function( $level ) {
    return in_array( $level, array( 'error', 'critical' ) ) ? $level : null;
} );
```

### 自定义通知逻辑

```php
// 自定义错误通知逻辑
add_action( 'wpmcs_log_error', function( $log ) {
    // 自定义通知逻辑
    if ( $log->type === 'upload' ) {
        // 发送短信通知
    }
} );
```

### 禁用日志记录

```php
// 完全禁用日志
add_filter( 'wpmcs_logging_enabled', '__return_false' );
```

## 集成示例

### 在自定义代码中使用

```php
// 上传功能集成
function my_custom_upload( $file_path ) {
    $logger = WPMCS_Plugin::instance()->get_logger();
    
    try {
        // 执行上传
        $result = upload_to_cloud( $file_path );
        
        $logger->info( 'upload', '上传成功', array(
            'file' => $file_path,
            'url' => $result['url']
        ) );
        
        return $result;
        
    } catch ( Exception $e ) {
        $logger->error( 'upload', '上传失败: ' . $e->getMessage(), array(
            'file' => $file_path,
            'trace' => $e->getTraceAsString()
        ) );
        
        return new WP_Error( 'upload_failed', $e->getMessage() );
    }
}
```

---

**提示**: 良好的日志习惯是系统稳定运行的保障。建议养成定期查看和分析日志的习惯，及时发现并解决潜在问题。