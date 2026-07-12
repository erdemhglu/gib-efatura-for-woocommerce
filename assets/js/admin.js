/* global jQuery, WGF */
(function ($) {
	'use strict';

	function showMessage($box, message, isError) {
		var $msg = $box.find('.wgf-message');
		$msg.removeClass('wgf-error wgf-success')
			.addClass(isError ? 'wgf-error' : 'wgf-success')
			.text(message)
			.show();
	}

	function showNotice($container, message, isError) {
		var $notice = $('<div class="notice"><p></p></div>')
			.addClass(isError ? 'notice-error' : 'notice-success');
		$notice.find('p').text(message);
		$container.empty().append($notice);
	}

	function toggleSpinner($box, show) {
		$box.find('.wgf-spinner').toggleClass('is-active', !!show);
	}

	function ajax(action, data) {
		return $.post(WGF.ajaxUrl, $.extend({ action: action, nonce: WGF.nonce }, data));
	}

	// -------------------------------------------------------------
	// Sipariş metabox'u: fatura oluşturma formu aç/kapa
	// (İki buton aynı formu açar; hangisine basıldığı irsaliye alanlarının
	// görünür/zorunlu olup olmayacağını belirler.)
	// -------------------------------------------------------------
	$(document).on('click', '#wgf-toggle-form, #wgf-toggle-form-irsaliye', function () {
		var withIrsaliye = $(this).data('irsaliye') === 1;
		var $form = $('#wgf-create-form');
		var $irsaliyeFields = $form.find('#wgf-irsaliye-fields');

		$form.slideDown();
		$form.data('with-irsaliye', withIrsaliye);

		if (withIrsaliye) {
			$irsaliyeFields.show();
		} else {
			$irsaliyeFields.hide();
			$irsaliyeFields.find('input').val('');
		}
	});

	// -------------------------------------------------------------
	// Fatura oluştur
	// -------------------------------------------------------------
	$(document).on('click', '.wgf-btn[data-action="create_invoice"]', function () {
		var $box = $(this).closest('.wgf-metabox');
		var orderId = $box.data('order-id');
		var $form = $box.find('#wgf-create-form');
		var withIrsaliye = $form.data('with-irsaliye') === true;

		var data = { order_id: orderId };
		$form.find('input[name], textarea[name]').each(function () {
			data[$(this).attr('name')] = $(this).val();
		});

		var $doviz = $form.find('input[name="dovizKuru"]');
		if ($doviz.length && !$doviz.val()) {
			showMessage($box, WGF.i18n.currencyRequired, true);
			return;
		}

		if (withIrsaliye && !data.irsaliyeNumarasi) {
			showMessage($box, WGF.i18n.irsaliyeRequired, true);
			return;
		}

		toggleSpinner($box, true);
		ajax('wgf_create_invoice', data)
			.done(function (response) {
				toggleSpinner($box, false);
				if (response.success) {
					showMessage($box, response.data.message, false);
					window.location.reload();
				} else {
					showMessage($box, (response.data && response.data.message) || WGF.i18n.genericError, true);
				}
			})
			.fail(function () {
				toggleSpinner($box, false);
				showMessage($box, WGF.i18n.genericError, true);
			});
	});

	// -------------------------------------------------------------
	// SMS ile imzalama başlat
	// -------------------------------------------------------------
	$(document).on('click', '.wgf-btn[data-action="start_sms"]', function () {
		var $box = $(this).closest('.wgf-metabox');
		var $actions = $(this).closest('.wgf-actions');
		var invoiceId = $actions.data('invoice-id');

		toggleSpinner($box, true);
		ajax('wgf_start_sms', { invoice_id: invoiceId })
			.done(function (response) {
				toggleSpinner($box, false);
				if (response.success) {
					showMessage($box, response.data.message, false);
					$actions.find('.wgf-sms-box').slideDown();
				} else {
					showMessage($box, (response.data && response.data.message) || WGF.i18n.genericError, true);
				}
			})
			.fail(function () {
				toggleSpinner($box, false);
				showMessage($box, WGF.i18n.genericError, true);
			});
	});

	// -------------------------------------------------------------
	// SMS kodunu doğrula
	// -------------------------------------------------------------
	$(document).on('click', '.wgf-btn[data-action="complete_sms"]', function () {
		var $box = $(this).closest('.wgf-metabox');
		var $actions = $(this).closest('.wgf-actions');
		var invoiceId = $actions.data('invoice-id');
		var code = $actions.find('.wgf-sms-code').val();

		if (!code) {
			showMessage($box, WGF.i18n.enterSmsCode, true);
			return;
		}

		toggleSpinner($box, true);
		ajax('wgf_complete_sms', { invoice_id: invoiceId, code: code })
			.done(function (response) {
				toggleSpinner($box, false);
				if (response.success) {
					showMessage($box, response.data.message, false);
					window.location.reload();
				} else {
					showMessage($box, (response.data && response.data.message) || WGF.i18n.genericError, true);
				}
			})
			.fail(function () {
				toggleSpinner($box, false);
				showMessage($box, WGF.i18n.genericError, true);
			});
	});

	// -------------------------------------------------------------
	// Taslağı sil
	// -------------------------------------------------------------
	$(document).on('click', '.wgf-btn[data-action="delete_draft"]', function () {
		if (!window.confirm(WGF.i18n.confirmDelete)) {
			return;
		}
		var $box = $(this).closest('.wgf-metabox');
		var $actions = $(this).closest('.wgf-actions');
		var invoiceId = $actions.data('invoice-id');

		toggleSpinner($box, true);
		ajax('wgf_delete_draft', { invoice_id: invoiceId })
			.done(function (response) {
				toggleSpinner($box, false);
				if (response.success) {
					showMessage($box, response.data.message, false);
					window.location.reload();
				} else {
					showMessage($box, (response.data && response.data.message) || WGF.i18n.genericError, true);
				}
			})
			.fail(function () {
				toggleSpinner($box, false);
				showMessage($box, WGF.i18n.genericError, true);
			});
	});

	// -------------------------------------------------------------
	// Taslağa sonradan irsaliye ekleme: kutuyu aç/kapa
	// -------------------------------------------------------------
	$(document).on('click', '#wgf-toggle-irsaliye-add', function () {
		$(this).closest('.wgf-actions').find('.wgf-irsaliye-add-box').slideToggle();
	});

	$(document).on('click', '.wgf-btn[data-action="add_irsaliye"]', function () {
		var $box = $(this).closest('.wgf-metabox');
		var $actions = $(this).closest('.wgf-actions');
		var invoiceId = $actions.data('invoice-id');
		var irsaliyeNo = $actions.find('.wgf-irsaliye-no').val();
		var irsaliyeTarihi = $actions.find('.wgf-irsaliye-tarihi').val();

		if (!irsaliyeNo) {
			showMessage($box, WGF.i18n.irsaliyeRequired, true);
			return;
		}

		toggleSpinner($box, true);
		ajax('wgf_add_irsaliye', { invoice_id: invoiceId, irsaliyeNumarasi: irsaliyeNo, irsaliyeTarihi: irsaliyeTarihi })
			.done(function (response) {
				toggleSpinner($box, false);
				if (response.success) {
					showMessage($box, response.data.message, false);
					window.location.reload();
				} else {
					showMessage($box, (response.data && response.data.message) || WGF.i18n.genericError, true);
				}
			})
			.fail(function () {
				toggleSpinner($box, false);
				showMessage($box, WGF.i18n.genericError, true);
			});
	});

	// -------------------------------------------------------------
	// Faturayı e-posta ile gönder
	// -------------------------------------------------------------
	$(document).on('click', '.wgf-btn[data-action="send_email"]', function () {
		var $box = $(this).closest('.wgf-metabox');
		var $actions = $(this).closest('.wgf-actions');
		var invoiceId = $actions.data('invoice-id');

		toggleSpinner($box, true);
		ajax('wgf_send_email', { invoice_id: invoiceId })
			.done(function (response) {
				toggleSpinner($box, false);
				showMessage($box, (response.data && response.data.message) || WGF.i18n.genericError, !response.success);
			})
			.fail(function () {
				toggleSpinner($box, false);
				showMessage($box, WGF.i18n.genericError, true);
			});
	});

	// -------------------------------------------------------------
	// Ayarlar sayfası: test kullanıcısı al
	// -------------------------------------------------------------
	$(document).on('click', '#wgf_fetch_test_creds', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		ajax('wgf_fetch_test_credentials', {})
			.done(function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					$('#wgf_test_username').val(response.data.username);
					$('#wgf_test_password').val(response.data.password);
					window.alert('Test kullanıcısı alındı: ' + response.data.username + ' — Ayarları kaydetmeyi unutmayın.');
				} else {
					window.alert((response.data && response.data.message) || WGF.i18n.genericError);
				}
			})
			.fail(function () {
				$btn.prop('disabled', false);
				window.alert(WGF.i18n.genericError);
			});
	});

	// -------------------------------------------------------------
	// Ayarlar sayfası: eklentiyi sıfırla
	// -------------------------------------------------------------
	$(document).on('click', '#wgf_reset_plugin', function () {
		if (!window.confirm(WGF.i18n.confirmReset)) {
			return;
		}
		var deleteFiles = window.confirm(WGF.i18n.confirmResetFiles);

		var $btn = $(this);
		var $spinner = $('#wgf_reset_spinner');
		var $result = $('#wgf_reset_result');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		ajax('wgf_reset_plugin', { delete_files: deleteFiles ? 1 : 0 })
			.done(function (response) {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);
				if (response.success) {
					showNotice($result, response.data.message, false);
					window.setTimeout(function () {
						window.location.reload();
					}, 1000);
				} else {
					showNotice($result, (response.data && response.data.message) || WGF.i18n.genericError, true);
				}
			})
			.fail(function () {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);
				showNotice($result, WGF.i18n.genericError, true);
			});
	});
})(jQuery);
