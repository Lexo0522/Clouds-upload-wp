# 性能优化指南

## 概述

WP Multi Cloud Storage 插件实现了多种性能优化机制，确保在高负载环境下也能高效运行。

## 核心优化功能

### 1. 异步上传队列

#### 工作原理

传统上传方式会阻塞用户操作，用户需要等待文件上传完成才能继续。异步上传队列将上传任务放入后台队列，由 WordPress Cron 系统异步处理。

```
用户上传文件 → 保存到本地 → 加入队列 → 返回成功
                              ↓
                    后台定时处理 → 上传到云端 → 更新元数据
```

#### 优势

- ✅ **无阻塞**: 用户无需等待云端上传完成
- ✅ **高并发**: 支持大量文件同时上传
- ✅ **可靠性**: 失败自动重试，最多 3 次
- ✅ **优先级**: 支持任务优先级管理

#### 配置

在插件设置页面启用「异步上传」选项。

#### 技术实现

**队列数据结构**:
```php
array(
    'attachment_id' => 123,
    'priority' => 10,
    'added_at' => 1648123456,
    'attempts' => 0,
    'max_attempts' => 3,
    'last_error' => ''
)
```

**处理流程**:
1. 每分钟执行一次 Cron 任务
2. 获取队列锁定（防止并发）
3. 批量处理 5 个任务
4. 更新队列状态
5. 释放锁定

**锁定机制**:
- 使用 WordPress Options 存储锁定状态
- 锁定时间：5 分钟
- 自动清理过期锁定

### 2. 缓存机制

#### 多层缓存架构

```
请求 → 内存缓存 → 对象缓存 → 数据库
         (最快)      (快)      (慢)
```

#### 缓存内容

**云端元数据缓存**:
- 键: `cloud_meta_{attachment_id}`
- 内容: 云端文件信息
- 过期: 1 小时

**云端 URL 缓存**:
- 键: `cloud_url_{attachment_id}_{size}`
- 内容: 完整的云端 URL
- 过期: 1 小时

#### 缓存 API

```php
// 获取缓存
$cache_manager->get( $key, $default );

// 设置缓存
$cache_manager->set( $key, $value, $expire );

// 删除缓存
$cache_manager->delete( $key );

// 清空缓存
$cache_manager->flush();
```

#### 批量查询优化

```php
// 批量获取云端元数据（单次数据库查询）
$metas = $cache_manager->batch_get_cloud_meta( $attachment_ids );
```

#### 缓存预热

在批量操作前预热缓存，避免后续大量查询：

```php
// 预热缓存
$cache_manager->warm_cache( $attachment_ids );
```

### 3. 数据库优化

#### 批量查询

使用单条 SQL 查询获取多个附件的元数据：

```sql
SELECT post_id, meta_value 
FROM wp_postmeta 
WHERE post_id IN (1,2,3,4,5) 
AND meta_key = '_wpmcs_cloud_meta'
```

#### 索引优化

确保 `postmeta` 表有以下索引：
- `post_id` 索引
- `meta_key` 索引
- 复合索引 `(post_id, meta_key)`

#### 查询计数

内置查询计数器，用于性能监控：

```php
// 获取查询次数
$query_count = WPMCS_Cache_Manager::get_query_count();
```

## 性能监控

### 查看缓存统计

```php
$stats = $cache_manager->get_stats();
// 返回:
// array(
//     'memory_cache_count' => 25,
//     'object_cache_enabled' => true
// )
```

### 查看队列状态

```php
$stats = $async_queue->get_queue_stats();
// 返回:
// array(
//     'total' => 100,
//     'pending' => 95,
//     'processing' => 3,
//     'failed' => 2,
//     'high_priority' => 10
// )
```

## 性能基准

### 同步上传 vs 异步上传

| 场景 | 同步上传 | 异步上传 |
|------|----------|----------|
| 10 个小文件 | ~20秒 | ~2秒（后台处理） |
| 100 个文件 | ~3分钟 | ~10秒（后台处理） |
| 1000 个文件 | ~30分钟 | ~2分钟（后台处理） |

