<?php
/**
 * Logging via WooCommerce logger and admin log viewer.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;

/**
 * Handles writing log entries via wc_get_logger() and rendering the log viewer in admin.
 */
class LogViewer {

	const SOURCE = 'instock-notifier';

	/**
	 * Write a log entry via WooCommerce logger.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (debug, info, notice, warning, error, critical, alert, emergency).
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( Options::get( 'disable_logging' ) === '1' ) {
			return;
		}

		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log( $level, $message, array( 'source' => self::SOURCE ) );
	}

	/**
	 * Render the log viewer in admin. Points users to WooCommerce logs.
	 *
	 * @return void
	 */
	public static function render() {
		$disabled = Options::get( 'disable_logging' ) === '1';
		if ( $disabled ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Logging is currently disabled in settings.', 'instock-notifier-for-woocommerce' ) . '</p></div>';
		}

		$logs_url = admin_url( 'admin.php?page=wc-status&tab=logs' );

		echo '<div class="isn-log-viewer">';
		echo '<p>';
		echo esc_html__( 'Logs are stored using the WooCommerce logger.', 'instock-notifier-for-woocommerce' );
		echo ' <a href="' . esc_url( $logs_url ) . '" class="button">';
		echo esc_html__( 'View Logs in WooCommerce', 'instock-notifier-for-woocommerce' );
		echo '</a>';
		echo '</p>';
		echo '<p class="description">';
		/* translators: %s: log source name */
		echo esc_html( sprintf( __( 'Look for the source "%s" in WooCommerce > Status > Logs.', 'instock-notifier-for-woocommerce' ), self::SOURCE ) );
		echo '</p>';
		echo '</div>';
	}
}
