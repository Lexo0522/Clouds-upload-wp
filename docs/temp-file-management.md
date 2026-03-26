# 临时文件管理说明

## 概述

`WPMCS_Temp_File_Manager` 提供了安全、高效的临时文件管理功能。

## 功能特性

### 1. 安全的临时文件存储

- ✅ 自动创建保护目录（`.htaccess` + `index.php`）
- ✅ 递归清理过期文件
- ✅ 文件权限控制（640）
- ✅ 防止目录遍历攻击

### 2. 自动清理

- ✅ 定时任务自动清理（每日）
- ✅ 可配置过期时间（默认 24 小时）
- ✅ 递归删除子目录

### 3. 统计监控

- ✅ 文件数量统计
- ✅ 总大小统计
- ✅ 过期文件数量
- ✅ 最新/最旧文件时间

## 使用方法

### 创建临时文件

```php
// 创建临时文本文件
$filepath = WPMCS_Temp_File_Manager::create_temp_file(
	'This is temporary content',
	'upload-',        // 文件名前缀
	'txt'            // 文件扩展名
);

// 创建临时二进制文件
$image_data = file_get_contents( 'image.jpg' );
$temp_image = WPMCS_Temp_File_Manager::create_temp_file(
	$image_data,
	'image-',
	'jpg'
);
```

### 创建临时目录

```php
// 创建临时目录
$temp_dir = WPMCS_Temp_File_Manager::create_temp_dir( 'batch-upload-' );

// 在目录中创建文件
file_put_contents( $temp_dir . '/file1.txt', 'content' );
file_put_contents( $temp_dir . '/file2.txt', 'content' );

// 清理时自动递归删除
```

### 手动清理临时文件

```php
// 清理超过 24 小时的文件（默认）
$count = WPMCS_Temp_File_Manager::cleanup_temp_files();
echo "Cleaned $count files";

// 清理超过 1 小时的文件
$count = WPMCS_Temp_File_Manager::cleanup_temp_files( 3600 );
echo "Cleaned $count files";

// 强制清理所有临时文件
$count = WPMCS_Temp_File_Manager::clear_all_temp_files();
echo "Cleared all $count files";
```

### 获取统计信息

```php
$stats = WPMCS_Temp_File_Manager::get_stats();

echo "临时目录: " . ( $stats['temp_dir_exists'] ? '存在' : '不存在' ) . "\n";
echo "文件数量: " . $stats['total_files'] . "\n";
echo "总大小: " . WPMCS_Temp_File_Manager::format_size( $stats['total_size'] ) . "\n";
echo "过期文件: " . $stats['expired_files'] . "\n";

if ( $stats['oldest_file'] > 0 ) {
	echo "最旧文件: " . date( 'Y-m-d H:i:s', $stats['oldest_file'] ) . "\n";
}
if ( $stats['newest_file'] > 0 ) {
	echo "最新文件: " . date( 'Y-m-d H:i:s', $stats['newest_file'] ) . "\n";
}
```

## 安全特性

### 1. 目录保护

临时目录自动创建保护文件：

**`.htaccess`**
```apache
Deny from all
```

**`index.php`**
```php
<?php
// Silence is golden.
```

这防止了：
- ❌ 直接访问临时文件
- ❌ 目录列表暴露
- ❌ 未授权访问

### 2. 文件权限

- 创建的文件权限：`640` (所有者读写，组只读)
- 目录权限：`755` (WordPress 默认)

### 3. 唯一文件名

使用 `wp_generate_password()` 生成随机文件名：
```
wpmcs-aB3dEf5gH7jK9.txt
```

### 4. 路径验证

清理前验证目录名，防止误删：
```php
$dir_name = basename( $temp_dir );
if ( 'wpmcs-temp' === $dir_name ) {
    // 安全清理
}
```

## 定时清理

插件会自动注册定时任务：

```php
// 每日清理过期临时文件
wp_schedule_event( time(), 'daily', 'wpmcs_cleanup_temp_files' );
```

执行时会清理：
- ✅ 超过 24 小时的文件
- ✅ 空子目录
- ✅ 递归子目录中的文件

## 目录结构

```
wp-content/uploads/
└── wpmcs-temp/
    ├── .htaccess           # 访问保护
    ├── index.php          # 防止目录列表
    ├── wpmcs-aB3dEf5.txt   # 临时文件
    ├── wpmcs-xY9zW2.jpg
    └── batch-upload-/
        ├── .htaccess       # 子目录保护
        ├── file1.txt
        └── file2.txt
```

