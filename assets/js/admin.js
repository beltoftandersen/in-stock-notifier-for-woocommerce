/**
 * In-Stock Notifier admin JavaScript.
 *
 * @package InStockNotifier
 */

/* global jQuery */
(function ($) {
	'use strict';

	$(function () {
		/* Select all checkbox for bulk actions. */
		$('.isn-tab-content').on('change', '#cb-select-all-1, #cb-select-all-2', function () {
			var checked = $(this).prop('checked');
			$('input[name="isn_ids[]"]').prop('checked', checked);
		});
	});
})(jQuery);
