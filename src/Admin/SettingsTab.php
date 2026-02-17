<?php
/**
 * Settings tab using WordPress Settings API.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InStockNotifier\Support\Options;

/**
 * Renders and registers the settings form.
 */
class SettingsTab {

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	public static function render() {
		$opts = Options::get_all();

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'instock-notifier-for-woocommerce' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'isn_settings_group' );

		/* ── General ────────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'General', 'instock-notifier-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::checkbox_row( 'enabled', __( 'Enable Notifications', 'instock-notifier-for-woocommerce' ), $opts );
		self::checkbox_row( 'form_position_enabled', __( 'Auto-place Form on Product Pages', 'instock-notifier-for-woocommerce' ), $opts, __( 'Uncheck to use the [instock_notifier] shortcode only.', 'instock-notifier-for-woocommerce' ) );
		self::checkbox_row( 'quantity_field_enabled', __( 'Show Quantity Field', 'instock-notifier-for-woocommerce' ), $opts );
		self::text_row( 'button_text', __( 'Button Text', 'instock-notifier-for-woocommerce' ), $opts );

		echo '</tbody></table>';

		/* ── GDPR ───────────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'GDPR / Consent', 'instock-notifier-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::checkbox_row( 'gdpr_enabled', __( 'Require GDPR Consent', 'instock-notifier-for-woocommerce' ), $opts );
		self::textarea_row( 'gdpr_text', __( 'Consent Text', 'instock-notifier-for-woocommerce' ), $opts );

		echo '</tbody></table>';

		/* ── Messages ───────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Messages', 'instock-notifier-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::text_row( 'success_message', __( 'Success Message', 'instock-notifier-for-woocommerce' ), $opts );
		self::text_row( 'already_subscribed_msg', __( 'Already Subscribed Message', 'instock-notifier-for-woocommerce' ), $opts );

		echo '</tbody></table>';

		/* ── Performance ────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Performance', 'instock-notifier-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::number_row( 'batch_size', __( 'Batch Size', 'instock-notifier-for-woocommerce' ), $opts, __( 'Emails per cron run (1-500).', 'instock-notifier-for-woocommerce' ) );
		self::number_row( 'throttle_seconds', __( 'Throttle (seconds)', 'instock-notifier-for-woocommerce' ), $opts, __( 'Delay between each email (0 = no delay).', 'instock-notifier-for-woocommerce' ) );
		self::number_row( 'rate_limit_per_ip', __( 'Rate Limit per IP', 'instock-notifier-for-woocommerce' ), $opts, __( 'Max subscriptions per IP per hour.', 'instock-notifier-for-woocommerce' ) );

		echo '</tbody></table>';

		/* ── Advanced ───────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Advanced', 'instock-notifier-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::number_row( 'cleanup_days', __( 'Cleanup After (days)', 'instock-notifier-for-woocommerce' ), $opts, __( 'Delete notified subscriptions older than this.', 'instock-notifier-for-woocommerce' ) );
		self::checkbox_row( 'disable_logging', __( 'Disable Logging', 'instock-notifier-for-woocommerce' ), $opts );
		self::checkbox_row( 'cleanup_on_uninstall', __( 'Remove Data on Uninstall', 'instock-notifier-for-woocommerce' ), $opts, __( 'Delete all plugin data when the plugin is deleted.', 'instock-notifier-for-woocommerce' ) );
		self::textarea_row( 'custom_css', __( 'Custom CSS', 'instock-notifier-for-woocommerce' ), $opts, __( 'Additional CSS for the notification form.', 'instock-notifier-for-woocommerce' ) );

		echo '</tbody></table>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Render a checkbox row.
	 *
	 * @param string               $key  Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts Current options.
	 * @param string               $desc Description text.
	 * @return void
	 */
	private static function checkbox_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '0';

		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', $value, false ) . ' /> ';
		if ( $desc ) {
			echo esc_html( $desc );
		}
		echo '</label>';
		echo '</td></tr>';
	}

	/**
	 * Render a text input row.
	 *
	 * @param string               $key   Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $desc  Description text.
	 * @return void
	 */
	private static function text_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '';

		echo '<tr><th scope="row"><label for="isn_' . esc_attr( $key ) . '">';
		echo esc_html( $label );
		echo '</label></th><td>';
		echo '<input type="text" id="isn_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a number input row.
	 *
	 * @param string               $key   Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $desc  Description text.
	 * @return void
	 */
	private static function number_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '0';

		echo '<tr><th scope="row"><label for="isn_' . esc_attr( $key ) . '">';
		echo esc_html( $label );
		echo '</label></th><td>';
		echo '<input type="number" id="isn_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text" min="0" />';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a textarea row.
	 *
	 * @param string               $key   Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $desc  Description text.
	 * @return void
	 */
	private static function textarea_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '';

		echo '<tr><th scope="row"><label for="isn_' . esc_attr( $key ) . '">';
		echo esc_html( $label );
		echo '</label></th><td>';
		echo '<textarea id="isn_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text">';
		echo esc_textarea( $value );
		echo '</textarea>';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}
}
