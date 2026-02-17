<?php
/**
 * WooCommerce email class for back-in-stock notifications.
 *
 * @package InStockNotifier
 */

namespace InStockNotifier\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Back In Stock notification email.
 *
 * Extends WC_Email so it inherits WooCommerce email template styling,
 * appears in WooCommerce > Settings > Emails, and can be customized
 * by copying the template to the active theme.
 */
class BackInStockEmail extends \WC_Email {

	/**
	 * Product object.
	 *
	 * @var \WC_Product|null
	 */
	public $product_obj = null;

	/**
	 * Unsubscribe URL.
	 *
	 * @var string
	 */
	public $unsubscribe_url = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'isn_back_in_stock';
		$this->customer_email = true;
		$this->title          = __( 'Back In Stock', 'in-stock-notifier-for-woocommerce' );
		$this->description    = __( 'Sent to customers who subscribed for a back-in-stock notification when the product returns to stock.', 'in-stock-notifier-for-woocommerce' );

		$this->template_html  = 'emails/back-in-stock.php';
		$this->template_plain = 'emails/plain/back-in-stock.php';
		$this->template_base  = ISN_PATH . 'templates/';

		$this->placeholders = array(
			'{product_name}' => '',
			'{site_title}'   => $this->get_blogname(),
		);

		/* Default subject and heading. */
		$this->heading = __( '{product_name} is back in stock!', 'in-stock-notifier-for-woocommerce' );
		$this->subject = __( '{product_name} is back in stock!', 'in-stock-notifier-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '{product_name} is back in stock!', 'in-stock-notifier-for-woocommerce' );
	}

	/**
	 * Get default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( '{product_name} is back in stock!', 'in-stock-notifier-for-woocommerce' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param \WC_Product $product         Product object.
	 * @param string      $recipient_email Recipient email address.
	 * @param string      $unsubscribe_url Unsubscribe URL.
	 * @return bool Whether the email was sent.
	 */
	public function trigger( $product, $recipient_email, $unsubscribe_url ) {
		$this->setup_locale();

		$this->product_obj    = $product;
		$this->unsubscribe_url = $unsubscribe_url;
		$this->recipient      = $recipient_email;

		$this->placeholders['{product_name}'] = $product->get_name();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			$this->restore_locale();
			return false;
		}

		$result = $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();

		return $result;
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html() {
		$product = $this->get_product_for_render();

		$this->placeholders['{product_name}'] = $product->get_name();

		return wc_get_template_html(
			$this->template_html,
			array(
				'product'         => $product,
				'email_heading'   => $this->get_heading(),
				'unsubscribe_url' => $this->unsubscribe_url ? $this->unsubscribe_url : '#',
				'sent_to_admin'   => false,
				'plain_text'      => false,
				'email'           => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain text.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		$product = $this->get_product_for_render();

		$this->placeholders['{product_name}'] = $product->get_name();

		return wc_get_template_html(
			$this->template_plain,
			array(
				'product'         => $product,
				'email_heading'   => $this->get_heading(),
				'unsubscribe_url' => $this->unsubscribe_url ? $this->unsubscribe_url : '#',
				'sent_to_admin'   => false,
				'plain_text'      => true,
				'email'           => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get product for rendering. Falls back to a sample product for previews.
	 *
	 * @return \WC_Product
	 */
	private function get_product_for_render() {
		if ( $this->product_obj ) {
			return $this->product_obj;
		}

		/* Preview mode: find any published product for sample data. */
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 1,
			)
		);

		if ( ! empty( $products ) ) {
			return $products[0];
		}

		/* Absolute fallback: create a dummy product object. */
		$dummy = new \WC_Product();
		$dummy->set_name( __( 'Sample Product', 'in-stock-notifier-for-woocommerce' ) );
		return $dummy;
	}

	/**
	 * Initialise settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		/* translators: %s: list of available placeholders */
		$placeholder_text = sprintf( __( 'Available placeholders: %s', 'in-stock-notifier-for-woocommerce' ), '<code>{product_name}, {site_title}</code>' );

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'in-stock-notifier-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'in-stock-notifier-for-woocommerce' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'in-stock-notifier-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'in-stock-notifier-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'in-stock-notifier-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'in-stock-notifier-for-woocommerce' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}
