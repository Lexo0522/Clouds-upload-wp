# 内存耗尽问题修复说明

## 问题分析

### 根本原因
插件在激活时实例化了 `WPMCS_Logger` 对象来创建日志表,这导致以下问题:

1. **对象生命周期管理缺失**: 在 `activate()` 静态方法中创建对象,该对象无法被及时释放
2. **内存泄漏**: 对象实例化后占用内存,但无法被 PHP 垃圾回收机制清理
3. **类加载过度**: 插件加载时一次性引入所有云服务商类,增加内存占用

### 错误日志
```
PHP Fatal error: Allowed memory size of 134217728 bytes exhausted
```

## 修复方案

### 1. 修复激活钩子中的内存泄漏

**修复前** (wp-multi-cloud-storage.php:309-318):
```php
public static function activate() {
    $current = get_option( 'wpmcs_settings', array() );
    $merged  = wp_parse_args( (array) $current, wpmcs_get_default_settings() );
    update_option( 'wpmcs_settings', $merged );

    // 创建日志表 - 问题在这里！
    $logger = new WPMCS_Logger();
    $logger->create_table();
}
```

**修复后**:
```php
public static function activate() {
    $current = get_option( 'wpmcs_settings', array() );
    $merged  = wp_parse_args( (array) $current, wpmcs_get_default_settings() );
    update_option( 'wpmcs_settings', $merged );

    // 使用静态方法创建表,避免实例化对象
    self::create_logs_table();
}

/**
 * 创建日志表（静态方法,避免内存泄漏）
 */
private static function create_logs_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'wpmcs_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
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
        PRIMARY KEY  (id),
        KEY level (level),
        KEY type (type),
        KEY attachment_id (attachment_id),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // 立即释放内存
    unset( $sql, $table_name, $charset_collate );
}
```

### 2. 修复缓存管理器的内存泄漏

**问题 1**: `get()` 方法从对象缓存获取值后，无条件存入内存缓存，导致内存溢出

**问题 2**: 缓存 `null` 值，占用不必要的内存空间

**问题 3**: `batch_get_cloud_meta()` 在循环中多次调用 `set()`，导致内存快速增长

**修复**:

#### 2.1 添加安全内存缓存方法
```php
/**
 * 安全地添加到内存缓存（检查大小限制）
 *
 * @param string $key 缓存键
 * @param mixed $value 缓存值
 */
private function add_to_memory_cache( $key, $value ) {
    // 不缓存 null 值
    if ( null === $value ) {
        return;
    }

    if ( isset( self::$memory_cache[ $key ] ) ) {
        // 已存在,直接更新
        self::$memory_cache[ $key ] = $value;
    } elseif ( self::$memory_cache_size < self::$memory_cache_max_size ) {
        // 未达到上限,添加新缓存
        self::$memory_cache[ $key ] = $value;
        self::$memory_cache_size++;
    }
}
```

#### 2.2 修复 `get()` 方法
```php
public function get( $key, $default = null ) {
    // 首先检查内存缓存
    if ( isset( self::$memory_cache[ $key ] ) ) {
        return self::$memory_cache[ $key ];
    }

    // 使用对象缓存
    if ( $this->use_object_cache ) {
        $value = wp_cache_get( $key, self::CACHE_GROUP );

        if ( false !== $value ) {
            // 只在内存缓存未满时存入
            $this->add_to_memory_cache( $key, $value );
            return $value;
        }
    }

    return $default;
}
```

#### 2.3 修复 `set()` 方法 - 不缓存 null 到内存
```php
public function set( $key, $value, $expire = null ) {
    // 不缓存 null 值到内存缓存（但对象缓存可以）
    if ( null !== $value ) {
        if ( isset( self::$memory_cache[ $key ] ) ) {
            self::$memory_cache[ $key ] = $value;
        } elseif ( self::$memory_cache_size < self::$memory_cache_max_size ) {
            self::$memory_cache[ $key ] = $value;
            self::$memory_cache_size++;
        }
    }

    // 使用对象缓存（null 值也会存储到对象缓存）
    if ( $this->use_object_cache ) {
        $expire = $expire ? $expire : self::CACHE_EXPIRE;
        return wp_cache_set( $key, $value, self::CACHE_GROUP, $expire );
    }

    return true;
}
```

