<?php
/**
 * Dashboard tab with stats cards and top products.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Subscription\Repository;
use InStockNotifier\Stock\NotificationQueue;
use InStockNotifier\Logging\LogViewer;

/**
 * Renders the Dashboard admin tab.
 */
class DashboardTab {

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public static function render() {
		self::handle_manual_send();

		if ( ! empty( $_GET['isn_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Notifications queued. They will be sent shortly via cron.', 'instock-notifier-for-woocommerce' );
			echo '</p></div>';
		}

		$counts = Repository::count_by_status();
		$total  = $counts['active'] + $counts['notified'] + $counts['unsubscribed'];

		echo '<div class="isn-stats-cards">';
		self::stat_card( __( 'Active', 'instock-notifier-for-woocommerce' ), $counts['active'], '#0073aa' );
		self::stat_card( __( 'Notified', 'instock-notifier-for-woocommerce' ), $counts['notified'], '#46b450' );
		self::stat_card( __( 'Unsubscribed', 'instock-notifier-for-woocommerce' ), $counts['unsubscribed'], '#dc3232' );
		self::stat_card( __( 'Total', 'instock-notifier-for-woocommerce' ), $total, '#666' );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Top Products by Active Subscriptions', 'instock-notifier-for-woocommerce' ) . '</h2>';

		$top = Repository::top_products( 10 );
		if ( empty( $top ) ) {
			echo '<p>' . esc_html__( 'No active subscriptions yet.', 'instock-notifier-for-woocommerce' ) . '</p>';
			return;
		}

		// Batch-load parent products to avoid N+1 queries.
		$product_ids = array_map( function ( $row ) { return absint( $row->product_id ); }, $top );
		$products_list = wc_get_products( array( 'include' => $product_ids, 'limit' => -1 ) );
		$product_map = array();
		foreach ( $products_list as $isn_product ) {
			$product_map[ $isn_product->get_id() ] = $isn_product;
		}

		// Batch-load variations where needed.
		$variation_ids = array();
		foreach ( $top as $row ) {
			if ( absint( $row->variation_id ) > 0 ) {
				$variation_ids[] = absint( $row->variation_id );
			}
		}
		$variation_map = array();
		if ( ! empty( $variation_ids ) ) {
			$variations_list = wc_get_products( array( 'include' => $variation_ids, 'type' => 'variation', 'limit' => -1 ) );
			foreach ( $variations_list as $isn_variation ) {
				$variation_map[ $isn_variation->get_id() ] = $isn_variation;
			}
		}

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Product', 'instock-notifier-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Variation', 'instock-notifier-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Subscribers', 'instock-notifier-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Stock Status', 'instock-notifier-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'instock-notifier-for-woocommerce' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $top as $row ) {
			$product      = isset( $product_map[ $row->product_id ] ) ? $product_map[ $row->product_id ] : null;
			$name         = $product ? $product->get_name() : ( '#' . $row->product_id );
			$edit         = $product ? get_edit_post_link( $row->product_id ) : '';
			$variation_id = absint( $row->variation_id );

			// Use variation stock status when a specific variation is tracked.
			if ( $variation_id > 0 && isset( $variation_map[ $variation_id ] ) ) {
				$stock_raw = $variation_map[ $variation_id ]->get_stock_status();
			} elseif ( $product ) {
				$stock_raw = $product->get_stock_status();
			} else {
				$stock_raw = '';
			}
			$stock = self::stock_label( $stock_raw );

			echo '<tr>';
			echo '<td>';
			if ( $edit ) {
				echo '<a href="' . esc_url( $edit ) . '">' . esc_html( $name ) . '</a>';
			} else {
				echo esc_html( $name );
			}
			echo '</td>';
			echo '<td>';
			if ( $variation_id > 0 && isset( $variation_map[ $variation_id ] ) ) {
				$attrs = $variation_map[ $variation_id ]->get_attributes();
				echo esc_html( implode( ', ', array_filter( $attrs ) ) );
				echo ' <small>(#' . absint( $variation_id ) . ')</small>';
			} elseif ( $variation_id > 0 ) {
				echo '#' . absint( $variation_id );
			} else {
				echo '—';
			}
			echo '</td>';
			echo '<td><strong>' . absint( $row->sub_count ) . '</strong></td>';
			echo '<td>' . esc_html( $stock ) . '</td>';
			echo '<td>';
			$send_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'         => AdminPage::PAGE_SLUG,
						'tab'          => 'dashboard',
						'isn_action'   => 'manual_send',
						'product_id'   => $row->product_id,
						'variation_id' => $row->variation_id,
					),
					admin_url( 'admin.php' )
				),
				'isn_manual_send'
			);
			echo '<a href="' . esc_url( $send_url ) . '" class="button button-small" onclick="return confirm(\'' . esc_js( __( 'Send notifications to all active subscribers for this product?', 'instock-notifier-for-woocommerce' ) ) . '\');">';
			echo esc_html__( 'Send Notifications', 'instock-notifier-for-woocommerce' );
			echo '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Handle manual send action.
	 *
	 * @return void
	 */
	private static function handle_manual_send() {
		if ( ! isset( $_GET['isn_action'] ) || 'manual_send' !== $_GET['isn_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		check_admin_referer( 'isn_manual_send' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'instock-notifier-for-woocommerce' ) );
		}

		$product_id   = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$variation_id = isset( $_GET['variation_id'] ) ? absint( $_GET['variation_id'] ) : 0;

		if ( ! $product_id ) {
			return;
		}

		NotificationQueue::enqueue( $product_id, $variation_id );
		LogViewer::log( 'MANUAL_SEND queued product_id=' . $product_id . ' variation_id=' . $variation_id );

		$redirect = add_query_arg(
			array(
				'page'         => AdminPage::PAGE_SLUG,
				'tab'          => 'dashboard',
				'isn_sent'     => '1',
				'product_id'   => $product_id,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render a stat card.
	 *
	 * @param string $label Card label.
	 * @param int    $value Card value.
	 * @param string $color Border color.
	 * @return void
	 */
	private static function stat_card( $label, $value, $color ) {
		echo '<div class="isn-stat-card" style="border-left-color:' . esc_attr( $color ) . ';">';
		echo '<div class="isn-stat-value">' . absint( $value ) . '</div>';
		echo '<div class="isn-stat-label">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	/**
	 * Convert WooCommerce stock status slug to readable label.
	 *
	 * @param string $status Raw stock status.
	 * @return string
	 */
	private static function stock_label( $status ) {
		$labels = array(
			'instock'     => __( 'In stock', 'instock-notifier-for-woocommerce' ),
			'outofstock'  => __( 'Out of stock', 'instock-notifier-for-woocommerce' ),
			'onbackorder' => __( 'On backorder', 'instock-notifier-for-woocommerce' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
