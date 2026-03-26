# 安全增强功能文档

## 概述

本插件提供全面的安全增强功能，包括文件类型控制、大小限制、权限控制和敏感信息加密，确保云存储上传的安全性。

## 功能模块

### 1. 文件类型控制

#### 白名单机制
- 定义允许上传的文件 MIME 类型
- 默认支持常见图片、视频、音频、文档格式
- 可自定义扩展白名单

**默认白名单**:
```
图片: image/jpeg, image/png, image/gif, image/webp, image/svg+xml
视频: video/mp4, video/mpeg, video/quicktime
音频: audio/mpeg, audio/mp3, audio/wav
文档: application/pdf, application/msword, application/vnd.openxmlformats-officedocument.*
压缩: application/zip, application/x-rar-compressed
```

#### 黑名单机制
- 定义禁止上传的文件类型
- 优先级高于白名单
- 默认禁止可执行文件和脚本文件

**默认黑名单**:
```
可执行文件: application/x-msdownload, application/x-executable
脚本文件: application/x-php, text/javascript, text/vbscript
系统文件: application/x-ms-shortcut
```

#### 危险扩展名阻止
自动阻止以下危险扩展名：
```
php, php3, php4, php5, phtml, phar
exe, bat, cmd, com, msi
sh, bash, zsh, py, pl, rb
asp, aspx, jsp, sql
htaccess, htpasswd
```

### 2. 文件大小限制

#### 分类限制
根据文件类型设置不同的大小限制：