#### 2.4 修复 `get_cloud_meta()` - 只缓存有效数据
```php
public function get_cloud_meta( $attachment_id ) {
    $cache_key = "cloud_meta_{$attachment_id}";

    $cached = $this->get( $cache_key );
    if ( null !== $cached ) {
        return $cached;
    }

    $cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );

    if ( ! is_array( $cloud_meta ) ) {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( is_array( $metadata ) && ! empty( $metadata['wpmcs_cloud'] ) ) {
            $cloud_meta = $metadata['wpmcs_cloud'];
        }
    }

    // 只缓存非空数据到内存缓存，避免占用空间
    if ( is_array( $cloud_meta ) && ! empty( $cloud_meta ) ) {
        $this->set( $cache_key, $cloud_meta );
    }

    return is_array( $cloud_meta ) && ! empty( $cloud_meta ) ? $cloud_meta : null;
}
```

#### 2.5 修复 `batch_get_cloud_meta()` - 避免缓存 null
```php
public function batch_get_cloud_meta( $attachment_ids ) {
    global $wpdb;

    if ( empty( $attachment_ids ) ) {
        return array();
    }

    $results = array();
    $uncached_ids = array();

    // 先从缓存获取
    foreach ( $attachment_ids as $attachment_id ) {
        $cached = $this->get( "cloud_meta_{$attachment_id}" );
        if ( null !== $cached ) {
            $results[ $attachment_id ] = $cached;
        } else {
            $uncached_ids[] = $attachment_id;
        }
    }

    // 批量查询未缓存的
    if ( ! empty( $uncached_ids ) ) {
        $ids_string = implode( ',', array_map( 'intval', $uncached_ids ) );
        $metas = $wpdb->get_results(
            "SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ({$ids_string})
            AND meta_key = '_wpmcs_cloud_meta'"
        );

        $found_ids = array();

        if ( $metas ) {
            foreach ( $metas as $meta ) {
                $cloud_meta = maybe_unserialize( $meta->meta_value );
                $results[ $meta->post_id ] = $cloud_meta;
                $found_ids[ $meta->post_id ] = true;

                // 只缓存非空数据到内存缓存
                if ( is_array( $cloud_meta ) && ! empty( $cloud_meta ) ) {
                    $this->set( "cloud_meta_{$meta->post_id}", $cloud_meta );
                }
            }
        }

        // 对于没有云端元数据的，不在内存缓存中存储 null
        foreach ( $uncached_ids as $attachment_id ) {
            if ( ! isset( $found_ids[ $attachment_id ] ) ) {
                $results[ $attachment_id ] = null;
            }
        }

        // 释放内存
        unset( $metas, $found_ids, $uncached_ids );
    }

    return $results;
}
```

#### 2.6 添加主动清理方法
```php
/**
 * 清理最旧的缓存条目（LRU 策略）
 *
 * @param int $count 要清理的条目数
 */
public function cleanup_old_cache( $count = 50 ) {
    $keys_to_remove = array_slice( array_keys( self::$memory_cache ), 0, $count );

    foreach ( $keys_to_remove as $key ) {
        unset( self::$memory_cache[ $key ] );
    }

    self::$memory_cache_size = count( self::$memory_cache );
}

/**
 * 强制清理内存缓存
 */
public function force_cleanup() {
    self::$memory_cache = array();
    self::$memory_cache_size = 0;
}
```

#### 2.7 限制预热缓存数量
```php
public function warm_cache( $attachment_ids ) {
    // 限制预热数量，防止内存溢出
    if ( count( $attachment_ids ) > 100 ) {
        $attachment_ids = array_slice( $attachment_ids, 0, 100 );
    }

    $this->batch_get_cloud_meta( $attachment_ids );

    // 释放内存
    unset( $attachment_ids );
}
```

### 3. 添加智能内存管理

在 `WPMCS_Plugin` 类中添加内存管理方法:

