<?php
/**
 * Uninstall handler - cleans up plugin data on deletion.
 *
 * @package BeltoftInStockNotifier
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
delete_option( 'isn_pending_queue' ); /* Legacy option from pre-1.0.8 queue. */

/* Remove cron events. */
wp_clear_scheduled_hook( 'isn_daily_cleanup' );

/* Unschedule all Action Scheduler actions for this plugin. */
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'isn_send_notification' );
}

/* Remove legacy log files if they exist (from pre-1.0.8 installs). */
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
