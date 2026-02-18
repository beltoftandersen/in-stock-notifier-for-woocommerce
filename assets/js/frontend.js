/**
 * In-Stock Notifier frontend JavaScript.
 *
 * Handles AJAX form submission and variable product variation detection.
 *
 * @package InStockNotifier
 */

/* global jQuery, isn_vars */
(function ($) {
	'use strict';

	/**
	 * Handle form submission via AJAX.
	 */
	function handleFormSubmit(e) {
		e.preventDefault();

		var $form = $(this);
		var $btn = $form.find('.isn-submit');
		var $msg = $form.closest('.isn-notify-form').find('.isn-form-message');

		if ($btn.prop('disabled')) {
			return;
		}

		$btn.prop('disabled', true);
		$msg.text('').removeClass('isn-success isn-error');

		var data = {
			action: 'isn_subscribe',
			isn_nonce: isn_vars.nonce,
			isn_email: $form.find('[name="isn_email"]').val(),
			isn_product_id: $form.find('[name="isn_product_id"]').val(),
			isn_variation_id: $form.find('[name="isn_variation_id"]').val() || '0',
			isn_quantity: $form.find('[name="isn_quantity"]').val() || '1',
			isn_gdpr: $form.find('[name="isn_gdpr"]').is(':checked') ? '1' : '',
			isn_website: $form.find('[name="isn_website"]').val() || ''
		};

		$.post(isn_vars.ajax_url, data, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$msg.text(response.data.message).addClass('isn-success');
				$form.find('[name="isn_email"]').val('');
			} else {
				var message = response.data && response.data.message
					? response.data.message
					: isn_vars.error_generic;
				$msg.text(message).addClass('isn-error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$msg.text(isn_vars.error_network).addClass('isn-error');
		});
	}

	/**
	 * Handle WooCommerce variation found event.
	 */
	function onVariationFound(event, variation) {
		var $form = $(event.target).closest('.product').find('.isn-notify-form');
		if (!$form.length) {
			return;
		}

		if (variation && !variation.is_in_stock) {
			$form.find('[name="isn_variation_id"]').val(variation.variation_id);
			$form.slideDown(200);
		} else {
			$form.slideUp(200);
		}
	}

	/**
	 * Handle WooCommerce variation reset.
	 */
	function onVariationReset(event) {
		var $form = $(event.target).closest('.product').find('.isn-notify-form');
		if ($form.length) {
			$form.slideUp(200);
		}
	}

	$(function () {
		$(document.body).on('submit', '.isn-form', handleFormSubmit);

		/* Ignore variation events during WooCommerce init to prevent flash. */
		var initialized = false;
		setTimeout(function () { initialized = true; }, 500);

		$(document.body).on('found_variation', '.variations_form', function (event, variation) {
			if (!initialized) { return; }
			onVariationFound(event, variation);
		});
		$(document.body).on('reset_data', '.variations_form', function (event) {
			if (!initialized) { return; }
			onVariationReset(event);
		});
	});
})(jQuery);
