<?php
/**
 * Purge product page cache on stock change.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Stock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears various cache layers when a product comes back in stock.
 */
class CacheBuster {

	/**
	 * Purge all cache layers for a product.
	 *
	 * @param int $product_id Product or post ID.
	 * @return void
	 */
	public static function purge_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		/* WordPress core object cache. */
		clean_post_cache( $product_id );

		/* WooCommerce transients. */
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		/* WP Super Cache. */
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $product_id );
		}

		/* W3 Total Cache. */
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $product_id );
		}

		/* WP Rocket. */
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $product_id );
		}

		/* LiteSpeed Cache. */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party hook.
		do_action( 'litespeed_purge_post', $product_id );

		/* WP Fastest Cache. */
		if ( function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
			wpfc_clear_post_cache_by_id( $product_id );
		}

		$url = get_permalink( $product_id );

		/**
		 * Allow custom cache purge logic (CDN, Varnish, etc.).
		 *
		 * @param int    $product_id Product ID.
		 * @param string $url        Product permalink.
		 */
		do_action( 'instock_notifier_cache_purge_product', $product_id, $url );
	}
}
