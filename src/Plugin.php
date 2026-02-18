<?php
/**
 * Central plugin initialization.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Admin\AdminPage;
use InStockNotifier\Frontend\FormRenderer;
use InStockNotifier\Frontend\AjaxHandler;
use InStockNotifier\Frontend\Shortcode;
use InStockNotifier\Stock\StockListener;
use InStockNotifier\Stock\NotificationSender;
use InStockNotifier\Unsubscribe\Handler as UnsubscribeHandler;
use InStockNotifier\Email\BackInStockEmail;
use InStockNotifier\Support\Installer;
use InStockNotifier\Support\Options;

/**
 * Plugin bootstrap.
 */
class Plugin {

	/**
	 * Initialize all components.
	 *
	 * @return void
	 */
	public static function init() {
		Options::init();
		self::maybe_upgrade_db();

		if ( is_admin() ) {
			AdminPage::init();
		}

		FormRenderer::init();
		AjaxHandler::init();
		Shortcode::init();
		StockListener::init();
		NotificationSender::init();
		UnsubscribeHandler::init();

		/* Register back-in-stock email with WooCommerce. */
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email_class' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Run database migrations if DB version is outdated.
	 *
	 * @return void
	 */
	private static function maybe_upgrade_db() {
		$current = get_option( 'isn_db_version', '1.0.0' );
		if ( version_compare( $current, ISN_DB_VERSION, '>=' ) ) {
			return;
		}
		Installer::activate();
	}

	/**
	 * Register the back-in-stock email class with WooCommerce.
	 *
	 * @param array $email_classes Existing email classes.
	 * @return array
	 */
	public static function register_email_class( $email_classes ) {
		$email_classes['ISN_Back_In_Stock'] = new BackInStockEmail();
		return $email_classes;
	}

	/**
	 * Enqueue admin CSS and JS on the plugin page only.
	 *
	 * @param string $hook_suffix The admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'woocommerce_page_isn-notifier' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'isn-admin',
			ISN_URL . 'assets/css/admin.css',
			array(),
			ISN_VERSION
		);

		wp_enqueue_script(
			'isn-admin',
			ISN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ISN_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend CSS and JS on product pages with OOS items.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}

		if ( empty( Options::get_all()['enabled'] ) ) {
			return;
		}

		$isn_product = wc_get_product( get_the_ID() );
		if ( ! $isn_product ) {
			return;
		}

		$needs_assets = false;
		if ( ! $isn_product->is_in_stock() ) {
			$needs_assets = true;
		} elseif ( $isn_product->is_type( 'variable' ) ) {
			$needs_assets = true;
		}

		if ( ! $needs_assets ) {
			return;
		}

		FormRenderer::enqueue_assets();
	}
}
