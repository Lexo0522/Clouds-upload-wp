(function ($) {
	'use strict';

	var WPMCS_Wizard = {
		currentStep: 'welcome',
		isSaving: false,

		init: function () {
			if (typeof wpmcsWizard === 'undefined') {
				return;
			}

			this.currentStep = wpmcsWizard.current_step || 'welcome';
			this.bindEvents();
			this.syncStepState();

			if (this.currentStep === 'configure') {
				this.loadProviderFields();
			}
		},

		bindEvents: function () {
			$(document).on('click', '.wpmcs-wizard-start', this.onStart.bind(this));
			$(document).on('click', '.wpmcs-wizard-prev', this.onPrev.bind(this));
			$(document).on('click', '.wpmcs-wizard-next', this.onNext.bind(this));
			$(document).on('click', '.wpmcs-wizard-complete', this.onComplete.bind(this));
			$(document).on('click', '.wpmcs-provider-card', this.onProviderSelect.bind(this));
			$(document).on('click', '.wpmcs-test-connection', this.onTestConnection.bind(this));
			$(document).on('click', '.wpmcs-wizard-skip-link', this.onSkip.bind(this));
			$(document).on('change', '#wpmcs-wizard-provider-fields select, #wpmcs-wizard-provider-fields input', this.clearMessage.bind(this));
		},

		syncStepState: function () {
			var $content = $('.wpmcs-step-content');
			if ($content.length) {
				$content.attr('data-step', this.currentStep);
			}
		},

		onStart: function (e) {
			e.preventDefault();
			this.goToStep('provider');
		},

		onPrev: function (e) {
			e.preventDefault();

			var index = this.getStepIndex(this.currentStep);
			if (index > 0) {
				this.goToStep(this.getStepByIndex(index - 1));
			}
		},

		onNext: function (e) {
			e.preventDefault();
			this.advanceStep();
		},

		onComplete: function (e) {
			e.preventDefault();
			var self = this;

			this.setBusy(true, '.wpmcs-wizard-complete');
			this.saveStep('options').done(function (response) {
				if (response && response.success) {
					$.ajax({
						url: wpmcsWizard.ajax_url,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'wpmcs_wizard_skip',
							nonce: wpmcsWizard.nonce
						}
					}).done(function (result) {
						if (result && result.success) {
							window.location.href = result.data && result.data.redirect ? result.data.redirect : wpmcsWizard.settings_url;
							return;
						}

						self.showNotice('error', self.getResponseMessage(result, '完成向导失败。'));
					}).fail(function () {
						self.showNotice('error', '完成向导失败，请稍后重试。');
					}).always(function () {
						self.setBusy(false, '.wpmcs-wizard-complete');
					});
					return;
				}

				self.showNotice('error', self.getResponseMessage(response, '保存配置失败。'));
				self.setBusy(false, '.wpmcs-wizard-complete');
			}).fail(function () {
				self.showNotice('error', '保存配置失败，请稍后重试。');
				self.setBusy(false, '.wpmcs-wizard-complete');
			});
		},

		onProviderSelect: function (e) {
			var $card = $(e.currentTarget);
			$card.addClass('selected').siblings().removeClass('selected');
			$card.find('input').prop('checked', true);
			this.clearMessage();
		},

		onTestConnection: function (e) {
			e.preventDefault();

			var self = this;
			var $btn = $(e.currentTarget);

			this.setBusy(true, $btn);
			this.showTestStatus('running');

			this.saveStep('configure').done(function (response) {
				if (!response || !response.success) {
					self.showTestStatus('error', self.getResponseMessage(response, '保存配置失败，无法继续测试。'));
					self.setBusy(false, $btn);
					return;
				}

				$.ajax({
					url: wpmcsWizard.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'wpmcs_wizard_test_connection',
						nonce: wpmcsWizard.nonce
					}
				}).done(function (result) {
					if (result && result.success) {
						self.showTestStatus('success');
						$('#wpmcs-wizard-next-after-test').prop('disabled', false);
						self.showNotice('success', '连接测试通过，可以继续下一步。');
						return;
					}

					self.showTestStatus('error', self.getResponseMessage(result, '连接测试失败。'));
				}).fail(function () {
					self.showTestStatus('error', '连接测试失败，请检查网络或配置。');
				}).always(function () {
					self.setBusy(false, $btn);
				});
			}).fail(function () {
				self.showTestStatus('error', '保存配置失败，无法继续测试。');
				self.setBusy(false, $btn);
			});
		},

		onSkip: function (e) {
			e.preventDefault();

			if (!window.confirm('确定要跳过初次配置向导吗？你之后仍然可以从后台菜单再次打开。')) {
				return;
			}

			$.ajax({
				url: wpmcsWizard.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpmcs_wizard_skip',
					nonce: wpmcsWizard.nonce
				}
			}).done(function (response) {
				if (response && response.success && response.data && response.data.redirect) {
					window.location.href = response.data.redirect;
					return;
				}

				this.showNotice('error', this.getResponseMessage(response, '跳过向导失败。'));
			}.bind(this)).fail(function () {
				this.showNotice('error', '跳过向导失败，请稍后重试。');
			}.bind(this));
		},

		advanceStep: function () {
			var self = this;
			var step = this.currentStep;

			this.setBusy(true, '.wpmcs-wizard-next, .wpmcs-wizard-complete');
			this.saveStep(step).done(function (response) {
				if (response && response.success) {
					var nextStep = response.data && response.data.next_step ? response.data.next_step : self.getNextStep(step);
					self.goToStep(nextStep);
					return;
				}

				self.showNotice('error', self.getResponseMessage(response, '保存配置失败。'));
				self.setBusy(false, '.wpmcs-wizard-next, .wpmcs-wizard-complete');
			}).fail(function () {
				self.showNotice('error', '保存配置失败，请稍后重试。');
				self.setBusy(false, '.wpmcs-wizard-next, .wpmcs-wizard-complete');
			});
		},

		goToStep: function (step) {
			var baseUrl = window.location.href.split('#')[0].split('?')[0];
			window.location.href = baseUrl + '?page=wpmcs-setup-wizard&step=' + encodeURIComponent(step);
		},

		getStepIndex: function (step) {
			var steps = this.getSteps();
			return steps.indexOf(step);
		},

		getSteps: function () {
			return ['welcome', 'provider', 'configure', 'test', 'options', 'complete'];
		},

		getStepByIndex: function (index) {
			var steps = this.getSteps();
			return steps[index] || 'complete';
		},

		getNextStep: function (currentStep) {
			var index = this.getStepIndex(currentStep);
			return index < this.getSteps().length - 1 ? this.getStepByIndex(index + 1) : 'complete';
		},

		saveStep: function (step) {
			var data = {};

			if (step === 'provider') {
				data.provider = $('.wpmcs-provider-card.selected input').val() || wpmcsWizard.current_provider || '';
			} else if (step === 'configure') {
				data = this.getFormData('#wpmcs-wizard-provider-fields');
			} else if (step === 'options') {
				data = {
					enabled: $('input[name="enabled"]').is(':checked') ? 1 : 0,
					replace_url: $('input[name="replace_url"]').is(':checked') ? 1 : 0,
					auto_rename: $('input[name="auto_rename"]').is(':checked') ? 1 : 0,
					async_upload: $('input[name="async_upload"]').is(':checked') ? 1 : 0,
					enable_cache: $('input[name="enable_cache"]').is(':checked') ? 1 : 0,
					enable_logging: $('input[name="enable_logging"]').is(':checked') ? 1 : 0
				};
			}

			return $.ajax({
				url: wpmcsWizard.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpmcs_wizard_save_step',
					nonce: wpmcsWizard.nonce,
					step: step,
					data: data
				}
			});
		},

		getFormData: function (selector) {
			var data = {};

			$(selector).find('input, select, textarea').each(function () {
				var $field = $(this);
				var name = $field.attr('name');

				if (!name) {
					return;
				}

				name = name.replace(/wpmcs_settings\[(.+)\]/, '$1');

				if ($field.attr('type') === 'checkbox') {
					data[name] = $field.is(':checked') ? 1 : 0;
					return;
				}

				data[name] = $field.val();
			});

			return data;
		},

		loadProviderFields: function () {
			var self = this;
			var provider = this.getCurrentProvider();
			var $container = $('#wpmcs-wizard-provider-fields');
			var $help = $('#wpmcs-provider-help');

			if (!$container.length) {
				return;
			}

			$container.addClass('loading');

			$.ajax({
				url: wpmcsWizard.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpmcs_get_provider_fields',
					provider: provider,
					nonce: wpmcsWizard.provider_nonce
				}
			}).done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$container.html(response.data.html);
					if ($help.length) {
						$help.html(self.getProviderHelpContent(provider));
					}
					return;
				}

				$container.html('<p class="description">暂时无法加载服务商字段，请刷新页面后重试。</p>');
			}).fail(function () {
				$container.html('<p class="description">暂时无法加载服务商字段，请刷新页面后重试。</p>');
			}).always(function () {
				$container.removeClass('loading');
			});
		},

		getCurrentProvider: function () {
			var provider = wpmcsWizard.current_provider || 'qiniu';
			var $selected = $('.wpmcs-provider-card.selected input:checked');

			if ($selected.length) {
				provider = $selected.val() || provider;
			}

			return provider;
		},

		getProviderHelpContent: function (provider) {
			var help = {
				qiniu: '<ol><li>准备七牛云的 AccessKey 和 SecretKey。</li><li>填写 Bucket 名称。</li><li>确认上传域名或外链域名已在控制台配置完成。</li></ol>',
				aliyun_oss: '<ol><li>准备阿里云 OSS 的 AccessKey ID 和 AccessKey Secret。</li><li>填写 Bucket 和 Endpoint。</li><li>建议先确认 Bucket 的访问权限和绑定域名。</li></ol>',
				tencent_cos: '<ol><li>准备腾讯云 COS 的 Secret ID 和 Secret Key。</li><li>填写 Bucket 和 Region。</li><li>如果是私有桶，测试连接中的“文件访问”返回 403 是正常现象。</li></ol>',
				upyun: '<ol><li>准备又拍云的操作员账号和操作员密码。</li><li>填写 Bucket 和 API 域名。</li><li>建议确认外链域名或 CDN 域名配置。</li></ol>',
				dogecloud: '<ol><li>准备多吉云的 AccessKey 和 SecretKey。</li><li>填写 Bucket 信息。</li><li>如果启用了 CDN 域名，请确保域名已解析并可访问。</li></ol>',
				aws_s3: '<ol><li>准备 AWS S3 的 Access Key ID 和 Secret Access Key。</li><li>填写 S3 Bucket 和 Region 或 Endpoint。</li><li>确认 IAM 权限至少包含上传、读取和删除对象的权限。</li></ol>'
			};

			return help[provider] || '<p class="description">请选择一个云服务商，然后填写它需要的连接信息。</p>';
		},

		getResponseMessage: function (response, fallback) {
			if (response && response.data && response.data.message) {
				return response.data.message;
			}

			return fallback || '操作失败。';
		},

		showTestStatus: function (status, message) {
			$('.wpmcs-test-status > div').hide();

			if (status === 'running') {
				$('.wpmcs-test-running').show();
				this.clearMessage();
				return;
			}

			if (status === 'success') {
				$('.wpmcs-test-success').show();
				this.showNotice('success', message || '连接测试通过。');
				return;
			}

			if (status === 'error') {
				$('.wpmcs-test-error').show();
				$('.wpmcs-test-error-message').text(message || '连接测试失败。');
				this.showNotice('error', message || '连接测试失败。');
			}
		},

		showNotice: function (type, message) {
			var $notice = $('.wpmcs-wizard-message');

			if (!$notice.length) {
				if (message) {
					window.alert(message);
				}
				return;
			}

			$notice
				.removeClass('notice-success notice-error notice-warning')
				.addClass(type === 'success' ? 'notice-success' : (type === 'warning' ? 'notice-warning' : 'notice-error'))
				.html('<p>' + message + '</p>')
				.show();
		},

		clearMessage: function () {
			var $notice = $('.wpmcs-wizard-message');
			if ($notice.length) {
				$notice.hide().empty();
			}
		},

		setBusy: function (state, target) {
			var $targets = typeof target === 'string' ? $(target) : $(target);

			if (state) {
				this.isSaving = true;
				$targets.prop('disabled', true).addClass('is-busy');
				return;
			}

			this.isSaving = false;
			$targets.prop('disabled', false).removeClass('is-busy');
		}
	};

	$(document).ready(function () {
		WPMCS_Wizard.init();
	});
})(jQuery);
