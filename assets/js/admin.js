document.addEventListener('DOMContentLoaded', function () {
  // 域名字段自动处理
  var domainField = document.querySelector('input[name="wpmcs_settings[domain]"]');

  if (domainField) {
    domainField.addEventListener('blur', function () {
      var value = domainField.value.trim();

      if (!value) {
        return;
      }

      domainField.value = value.replace(/\/+$/, '');
    });
  }

  // 测试连接功能
  var testButton = document.getElementById('wpmcs-test-connection');
  var testLoading = document.getElementById('wpmcs-test-loading');
  var testResult = document.getElementById('wpmcs-test-result');

  if (testButton) {
    testButton.addEventListener('click', function () {
      // 显示加载状态
      testButton.disabled = true;
      testLoading.style.display = 'inline-block';
      testResult.style.display = 'none';

      // 发送 AJAX 请求
      var xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxurl, true);
      var form = document.querySelector('.wpmcs-settings form');
      var formData = form ? new FormData(form) : new FormData();
      formData.append('action', 'wpmcs_test_connection');
      formData.append('nonce', window.wpmcsNonce || '');
      
      xhr.onload = function () {
        testButton.disabled = false;
        testLoading.style.display = 'none';
        testResult.style.display = 'block';

        if (xhr.status === 200) {
          try {
            var response = JSON.parse(xhr.responseText);
            
            if (response.success) {
              // 成功
              testResult.innerHTML = 
                '<div class="notice notice-success inline">' +
                  '<p><strong>✓ ' + escapeHtml(response.data.message) + '</strong></p>' +
                  '<div style="margin-top: 10px; padding: 10px; background: #f7f7f7; border-left: 4px solid #46b450;">' +
                    '<p style="margin: 0; font-weight: bold;">测试详情:</p>' +
                    '<ul style="margin: 5px 0 0 0; padding-left: 20px;">' +
                      response.data.details.map(function (detail) {
                        return '<li>' + escapeHtml(detail) + '</li>';
                      }).join('') +
                    '</ul>' +
                  '</div>' +
                '</div>';
            } else {
              // 失败
              testResult.innerHTML = 
                '<div class="notice notice-error inline">' +
                  '<p><strong>✗ ' + escapeHtml(response.data.message) + '</strong></p>' +
                  (response.data.details && response.data.details.length > 0 ?
                    '<div style="margin-top: 10px; padding: 10px; background: #f7f7f7; border-left: 4px solid #dc3232;">' +
                      '<p style="margin: 0; font-weight: bold;">错误详情:</p>' +
                      '<ul style="margin: 5px 0 0 0; padding-left: 20px;">' +
                        response.data.details.map(function (detail) {
                          return '<li>' + escapeHtml(detail) + '</li>';
                        }).join('') +
                      '</ul>' +
                    '</div>' : ''
                  ) +
                '</div>';
            }
          } catch (e) {
            testResult.innerHTML = 
              '<div class="notice notice-error inline">' +
                '<p><strong>✗ 解析响应失败</strong></p>' +
                '<p>响应内容: ' + escapeHtml(xhr.responseText) + '</p>' +
              '</div>';
          }
        } else {
          testResult.innerHTML = 
            '<div class="notice notice-error inline">' +
              '<p><strong>✗ 请求失败</strong></p>' +
              '<p>状态码: ' + xhr.status + '</p>' +
            '</div>';
        }
      };

      xhr.onerror = function () {
        testButton.disabled = false;
        testLoading.style.display = 'none';
        testResult.style.display = 'block';
        
        testResult.innerHTML = 
          '<div class="notice notice-error inline">' +
            '<p><strong>✗ 网络错误，请检查网络连接</strong></p>' +
          '</div>';
      };

      // 发送请求
      xhr.send(formData);
    });
  }

  // HTML 转义函数
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
});
