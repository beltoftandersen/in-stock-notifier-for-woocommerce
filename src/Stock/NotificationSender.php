<?php
/**
 * Cron worker that sends notification emails.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Stock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;
use InStockNotifier\Subscription\Repository;
use InStockNotifier\Unsubscribe\TokenManager;
use InStockNotifier\Email\BackInStockEmail;
use InStockNotifier\Logging\LogViewer;

/**
 * Processes the notification queue in batches via cron.
 */
class NotificationSender {

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( NotificationQueue::CRON_HOOK, array( __CLASS__, 'process_batch' ) );
		add_action( 'isn_daily_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Process one batch of notifications.
	 *
	 * @return void
	 */
	public static function process_batch() {
		/* Prevent concurrent processing. */
		if ( get_transient( 'isn_processing_lock' ) ) {
			return;
		}
		set_transient( 'isn_processing_lock', true, 5 * MINUTE_IN_SECONDS );

		$item = NotificationQueue::dequeue();
		if ( ! $item ) {
			delete_transient( 'isn_processing_lock' );
			return;
		}

		$product_id   = $item['product_id'];
		$variation_id = $item['variation_id'];
		$opts         = Options::get_all();
		$batch_size   = absint( $opts['batch_size'] );
		$throttle     = absint( $opts['throttle_seconds'] );

		/* Re-verify stock status. */
		$check_id = $variation_id ? $variation_id : $product_id;
		$product  = wc_get_product( $check_id );
		if ( ! $product || ! $product->is_in_stock() ) {
			LogViewer::log( 'SKIP product=' . $check_id . ' no_longer_in_stock' );
			delete_transient( 'isn_processing_lock' );
			self::maybe_continue();
			return;
		}

		/* Get active subscriptions. */
		$subs = Repository::get_active_for_product( $product_id, $variation_id, $batch_size );
		if ( empty( $subs ) ) {
			/* Try with variation_id=0 if we checked a specific variation. */
			if ( $variation_id ) {
				$subs = Repository::get_active_for_product( $product_id, 0, $batch_size );
			}
		}

		if ( empty( $subs ) ) {
			LogViewer::log( 'SKIP product=' . $product_id . ' no_active_subscriptions' );
			delete_transient( 'isn_processing_lock' );
			self::maybe_continue();
			return;
		}

		$sent_count = 0;
		$start_time = microtime( true );

		foreach ( $subs as $sub ) {
			/**
			 * Fires before a notification email is sent.
			 *
			 * @param object      $sub     Subscription record.
			 * @param \WC_Product $product Product object.
			 */
			do_action( 'instock_notifier_before_notification_sent', $sub, $product );

			$unsub_url = TokenManager::get_url( $sub->unsubscribe_token );

			/* Send via WooCommerce email class for consistent template styling. */
			$email_instance = self::get_email_instance();
			$sent           = $email_instance->trigger( $product, $sub->email, $unsub_url );

			if ( $sent ) {
				Repository::mark_notified( $sub->id );
				++$sent_count;
				LogViewer::log( 'SENT email=' . $sub->email . ' product=' . $product_id );

				/**
				 * Fires after a notification email is sent.
				 *
				 * @param object      $sub     Subscription record.
				 * @param \WC_Product $product Product object.
				 */
				do_action( 'instock_notifier_after_notification_sent', $sub, $product );
			} else {
				LogViewer::log( 'FAIL email=' . $sub->email . ' product=' . $product_id . ' wp_mail_failed' );
			}

			if ( $throttle > 0 ) {
				sleep( $throttle );
			}

			/* Break if approaching PHP timeout to avoid abrupt termination. */
			$max_time = (int) ini_get( 'max_execution_time' );
			if ( $max_time > 0 ) {
				$elapsed = microtime( true ) - $start_time;
				if ( $elapsed > ( $max_time * 0.8 ) ) {
					LogViewer::log( 'BATCH_TIMEOUT product=' . $product_id . ' sent=' . $sent_count . ' approaching_max_execution_time' );
					break;
				}
			}
		}

		/**
		 * Fires after a batch of notifications is sent.
		 *
		 * @param int         $sent_count Number of emails sent.
		 * @param int         $product_id Product ID.
		 * @param \WC_Product $product    Product object.
		 */
		do_action( 'instock_notifier_after_batch_sent', $sent_count, $product_id, $product );

		LogViewer::log( 'BATCH_DONE product=' . $product_id . ' sent=' . $sent_count );

		/* If more subs remain, re-enqueue this product. */
		$remaining = Repository::get_active_for_product( $product_id, $variation_id, 1 );
		if ( empty( $remaining ) && $variation_id ) {
			/* Also check variation_id=0 subscriptions (generic "any variation"). */
			$remaining = Repository::get_active_for_product( $product_id, 0, 1 );
		}
		if ( ! empty( $remaining ) ) {
			NotificationQueue::enqueue( $product_id, $variation_id );
		}

		delete_transient( 'isn_processing_lock' );
		self::maybe_continue();
	}

	/**
	 * Get the WooCommerce BackInStockEmail instance.
	 *
	 * @return BackInStockEmail
	 */
	private static function get_email_instance() {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['ISN_Back_In_Stock'] ) ) {
			return $emails['ISN_Back_In_Stock'];
		}
		return new BackInStockEmail();
	}

	/**
	 * Schedule next batch if queue has more items.
	 *
	 * @return void
	 */
	private static function maybe_continue() {
		if ( NotificationQueue::has_items() ) {
			wp_schedule_single_event( time() + 60, NotificationQueue::CRON_HOOK );
		}
	}

	/**
	 * Daily cleanup of old notified subscriptions.
	 *
	 * @return void
	 */
	public static function cleanup() {
		$days    = absint( Options::get( 'cleanup_days' ) );
		$deleted = Repository::cleanup_old( $days );
		if ( $deleted > 0 ) {
			LogViewer::log( 'CLEANUP deleted=' . $deleted . ' older_than=' . $days . '_days' );
		}
	}
}
