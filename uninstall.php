<?php
/**
 * Uninstall handler - cleans up plugin data on deletion.
 *
 * @package InStockNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$instock_notifier_options = get_option( 'isn_options', array() );

if ( ! is_array( $instock_notifier_options ) || empty( $instock_notifier_options['cleanup_on_uninstall'] ) || '1' !== $instock_notifier_options['cleanup_on_uninstall'] ) {
	return;
}

/* Drop the subscriptions table. */
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}isn_subscriptions" );

/* Delete options. */
delete_option( 'isn_options' );
delete_option( 'isn_db_version' );
delete_option( 'isn_pending_queue' );

/* Remove cron events. */
wp_clear_scheduled_hook( 'isn_send_notifications' );
wp_clear_scheduled_hook( 'isn_daily_cleanup' );

/* Remove log files using WP_Filesystem. */
if ( ! function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();
global $wp_filesystem;
if ( $wp_filesystem ) {
	$instock_notifier_upload  = wp_upload_dir();
	$instock_notifier_log_dir = trailingslashit( $instock_notifier_upload['basedir'] ) . 'isn-logs/';
	if ( $wp_filesystem->is_dir( $instock_notifier_log_dir ) ) {
		$wp_filesystem->delete( $instock_notifier_log_dir, true );
	}
}
