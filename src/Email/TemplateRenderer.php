<?php
/**
 * Email template rendering with placeholder replacement.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders email subject and body.
 */
class TemplateRenderer {

	/**
	 * Render the email subject line.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public static function render_subject( $product ) {
		/* translators: %s: product name */
		$template = sprintf( __( '%s is back in stock!', 'instock-notifier-for-woocommerce' ), $product->get_name() );

		/**
		 * Filter the notification email subject.
		 *
		 * @param string      $subject Subject line.
		 * @param \WC_Product $product Product.
		 */
		return apply_filters( 'instock_notifier_email_subject', wp_strip_all_tags( $template ), $product );
	}

	/**
	 * Render the email body wrapped in WooCommerce email template.
	 *
	 * @param \WC_Product $product       Product object.
	 * @param string      $unsubscribe_url Unsubscribe URL.
	 * @return string
	 */
	public static function render_body( $product, $unsubscribe_url ) {
		$content = self::build_email_body( $product, $unsubscribe_url );

		/* Wrap in WooCommerce email template (header + footer + styles). */
		/* translators: %s: product name */
		$heading = sprintf( __( '%s is back in stock!', 'instock-notifier-for-woocommerce' ), $product->get_name() );

		/**
		 * Filter the email heading shown inside the WooCommerce template.
		 *
		 * @param string      $heading Email heading.
		 * @param \WC_Product $product Product.
		 */
		$heading = apply_filters( 'instock_notifier_email_heading', $heading, $product );

		$mailer = WC()->mailer();
		$body   = $mailer->wrap_message( $heading, $content );

		/* Apply WooCommerce inline styles (same as order emails). */
		$body = apply_filters( 'woocommerce_mail_content', $body ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		/**
		 * Filter the notification email body.
		 *
		 * @param string      $body    Email body HTML.
		 * @param \WC_Product $product Product.
		 */
		return apply_filters( 'instock_notifier_email_body', $body, $product );
	}

	/**
	 * Build the email body with translatable strings.
	 *
	 * @param \WC_Product $product         Product object.
	 * @param string      $unsubscribe_url Unsubscribe URL.
	 * @return string
	 */
	private static function build_email_body( $product, $unsubscribe_url ) {
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

		$stock_qty = $product->get_stock_quantity();
		if ( null === $stock_qty ) {
			$stock_qty = __( 'Available', 'instock-notifier-for-woocommerce' );
		}

		$product_url = $product->get_permalink();
		$site_name   = get_bloginfo( 'name' );
		$site_url    = home_url( '/' );

		$body = '';

		if ( $image_url ) {
			$body .= '<p style="text-align:center;"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $product->get_name() ) . '" style="max-width:200px;height:auto;" /></p>';
		}

		/* translators: 1: product name, 2: site URL, 3: site name */
		$body .= '<p>' . sprintf( __( 'Good news! <strong>%1$s</strong> is back in stock at <a href="%2$s">%3$s</a>.', 'instock-notifier-for-woocommerce' ), esc_html( $product->get_name() ), esc_url( $site_url ), esc_html( $site_name ) ) . '</p>';

		$body .= '<p style="text-align:center;margin:1.5em 0;"><a href="' . esc_url( $product_url ) . '" style="display:inline-block;padding:12px 24px;background-color:#7f54b3;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">';
		$body .= esc_html__( 'Shop Now', 'instock-notifier-for-woocommerce' );
		$body .= '</a></p>';

		/* translators: %s: stock quantity or "Available" */
		$body .= '<p>' . sprintf( __( 'Current stock: %s available.', 'instock-notifier-for-woocommerce' ), esc_html( $stock_qty ) ) . '</p>';

		/* translators: 1: site name, 2: unsubscribe URL */
		$body .= '<p style="font-size:12px;color:#888;">' . sprintf( __( 'You received this email because you subscribed to a back-in-stock notification on %1$s. <a href="%2$s">Unsubscribe</a>', 'instock-notifier-for-woocommerce' ), esc_html( $site_name ), esc_url( $unsubscribe_url ) ) . '</p>';

		/**
		 * Filter available email placeholders.
		 *
		 * Placeholders are applied to the body via str_replace, allowing
		 * developers to insert custom placeholder tokens via the
		 * instock_notifier_email_body filter and have them resolved here.
		 *
		 * @param array       $placeholders Key-value placeholder pairs.
		 * @param \WC_Product $product      Product.
		 */
		$placeholders = apply_filters( 'instock_notifier_placeholders', array(
			'{product_name}'    => $product->get_name(),
			'{product_url}'     => $product_url,
			'{product_image}'   => $image_url,
			'{stock_qty}'       => $stock_qty,
			'{unsubscribe_url}' => $unsubscribe_url,
			'{site_name}'       => $site_name,
			'{site_url}'        => $site_url,
		), $product );

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );
	}
}
