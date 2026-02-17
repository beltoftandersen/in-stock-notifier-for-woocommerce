<?php
/**
 * Logs tab delegates to LogViewer.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Logging\LogViewer;

/**
 * Renders the Logs admin tab.
 */
class LogsTab {

	/**
	 * Render the logs tab.
	 *
	 * @return void
	 */
	public static function render() {
		LogViewer::render();
	}
}
