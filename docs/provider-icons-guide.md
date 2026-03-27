# 服务商图标使用指南

## 概述

本文档介绍如何在插件中使用云服务商专属图标。所有图标都有统一的尺寸约束，确保在不同场景下显示一致。

## 图标文件位置

所有服务商图标 SVG 文件位于：
```
assets/images/providers/
├── qiniu.svg          # 七牛云
├── Aliyun.svg         # 阿里云 OSS
├── TencentCloud.svg   # 腾讯云 COS
├── upyun.svg          # 又拍云
├── dogecloud.svg      # 多吉云
├── AWSS3.svg          # AWS S3
└── index.php          # 防止目录浏览
```

## 尺寸规范

所有图标使用 CSS 类来统一控制尺寸，确保显示一致：

| 尺寸类 | 像素大小 | 适用场景 |
|--------|----------|----------|
| `size-xs` | 16×16px | 行内文本、表格、小标签 |
| `size-sm` | 20×20px | 列表、下拉选项、状态栏 |
| `size-md` | 32×32px | 卡片、表单、按钮 |
| `size-lg` | 48×48px | 预览、突出显示、标题 |
| `size-xl` | 64×64px | 英雄区域、大卡片 |
| `size-xxl` | 96×96px | 展示页面、关于页 |

## 使用方法

### 1. 在 PHP 中使用

```php
// 渲染图标（自动应用尺寸类）
echo WPMCS_Provider_Icons::render_icon( 'qiniu', 32 );
// 输出: <img src="..." class="wpmcs-provider-icon size-md" alt="七牛云" />

// 渲染图标和名称（默认使用 size-sm）
echo WPMCS_Provider_Icons::render_icon_with_name( 'aliyun_oss', 24, true );

// 获取服务商信息
$name = WPMCS_Provider_Icons::get_name( 'qiniu' );           // 七牛云
$icon_url = WPMCS_Provider_Icons::get_icon_url( 'aliyun_oss' );
$color = WPMCS_Provider_Icons::get_color( 'tencent_cos' );   // #00A3FF
```

### 2. 在 HTML 中手动使用尺寸类

```html
<!-- 超小图标 (16px) -->
<img src=".../qiniu.svg" class="wpmcs-provider-icon size-xs" alt="七牛云" />

<!-- 小图标 (20px) -->
<img src=".../Aliyun.svg" class="wpmcs-provider-icon size-sm" alt="阿里云" />

<!-- 中等图标 (32px) - 默认 -->
<img src=".../TencentCloud.svg" class="wpmcs-provider-icon size-md" alt="腾讯云" />

<!-- 大图标 (48px) -->
<img src=".../upyun.svg" class="wpmcs-provider-icon size-lg" alt="又拍云" />

<!-- 超大图标 (64px) -->
<img src=".../dogecloud.svg" class="wpmcs-provider-icon size-xl" alt="多吉云" />

<!-- 特大图标 (96px) -->
<img src=".../AWSS3.svg" class="wpmcs-provider-icon size-xxl" alt="AWS S3" />
```

### 3. 在 JavaScript 中使用

```javascript
// 创建图标元素并应用尺寸类
var icon = document.createElement('img');
icon.src = wpmcsProviderData.pluginUrl + 'assets/images/providers/qiniu.svg';
icon.alt = '七牛云';
icon.className = 'wpmcs-provider-icon size-md';
container.appendChild(icon);

// 使用模板字符串
var iconHtml = '<img src="' + iconUrl + '" class="wpmcs-provider-icon size-lg" alt="服务商" />';
```

## 预设场景样式

### 服务商预览区域

```html
<div class="wpmcs-provider-preview">
    <!-- 自动显示为 44×44px -->
    <img src=".../qiniu.svg" class="wpmcs-provider-icon" alt="七牛云" />
</div>
```

### 服务商徽章

```html
<span class="wpmcs-provider-badge">
    <!-- 自动显示为 24×24px -->
    <img src=".../Aliyun.svg" class="wpmcs-provider-icon" alt="阿里云" />
    <span class="wpmcs-provider-name">阿里云 OSS</span>
</span>
```

### 服务商卡片

```html
<div class="wpmcs-provider-card">
    <div class="wpmcs-provider-card-icon">
        <!-- 自动显示为 48×48px -->
        <img src=".../TencentCloud.svg" class="wpmcs-provider-icon" alt="腾讯云" />
    </div>
    <div class="wpmcs-provider-card-info">
        <div class="wpmcs-provider-card-name">腾讯云 COS</div>
        <div class="wpmcs-provider-card-desc">当前使用的云存储服务商</div>
    </div>
</div>
```

## 可用方法

### 静态方法列表

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| `get_all_providers()` | 无 | array | 获取所有服务商配置 |
| `get_provider($provider)` | $provider: string | array/null | 获取单个服务商配置 |
| `get_name($provider)` | $provider: string | string | 获取服务商名称 |
| `get_icon_url($provider)` | $provider: string | string | 获取图标 URL |
| `get_color($provider)` | $provider: string | string | 获取品牌色 |
| `get_gradient($provider)` | $provider: string | string | 获取渐变色 CSS |
| `get_website($provider)` | $provider: string | string | 获取官网链接 |
| `render_icon($provider, $size, $attrs)` | 多个 | string | 渲染图标 HTML（自动应用尺寸类） |
| `render_icon_with_name($provider, $size, $show_name)` | 多个 | string | 渲染图标和名称 |
| `render_provider_selector($selected, $name, $id)` | 多个 | string | 渲染选择器 |
| `render_status_badge($provider, $status)` | 多个 | string | 渲染状态徽章 |

