# 多云服务商支持文档

## 功能概述

WP Multi Cloud Storage 插件现已支持多个主流云存储服务商，包括：

1. **七牛云** (Qiniu)
2. **阿里云 OSS** (Aliyun OSS)
3. **腾讯云 COS** (Tencent COS)
4. **又拍云** (Upyun)
5. **多吉云** (Dogecloud)
6. **AWS S3** (Amazon S3)

## 服务商配置详解

### 1. 七牛云 (Qiniu)

**配置项**:
- **Access Key**: 七牛云 AK
- **Secret Key**: 七牛云 SK
- **Bucket**: 存储空间名称
- **上传接口**: 根据地域选择
  - 华东：`https://upload.qiniup.com`
  - 华北：`https://upload-z1.qiniup.com`
  - 华南：`https://upload-z2.qiniup.com`
  - 北美：`https://upload-na0.qiniup.com`
  - 东南亚：`https://upload-as0.qiniup.com`
- **加速域名**: 七牛云绑定的访问域名

**特点**:
- ✅ 国内最流行的云存储服务商之一
- ✅ 提供丰富的图片处理功能
- ✅ CDN 加速性能优秀

### 2. 阿里云 OSS (Aliyun OSS)

**配置项**:
- **Access Key ID**: 阿里云 AccessKey ID
- **Access Key Secret**: 阿里云 AccessKey Secret
- **Bucket**: 存储空间名称
- **Endpoint**: 地域节点
  - 华东1（杭州）：`oss-cn-hangzhou.aliyuncs.com`
  - 华东2（上海）：`oss-cn-shanghai.aliyuncs.com`
  - 华北1（青岛）：`oss-cn-qingdao.aliyuncs.com`
  - 华北2（北京）：`oss-cn-beijing.aliyuncs.com`
  - 华南1（深圳）：`oss-cn-shenzhen.aliyuncs.com`
  - 香港：`oss-cn-hongkong.aliyuncs.com`
- **加速域名**: 自定义 CDN 域名（可选）

**特点**:
- ✅ 阿里巴巴旗下，稳定可靠
- ✅ 与阿里云其他产品集成度高
- ✅ 支持多种存储类型（标准、低频、归档）

### 3. 腾讯云 COS (Tencent COS)

**配置项**:
- **Secret ID**: 腾讯云 SecretId
- **Secret Key**: 腾讯云 SecretKey
- **Bucket**: 存储桶名称
- **Region**: 地域
  - 北京：`ap-beijing`
  - 上海：`ap-shanghai`
  - 广州：`ap-guangzhou`
  - 成都：`ap-chengdu`
  - 重庆：`ap-chongqing`
  - 香港：`ap-hongkong`
  - 新加坡：`ap-singapore`
- **加速域名**: 自定义 CDN 域名（可选）

**特点**:
- ✅ 腾讯旗下，与微信生态集成好
- ✅ 提供丰富的数据处理能力
- ✅ 支持版本控制和跨地域复制

### 4. 又拍云 (Upyun)

**配置项**:
- **操作员账号**: 又拍云操作员账号
- **操作员密码**: 又拍云操作员密码
- **服务名称**: 又拍云服务名称
- **API 接口**: 默认 `v0.api.upyun.com`
- **加速域名**: 自定义域名（可选）

**特点**:
- ✅ 专注图片处理和 CDN 加速
- ✅ 提供强大的图片处理功能
- ✅ 价格相对便宜

### 5. 多吉云 (Dogecloud)

**配置项**:
- **Access Key**: 多吉云 Access Key
- **Secret Key**: 多吉云 Secret Key
- **Bucket**: 存储空间名称
- **加速域名**: 自定义域名（可选）

**特点**:
- ✅ 新兴云存储服务商
- ✅ 价格透明，按量计费
- ✅ 界面简洁易用

### 6. AWS S3 (Amazon S3)

**配置项**:
- **Access Key ID**: AWS Access Key ID
- **Secret Access Key**: AWS Secret Access Key
- **Bucket**: S3 Bucket 名称
- **Region**: AWS 区域
  - 美国东部 (弗吉尼亚北部): us-east-1
  - 美国东部 (俄亥俄): us-east-2
  - 美国西部 (加利福尼亚北部): us-west-1
  - 美国西部 (俄勒冈): us-west-2
  - 欧洲 (爱尔兰): eu-west-1
  - 欧洲 (伦敦): eu-west-2
  - 欧洲 (巴黎): eu-west-3
  - 欧洲 (法兰克福): eu-central-1
  - 亚太地区 (东京): ap-northeast-1
  - 亚太地区 (首尔): ap-northeast-2
  - 亚太地区 (新加坡): ap-southeast-1
  - 亚太地区 (悉尼): ap-southeast-2
  - 亚太地区 (孟买): ap-south-1
  - 南美洲 (圣保罗): sa-east-1
  - 加拿大 (中部): ca-central-1
- **加速域名**: 自定义域名（可选，如 CloudFront）

**特点**:
- ✅ 亚马逊旗下，全球最成熟的云存储服务
- ✅ 高可用性和持久性（99.99%）
- ✅ 全球多个数据中心
- ✅ 与 AWS 生态深度集成
- ✅ 支持版本控制、生命周期管理

**配置建议**:
- 选择离用户最近的区域以减少延迟
- 考虑使用 CloudFront CDN 加速
- 配置 Bucket 策略和 IAM 权限
- 启用版本控制防止误删除

## 使用步骤

### 1. 选择服务商

进入 **设置 → WP Multi Cloud Storage**，在「云服务商」下拉菜单中选择你要使用的服务商。

### 2. 配置参数

根据选择的服务商，页面会自动显示对应的配置字段。填写相应的参数。