### 缓存命中率

- 首次访问: 数据库查询
- 二次访问: 缓存命中
- 缓存命中率: > 95%

### 数据库查询优化

- 无缓存: 每个附件 1-2 次查询
- 有缓存: 批量查询 + 缓存复用
- 查询减少: > 90%

## 最佳实践

### 1. 启用缓存

在插件设置中启用缓存：
```
设置 → WP Multi Cloud Storage → 启用缓存 ✓
```

### 2. 使用对象缓存插件

安装持久化对象缓存插件：
- Redis Object Cache
- Memcached Object Cache
- W3 Total Cache

### 3. 异步上传适合场景

**适合**:
- 大量文件上传
- 高并发网站
- 访问量高的时段

**不适合**:
- 文件需要立即访问
- 对实时性要求极高

### 4. 批量操作优化

进行批量操作时：
```php
// 1. 预热缓存
$cache_manager->warm_cache( $attachment_ids );

// 2. 批量处理
foreach ( $attachment_ids as $id ) {
    $url = $cache_manager->get_cloud_url( $id );
    // 使用 URL
}

// 3. 清理缓存（如有修改）
$cache_manager->flush();
```

### 5. 服务器配置优化

**PHP 配置**:
```ini
memory_limit = 256M
max_execution_time = 300
```

**数据库优化**:
```sql
-- 添加索引
ALTER TABLE wp_postmeta ADD INDEX idx_post_meta (post_id, meta_key);

-- 定期优化表
OPTIMIZE TABLE wp_postmeta;
```

## 监控和调试

### 启用调试模式

```php
// 在 wp-config.php 中添加
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### 查看队列处理日志

异步队列处理时会记录日志：
```
wp-content/debug.log
```

### 监控 Cron 任务

使用插件查看 Cron 执行情况：
- WP Crontrol
- Advanced Cron Manager

### 性能分析

使用查询分析插件：
- Query Monitor
- Debug Bar

## 故障排除

### 问题 1: 异步上传未执行

**可能原因**:
- Cron 未运行
- 队列被锁定

**解决方案**:
1. 检查 Cron 是否工作
2. 清理锁定: 删除 `wpmcs_queue_lock` option
3. 手动触发: 访问 `wp-cron.php?doing_wp_cron`

### 问题 2: 缓存失效

**可能原因**:
- 对象缓存插件配置错误
- 内存不足

**解决方案**:
1. 检查缓存插件状态
2. 增加内存限制
3. 清空缓存重新构建

### 问题 3: 队列积压

**可能原因**:
- 文件太大
- 网络慢
- Cron 间隔太长

**解决方案**:
1. 减小批次大小
2. 缩短 Cron 间隔
3. 增加并发处理（需要修改代码）

## 高级配置

### 自定义批次大小

```php
// 修改批次大小（默认 5）
define( 'WPMCS_BATCH_SIZE', 10 );
```

### 自定义缓存过期时间

```php
// 修改缓存过期时间（默认 3600 秒）
define( 'WPMCS_CACHE_EXPIRE', 7200 );
```

### 自定义锁定时间

```php
// 修改锁定时间（默认 300 秒）
define( 'WPMCS_LOCK_EXPIRE', 600 );
```

## 性能优化建议总结

### 优先级排序

1. **启用缓存** - 最重要，效果最明显
2. **使用对象缓存** - 提升缓存持久性
3. **异步上传** - 适合高并发场景
4. **数据库优化** - 确保查询效率
5. **服务器优化** - 基础保障

### 性能检查清单

- [ ] 启用缓存选项
- [ ] 安装对象缓存插件
- [ ] 配置 PHP 内存限制 >= 256M
- [ ] 添加数据库索引
- [ ] 根据需要启用异步上传
- [ ] 定期监控队列状态
- [ ] 定期清理过期数据

---

**提示**: 性能优化是一个持续的过程。建议定期监控网站性能，根据实际情况调整配置。