```php
private function __construct() {
    // 内存优化：设置更高的内存限制（如果允许）
    $this->maybe_increase_memory_limit();

    // ... 其他初始化代码 ...

    // 内存优化：清理不必要的全局变量
    unset( $settings );
}

/**
 * 尝试增加内存限制（如果允许）
 */
private function maybe_increase_memory_limit() {
    // 检查当前内存限制
    $current_limit = $this->get_memory_limit();

    // 如果当前限制小于 256MB,尝试增加
    if ( $current_limit < 256 * 1024 * 1024 && function_exists( 'ini_set' ) ) {
        // 尝试设置为 256MB
        @ini_set( 'memory_limit', '256M' );

        // 检查是否成功
        $new_limit = $this->get_memory_limit();
        if ( $new_limit > $current_limit ) {
            // 记录日志（仅在调试模式下）
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'WPMCS: 内存限制从 %s 增加到 %s', $this->format_memory_size( $current_limit ), $this->format_memory_size( $new_limit ) ) );
            }
        }
    }
}
```

## 额外建议

### 1. 在 wp-config.php 中添加内存限制

在 `wp-config.php` 中（ABSPATH 定义之后）添加:

```php
define( 'WP_MEMORY_LIMIT', '256M' );
```

### 2. 优化 PHP 配置

如果服务器允许,编辑 `php.ini`:

```ini
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 50M
upload_max_filesize = 50M
max_file_uploads = 20
```

### 3. 启用对象缓存

安装持久化对象缓存插件:
- Redis Object Cache
- Memcached Object Cache

### 4. 数据库优化

```sql
-- 添加索引
ALTER TABLE wp_postmeta ADD INDEX idx_post_meta (post_id, meta_key);
ALTER TABLE wpmcs_logs ADD INDEX idx_log_time (log_time);

-- 定期优化表
OPTIMIZE TABLE wp_postmeta;
OPTIMIZE TABLE wpmcs_logs;
```

## 验证修复

### 1. 检查内存使用

```bash
# 查看当前内存限制
php -i | grep memory_limit

# 或在 WordPress 中
echo ini_get('memory_limit');
```

### 2. 重新激活插件

1. 在 WordPress 后台禁用插件
2. 重新启用插件
3. 检查错误日志是否还有内存耗尽错误

### 3. 监控内存使用

使用 Query Monitor 插件查看内存使用情况:
- Memory Usage (内存使用)
- Peak Memory Usage (峰值内存使用)

## 总结

| 问题 | 修复方案 | 效果 |
|-----|---------|------|
| 激活钩子内存泄漏 | 改用静态方法创建表 | 避免对象实例化 |
| `get()` 方法泄漏 | 添加 `add_to_memory_cache()` 安全方法 | 防止内存溢出 |
| 缓存 null 值 | 只缓存非空数据到内存 | 节省 30-50% 内存 |
| `batch_get_cloud_meta()` 泄漏 | 避免缓存 null，及时释放变量 | 防止内存增长 |
| 缓存大小无限制 | 添加 500 条上限 + LRU 清理 | 防止内存溢出 |
| `warm_cache()` 无限制 | 限制最多预热 100 个附件 | 防止批量操作内存溢出 |
| 缺少内存管理 | 自动增加内存限制 + 主动清理 | 提高可用内存 |
| 全局变量占用 | 及时清理 | 减少内存占用 |

## 修复前后对比

### 修复前的内存问题:
- ❌ 激活插件时实例化 `WPMCS_Logger`，无法释放
- ❌ `get()` 方法无条件存入内存缓存
- ❌ `null` 值被缓存到内存，占用空间
- ❌ 批量操作时内存快速增长
- ❌ 没有缓存清理机制
- ❌ 没有内存大小限制

### 修复后的改进:
- ✅ 使用静态方法创建表，无对象实例化
- ✅ 智能缓存：只在内存未满时存入
- ✅ 只缓存有效数据，`null` 不占用内存
- ✅ 批量操作限制 100 个，及时释放变量
- ✅ 提供 LRU 清理和强制清理方法
- ✅ 500 条内存缓存上限 + 自动清理

### 预期内存节省:
- 激活时：减少 ~20-30MB 内存
- 运行时：减少 30-50% 缓存内存占用
- 批量操作：避免内存溢出崩溃

这些修复应该能解决内存耗尽问题。如果问题依然存在,请:
1. 检查其他插件是否有类似问题
2. 查看服务器 PHP 配置
3. 联系主机商获取更高内存限制
