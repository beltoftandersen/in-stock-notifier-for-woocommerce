<?php
/**
 * Subscription input validation.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;

/**
 * Validates subscription form submissions.
 */
class Validator {

	/**
	 * Validate a subscription request.
	 *
	 * Nonce verification is handled by AjaxHandler before calling this method.
	 * Returns a WP_Error on failure or true on success.
	 *
	 * @param array<string, mixed> $data Form data.
	 * @return true|\WP_Error
	 */
	public static function validate( $data ) {
		/* Honeypot check. */
		if ( ! empty( $data['isn_website'] ) ) {
			return new \WP_Error( 'isn_spam', __( 'Spam detected.', 'in-stock-notifier-for-woocommerce' ) );
		}

		/* Email. */
		$email = isset( $data['isn_email'] ) ? sanitize_email( wp_unslash( $data['isn_email'] ) ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new \WP_Error( 'isn_email', __( 'Please enter a valid email address.', 'in-stock-notifier-for-woocommerce' ) );
		}

		/* Product ID. */
		$product_id = isset( $data['isn_product_id'] ) ? absint( $data['isn_product_id'] ) : 0;
		if ( ! $product_id ) {
			return new \WP_Error( 'isn_product', __( 'Invalid product.', 'in-stock-notifier-for-woocommerce' ) );
		}

		/* GDPR consent. */
		$opts = Options::get_all();
		if ( '1' === $opts['gdpr_enabled'] && empty( $data['isn_gdpr'] ) ) {
			return new \WP_Error( 'isn_gdpr', __( 'Please accept the consent checkbox.', 'in-stock-notifier-for-woocommerce' ) );
		}

		/* Rate limiting. */
		$ip    = self::get_client_ip();
		$limit = absint( $opts['rate_limit_per_ip'] );
		if ( $limit > 0 && Repository::count_recent_by_ip( $ip ) >= $limit ) {
			return new \WP_Error( 'isn_rate_limit', __( 'Too many requests. Please try again later.', 'in-stock-notifier-for-woocommerce' ) );
		}

		/**
		 * Allow third-party validation.
		 *
		 * @param true|\WP_Error $valid Current validation result.
		 * @param array          $data  Form data.
		 */
		$result = apply_filters( 'instock_notifier_validate_subscription', true, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get the client IP address.
	 *
	 * Uses REMOTE_ADDR only, as it cannot be spoofed by the client.
	 * X-Forwarded-For is only trusted when explicitly opted in via filter
	 * (for sites behind a trusted reverse proxy).
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/**
		 * Whether to trust X-Forwarded-For for IP detection.
		 *
		 * Only enable this if the site is behind a trusted reverse proxy.
		 *
		 * @param bool $trust Default false.
		 */
		if ( apply_filters( 'instock_notifier_trust_forwarded_for', false )
			&& ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			// Use the rightmost IP (closest to the trusted proxy), not the leftmost (client-provided).
			return trim( end( $ips ) );
		}

		return '';
	}
}
