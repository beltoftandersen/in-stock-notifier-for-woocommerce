<?php
/**
 * Renders the subscription form on product pages.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;

/**
 * Hooks into WooCommerce to display the notify form.
 */
class FormRenderer {

	/**
	 * Track if custom CSS has been output.
	 *
	 * @var bool
	 */
	private static $css_output = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_simple' ), 31 );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_variable' ) );
	}

	/**
	 * Render form for simple / grouped / external products.
	 *
	 * @return void
	 */
	public static function render_simple() {
		$opts = Options::get_all();
		if ( '1' !== $opts['enabled'] || '1' !== $opts['form_position_enabled'] ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			return; // Handled by render_variable.
		}

		if ( $product->is_in_stock() ) {
			return;
		}

		echo self::get_form_html( $product->get_id(), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render hidden form container for variable products.
	 * JS will show/hide based on selected variation stock.
	 *
	 * @return void
	 */
	public static function render_variable() {
		$opts = Options::get_all();
		if ( '1' !== $opts['enabled'] || '1' !== $opts['form_position_enabled'] ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		echo self::get_form_html( $product->get_id(), 0, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the form HTML.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	public static function get_form_html( $product_id, $variation_id, $hidden = false ) {
		$opts = Options::get_all();

		/**
		 * Filter the heading text.
		 *
		 * @param string $heading Heading text.
		 * @param int    $product_id Product ID.
		 */
		$heading = apply_filters(
			'instock_notifier_form_heading_text',
			__( 'Want to know when it\'s back? Leave your email below.', 'instock-notifier-for-woocommerce' ),
			$product_id
		);

		$hide_attr = $hidden ? ' style="display:none;"' : '';
		$html  = '<div class="isn-notify-form"' . $hide_attr . '>';
		$html .= '<p class="isn-form-heading">' . esc_html( $heading ) . '</p>';
		$html .= '<form class="isn-form" data-isn-form="1">';

		/* Quantity field (optional) — above the inline row. */
		if ( '1' === $opts['quantity_field_enabled'] ) {
			$html .= '<div class="isn-field isn-field-quantity">';
			$html .= '<label for="isn-quantity-' . absint( $product_id ) . '">' . esc_html__( 'Desired quantity', 'instock-notifier-for-woocommerce' ) . '</label>';
			$html .= '<input type="number" id="isn-quantity-' . absint( $product_id ) . '" name="isn_quantity" min="1" value="1" class="isn-quantity-input" />';
			$html .= '</div>';
		}

		/* GDPR checkbox (optional) — above the inline row. */
		if ( '1' === $opts['gdpr_enabled'] ) {
			$html .= '<div class="isn-field isn-field-gdpr">';
			$html .= '<label><input type="checkbox" name="isn_gdpr" value="1" required /> ';
			$html .= esc_html( $opts['gdpr_text'] );
			$html .= '</label>';
			$html .= '</div>';
		}

		/* Inline row: email + submit button side by side. */
		$html .= '<div class="isn-fields-row">';

		/* Email field — prefill for logged-in users. */
		$user_email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
		$html .= '<label for="isn-email-' . absint( $product_id ) . '" class="screen-reader-text">' . esc_html__( 'Email address', 'instock-notifier-for-woocommerce' ) . '</label>';
		$html .= '<input type="email" id="isn-email-' . absint( $product_id ) . '" name="isn_email" placeholder="' . esc_attr__( 'Your email address', 'instock-notifier-for-woocommerce' ) . '" value="' . esc_attr( $user_email ) . '" required class="isn-email-input" />';

		/* Submit button. */
		$html .= '<button type="submit" class="isn-submit">';
		$html .= esc_html( $opts['button_text'] );
		$html .= '</button>';

		$html .= '</div>'; /* /.isn-fields-row */

		/* Honeypot. */
		$html .= '<div style="display:none !important;" aria-hidden="true">';
		$html .= '<input type="text" name="isn_website" tabindex="-1" autocomplete="off" />';
		$html .= '</div>';

		/* Hidden fields. */
		$html .= '<input type="hidden" name="isn_nonce" value="" />';
		$html .= '<input type="hidden" name="isn_product_id" value="' . absint( $product_id ) . '" />';
		$html .= '<input type="hidden" name="isn_variation_id" value="' . absint( $variation_id ) . '" />';

		/**
		 * Allow adding extra form fields.
		 *
		 * @param string $fields     Extra HTML.
		 * @param int    $product_id Product ID.
		 */
		$extra = apply_filters( 'instock_notifier_form_fields', '', $product_id );
		if ( $extra ) {
			$html .= wp_kses_post( $extra );
		}

		$html .= '</form>';
		$html .= '<div class="isn-form-message" role="status" aria-live="polite"></div>';

		/* Custom CSS: add as inline style attached to the enqueued stylesheet. */
		if ( ! empty( $opts['custom_css'] ) && ! self::$css_output ) {
			wp_add_inline_style( 'isn-frontend', wp_strip_all_tags( $opts['custom_css'] ) );
			self::$css_output = true;
		}

		$html .= '</div>';

		/**
		 * Filter the complete form HTML.
		 *
		 * @param string $html       Full form HTML.
		 * @param int    $product_id Product ID.
		 */
		return apply_filters( 'instock_notifier_form_html', $html, $product_id );
	}
}
