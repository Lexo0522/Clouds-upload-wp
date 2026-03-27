document.addEventListener('DOMContentLoaded', function () {
  // 初始化
  if (typeof wpmcsMedia === 'undefined') {
    console.error('WPMCS Media data not loaded');
    return;
  }
  
  // 绑定重新上传事件
  document.addEventListener('click', function (e) {
    var reuploadBtn = e.target.closest('.wpmcs-reupload');
    if (reuploadBtn) {
      e.preventDefault();
      handleReupload(reuploadBtn);
    }
  });
  
  // 绑定复制 URL 事件
  document.addEventListener('click', function (e) {
    var copyBtn = e.target.closest('.wpmcs-copy-url');
    if (copyBtn) {
      e.preventDefault();
      handleCopyUrl(copyBtn);
    }
  });
  
  // 处理批量操作结果通知
  var urlParams = new URLSearchParams(window.location.search);
  var uploaded = urlParams.get('wpmcs_uploaded');
  var failed = urlParams.get('wpmcs_failed');
  
  if (uploaded !== null || failed !== null) {
    showBulkResultNotification(uploaded, failed);
  }
});

/**
 * 处理重新上传
 */
function handleReupload(button) {
  var attachmentId = button.getAttribute('data-id');
  
  if (!attachmentId) {
    alert('无效的附件 ID');
    return;
  }
  
  // 确认操作
  var confirmMsg = '确定要上传到云端吗？';
  if (!confirm(confirmMsg)) {
    return;
  }
  
  // 禁用按钮
  var originalText = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + wpmcsMedia.text_uploading;
  
  // 发送 AJAX 请求
  var xhr = new XMLHttpRequest();
  xhr.open('POST', wpmcsMedia.ajax_url, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  
  xhr.onload = function () {
    // 恢复按钮
    button.disabled = false;
    button.innerHTML = originalText;
    
    if (xhr.status === 200) {
      try {
        var response = JSON.parse(xhr.responseText);
        
        if (response.success) {
          showSuccessNotification(wpmcsMedia.text_success);
          
          // 刷新页面以更新状态
          setTimeout(function () {
            location.reload();
          }, 1500);
          
        } else {
          showErrorNotification(response.data.message || wpmcsMedia.text_failed);
        }
      } catch (e) {
        showErrorNotification('解析响应失败');
      }
    } else {
      showErrorNotification('请求失败');
    }
  };
  
  xhr.onerror = function () {
    button.disabled = false;
    button.innerHTML = originalText;
    showErrorNotification('网络错误');
  };
  
  // 发送请求
  xhr.send('action=wpmcs_reupload_attachment&attachment_id=' + encodeURIComponent(attachmentId) + '&nonce=' + encodeURIComponent(wpmcsMedia.nonce_reupload));
}

/**
 * 处理复制 URL
 */
function handleCopyUrl(button) {
  var url = button.getAttribute('data-url');
  
  if (!url) {
    alert('URL 为空');
    return;
  }
  
  // 使用 Clipboard API
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(function () {
      showSuccessNotification(wpmcsMedia.text_copied);
    }).catch(function (err) {
      // 降级到传统方法
      fallbackCopyToClipboard(url);
    });
  } else {
    // 降级到传统方法
    fallbackCopyToClipboard(url);
  }
}

/**
 * 降级复制方法
 */
function fallbackCopyToClipboard(text) {
  var textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.style.position = 'fixed';
  textarea.style.left = '-9999px';
  document.body.appendChild(textarea);
  textarea.select();
  
  try {
    var successful = document.execCommand('copy');
    if (successful) {
      showSuccessNotification(wpmcsMedia.text_copied);
    } else {
      showErrorNotification('复制失败');
    }
  } catch (err) {
    showErrorNotification('复制失败');
  }
  
  document.body.removeChild(textarea);
}

/**
 * 显示成功通知
 */
function showSuccessNotification(message) {
  var notification = createNotification('success', message);
  document.body.appendChild(notification);
  
  setTimeout(function () {
    removeNotification(notification);
  }, 3000);
}

/**
 * 显示错误通知
 */
function showErrorNotification(message) {
  var notification = createNotification('error', message);
  document.body.appendChild(notification);
  
  setTimeout(function () {
    removeNotification(notification);
  }, 5000);
}

/**
 * 创建通知元素
 */
function createNotification(type, message) {
  var div = document.createElement('div');
  div.className = 'wpmcs-notification wpmcs-notification-' + type;
  div.innerHTML = message;
  div.style.cssText = [
    'position: fixed',
    'top: 20px',
    'right: 20px',
    'padding: 12px 20px',
    'background: ' + (type === 'success' ? '#46b450' : '#dc3232'),
    'color: #fff',
    'border-radius: 4px',
    'box-shadow: 0 2px 8px rgba(0,0,0,0.2)',
    'z-index: 99999',
    'animation: wpmcsSlideIn 0.3s ease-out',
    'max-width: 300px'
  ].join(';');
  
  return div;
}

/**
 * 移除通知
 */
function removeNotification(notification) {
  notification.style.animation = 'wpmcsSlideOut 0.3s ease-out';
  setTimeout(function () {
    if (notification.parentNode) {
      notification.parentNode.removeChild(notification);
    }
  }, 300);
}

/**
 * 显示批量操作结果通知
 */
function showBulkResultNotification(uploaded, failed) {
  var message = '';
  
  if (uploaded > 0 && failed > 0) {
    message = '批量上传完成：成功 ' + uploaded + ' 个，失败 ' + failed + ' 个';
  } else if (uploaded > 0) {
    message = '批量上传成功：共上传 ' + uploaded + ' 个文件';
  } else if (failed > 0) {
    message = '批量上传失败：' + failed + ' 个文件上传失败';
  }
  
  if (message) {
    showSuccessNotification(message);
  }
  
  // 清除 URL 参数
  var url = new URL(window.location);
  url.searchParams.delete('wpmcs_uploaded');
  url.searchParams.delete('wpmcs_failed');
  window.history.replaceState({}, '', url);
}

// 添加 CSS 动画
var style = document.createElement('style');
style.textContent = [
  '@keyframes wpmcsSlideIn {',
  '  from {',
  '    transform: translateX(400px);',
  '    opacity: 0;',
  '  }',
  '  to {',
  '    transform: translateX(0);',
  '    opacity: 1;',
  '  }',
  '}',
  '@keyframes wpmcsSlideOut {',
  '  from {',
  '    transform: translateX(0);',
  '    opacity: 1;',
  '  }',
  '  to {',
  '    transform: translateX(400px);',
  '    opacity: 0;',
  '  }',
  '}'
].join('\n');
document.head.appendChild(style);