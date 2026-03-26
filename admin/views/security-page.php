<div class="wrap wpmcs-security-page">
    <h1>安全设置</h1>
    
    <div class="wpmcs-security-intro">
        <p>配置文件上传安全策略，包括文件类型限制、大小限制、权限控制和数据加密。</p>
    </div>
    
    <form method="post" action="options.php">
        <?php settings_fields( 'wpmcs_security_settings_group' ); ?>
        
        <!-- 文件类型控制 -->
        <div class="wpmcs-security-section">
            <h2>
                <span class="dashicons dashicons-media-default"></span>
                文件类型控制
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">文件类型白名单</th>
                    <td>
                        <textarea 
                            name="wpmcs_allowed_file_types" 
                            rows="5" 
                            cols="50"
                            class="large-text code"
                            placeholder="image/jpeg, image/png, application/pdf"><?php 
                                echo esc_textarea( get_option( 'wpmcs_allowed_file_types', '' ) ); 
                            ?></textarea>
                        <p class="description">
                            输入允许上传的文件 MIME 类型，用逗号分隔。留空则使用默认白名单。
                            <br>示例：image/jpeg, image/png, application/pdf
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">文件类型黑名单</th>
                    <td>
                        <textarea 
                            name="wpmcs_blocked_file_types" 
                            rows="5" 
                            cols="50"
                            class="large-text code"
                            placeholder="application/x-php, application/x-executable"><?php 
                                echo esc_textarea( get_option( 'wpmcs_blocked_file_types', '' ) ); 
                            ?></textarea>
                        <p class="description">
                            输入禁止上传的文件类型，用逗号分隔。黑名单优先级高于白名单。
                            <br>默认已禁止：可执行文件、脚本文件等
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">危险扩展名</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpmcs_block_dangerous_extensions" value="1" 
                                <?php checked( get_option( 'wpmcs_block_dangerous_extensions', '1' ), '1' ); ?>>
                            阻止危险文件扩展名上传
                        </label>
                        <p class="description">
                            阻止上传可能包含恶意代码的文件扩展名：php, exe, sh, py, pl 等
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 文件大小限制 -->
        <div class="wpmcs-security-section">
            <h2>
                <span class="dashicons dashicons-upload"></span>
                文件大小限制
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">图片文件</th>
                    <td>
                        <input type="text" name="wpmcs_file_size_limits[image]" 
                            value="<?php echo esc_attr( size_format( $size_limits['image'] ) ); ?>" 
                            class="regular-text">
                        <span class="description">默认：10 MB</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">视频文件</th>
                    <td>
                        <input type="text" name="wpmcs_file_size_limits[video]" 
                            value="<?php echo esc_attr( size_format( $size_limits['video'] ) ); ?>" 
                            class="regular-text">
                        <span class="description">默认：500 MB</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">音频文件</th>
                    <td>
                        <input type="text" name="wpmcs_file_size_limits[audio]" 
                            value="<?php echo esc_attr( size_format( $size_limits['audio'] ) ); ?>" 
                            class="regular-text">
                        <span class="description">默认：50 MB</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">文档文件</th>
                    <td>
                        <input type="text" name="wpmcs_file_size_limits[application]" 
                            value="<?php echo esc_attr( size_format( $size_limits['application'] ) ); ?>" 
                            class="regular-text">
                        <span class="description">默认：20 MB</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">文本文件</th>
                    <td>
                        <input type="text" name="wpmcs_file_size_limits[text]" 
                            value="<?php echo esc_attr( size_format( $size_limits['text'] ) ); ?>" 
                            class="regular-text">
                        <span class="description">默认：5 MB</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">默认限制</th>
                    <td>
                        <input type="text" name="wpmcs_file_size_limits[default]" 
                            value="<?php echo esc_attr( size_format( $size_limits['default'] ) ); ?>" 
                            class="regular-text">
                        <span class="description">默认：10 MB</span>
                    </td>
                </tr>
            </table>
            
            <p class="description">
                注意：实际限制还受服务器配置影响（upload_max_filesize, post_max_size）
            </p>
        </div>
        
        <!-- 安全模式 -->
        <div class="wpmcs-security-section">
            <h2>
                <span class="dashicons dashicons-shield"></span>
                安全模式
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">严格安全模式</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpmcs_strict_mode" value="1" 
                                <?php checked( get_option( 'wpmcs_strict_mode', '0' ), '1' ); ?>>
                            启用严格安全模式
                        </label>
                        <p class="description">
                            启用后将深度检查文件内容，检测潜在的恶意代码。可能会影响上传速度。
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">真实 MIME 检测</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpmcs_check_real_mime" value="1" 
                                <?php checked( get_option( 'wpmcs_check_real_mime', '1' ), '1' ); ?>>
                            检测文件真实 MIME 类型
                        </label>
                        <p class="description">
                            使用 fileinfo 扩展检测文件的真实类型，防止伪造文件扩展名。
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 数据加密 -->
        <div class="wpmcs-security-section">
            <h2>
                <span class="dashicons dashicons-lock"></span>
                数据加密
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">敏感信息加密</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpmcs_encrypt_sensitive_data" value="1" 
                                <?php checked( get_option( 'wpmcs_encrypt_sensitive_data', '1' ), '1' ); ?>>
                            加密存储敏感配置信息
                        </label>
                        <p class="description">
                            使用 AES-256-CBC 加密存储 Access Key、Secret Key 等敏感信息。
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">加密密钥状态</th>
                    <td>
                        <?php 
                        $key_exists = get_option( 'wpmcs_encryption_key' );
                        if ( $key_exists ) : 
                        ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <span style="color: #46b450;">加密密钥已生成</span>
                        <?php else : ?>
                            <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                            <span style="color: #ffb900;">加密密钥未生成</span>
                        <?php endif; ?>
                        
                        <p class="description">
                            加密密钥用于保护敏感信息，保存设置时自动生成。
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">重新生成密钥</th>
                    <td>
                        <button type="button" id="regenerate-key" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            重新生成加密密钥
                        </button>
                        <p class="description">
                            <strong style="color: #dc3232;">警告：</strong>重新生成密钥后，之前加密的数据将无法解密！
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 权限控制 -->
        <div class="wpmcs-security-section">
            <h2>
                <span class="dashicons dashicons-admin-users"></span>
                权限控制
            </h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">角色权限映射</th>
                    <td>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>角色</th>
                                    <th>上传文件</th>
                                    <th>管理设置</th>
                                    <th>查看统计</th>
                                    <th>查看日志</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>管理员</td>
                                    <td><span class="dashicons dashicons-yes" style="color: #46b450;"></span></td>
                                    <td><span class="dashicons dashicons-yes" style="color: #46b450;"></span></td>
                                    <td><span class="dashicons dashicons-yes" style="color: #46b450;"></span></td>
                                    <td><span class="dashicons dashicons-yes" style="color: #46b450;"></span></td>
                                </tr>
                                <tr>
                                    <td>编辑</td>
                                    <td><span class="dashicons dashicons-yes" style="color: #46b450;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                </tr>
                                <tr>
                                    <td>作者</td>
                                    <td><span class="dashicons dashicons-yes" style="color: #46b450;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                </tr>
                                <tr>
                                    <td>投稿者</td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                </tr>
                                <tr>
                                    <td>订阅者</td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                    <td><span class="dashicons dashicons-no" style="color: #dc3232;"></span></td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="description">
                            权限基于 WordPress 角色系统。管理员拥有所有权限。
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button( '保存安全设置' ); ?>
    </form>
    
    <!-- 安全状态概览 -->
    <div class="wpmcs-security-overview">
        <h2>
            <span class="dashicons dashicons-chart-pie"></span>
            安全状态概览
        </h2>
        
        <div class="wpmcs-security-stats">
            <div class="wpmcs-stat-card">
                <div class="wpmcs-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <span class="dashicons dashicons-upload"></span>
                </div>
                <div class="wpmcs-stat-content">
                    <span class="wpmcs-stat-value"><?php echo number_format( $upload_count ); ?></span>
                    <span class="wpmcs-stat-label">今日上传</span>
                </div>
            </div>
            
            <div class="wpmcs-stat-card">
                <div class="wpmcs-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <span class="dashicons dashicons-block-default"></span>
                </div>
                <div class="wpmcs-stat-content">
                    <span class="wpmcs-stat-value"><?php echo number_format( $blocked_count ); ?></span>
                    <span class="wpmcs-stat-label">已拦截</span>
                </div>
            </div>
            
            <div class="wpmcs-stat-card">
                <div class="wpmcs-stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="wpmcs-stat-content">
                    <span class="wpmcs-stat-value"><?php echo number_format( $warning_count ); ?></span>
                    <span class="wpmcs-stat-label">警告事件</span>
                </div>
            </div>
            
            <div class="wpmcs-stat-card">
                <div class="wpmcs-stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <span class="dashicons dashicons-shield"></span>
                </div>
                <div class="wpmcs-stat-content">
                    <span class="wpmcs-stat-value"><?php echo $security_score; ?>%</span>
                    <span class="wpmcs-stat-label">安全评分</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 最近安全事件 -->
    <div class="wpmcs-security-events">
        <h2>
            <span class="dashicons dashicons-list-view"></span>
            最近安全事件
        </h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="manage-column column-time">时间</th>
                    <th class="manage-column column-type">事件类型</th>
                    <th class="manage-column column-file">文件名</th>
                    <th class="manage-column column-user">用户</th>
                    <th class="manage-column column-status">状态</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $recent_events ) ) : ?>
                    <?php foreach ( $recent_events as $event ) : ?>
                        <tr>
                            <td><?php echo esc_html( $event['time'] ); ?></td>
                            <td><?php echo esc_html( $event['type'] ); ?></td>
                            <td><?php echo esc_html( $event['file'] ); ?></td>
                            <td><?php echo esc_html( $event['user'] ); ?></td>
                            <td>
                                <span class="wpmcs-status-badge wpmcs-status-<?php echo esc_attr( $event['status'] ); ?>">
                                    <?php echo esc_html( $event['status_label'] ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">暂无安全事件记录</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 重新生成密钥
    $('#regenerate-key').on('click', function() {
        if (!confirm('确定要重新生成加密密钥吗？这将导致之前加密的数据无法解密！')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmcs_regenerate_encryption_key',
                nonce: '<?php echo wp_create_nonce( 'wpmcs_security' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('加密密钥已重新生成');
                    location.reload();
                } else {
                    alert('操作失败: ' + response.data.message);
                }
            },
            error: function() {
                alert('请求失败，请稍后重试');
            },
            complete: function() {
                $btn.prop('disabled', false).find('.dashicons').removeClass('is-active');
            }
        });
    });
});
</script>

<style>
.wpmcs-security-page {
    max-width: 1200px;
}

.wpmcs-security-intro {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-security-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wpmcs-security-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}

.wpmcs-security-section h2 .dashicons {
    color: #0073aa;
}

.wpmcs-security-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.wpmcs-stat-card {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.wpmcs-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.wpmcs-stat-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.wpmcs-stat-content {
    flex: 1;
}

.wpmcs-stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #23282d;
}

.wpmcs-stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}

.wpmcs-security-events {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-top: 20px;
    border-radius: 4px;
}

.wpmcs-security-events h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}

.wpmcs-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.wpmcs-status-allowed {
    background: #d5f4e6;
    color: #1b7f4a;
}

.wpmcs-status-blocked {
    background: #ffdcdc;
    color: #a00;
}

.wpmcs-status-warning {
    background: #fff3cd;
    color: #856404;
}

.dashicons.is-active {
    animation: rotation 2s infinite linear;
}

@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