| 文件类型 | 默认限制 | 说明 |
|---------|---------|------|
| 图片 | 10 MB | image/* |
| 视频 | 500 MB | video/* |
| 音频 | 50 MB | audio/* |
| 文档 | 20 MB | application/* |
| 文本 | 5 MB | text/* |
| 默认 | 10 MB | 其他类型 |

#### 服务器限制
自动检测并应用以下限制：
- PHP upload_max_filesize
- PHP post_max_size
- WordPress WP_MEMORY_LIMIT

### 3. 严格安全模式

启用后将执行深度检查：

#### 文件内容扫描
- 检测 PHP 标签
- 检测 JavaScript 脚本
- 检测 eval(), base64_decode() 等危险函数
- 检测系统命令执行函数

#### MIME 类型验证
- 使用 fileinfo 扩展检测真实 MIME 类型
- 防止伪造文件扩展名
- 图片文件有效性验证

#### SVG 特殊处理
- 检查 SVG 中的脚本标签
- 检查事件处理器 (onload, onerror 等)
- 防止 XSS 攻击

### 4. 权限控制

#### 角色权限映射

| 角色 | 上传文件 | 管理设置 | 查看统计 | 查看日志 |
|------|---------|---------|---------|---------|
| 管理员 | ✅ | ✅ | ✅ | ✅ |
| 编辑 | ✅ | ❌ | ❌ | ❌ |
| 作者 | ✅ | ❌ | ❌ | ❌ |
| 投稿者 | ❌ | ❌ | ❌ | ❌ |
| 订阅者 | ❌ | ❌ | ❌ | ❌ |

#### 权限检查方法
```php
// 检查用户是否可以上传文件
$security_manager = new WPMCS_Security_Manager( $settings );
if ( $security_manager->can_upload_files() ) {
    // 允许上传
}

// 检查用户是否可以管理设置
if ( $security_manager->can_manage_settings() ) {
    // 允许管理
}
```

### 5. 敏感信息加密

#### 加密算法
- **算法**: AES-256-CBC
- **密钥长度**: 256 位
- **IV**: 随机生成

#### 自动加密字段
以下字段自动加密存储：
- `access_key` - 访问密钥
- `secret_key` - 密钥
- `secret_id` - 密钥 ID
- `password` - 密码

#### 使用方法

**加密数据**:
```php
$security_manager = new WPMCS_Security_Manager( $settings );
$encrypted = $security_manager->encrypt( 'sensitive_data' );
```

**解密数据**:
```php
$decrypted = $security_manager->decrypt( $encrypted_data );
```

**加密设置**:
```php
$settings = $security_manager->encrypt_settings( $settings );
update_option( 'wpmcs_settings', $settings );
```

**解密设置**:
```php
$settings = get_option( 'wpmcs_settings' );
$settings = $security_manager->decrypt_settings( $settings );
```

## 管理界面

### 访问路径
WordPress 后台 → 设置 → 云存储安全

### 安全统计
- 今日上传数量
- 被拦截文件数量
- 警告事件数量
- 安全评分

### 最近安全事件
显示最近 10 条安全相关日志：
- 时间
- 事件类型
- 文件名
- 用户
- 状态（已允许/已拦截/警告）

## 配置选项

### 文件类型设置
```php
// 自定义白名单
update_option( 'wpmcs_allowed_file_types', 'image/jpeg,image/png,application/pdf' );

// 自定义黑名单
update_option( 'wpmcs_blocked_file_types', 'application/x-pdf' );

// 阻止危险扩展名
update_option( 'wpmcs_block_dangerous_extensions', '1' );
```

### 文件大小设置
```php
// 图片大小限制（字节）
update_option( 'wpmcs_file_size_limits_image', 20971520 ); // 20 MB

// 视频大小限制
update_option( 'wpmcs_file_size_limits_video', 1073741824 ); // 1 GB
```

### 安全模式设置
```php
// 启用严格模式
update_option( 'wpmcs_strict_mode', '1' );

// 真实 MIME 检测
update_option( 'wpmcs_check_real_mime', '1' );

// 加密敏感数据
update_option( 'wpmcs_encrypt_sensitive_data', '1' );
```

## API 参考

### 验证文件类型

```php
$security_manager = new WPMCS_Security_Manager( $settings );

// 验证文件类型
$result = $security_manager->validate_file_type( 
    'document.pdf',           // 文件名
    'application/pdf',        // MIME 类型
    '/tmp/uploaded_file'      // 文件路径（可选）
);

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
} else {
    echo '文件类型验证通过';
}
```

### 验证文件大小

```php
// 验证文件大小
$result = $security_manager->validate_file_size( 
    5242880,                  // 文件大小（字节）
    'image/jpeg'              // MIME 类型
);

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
}
```

### 验证文件内容

```php
// 验证文件内容（严格模式）
$result = $security_manager->validate_file_content(
    '/tmp/uploaded_file',     // 文件路径
    'image/jpeg'              // MIME 类型
);

if ( is_wp_error( $result ) ) {
    echo '检测到潜在威胁: ' . $result->get_error_message();
}
```

## 安全最佳实践

### 1. 启用严格模式
在生产环境中建议启用严格安全模式，虽然会轻微影响性能，但能大幅提升安全性。

### 2. 自定义白名单
根据实际需求配置文件类型白名单，仅允许必要的文件类型上传。

### 3. 定期检查日志
定期查看安全事件日志，了解上传情况和潜在威胁。

### 4. 定期更换密钥
建议每隔 3-6 个月重新生成加密密钥：
- 进入安全设置页面
- 点击"重新生成加密密钥"
- 注意：此操作会使之前加密的数据无法解密

### 5. 限制上传权限
根据团队角色合理分配上传权限，避免不必要的风险。

### 6. 监控安全评分
保持安全评分在 80% 以上，确保各项安全措施都已启用。

## 常见问题

### Q: 为什么某些合法文件被拦截？
A: 可能原因：
1. 文件扩展名不在白名单中
2. MIME 类型检测失败
3. 文件内容包含可疑代码

解决方法：
- 检查并更新白名单配置
- 确保文件扩展名与内容匹配
- 如确认安全，可临时关闭严格模式

### Q: 加密密钥丢失怎么办？
A: 加密密钥存储在数据库中，如丢失将无法解密敏感信息，需要重新配置云服务商密钥。

### Q: 如何处理大量误报？
A: 建议：
1. 查看具体拦截原因
2. 调整安全策略配置
3. 针对特定场景关闭某些检测

### Q: 安全评分如何计算？
A: 评分基于以下因素：
- 严格模式启用 (+20%)
- 真实 MIME 检测 (+20%)
- 敏感信息加密 (+20%)
- 危险扩展名阻止 (+20%)
- 加密密钥存在 (+10%)
- 自定义白名单 (+10%)

## 技术细节

### 挂钩点
```php
// 文件上传验证钩子
add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_upload' ), 5 );
```

### 数据库表
安全日志使用 `wp_wpmcs_logs` 表：
```sql
CREATE TABLE wp_wpmcs_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    level varchar(20) NOT NULL DEFAULT 'info',
    type varchar(50) NOT NULL DEFAULT 'system',
    message text NOT NULL,
    context longtext,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY level (level),
    KEY type (type),
    KEY created_at (created_at)
);
```

### 加密密钥存储
加密密钥存储在 WordPress options 表：
```
option_name: wpmcs_encryption_key
option_value: [64位十六进制字符串]
```

## 更新日志

- **v1.0.0** - 初始版本
  - 文件类型白名单/黑名单
  - 文件大小限制
  - 严格安全模式
  - 敏感信息加密
  - 权限控制