## 卸载清理

卸载插件时会：
1. ✅ 递归删除 `wpmcs-temp` 目录
2. ✅ 删除所有文件和子目录
3. ✅ 记录删除的文件数量

## 使用场景

### 场景 1：上传前临时存储

```php
// 用户上传文件到临时目录
$temp_file = WPMCS_Temp_File_Manager::create_temp_file(
	$file_content,
	'upload-',
	'tmp'
);

// 处理文件
$processed = process_file( $temp_file );

// 上传到云存储后，临时文件会自动清理
upload_to_cloud( $processed );
```

### 场景 2：批量操作缓存

```php
// 创建临时目录存储批量上传的文件
$temp_dir = WPMCS_Temp_File_Manager::create_temp_dir( 'batch-' );

// 保存文件到临时目录
foreach ( $files as $file ) {
	file_put_contents( $temp_dir . '/' . $file['name'], $file['content'] );
}

// 批量上传
batch_upload( $temp_dir );

// 定时任务会自动清理
```

### 场景 3：日志转储

```php
// 将大日志转储到临时文件
$temp_log = WPMCS_Temp_File_Manager::create_temp_file(
	$log_content,
	'log-',
	'log'
);

// 发送日志到远程服务
send_log_to_remote( $temp_log );

// 自动清理
```

## 性能优化

### 1. 递归扫描优化

- 使用 `scandir()` 而非 `glob()`
- 跳过特殊目录（`.` 和 `..`）
- 跳过保护文件（`.htaccess`, `index.php`）

### 2. 文件时间检查

- 使用 `filemtime()` 检查修改时间
- 只处理过期文件
- 减少不必要的操作

### 3. 批量删除

- 删除前收集所有路径
- 避免重复扫描
- 减少系统调用

## 故障排除

### 问题 1：临时文件未清理

**原因**：定时任务未运行

**解决**：
```php
// 检查定时任务
$next = wp_next_scheduled( 'wpmcs_cleanup_temp_files' );
if ( $next ) {
	echo "Next cleanup: " . date( 'Y-m-d H:i:s', $next );
} else {
	echo "Cron not scheduled";
}

// 手动触发清理
$count = WPMCS_Temp_File_Manager::cleanup_temp_files();
```

### 问题 2：无法创建临时文件

**原因**：权限不足

**解决**：
```bash
# 检查上传目录权限
ls -la wp-content/uploads/

# 确保可写
chmod 755 wp-content/uploads/
```

### 问题 3：临时文件直接可访问

**原因**：`.htaccess` 未生效（Nginx）

**解决**：
在 Nginx 配置中添加：
```nginx
location ~* /wp-content/uploads/wpmcs-temp/ {
    deny all;
    return 404;
}
```

## 最佳实践

### 1. 及时清理

- 使用完临时文件后尽快删除
- 不要依赖自动清理处理敏感数据
- 敏感数据应在处理后立即删除

### 2. 使用有意义的文件名前缀

```php
// 好
$temp_file = WPMCS_Temp_File_Manager::create_temp_file(
	$data,
	'upload-',  // 明确标识用途
	'tmp'
);

// 差
$temp_file = WPMCS_Temp_File_Manager::create_temp_file(
	$data,
	'',        // 不清楚用途
	'tmp'
);
```

### 3. 监控临时文件

定期检查统计信息：
```php
$stats = WPMCS_Temp_File_Manager::get_stats();
if ( $stats['total_size'] > 100 * 1024 * 1024 ) { // 100MB
	// 记录警告
	error_log( 'Temp files size exceeds 100MB' );
}
```

### 4. 使用合适的过期时间

```php
// 短期缓存（1小时）
WPMCS_Temp_File_Manager::cleanup_temp_files( 3600 );

// 中期缓存（1天，默认）
WPMCS_Temp_File_Manager::cleanup_temp_files();

// 长期缓存（1周）
WPMCS_Temp_File_Manager::cleanup_temp_files( 604800 );
```

## 相关文件

- `includes/class-wpmcs-temp-file-manager.php` - 临时文件管理器类
- `uninstall.php` - 卸载清理逻辑
- `wp-multi-cloud-storage.php` - 定时任务注册

## 更新日志

- **v0.2.0** - 添加临时文件管理器
  - 自动创建保护目录
  - 定时清理过期文件
  - 统计监控功能
  - 安全文件权限
