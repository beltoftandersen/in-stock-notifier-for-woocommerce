<?php
/**
 * Shortcode for the notify form.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;

/**
 * Registers the [instock_notifier] shortcode.
 */
class Shortcode {

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'instock_notifier', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$opts = Options::get_all();
		if ( '1' !== $opts['enabled'] ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'product_id'   => '0',
				'variation_id' => '0',
			),
			$atts,
			'instock_notifier'
		);

		$product_id   = absint( $atts['product_id'] );
		$variation_id = absint( $atts['variation_id'] );

		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		/* Check stock status: only show form for out-of-stock products. */
		$isn_product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $isn_product ) {
			return '';
		}

		/* Variable products: render hidden, JS shows on out-of-stock variation select. */
		$hidden = false;
		if ( $isn_product->is_type( 'variable' ) ) {
			$hidden = true;
		} elseif ( $isn_product->is_in_stock() ) {
			return '';
		}

		/* Ensure frontend assets are enqueued even outside product pages. */
		self::ensure_assets();

		return FormRenderer::get_form_html( $product_id, $variation_id, $hidden );
	}

	/**
	 * Enqueue frontend assets when the shortcode is used outside product pages.
	 *
	 * @return void
	 */
	private static function ensure_assets() {
		if ( wp_script_is( 'isn-frontend', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'isn-frontend',
			ISN_URL . 'assets/css/frontend.css',
			array(),
			ISN_VERSION
		);

		wp_enqueue_script(
			'isn-frontend',
			ISN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			ISN_VERSION,
			true
		);

		wp_localize_script(
			'isn-frontend',
			'isn_vars',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'isn_subscribe_nonce' ),
				'error_generic' => esc_html__( 'An error occurred.', 'in-stock-notifier-for-woocommerce' ),
				'error_network' => esc_html__( 'An error occurred. Please try again.', 'in-stock-notifier-for-woocommerce' ),
			)
		);
	}
}
