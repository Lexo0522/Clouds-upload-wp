(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var providerSelect = document.getElementById('wpmcs-provider-select');
    var providerFieldsContainer = document.getElementById('wpmcs-provider-fields');
    var providerDesc = document.getElementById('wpmcs-provider-desc');

    if (!providerSelect) {
      return;
    }

    var providerDescriptions = {
      qiniu: '七牛云对象存储',
      aliyun_oss: '阿里云 OSS 对象存储',
      tencent_cos: '腾讯云 COS 对象存储',
      upyun: '又拍云 CDN / 存储服务',
      dogecloud: '多吉云存储服务',
      aws_s3: 'AWS S3 对象存储'
    };

    function getSelectedOption() {
      return providerSelect.options[providerSelect.selectedIndex] || null;
    }

    function updateProviderIcon() {
      var selectedOption = getSelectedOption();
      var iconUrl = selectedOption ? selectedOption.getAttribute('data-icon') : '';
      var providerName = selectedOption ? selectedOption.textContent : providerSelect.value;
      var previewContainer = document.querySelector('.wpmcs-provider-preview');

      if (previewContainer && iconUrl) {
        previewContainer.innerHTML = '<img src="' + iconUrl + '" alt="' + providerName + '" class="wpmcs-provider-icon size-lg" />';
      }
    }

    function updateProviderDesc() {
      var selectedProvider = providerSelect.value;

      if (providerDesc && providerDescriptions[selectedProvider]) {
        providerDesc.textContent = ' - ' + providerDescriptions[selectedProvider];
      }
    }

    function loadProviderFields() {
      var selectedProvider = providerSelect.value;
      var xhr = new XMLHttpRequest();
      var providerNonce = '';

      if (typeof wpmcsProviderData !== 'undefined' && wpmcsProviderData.nonce) {
        providerNonce = wpmcsProviderData.nonce;
      }

      xhr.open('POST', ajaxurl, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      xhr.onload = function () {
        if (xhr.status !== 200 || !providerFieldsContainer) {
          return;
        }

        try {
          var response = JSON.parse(xhr.responseText);

          if (response.success) {
            providerFieldsContainer.innerHTML = response.data.html;
          }
        } catch (e) {
          console.error('Failed to parse response:', e);
        }
      };

      xhr.send(
        'action=wpmcs_get_provider_fields&provider=' +
          encodeURIComponent(selectedProvider) +
          '&nonce=' +
          encodeURIComponent(providerNonce)
      );
    }

    providerSelect.addEventListener('change', function () {
      updateProviderIcon();
      updateProviderDesc();
      loadProviderFields();
    });

    updateProviderIcon();
    updateProviderDesc();
  });
})();
