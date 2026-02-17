<?php
/**
 * File-based logging and admin log viewer.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;

/**
 * Handles writing log entries and rendering the log viewer in admin.
 */
class LogViewer {

	const MAX_SIZE = 2097152; // 2 MiB

	/**
	 * Whether we already checked trim this request.
	 *
	 * @var bool
	 */
	private static $trimmed_this_request = false;

	/**
	 * Cached log dir path.
	 *
	 * @var string|null
	 */
	private static $log_dir_cache = null;

	/**
	 * Initialize the log directory.
	 *
	 * @return void
	 */
	public static function init() {
		$dir = self::log_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			self::write_htaccess( $htaccess );
		}
		/* Blank index.php to prevent directory listing on non-Apache servers. */
		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			self::write_index( $index );
		}
	}

	/**
	 * Write the index.php guard file via WP_Filesystem.
	 *
	 * @param string $path Full path to index.php.
	 * @return void
	 */
	private static function write_index( $path ) {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		if ( $wp_filesystem ) {
			$wp_filesystem->put_contents( $path, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
		}
	}

	/**
	 * Write the .htaccess file to protect logs (one-time setup).
	 *
	 * @param string $path Full path to .htaccess file.
	 * @return void
	 */
	private static function write_htaccess( $path ) {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		if ( $wp_filesystem ) {
			$wp_filesystem->put_contents( $path, "Deny from all\n", FS_CHMOD_FILE );
		}
	}

	/**
	 * Get the log directory path (cached).
	 *
	 * @return string
	 */
	public static function log_dir() {
		if ( null === self::$log_dir_cache ) {
			$upload             = wp_upload_dir();
			self::$log_dir_cache = trailingslashit( $upload['basedir'] ) . 'isn-logs/';
		}
		return self::$log_dir_cache;
	}

	/**
	 * Get the log file path.
	 *
	 * @return string
	 */
	public static function log_file() {
		return self::log_dir() . 'isn-log.txt';
	}

	/**
	 * Write a log entry. Uses direct file append for performance.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public static function log( $message ) {
		if ( Options::get( 'disable_logging' ) === '1' ) {
			return;
		}

		$file = self::log_file();

		/* Trim at most once per request to avoid repeated filesize() calls. */
		if ( ! self::$trimmed_this_request ) {
			self::maybe_trim( $file );
			self::$trimmed_this_request = true;
		}

		$line = '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Trim the log file if it exceeds the max size.
	 * Uses direct file ops for performance on this hot path.
	 *
	 * @param string $file Full file path.
	 * @return void
	 */
	private static function maybe_trim( $file ) {
		if ( ! file_exists( $file ) ) {
			return;
		}
		$size = filesize( $file );
		if ( false === $size || $size < self::MAX_SIZE ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			return;
		}
		$half = (int) ( strlen( $contents ) / 2 );
		$pos  = strpos( $contents, "\n", $half );
		if ( false !== $pos ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, substr( $contents, $pos + 1 ), LOCK_EX );
		}
	}

	/**
	 * Clear the log file. Admin-only, uses WP_Filesystem.
	 *
	 * @return void
	 */
	public static function clear() {
		$file = self::log_file();
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		if ( $wp_filesystem && $wp_filesystem->exists( $file ) ) {
			$wp_filesystem->put_contents( $file, '', FS_CHMOD_FILE );
		}
	}

	/**
	 * Render the log viewer in admin.
	 *
	 * @return void
	 */
	public static function render() {
		if ( isset( $_POST['isn_clear_logs'] ) && check_admin_referer( 'isn_clear_logs_action' ) && current_user_can( 'manage_woocommerce' ) ) {
			self::clear();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared.', 'instock-notifier-for-woocommerce' ) . '</p></div>';
		}

		$disabled = Options::get( 'disable_logging' ) === '1';
		if ( $disabled ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Logging is currently disabled in settings.', 'instock-notifier-for-woocommerce' ) . '</p></div>';
		}

		$file    = self::log_file();
		$content = '';
		if ( file_exists( $file ) ) {
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( $wp_filesystem ) {
				$content = $wp_filesystem->get_contents( $file );
			}
		}

		echo '<div class="isn-log-viewer">';
		echo '<form method="post">';
		wp_nonce_field( 'isn_clear_logs_action' );
		echo '<p>';
		echo '<button type="submit" name="isn_clear_logs" value="1" class="button">';
		echo esc_html__( 'Clear Logs', 'instock-notifier-for-woocommerce' );
		echo '</button>';
		echo '</p>';
		echo '</form>';
		echo '<pre class="isn-log-content" style="max-height:400px;overflow:auto;background:#1d2327;color:#f0f0f1;padding:12px;font-size:13px;">';
		if ( ! empty( $content ) ) {
			echo esc_html( $content );
		} else {
			echo esc_html__( 'No log entries.', 'instock-notifier-for-woocommerce' );
		}
		echo '</pre>';
		echo '</div>';
	}
}
