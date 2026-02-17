<?php
/**
 * Notification queue for batched email sending.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Stock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the pending queue of product IDs for notification.
 */
class NotificationQueue {

	const QUEUE_OPTION = 'isn_pending_queue';
	const CRON_HOOK    = 'isn_send_notifications';

	/**
	 * Add a product to the notification queue.
	 *
	 * @param int $product_id   Product ID (parent for variations).
	 * @param int $variation_id Variation ID (0 for simple products).
	 * @return void
	 */
	public static function enqueue( $product_id, $variation_id = 0 ) {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		$key = absint( $product_id ) . ':' . absint( $variation_id );
		if ( ! in_array( $key, $queue, true ) ) {
			$queue[] = $key;
			update_option( self::QUEUE_OPTION, $queue, false );
		}

		self::schedule();
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}

	/**
	 * Get and clear the next item from the queue.
	 *
	 * @return array{product_id: int, variation_id: int}|null
	 */
	public static function dequeue() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return null;
		}

		$key = array_shift( $queue );
		update_option( self::QUEUE_OPTION, $queue, false );

		$parts = explode( ':', $key );
		return array(
			'product_id'   => absint( $parts[0] ),
			'variation_id' => isset( $parts[1] ) ? absint( $parts[1] ) : 0,
		);
	}

	/**
	 * Check if queue has items.
	 *
	 * @return bool
	 */
	public static function has_items() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return is_array( $queue ) && ! empty( $queue );
	}
}
