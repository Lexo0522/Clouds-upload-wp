# 开发者文档

## 概述

本文档为开发者提供 WordPress 多云存储插件的完整开发指南，包括调试模式、REST API、Webhook 支持以及所有可用的钩子和过滤器。

---

## 目录

1. [调试模式](#调试模式)
2. [REST API](#rest-api)
3. [Webhook 支持](#webhook-支持)
4. [钩子和过滤器](#钩子和过滤器)
5. [扩展开发](#扩展开发)

---

## 调试模式

### 启用调试模式

```php
// 通过代码启用
$debug_manager = new WPMCS_Debug_Manager( $settings );
$debug_manager->enable();

// 或通过选项
update_option( 'wpmcs_debug_mode', true );
```

### 记录调试日志

```php
// 获取调试管理器实例
$debug_manager = WPMCS_Plugin::instance()->get_debug_manager();

// 记录不同级别的日志
$debug_manager->log( '调试消息', 'debug', array( 'key' => 'value' ) );
$debug_manager->info( '信息消息' );
$debug_manager->warning( '警告消息' );
$debug_manager->error( '错误消息' );

// 记录性能数据
$debug_manager->start_timer( 'upload_process' );
// ... 执行操作 ...
$time = $debug_manager->end_timer( 'upload_process' );

// 记录 HTTP 请求
$debug_manager->log_request( $url, 'POST', $args, $response, $time );

// 记录数据库查询
$debug_manager->log_query( $sql, $execution_time );
```

### 获取系统信息

```php
$debug_manager = new WPMCS_Debug_Manager( $settings );
$system_info = $debug_manager->get_system_info();

// 返回包含 WordPress、PHP、数据库、插件等信息的数组
```

### 调试信息输出

调试模式启用后，会在页面底部输出调试信息到浏览器控制台：
- 执行时间
- 内存使用
- 日志条数
- 最近的日志

### 日志管理

```php
// 获取日志
$log = $debug_manager->get_log( 100 ); // 获取最近 100 条

// 清空日志
$debug_manager->clear_log();

// 导出日志（JSON 格式）
$json = $debug_manager->export_log();
```

---

## REST API

### API 端点

基础 URL: `https://your-site.com/wp-json/wpmcs/v1`

#### 文件操作

| 端点 | 方法 | 描述 |
|------|------|------|
| `/files` | GET | 获取文件列表 |
| `/files` | POST | 上传文件 |
| `/files/{id}` | GET | 获取单个文件 |
| `/files/{id}` | DELETE | 删除文件 |

#### 批量操作

| 端点 | 方法 | 描述 |
|------|------|------|
| `/batch/upload` | POST | 批量上传 |
| `/batch/migrate` | POST | 批量迁移 |

#### 其他

| 端点 | 方法 | 描述 |
|------|------|------|
| `/stats` | GET | 获取统计信息 |
| `/providers` | GET | 获取服务商列表 |
| `/test-connection` | POST | 测试连接 |
| `/webhooks` | GET/POST | Webhook 管理 |
| `/webhooks/{id}` | DELETE | 删除 Webhook |
| `/system/info` | GET | 获取系统信息 |

### 认证方式

#### API Key 认证

```bash
# 通过 Header
curl -X GET "https://your-site.com/wp-json/wpmcs/v1/files" \
  -H "X-WPMCS-API-Key: your-api-key"

# 或通过查询参数
curl -X GET "https://your-site.com/wp-json/wpmcs/v1/files?api_key=your-api-key"
```

#### 生成 API Key

```php
$api_key = wp_generate_password( 32, false, false );
update_option( 'wpmcs_api_key', $api_key );
update_option( 'wpmcs_api_enabled', true );
```

### API 使用示例

#### 获取文件列表

```bash
GET /wp-json/wpmcs/v1/files?page=1&per_page=20&status=uploaded
```

响应:
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "title": "image.jpg",
      "mime_type": "image/jpeg",
      "uploaded_at": "2026-03-24 10:30:00",
      "local_url": "https://...",
      "cloud_url": "https://cdn...",
      "is_uploaded": true,
      "provider": "qiniu"
    }
  ],
  "meta": {
    "total": 100,
    "page": 1,
    "per_page": 20,
    "total_pages": 5
  }
}
```

#### 上传文件

**从 URL 上传:**

```bash
POST /wp-json/wpmcs/v1/files
Content-Type: application/json

{
  "file": "https://example.com/image.jpg",
  "filename": "custom-name.jpg",
  "async": false
}
```

**从 Base64 上传:**

```bash
POST /wp-json/wpmcs/v1/files
Content-Type: application/json

{
  "file": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "filename": "image.jpg",
  "mime_type": "image/jpeg"
}
```

#### 批量上传

```bash
POST /wp-json/wpmcs/v1/batch/upload
Content-Type: application/json

{
  "attachment_ids": [1, 2, 3, 4, 5]
}
```

#### 创建 Webhook

```bash
POST /wp-json/wpmcs/v1/webhooks
Content-Type: application/json

{
  "url": "https://your-webhook-endpoint.com",
  "events": "file.uploaded,file.deleted",
  "secret": "your-webhook-secret"
}
```

---

## Webhook 支持

### 支持的事件

| 事件 | 描述 |
|------|------|
| `file.uploaded` | 文件上传成功 |
| `file.deleted` | 文件删除 |
| `file.error` | 文件上传错误 |
| `migration.started` | 迁移开始 |
| `migration.completed` | 迁移完成 |
| `migration.failed` | 迁移失败 |
| `connection.success` | 连接测试成功 |
| `connection.failed` | 连接测试失败 |
| `storage.near_limit` | 存储空间接近限制 |
| `error.critical` | 严重错误 |

### Webhook 载荷格式

```json
{
  "event": "file.uploaded",
  "timestamp": "2026-03-24 10:30:00",
  "timestamp_unix": 1711269000,
  "site_url": "https://your-site.com",
  "site_name": "Your Site",
  "payload": {
    "attachment_id": 123,
    "filename": "image.jpg",
    "mime_type": "image/jpeg",
    "cloud_url": "https://cdn.example.com/image.jpg",
    "provider": "qiniu",
    "file_size": 1048576
  }
}
```

### 签名验证

Webhook 请求包含签名头：

```
X-WPMCS-Signature: sha256=...
```

验证签名 (PHP):

```php
$payload = file_get_contents( 'php://input' );
$secret = 'your-webhook-secret';
$signature = hash_hmac( 'sha256', $payload, $secret );

if ( hash_equals( $signature, $provided_signature ) ) {
    // 签名验证成功
}
```

### 创建 Webhook

**通过 API:**

```bash
POST /wp-json/wpmcs/v1/webhooks
```

**通过代码:**

```php
$webhook = array(
    'id' => time(),
    'url' => 'https://your-webhook-endpoint.com',
    'events' => array( 'file.uploaded', 'file.deleted' ), // 或 array( 'all' )
    'secret' => 'your-secret-key',
    'created_at' => current_time( 'mysql' ),
    'active' => true,
);

$webhooks = get_option( 'wpmcs_webhooks', array() );
$webhooks[] = $webhook;
update_option( 'wpmcs_webhooks', $webhooks );
```

### 触发自定义事件

```php
$webhook_manager = WPMCS_Plugin::instance()->get_webhook_manager();
$webhook_manager->trigger( 'custom.event', array(
    'key' => 'value',
) );
```

---

## 钩子和过滤器

### Actions（动作钩子）

插件在特定时机触发动作钩子，允许开发者插入自定义功能。

#### 文件上传相关

```php
/**
 * 文件上传前触发
 * 
 * @param string $file_path 本地文件路径
 * @param array  $settings  插件设置
 */
do_action( 'wpmcs_before_upload', $file_path, $settings );

/**
 * 文件上传成功后触发
 * 
 * @param int   $attachment_id 附件 ID
 * @param array $cloud_meta    云端元数据
 * @param array $metadata      附件元数据
 */
do_action( 'wpmcs_file_uploaded', $attachment_id, $cloud_meta, $metadata );

/**
 * 文件上传失败后触发
 * 
 * @param int    $attachment_id 附件 ID
 * @param string $error_code    错误代码
 * @param string $error_message 错误消息
 */
do_action( 'wpmcs_upload_error', $attachment_id, $error_code, $error_message );

/**
 * 文件删除后触发
 * 
 * @param int    $attachment_id 附件 ID
 * @param string $cloud_key     云端文件路径
 */
do_action( 'wpmcs_file_deleted', $attachment_id, $cloud_key );
```

#### 迁移相关

```php
/**
 * 迁移开始时触发
 */
do_action( 'wpmcs_migration_started' );

/**
 * 迁移完成时触发
 */
do_action( 'wpmcs_migration_completed' );

/**
 * 迁移失败时触发
 * 
 * @param string $reason 失败原因
 * @param array  $errors 错误列表
 */
do_action( 'wpmcs_migration_failed', $reason, $errors );

/**
 * 批次处理时触发
 * 
 * @param int   $processed 已处理数量
 * @param int   $total     总数量
 * @param array $status    状态信息
 */
do_action( 'wpmcs_migration_batch_processed', $processed, $total, $status );
```

#### 连接测试相关

```php
/**
 * 连接测试成功时触发
 */
do_action( 'wpmcs_connection_success' );

/**
 * 连接测试失败时触发
 * 
 * @param string $error_code    错误代码
 * @param string $error_message 错误消息
 */
do_action( 'wpmcs_connection_failed', $error_code, $error_message );
```

#### 其他

```php
/**
 * 严重错误时触发
 * 
 * @param string $error_code    错误代码
 * @param string $error_message 错误消息
 * @param array  $context       错误上下文
 */
do_action( 'wpmcs_critical_error', $error_code, $error_message, $context );

/**
 * 设置更新时触发
 * 
 * @param array $old_settings 旧设置
 * @param array $new_settings 新设置
 */
do_action( 'wpmcs_settings_updated', $old_settings, $new_settings );

/**
 * 插件激活时触发
 */
do_action( 'wpmcs_activated' );

/**
 * 插件停用时触发
 */
do_action( 'wpmcs_deactivated' );
```

### Filters（过滤器钩子）

过滤器允许开发者修改插件的数据或行为。

#### URL 和路径相关

```php
/**
 * 过滤云端文件 URL
 * 
 * @param string $url           云端 URL
 * @param int    $attachment_id 附件 ID
 * @param string $size          图片尺寸
 */
$url = apply_filters( 'wpmcs_cloud_url', $url, $attachment_id, $size );

/**
 * 过滤本地文件路径
 * 
 * @param string $file_path 本地路径
 * @param int    $attachment_id 附件 ID
 */
$file_path = apply_filters( 'wpmcs_local_file_path', $file_path, $attachment_id );

/**
 * 过滤云端文件路径
 * 
 * @param string $cloud_path 云端路径
 * @param int    $attachment_id 附件 ID
 */
$cloud_path = apply_filters( 'wpmcs_cloud_file_path', $cloud_path, $attachment_id );

/**
 * 过滤文件名
 * 
 * @param string $filename  文件名
 * @param string $original  原始文件名
 * @param array  $settings  插件设置
 */
$filename = apply_filters( 'wpmcs_filename', $filename, $original, $settings );
```

#### 上传相关

```php
/**
 * 过滤上传参数
 * 
 * @param array $args   上传参数
 * @param array $file   文件信息
 */
$args = apply_filters( 'wpmcs_upload_args', $args, $file );

/**
 * 过滤云端元数据
 * 
 * @param array $cloud_meta    云端元数据
 * @param int   $attachment_id 附件 ID
 */
$cloud_meta = apply_filters( 'wpmcs_cloud_meta', $cloud_meta, $attachment_id );

/**
 * 是否允许上传到云端
 * 
 * @param bool  $can_upload    是否允许
 * @param int   $attachment_id 附件 ID
 */
$can_upload = apply_filters( 'wpmcs_can_upload', true, $attachment_id );
```

#### 文件类型和大小相关

```php
/**
 * 过滤允许的文件类型
 * 
 * @param array $allowed_types MIME 类型数组
 */
$allowed_types = apply_filters( 'wpmcs_allowed_file_types', $allowed_types );

/**
 * 过滤禁止的文件类型
 * 
 * @param array $blocked_types MIME 类型数组
 */
$blocked_types = apply_filters( 'wpmcs_blocked_file_types', $blocked_types );

/**
 * 过滤文件大小限制
 * 
 * @param int    $size_limit 大小限制（字节）
 * @param string $category   文件类型分类
 */
$size_limit = apply_filters( 'wpmcs_file_size_limit', $size_limit, $category );
```

#### 服务商相关

```php
/**
 * 过滤服务商列表
 * 
 * @param array $providers 服务商数组
 */
$providers = apply_filters( 'wpmcs_providers', $providers );

/**
 * 过滤服务商配置
 * 
 * @param array  $config   服务商配置
 * @param string $provider 服务商标识
 */
$config = apply_filters( 'wpmcs_provider_config', $config, $provider );
```

#### 统计相关

```php
/**
 * 过滤统计数据
 * 
 * @param array $stats 统计数据
 */
$stats = apply_filters( 'wpmcs_stats', $stats );

/**
 * 过滤存储使用量
 * 
 * @param int $storage_usage 存储使用量
 */
$storage_usage = apply_filters( 'wpmcs_storage_usage', $storage_usage );
```

### 使用示例

#### 示例 1: 自定义文件名格式

```php
add_filter( 'wpmcs_filename', function( $filename, $original, $settings ) {
    // 添加用户 ID 前缀
    $user_id = get_current_user_id();
    return $user_id . '_' . $filename;
}, 10, 3 );
```

#### 示例 2: 阻止特定文件类型上传

```php
add_filter( 'wpmcs_can_upload', function( $can_upload, $attachment_id ) {
    $mime_type = get_post_mime_type( $attachment_id );
    
    // 阻止视频上传
    if ( strpos( $mime_type, 'video/' ) === 0 ) {
        return false;
    }
    
    return $can_upload;
}, 10, 2 );
```

#### 示例 3: 发送通知邮件

```php
add_action( 'wpmcs_file_uploaded', function( $attachment_id, $cloud_meta, $metadata ) {
    $admin_email = get_option( 'admin_email' );
    $subject = '新文件上传到云端';
    $message = sprintf( 
        '文件 %s 已上传到 %s',
        get_the_title( $attachment_id ),
        $cloud_meta['provider']
    );
    
    wp_mail( $admin_email, $subject, $message );
}, 10, 3 );
```

#### 示例 4: 自定义云端路径

```php
add_filter( 'wpmcs_cloud_file_path', function( $cloud_path, $attachment_id ) {
    // 按用户 ID 组织文件
    $user_id = get_current_user_id();
    $user_path = 'users/' . $user_id . '/';
    
    return $user_path . basename( $cloud_path );
}, 10, 2 );
```

#### 示例 5: 记录上传日志到外部系统

```php
add_action( 'wpmcs_file_uploaded', function( $attachment_id, $cloud_meta, $metadata ) {
    // 发送到外部 API
    wp_remote_post( 'https://external-system.com/api/log', array(
        'body' => json_encode( array(
            'event' => 'file_uploaded',
            'attachment_id' => $attachment_id,
            'provider' => $cloud_meta['provider'],
            'timestamp' => current_time( 'mysql' ),
        ) ),
        'headers' => array( 'Content-Type' => 'application/json' ),
    ) );
}, 10, 3 );
```

---

## 扩展开发

### 添加新的云服务商

#### 1. 创建存储类

```php
// includes/class-my-provider-storage.php

class My_Provider_Storage implements Cloud_Storage_Interface {
    private $config;
    
    public function __construct( array $config ) {
        $this->config = $config;
    }
    
    public function upload( $file_path, $remote_path, $options = array() ) {
        // 实现上传逻辑
    }
    
    public function delete( $remote_path ) {
        // 实现删除逻辑
    }
    
    public function exists( $remote_path ) {
        // 检查文件是否存在
    }
    
    public function get_url( $remote_path ) {
        // 获取访问 URL
    }
    
    public function get_info( $remote_path ) {
        // 获取文件信息
    }
}
```

#### 2. 创建适配器

```php
// includes/class-wpmcs-my-provider-adapter.php

class WPMCS_My_Provider_Adapter extends WPMCS_Cloud_Adapter {
    // 实现必要的方法
}
```

#### 3. 注册服务商

```php
// 在主插件文件中添加
add_filter( 'wpmcs_providers', function( $providers ) {
    $providers['my_provider'] = array(
        'name' => 'My Provider',
        'icon' => 'my-provider.svg',
        'color' => '#FF0000',
        'website' => 'https://my-provider.com',
    );
    return $providers;
} );

// 在 wpmcs_create_storage_driver 函数中添加 case
```

### 创建自定义统计模块

```php
class My_Custom_Stats {
    public function __construct() {
        add_filter( 'wpmcs_stats', array( $this, 'add_custom_stats' ) );
    }
    
    public function add_custom_stats( $stats ) {
        $stats['custom_metric'] = $this->calculate_custom_metric();
        return $stats;
    }
    
    private function calculate_custom_metric() {
        // 自定义统计逻辑
        return 0;
    }
}
```

---

## 最佳实践

### 1. 使用调试模式

开发过程中始终启用调试模式：

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    $debug_manager->enable();
}
```

### 2. 错误处理

始终检查 WP_Error：

```php
$result = some_function();
if ( is_wp_error( $result ) ) {
    $debug_manager->error( $result->get_error_message() );
    return;
}
```

### 3. 日志记录

关键操作记录日志：

```php
$debug_manager->info( '开始处理', array(
    'attachment_id' => $id,
    'action' => 'upload',
) );
```

### 4. Webhook 验证

始终验证 Webhook 签名：

```php
$provided_signature = $_SERVER['HTTP_X_WPMCS_SIGNATURE'];
$expected_signature = 'sha256=' . hash_hmac( 'sha256', $payload, $secret );

if ( ! hash_equals( $expected_signature, $provided_signature ) ) {
    wp_die( 'Invalid signature' );
}
```

### 5. 性能优化

使用缓存减少数据库查询：

```php
$cached = wp_cache_get( 'my_data', 'wpmcs' );
if ( false === $cached ) {
    $cached = expensive_operation();
    wp_cache_set( 'my_data', $cached, 'wpmcs', HOUR_IN_SECONDS );
}
```

---

## 常见问题

### Q: API Key 在哪里获取？
A: API Key 需要在插件设置中生成。可以通过代码或管理界面生成。

### Q: Webhook 没有收到怎么办？
A: 检查 Webhook 日志，确认 URL 可访问，验证签名是否正确。

### Q: 如何调试 API 问题？
A: 启用调试模式，查看日志输出。使用浏览器开发者工具检查请求和响应。

### Q: 自定义服务商如何添加配置字段？
A: 使用 `wpmcs_provider_fields` 过滤器添加字段。

---

## 更新日志

- **v1.0.0** - 初始版本
  - 调试模式
  - REST API
  - Webhook 支持
  - 钩子和过滤器