## 服务商配置

每个服务商包含以下配置信息：

```php
array(
    'name' => '七牛云',                    // 名称
    'icon' => 'qiniu.svg',                 // 图标文件名
    'color' => '#00B4D8',                  // 品牌主色
    'gradient' => 'linear-gradient(...)',  // 渐变色
    'website' => 'https://www.qiniu.com',  // 官网地址
)
```

## 支持的服务商

| 标识 | 名称 | 图标文件 | 品牌色 |
|------|------|----------|--------|
| `qiniu` | 七牛云 | qiniu.svg | #00B4D8 |
| `aliyun_oss` | 阿里云 OSS | Aliyun.svg | #FF6A00 |
| `tencent_cos` | 腾讯云 COS | TencentCloud.svg | #00A3FF |
| `upyun` | 又拍云 | upyun.svg | #0096E0 |
| `dogecloud` | 多吉云 | dogecloud.svg | #4285F4 |
| `aws_s3` | AWS S3 | AWSS3.svg | #FF9900 |

## CSS 类参考

### 基础类

```css
.wpmcs-provider-icon          /* 图标基础样式 */
.wpmcs-provider-badge         /* 图标+名称容器 */
.wpmcs-provider-name          /* 服务商名称 */
```

### 尺寸类

```css
.wpmcs-provider-icon.size-xs  /* 16×16px */
.wpmcs-provider-icon.size-sm  /* 20×20px */
.wpmcs-provider-icon.size-md  /* 32×32px */
.wpmcs-provider-icon.size-lg  /* 48×48px */
.wpmcs-provider-icon.size-xl  /* 64×64px */
.wpmcs-provider-icon.size-xxl /* 96×96px */
```

### 容器类

```css
.wpmcs-provider-preview       /* 图标预览区域 (60×60px 容器) */
.wpmcs-provider-selector      /* 选择器容器 */
.wpmcs-provider-dropdown      /* 下拉选择框 */
.wpmcs-status-badge           /* 状态徽章 */
.wpmcs-provider-card          /* 服务商卡片 */
.wpmcs-provider-card-icon     /* 卡片图标区域 (60×60px) */
```

### 工具类

```css
.wpmcs-icon-circle            /* 圆形容器 */
.wpmcs-icon-rounded           /* 圆角矩形容器 */
.wpmcs-icon-shadow            /* 阴影效果 */
.wpmcs-icon-hover             /* 悬停动画 */
```

## 响应式设计

所有图标在移动端会自动适配：

```css
@media screen and (max-width: 782px) {
    /* 大图标在移动端缩小 */
    .wpmcs-provider-icon.size-lg {
        width: 40px !important;
        height: 40px !important;
    }
}
```

## 完整示例

### 在设置页面显示服务商选择器

```php
<?php
$settings = wpmcs_get_settings();
$provider = $settings['provider'];

// 输出图标 CSS
WPMCS_Provider_Icons::output_icon_css();

// 渲染选择器
echo WPMCS_Provider_Icons::render_provider_selector( $provider );
?>
```

### 在媒体库列表中显示状态

```php
public function render_column_content( $column, $attachment_id ) {
    if ( $column === 'wpmcs_cloud_status' ) {
        $cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
        
        if ( $cloud_meta && isset( $cloud_meta['provider'] ) ) {
            // 小图标用于表格
            echo WPMCS_Provider_Icons::render_icon( $cloud_meta['provider'], 20 );
            echo ' <span>已上传</span>';
        }
    }
}
```

### 创建服务商信息卡片

```php
<div class="wpmcs-provider-card">
    <div class="wpmcs-provider-card-icon">
        <?php echo WPMCS_Provider_Icons::render_icon( $provider, 48 ); ?>
    </div>
    <div class="wpmcs-provider-card-info">
        <div class="wpmcs-provider-card-name">
            <?php echo esc_html( WPMCS_Provider_Icons::get_name( $provider ) ); ?>
        </div>
        <div class="wpmcs-provider-card-desc">
            Bucket: <?php echo esc_html( $settings['bucket'] ); ?>
        </div>
    </div>
</div>
```

## 注意事项

1. **统一使用尺寸类**: 不要直接设置 width/height 属性，使用 CSS 尺寸类确保一致性
2. **SVG 格式优势**: 支持无损缩放，在任何尺寸下都保持清晰
3. **响应式适配**: 图标会自动适配移动端显示
4. **性能优化**: SVG 文件体积小，加载速度快
5. **暗色模式**: CSS 已包含暗色模式支持

## 扩展新服务商

如需添加新的云服务商：

1. 在 `assets/images/providers/` 目录添加 SVG 图标
2. 在 `class-wpmcs-provider-icons.php` 的 `$providers` 数组中添加配置
3. 实现对应的存储适配器类

## 样式文件

所有图标样式位于：`assets/css/provider-icons.css`

## 更新日志

- **v1.0.0** - 初始版本
- **v1.1.0** - 添加统一的尺寸约束系统，使用 CSS 类控制图标大小
