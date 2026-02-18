<?php
/**
 * Plugin Name:       In-Stock Notifier for WooCommerce
 * Plugin URI:        https://developer.wordpress.org/plugins/in-stock-notifier-for-woocommerce/
 * Description:       Let customers subscribe to out-of-stock product notifications and automatically email them when items are back in stock.
 * Version:           1.0.26
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Chimkins IT
 * Author URI:        https://chimkins.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       in-stock-notifier-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.6
 *
 * @package InStockNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── PSR-4 autoloader ──────────────────────────────────────────── */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'InStockNotifier\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( 'InStockNotifier\\' ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
		$file     = plugin_dir_path( __FILE__ ) . 'src/' . $relative;
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/* ── Constants ─────────────────────────────────────────────────── */
define( 'ISN_VERSION', '1.0.26' );
define( 'ISN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ISN_URL', plugin_dir_url( __FILE__ ) );
define( 'ISN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ISN_DB_VERSION', '1.1.1' );

/* ── Activation / deactivation ─────────────────────────────────── */
register_activation_hook(
	__FILE__,
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			/* translators: %s: WooCommerce plugin name */
			wp_die( esc_html__( 'In-Stock Notifier requires WooCommerce to be installed and active.', 'in-stock-notifier-for-woocommerce' ) );
		}
		InStockNotifier\Support\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'isn_daily_cleanup' );
		/* Unschedule all Action Scheduler actions. */
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'isn_send_notification' );
		}
		/* Clean up legacy cron hook from pre-1.0.8. */
		wp_clear_scheduled_hook( 'isn_send_notifications' );
		delete_transient( 'isn_processing_lock' );
	}
);

/* ── Bootstrap ─────────────────────────────────────────────────── */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		InStockNotifier\Plugin::init();
	}
);

/* ── Settings link on Plugins page ─────────────────────────────── */
add_filter(
	'plugin_action_links_' . ISN_BASENAME,
	function ( $links ) {
		$url           = admin_url( 'admin.php?page=isn-notifier' );
		$settings_link = '<a href="' . esc_url( $url ) . '">'
			. esc_html__( 'Settings', 'in-stock-notifier-for-woocommerce' )
			. '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/* ── HPOS compatibility ────────────────────────────────────────── */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
