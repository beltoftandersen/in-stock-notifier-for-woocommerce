<?php
/**
 * Shortcode for the notify form.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;

/**
 * Registers the [bisn_form] shortcode.
 */
class Shortcode {

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'bisn_form', array( __CLASS__, 'render' ) );

		// Backward-compatible alias for the old unprefixed shortcode.
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
			'bisn_form'
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

		FormRenderer::enqueue_assets();

		return FormRenderer::get_form_html( $product_id, $variation_id, $hidden );
	}

}
