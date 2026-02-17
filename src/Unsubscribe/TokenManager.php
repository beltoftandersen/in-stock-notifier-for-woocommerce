<?php
/**
 * HMAC token generation for unsubscribe links.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Unsubscribe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and validates HMAC-SHA256 unsubscribe tokens.
 */
class TokenManager {

	/**
	 * Generate a unique token for a subscription.
	 *
	 * @param string $email Subscriber email.
	 * @return string 64-character hex token.
	 */
	public static function generate( $email ) {
		$unique = wp_generate_uuid4();
		$data   = $unique . '|' . sanitize_email( $email );
		return hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
	}

	/**
	 * Build the unsubscribe URL for a token.
	 *
	 * @param string $token Unsubscribe token.
	 * @return string
	 */
	public static function get_url( $token ) {
		return add_query_arg(
			array(
				'isn_action' => 'unsubscribe',
				'token'      => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}
}