### 3. 测试连接

填写配置后，点击「测试连接」按钮，验证配置是否正确。

### 4. 保存设置

测试成功后，点击「保存设置」保存配置。

### 5. 开始使用

配置完成后，上传媒体文件时会自动同步到云存储。

## 通用配置

除了各服务商的特定配置外，还有以下通用配置：

### 加速域名
- 访问云端文件的域名
- 如果不填写，将使用服务商提供的默认域名
- 建议绑定自定义域名以提升品牌形象和访问速度

### 上传目录前缀
- 云端文件的存储路径前缀
- 例如：`wp-content/uploads`
- 方便在云端管理文件

### 自动重命名
- 上传前自动重命名文件
- 避免文件名冲突
- 格式：`{Y}{m}{d}{H}{i}{s}_{random6}.{ext}`

### 替换媒体 URL
- 自动将本地 URL 替换为云端 URL
- 实现真正的云存储加速
- 建议开启

## 切换服务商

如果需要切换云服务商：

1. **导出配置**：建议先记录当前配置
2. **切换服务商**：在下拉菜单选择新的服务商
3. **重新配置**：填写新服务商的参数
4. **测试连接**：确保配置正确
5. **保存设置**：保存新配置

**注意**：切换服务商后，之前上传的文件仍会保留在原服务商，但不会自动迁移。建议使用批量迁移工具或手动迁移。

## 多服务商对比

| 服务商 | 价格 | 速度 | 稳定性 | 图片处理 | 推荐场景 |
|--------|------|------|--------|----------|----------|
| 七牛云 | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | 图片密集型应用 |
| 阿里云 OSS | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | 企业级应用 |
| 腾讯云 COS | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | 微信生态应用 |
| 又拍云 | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | 图片处理需求 |
| 多吉云 | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | 小型项目 |
| AWS S3 | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | 全球化应用 |

## 技术实现

### 存储驱动接口

所有服务商都实现了统一的 `Cloud_Storage_Interface` 接口：

```php
interface Cloud_Storage_Interface {
    public function upload( $file_path, $file_name );
    public function delete( $file_path );
    public function get_url( $file_path );
}
```

### 适配器模式

每个服务商都有自己的适配器，继承自 `WPMCS_Cloud_Adapter` 基类：

- `WPMCS_Qiniu_Adapter` - 七牛云
- `WPMCS_Aliyun_OSS_Adapter` - 阿里云 OSS
- `WPMCS_Tencent_COS_Adapter` - 腾讯云 COS
- `WPMCS_Upyun_Adapter` - 又拍云
- `WPMCS_Dogecloud_Adapter` - 多吉云

### 扩展新服务商

如需添加新的云服务商支持，需要：

1. 创建存储类，实现 `Cloud_Storage_Interface` 接口
2. 创建适配器类，继承 `WPMCS_Cloud_Adapter`
3. 在 `wpmcs_create_storage_driver()` 函数中添加 case
4. 在 `WPMCS_Plugin::create_adapter()` 方法中添加 case
5. 在设置页面的服务商列表中添加选项

## 常见问题

### Q: 如何选择服务商？
A: 根据你的实际需求：
- 图片处理需求强烈：七牛云或又拍云
- 企业级应用：阿里云 OSS
- 微信生态：腾讯云 COS
- 预算有限：多吉云或又拍云

### Q: 可以同时使用多个服务商吗？
A: 当前版本一次只能配置一个服务商。未来版本可能会支持多服务商同时使用。

### Q: 切换服务商会丢失数据吗？
A: 不会。已上传的文件会保留在原服务商，只是新上传的文件会使用新服务商。

### Q: 如何查看使用了哪个服务商？
A: 在媒体库中，云端状态列会显示服务商名称。

### Q: 各服务商的访问速度如何？
A: 速度取决于你的服务器位置和用户分布。建议：
- 国内用户：选择国内服务商
- 海外用户：选择有海外节点的服务商
- 混合用户：配置 CDN 加速

## 价格参考（仅供参考）

### 七牛云
- 存储：0.148元/GB/月起
- 流量：0.29元/GB起
- 请求：GET 0.01元/万次，PUT/DELETE 0.01元/千次

### 阿里云 OSS
- 存储：0.12元/GB/月起（标准存储）
- 流量：0.5元/GB起
- 请求：PUT/DELETE 0.01元/万次，GET 0.01元/万次

### 腾讯云 COS
- 存储：0.118元/GB/月起
- 流量：0.5元/GB起
- 请求：PUT/DELETE 0.01元/万次，GET 0.01元/万次

### 又拍云
- 存储：0.128元/GB/月
- 流量：0.29元/GB起
- 提供免费额度

### 多吉云
- 存储：按量计费
- 流量：按量计费
- 价格透明

### AWS S3
- 存储：$0.023/GB/月起（标准存储）
- 流量：$0.09/GB起（数据传出）
- 请求：PUT $0.005/千次，GET $0.0004/千次
- 提供免费额度（新用户 12 个月）

**注意**: 价格会随时调整，请以官网最新价格为准。

## 最佳实践

### 1. 选择合适的地域
- 选择离用户最近的地域
- 减少延迟，提升体验

### 2. 配置自定义域名
- 使用自己的域名
- 提升品牌形象
- 方便后续迁移

### 3. 开启 CDN 加速
- 大多数服务商提供 CDN 功能
- 提升访问速度
- 减少源站压力

### 4. 定期清理无用文件
- 节省存储成本
- 提高管理效率

### 5. 备份重要配置
- 定期备份配置信息
- 记录服务商密钥
- 准备应急方案

---

**提示**: 本插件持续更新，如有问题或建议，请访问 GitHub 提交 Issue。