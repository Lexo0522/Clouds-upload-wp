(function ($) {
	'use strict';

	var WPMCS_QuickSetup = {
		init: function () {
			this.bindEvents();
			this.updateProviderIcon();
			this.loadProviderFields();
		},

		bindEvents: function () {
			$(document).on('click', '.wpmcs-apply-preset', this.applyPreset.bind(this));
			$(document).on('change', '#wpmcs-quick-provider', this.onProviderChange.bind(this));
			$(document).on('click', '#wpmcs-quick-setup-submit', this.submitQuickSetup.bind(this));
		},

		applyPreset: function (e) {
			e.preventDefault();

			var $card = $(e.currentTarget).closest('.wpmcs-preset-card');
			var preset = $card.data('preset');

			if (!preset) {
				return;
			}

			if (!confirm('确认应用这个预设吗？')) {
				return;
			}

			var $btn = $(e.currentTarget);

			$btn.prop('disabled', true).text('正在应用...');

			$.ajax({
				url: wpmcsQuickSetup.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpmcs_quick_setup_preset',
					nonce: wpmcsQuickSetup.nonce,
					preset: preset
				},
				success: function (response) {
					if (response && response.success) {
						$btn.text('已应用');
						setTimeout(function () {
							location.reload();
						}, 1000);
					} else {
						$btn.prop('disabled', false).text('应用预设');
						alert(response && response.data && response.data.message ? response.data.message : '应用失败');
					}
				},
				error: function () {
					$btn.prop('disabled', false).text('应用预设');
					alert('请求失败，请稍后重试');
				}
			});
		},

		onProviderChange: function () {
			this.updateProviderIcon();
			this.loadProviderFields();
		},

		getSelectedOption: function () {
			return $('#wpmcs-quick-provider').find('option:selected');
		},

		updateProviderIcon: function () {
			var $select = $('#wpmcs-quick-provider');
			var $preview = $('#wpmcs-quick-provider-preview');

			if (!$select.length || !$preview.length) {
				return;
			}

			var $selectedOption = this.getSelectedOption();
			var iconUrl = $selectedOption.attr('data-icon');
			var providerName = $selectedOption.text();

			if (!iconUrl) {
				return;
			}

			$preview.html('<img src="' + iconUrl + '" alt="' + providerName + '" class="wpmcs-provider-icon size-lg" />');
		},

		loadProviderFields: function () {
			var provider = $('#wpmcs-quick-provider').val();
			var $container = $('#wpmcs-quick-provider-fields');
			var providerNonce = '';

			if (typeof wpmcsQuickSetup !== 'undefined' && wpmcsQuickSetup.provider_nonce) {
				providerNonce = wpmcsQuickSetup.provider_nonce;
			} else if (typeof wpmcsProviderNonce !== 'undefined') {
				providerNonce = wpmcsProviderNonce;
			}

			if (!$container.length) {
				return;
			}

			$container.html('<p class="loading">正在加载字段...</p>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpmcs_get_provider_fields',
					provider: provider,
					nonce: providerNonce
				},
				success: function (response) {
					if (response && response.success) {
						$container.html(response.data.html);
						return;
					}

					var message = response && response.data && response.data.message ? response.data.message : '加载字段失败';
					$container.html('<p class="error">' + message + '</p>');
				},
				error: function (xhr) {
					var message = '加载字段失败';

					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						message = xhr.responseJSON.data.message;
					}

					$container.html('<p class="error">' + message + '</p>');
				}
			});
		},

		submitQuickSetup: function (e) {
			e.preventDefault();

			var $btn = $('#wpmcs-quick-setup-submit');
			var $spinner = $('#wpmcs-quick-setup-spinner');
			var $result = $('#wpmcs-quick-setup-result');
			var provider = $('#wpmcs-quick-provider').val();
			var credentials = {};

			$('#wpmcs-quick-provider-fields input, #wpmcs-quick-provider-fields select').each(function () {
				var $el = $(this);
				var name = $el.attr('name');

				if (name) {
					name = name.replace(/wpmcs_settings\[(.+)\]/, '$1');
					credentials[name] = $el.val();
				}
			});

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.hide();

			$.ajax({
				url: wpmcsQuickSetup.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpmcs_quick_setup_provider',
					nonce: wpmcsQuickSetup.nonce,
					provider: provider,
					credentials: credentials
				},
				success: function (response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response && response.success) {
						$result.removeClass('error').addClass('success').show();
						$result.html(
							'<div class="notice notice-success inline">' +
							'<p><span class="dashicons dashicons-yes-alt"></span> ' +
							response.data.message + '</p></div>'
						);

						setTimeout(function () {
							location.reload();
						}, 1500);
					} else {
						$result.removeClass('success').addClass('error').show();
						$result.html(
							'<div class="notice notice-error inline">' +
							'<p><span class="dashicons dashicons-warning"></span> ' +
							(response && response.data && response.data.message ? response.data.message : '保存失败') +
							'</p></div>'
						);
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					$result.removeClass('success').addClass('error').show();
					$result.html(
						'<div class="notice notice-error inline">' +
						'<p>请求失败，请稍后重试</p></div>'
					);
				}
			});
		}
	};

	$(document).ready(function () {
		WPMCS_QuickSetup.init();
	});
})(jQuery);
