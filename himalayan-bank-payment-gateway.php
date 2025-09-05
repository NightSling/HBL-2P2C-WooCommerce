<?php

/**
 * Plugin Name: Himalayan Bank Payment Gateway
 * Description: Provides Himalayan Bank payment gateway integration for WooCommerce.
 * Version: 1.3.0
 * Author: Sushant Pangeni
 * Author URI: https://github.com/NightSling
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 8.0.0
 * WC tested up to: 9.8.1
 * Requires PHP: 8.2
 */

if (! defined('ABSPATH')) {
	exit;
}

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use HBL\HimalayanBank\Payment;
use HBL\HimalayanBank\SecurityData;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Payment.php';

// Add admin notice if WooCommerce is not active
add_action('admin_notices', 'hbl_woocommerce_missing_notice');
function hbl_woocommerce_missing_notice(): void
{
	if (! class_exists('WooCommerce')) {
		$message = sprintf(
			/* translators: %s: WooCommerce URL */
			esc_html__('Himalayan Bank Payment Gateway requires WooCommerce to be installed and active. You can download %s here.', 'hbl-himalayan-bank-payment-gateway'),
			'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
		);
		echo '<div class="error"><p>' . wp_kses_post($message) . '</p></div>';
	}
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'hbl_add_settings_link');
function hbl_add_settings_link($links)
{
	$settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=hbl_himalayan_bank_payment_gateway') . '">' .
		esc_html__('Settings', 'hbl-himalayan-bank-payment-gateway') . '</a>';
	array_unshift($links, $settings_link);

	return $links;
}

add_action('before_woocommerce_init', 'hbl_himalayan_bank_payment_gateway_before_woocommerce_hpos');
function hbl_himalayan_bank_payment_gateway_before_woocommerce_hpos()
{
	if (class_exists(FeaturesUtil::class)) {
		FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
	}
}

add_action('plugins_loaded', 'hbl_himalayan_bank_payment_gateway_init');
function hbl_himalayan_bank_payment_gateway_init(): void
{
	if (! class_exists('WC_Payment_Gateway')) {
		return;
	}

	load_plugin_textdomain(
		'hbl-himalayan-bank-payment-gateway',
		false,
		basename(__DIR__) . '/languages'
	);

	/**
	 * Represents the Himalayan Bank Payment Gateway for WooCommerce.
	 *
	 * This class extends the WooCommerce payment gateway class to implement a custom payment gateway for Himalayan Bank.
	 * It handles the initialization of the gateway settings, form fields, and processes payments.
	 * The gateway supports standard transactions, including handling of success, failure, and cancellation responses.
	 * It also integrates with WooCommerce's checkout process and order management system.
	 */
	class HBL_Himalayan_Bank_Payment_Gateway extends WC_Payment_Gateway
	{

		/**
		 * WC_Himalayan_Bank_Payment_Gateway constructor.
		 * Initializes the payment gateway by setting up its unique ID, method title, and description.
		 * It also initializes form fields and settings, and registers necessary hooks.
		 */
		public function __construct()
		{
			$this->id                 = 'hbl_himalayan_bank_payment_gateway';
			$this->method_title       = esc_html__('Himalayan Bank Payment Gateway', 'hbl-himalayan-bank-payment-gateway');
			$this->method_description = esc_html__(
				'Himalayan Bank payment gateway integration for WooCommerce.',
				'hbl-himalayan-bank-payment-gateway'
			);
			$this->has_fields         = false;
			$this->supports           = array('products');

			$this->init_form_fields();
			$this->init_settings();

			$this->title   = $this->get_option('title');
			$this->enabled = $this->get_option('enabled');

			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array($this, 'process_admin_options')
			);
			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_js'));
			add_action('woocommerce_review_order_before_submit', array($this, 'display_card_fee_message'));
			add_action('woocommerce_api_hbl_himalayan_bank_payment_gateway', array($this, 'check_ipn_response'));
		}

		public function get_icon(): string
		{
			$icon_url = plugins_url('icon.png', __FILE__);

			// Optional: add alt/title for accessibility
			return '<img src="' . esc_url($icon_url) . '" alt="Himalayan Bank" title="Himalayan Bank" style="max-height: 32px;" />';
		}

		/**
		 * Displays a message about card fees on the checkout page.
		 *
		 * This method checks if the card fee is enabled and the percentage is greater than 0.
		 * If so, it displays a message about the additional bank charges.
		 * The message can be customized through the gateway settings.
		 */
		public function display_card_fee_message(): void
		{
			if ($this->get_option('card_fee_enabled') === 'yes' && $this->get_option('card_fee_percentage') > 0) {
				$message = $this->get_option('card_fee_message');
				if (empty($message)) {
					$message = sprintf(
						esc_html__('Note: You will be charged an extra %s%% bank charges when using %s.', 'hbl-himalayan-bank-payment-gateway'),
						$this->get_option('card_fee_percentage'),
						$this->title
					);
				}
				echo '<div class="card-fee-message">' . esc_html($message) . '</div>';
			}
		}

		/**
		 * Enqueues the admin JavaScript file for the payment gateway settings page.
		 * This method is hooked to the 'admin_enqueue_scripts' action to load the necessary JavaScript
		 * for the admin settings page of the payment gateway.
		 */
		public function enqueue_admin_js(): void
		{
			wp_enqueue_script(
				'hbl-himalayan-bank-payment-gateway-admin',
				plugins_url('/assets/admin.js', __FILE__),
				array('jquery'),
				'1.0',
				true
			);
		}

		/**
		 * Initializes form fields for the admin settings page.
		 * Defines the structure and settings of the gateway's configuration options in the WooCommerce settings.
		 */
		public function init_form_fields(): void
		{
			$this->form_fields = array(
				'general'                      => array(
					'title' => esc_html__('General Settings', 'hbl-himalayan-bank-payment-gateway'),
					'type'  => 'title',
				),
				'title'                        => array(
					'title'       => esc_html__('Title', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'text',
					'description' => esc_html__(
						'This controls the title that the user sees during checkout.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => esc_html__('Himalayan Bank Payment Gateway', 'hbl-himalayan-bank-payment-gateway'),
					'desc_tip'    => false,
				),
				'currency'                     => array(
					'title'       => esc_html__('Currency', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'text',
					'description' => esc_html__(
						'Enter your currency. Example: USD, EUR, etc. Please contact Himalayan Bank for supported currencies.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => 'USD',
					'desc_tip'    => false,
				),
				'3d_secure'                    => array(
					'title'       => esc_html__('Enable/Disable 3D Secure', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'checkbox',
					'label'       => esc_html__('Enable 3D Secure', 'hbl-himalayan-bank-payment-gateway'),
					'description' => esc_html__(
						'Enable 3D Secure for this payment gateway. Turn it off for test mode.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => 'yes',
					'desc_tip'    => false,
				),
				'enabled_test_mode'            => array(
					'title'       => esc_html__('Enable/Disable Test Mode', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'checkbox',
					'label'       => esc_html__('Enable/Disable Test Mode', 'hbl-himalayan-bank-payment-gateway'),
					'description' => esc_html__(
						'Turn on to enable test mode for this payment gateway.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => 'yes',
					'desc_tip'    => false,
				),
				'encryption_keys'              => array(
					'title' => __('Encryption Keys (Production Only)', 'hbl-himalayan-bank-payment-gateway'),
					'type'  => 'title',
				),
				'merchant_id'                  => array(
					'title'       => esc_html__('Merchant ID', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'text',
					'description' => esc_html__('Enter your merchant ID.', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'desc_tip'    => false,
				),
				'encryption_key'               => array(
					'title'       => esc_html__('Encryption Key', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'text',
					'description' => esc_html__('Enter your encryption key.', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'desc_tip'    => false,
				),
				'access_token'                 => array(
					'title'       => esc_html__('Access Token', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'text',
					'description' => esc_html__('Enter your access token.', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'desc_tip'    => false,
				),
				'merchant_sign_private_key'    => array(
					'title'       => esc_html__('Merchant Signing Private Key', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__(
						'Enter your Merchant Signing Private Key.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => '',
					'desc_tip'    => false,
				),
				'merchant_decrypt_private_key' => array(
					'title'       => esc_html__('Merchant Encryption Private Key', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__(
						'Enter your Merchant Encryption Private Key.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => '',
					'desc_tip'    => false,
				),
				'paco_sign_public_key'         => array(
					'title'       => esc_html__('PACO Signing Public Key', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__('Enter your PACO Signing Public Key.', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'desc_tip'    => false,
				),
				'paco_encrypt_public_key'      => array(
					'title'       => esc_html__('PACO Encryption Public Key', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__(
						'Enter your PACO Encryption Public Key.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => '',
					'desc_tip'    => false,
				),
				'card_fee_settings'            => array(
					'title' => esc_html__('Card Fee Settings', 'hbl-himalayan-bank-payment-gateway'),
					'type'  => 'title',
				),
				'card_fee_enabled'             => array(
					'title'    => esc_html__('Enable Card Fee', 'hbl-himalayan-bank-payment-gateway'),
					'type'     => 'checkbox',
					'label'    => esc_html__('Enable card fee calculation', 'hbl-himalayan-bank-payment-gateway'),
					'default'  => 'no',
					'desc_tip' => false,
				),
				'card_fee_percentage'          => array(
					'title'             => esc_html__('Card Fee Percentage', 'hbl-himalayan-bank-payment-gateway'),
					'type'              => 'number',
					'description'       => esc_html__('Enter the card fee percentage to be added to the total amount', 'hbl-himalayan-bank-payment-gateway'),
					'default'           => '0',
					'custom_attributes' => array(
						'step' => '0.01',
						'min'  => '0',
						'max'  => '100'
					),
					'desc_tip'          => false,
				),
				'card_fee_message'             => array(
					'title'       => esc_html__('Card Fee Message', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__('Message to display about the card fee on checkout. Leave blank for default message.', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'desc_tip'    => false,
				),
				'messages'                     => array(
					'title' => esc_html__('Messages', 'hbl-himalayan-bank-payment-gateway'),
					'type'  => 'title',
				),
				'failure_message'              => array(
					'title'       => esc_html__('Failure Message', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__(
						'Message displayed on payment failure.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => esc_html__(
						'Oops! Something went wrong with your payment.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'desc_tip'    => false,
				),
				'cancel_message'               => array(
					'title'       => esc_html__('Cancellation Message', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'textarea',
					'description' => esc_html__(
						'Message displayed when payment is cancelled.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'default'     => esc_html__(
						'Payment cancelled. If you have any questions, please contact us.',
						'hbl-himalayan-bank-payment-gateway'
					),
					'desc_tip'    => false,
				),
				'redirect_pages'               => array(
					'title'       => esc_html__('Redirect Pages', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'title',
					'description' => esc_html__('Set custom pages for payment responses. Leave blank to use default WooCommerce pages.', 'hbl-himalayan-bank-payment-gateway'),
				),
				'success_page'                 => array(
					'title'       => esc_html__('Success Page', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'select',
					'description' => esc_html__('Page to redirect to after successful payment', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'options'     => $this->get_pages_list(),
					'desc_tip'    => true,
				),
				'failure_page'                 => array(
					'title'       => esc_html__('Failure Page', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'select',
					'description' => esc_html__('Page to redirect to after failed payment', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'options'     => $this->get_pages_list(),
					'desc_tip'    => true,
				),
				'cancel_page'                  => array(
					'title'       => esc_html__('Cancel Page', 'hbl-himalayan-bank-payment-gateway'),
					'type'        => 'select',
					'description' => esc_html__('Page to redirect to after cancelled payment', 'hbl-himalayan-bank-payment-gateway'),
					'default'     => '',
					'options'     => $this->get_pages_list(),
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Get list of pages for select options
		 */
		private function get_pages_list(): array
		{
			$pages         = get_pages();
			$pages_options = array(
				'' => esc_html__('Default', 'hbl-himalayan-bank-payment-gateway')
			);

			if ($pages) {
				foreach ($pages as $page) {
					$pages_options[$page->ID] = $page->post_title;
				}
			}

			return $pages_options;
		}

		/**
		 * Processes the payment and returns the result.
		 * Handles the payment submission to the payment gateway and updates the order status based on the response.
		 *
		 * @param int $order_id The ID of the order being processed.
		 *
		 * @return array An associative array containing the result of the payment process and the redirect URL.
		 */
		public function process_payment($order_id): array
		{
			$order = wc_get_order($order_id);

			$existing_url    = $order->get_meta('hbl_payment_url');
			$expiry_datetime = $order->get_meta('hbl_payment_expiry');
			if ($order->has_status('pending') && ! empty($existing_url) && ! empty($expiry_datetime)) {
				$now = new \DateTime('now', new \DateTimeZone('UTC'));
				$expiry = new \DateTime($expiry_datetime, new \DateTimeZone('UTC'));

				if ($expiry > $now) {
					return array(
						'result'   => 'success',
						'redirect' => $existing_url,
					);
				} else {
					$logger = wc_get_logger();
					$context = array('source' => 'hbl-himalayan-bank-payment-gateway');
					$logger?->info('Existing payment URL has expired. Generating a new payment URL.', $context);
					$order->delete_meta_data('hbl_payment_url');
					$order->delete_meta_data('hbl_payment_expiry');
				}
			}

			// Generate secure salt for payment verification
			$secure_salt = wp_generate_password(32, false);
			$order->update_meta_data('hbl_payment_secure_salt', $secure_salt);
			$order->save();

			$currency = wc_clean($this->get_option('currency'));
			$amount   = $order->get_total();
			if ($this->get_option('card_fee_enabled') === 'yes' && $this->get_option('card_fee_percentage') > 0) {
				$amount += ($amount * $this->get_option('card_fee_percentage')) / 100;
				$amount = round($amount, 2);
			}
			$threeD          = wc_clean($this->get_option('3d_secure')) === 'yes' ? 'Y' : 'N';
			$success_page_id = $this->get_option('success_page');
			$success_url     = $success_page_id ?
				add_query_arg(array(
					'order_id' => $order_id,
					'salt' => $secure_salt
				), esc_url(get_permalink($success_page_id))) :
				add_query_arg(array(
					'salt' => $secure_salt
				), $order->get_checkout_order_received_url());

			$redirect_url = wp_nonce_url(esc_url(wc_get_checkout_url()), 'payment_action', 'payment_nonce');

			$failure_page_id = $this->get_option('failure_page');
			$fail_url        = $failure_page_id ?
				add_query_arg(array(
					'action'   => 'fail',
					'order_id' => $order_id
				), esc_url(get_permalink($failure_page_id))) :
				add_query_arg('action', 'fail', $redirect_url);

			$cancel_page_id = $this->get_option('cancel_page');
			$cancel_url     = $cancel_page_id ?
				add_query_arg(array(
					'action'   => 'cancel',
					'order_id' => $order_id
				), esc_url(get_permalink($cancel_page_id))) :
				add_query_arg('action', 'cancel', $redirect_url);

			$backend_url = home_url('/wc-api/hbl_himalayan_bank_payment_gateway');
			$product_list = $this->create_order_items_array_for_payment($order_id);
			$fail_message = $this->get_option('failure_message');

			$order->update_status(
				'pending',
				esc_html__('Awaiting payment confirmation', 'hbl-himalayan-bank-payment-gateway')
			);

			try {
				$payment = new Payment();
				$request = $payment->ExecuteFormJose(
					$order_id,
					$currency,
					$amount,
					$threeD,
					$success_url,
					$fail_url,
					$cancel_url,
					$backend_url,
					$product_list
				);

				$result = json_decode($request);

				return array(
					'result'   => 'success',
					'redirect' => $result->response->Data->paymentPage->paymentPageURL,
				);
			} catch (Exception $e) {
				$logger  = wc_get_logger();
				$context = array('source' => 'hbl-himalayan-bank-payment-gateway');
				$logger?->error($e->getMessage(), $context);
				$logger?->error($e->getTraceAsString(), $context);

				wc_add_notice($fail_message, 'error');
				$order->update_status('failed');

				return array(
					'result'   => 'fail',
					'redirect' => $redirect_url,
				);
			}
		}

		/**
		 * Verify secure salt for payment confirmation
		 *
		 * @param int $order_id The order ID
		 * @param string $provided_salt The salt provided in the URL
		 * @return bool True if salt is valid
		 */
		private function verify_payment_salt($order_id, $provided_salt): bool
		{
			if (empty($provided_salt) || empty($order_id)) {
				return false;
			}

			$order = wc_get_order($order_id);
			if (!$order) {
				return false;
			}

			$stored_salt = $order->get_meta('hbl_payment_secure_salt');
			if (empty($stored_salt)) {
				return false;
			}

			// Use hash_equals for constant-time comparison to prevent timing attacks
			return hash_equals($stored_salt, $provided_salt);
		}

		/**
		 * Generates an array of items for the payment request.
		 * Prepares an array of order items, including product details and prices, formatted for the payment gateway.
		 *
		 * @param int $order_id The ID of the order for which items are being prepared.
		 *
		 * @return array An array of items included in the order, formatted for the payment gateway.
		 */
		private function create_order_items_array_for_payment($order_id): array
		{
			$order             = wc_get_order($order_id);
			$items             = $order->get_items();
			$order_items_array = [];

			/**
			 * @var WC_Order_Item_Product $item
			 */
			foreach ($items as $item) {
				$product     = $item->get_product();
				$amount      = $item->get_total();
				$amount_text = str_pad((string) ($amount * 100), 12, "0", STR_PAD_LEFT); // Convert to the required format

				$order_items_array[] = [
					"purchaseItemType"        => "ticket",
					"referenceNo"             => $product->get_id(),
					"purchaseItemDescription" => $product->get_name(),
					"purchaseItemPrice"       => [
						"amountText"    => $amount_text,
						"currencyCode"  => wc_clean($this->get_option('currency')),
						"decimalPlaces" => 2,
						"amount"        => $amount,
					],
					"subMerchantID"           => "string",
					"passengerSeqNo"          => 1,
				];
			}

			return $order_items_array;
		}

		/**
		 * Handle IPN (Instant Payment Notification) response from the payment gateway
		 * This method processes payment status updates sent by the gateway
		 */
		public function check_ipn_response(): void
		{
			$logger = wc_get_logger();
			$context = array('source' => 'hbl-himalayan-bank-payment-gateway-ipn');

			// Get the raw POST data
			$raw_data = file_get_contents('php://input');
			if (empty($raw_data)) {
				$logger->error('Empty body received in IPN', $context);
				$this->respond_to_ipn(400);
				return;
			}

			$logger->info('IPN received: ' . $raw_data, $context);

			try {
				// Try to decrypt the response using the Payment class
				$payment = new Payment();
				$decrypting_key = $payment->GetPrivateKey(SecurityData::get_merchant_decryption_private_key());
				$signature_verification_key = $payment->GetPublicKey(SecurityData::get_paco_signing_public_key());
				$decrypted_data = $payment->DecryptToken($raw_data, $decrypting_key, $signature_verification_key);
				$data = json_decode($decrypted_data, true);

				if (json_last_error() !== JSON_ERROR_NONE) {
					$logger->error('Invalid JSON in IPN after decryption: ' . json_last_error_msg(), $context);
					$this->respond_to_ipn(400);
					return;
				}

				$logger->info('Decrypted IPN data: ' . print_r($data, true), $context);
			} catch (Exception $e) {
				$logger->error('Decryption error: ' . $e->getMessage(), $context);
				// Try to process as plain JSON if decryption fails
				$data = json_decode($raw_data, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					$logger->error('Failed to parse as JSON: ' . json_last_error_msg(), $context);
					$this->respond_to_ipn(400);
					return;
				}
			}

			// Validate timestamps
			if (!$this->validate_ipn_timestamps($data)) {
				$this->respond_to_ipn(400);
				return;
			}

			// Extract payment result data
			$payment_result = $data['data']['paymentResult'] ?? null;
			if (!$payment_result) {
				$logger->error('Missing paymentResult in IPN', $context);
				$this->respond_to_ipn(400);
				return;
			}

			$order_no = $payment_result['orderNo'] ?? '';
			$payment_status = $payment_result['paymentStatusInfo']['paymentStatus'] ?? '';

			if (!$order_no || !$payment_status) {
				$logger->error('Missing orderNo or paymentStatus', $context);
				$this->respond_to_ipn(400);
				return;
			}

			// Get the order
			$order = wc_get_order($order_no);
			if (!$order) {
				$logger->error("Order not found: $order_no", $context);
				$this->respond_to_ipn(404);
				return;
			}

			// Extract card details if available
			$card_details = $payment_result['creditCardAuthenticatedDetails'] ?? [];
			$card_info = $this->extract_card_info($card_details);

			// Process payment status
			$this->process_payment_status($order, $payment_status, $card_info, $logger, $context);

			$this->respond_to_ipn(200, ['message' => 'OK']);
		}

		/**
		 * Extract card information from payment result
		 *
		 * @param array $card_details Card details from payment result
		 * @return string Formatted card information
		 */
		private function extract_card_info(array $card_details): string
		{
			if (empty($card_details)) {
				return '';
			}

			$card_number_masked = $card_details['cardNumber'] ?? 'N/A';
			$card_expiry = $card_details['cardExpiryMMYY'] ?? 'N/A';
			$card_holder = $card_details['cardHolderName'] ?? 'N/A';
			$issuer_country = $card_details['issuerBankCountry'] ?? 'N/A';

			$card_note = "\n\nCard Details:\n";
			$card_note .= "- Number (masked): $card_number_masked\n";
			$card_note .= "- Expiry: $card_expiry\n";
			$card_note .= "- Cardholder Name: $card_holder\n";
			$card_note .= "- Issuer Country: $issuer_country";

			return $card_note;
		}

		/**
		 * Process payment status and update order accordingly
		 *
		 * @param WC_Order $order The WooCommerce order
		 * @param string $payment_status Payment status from gateway
		 * @param string $card_info Card information
		 * @param WC_Logger $logger Logger instance
		 * @param array $context Logging context
		 */
		private function process_payment_status($order, $payment_status, $card_info, $logger, $context): void
		{
			switch ($payment_status) {
				case 'PCPS':
					$order->update_status('pending', 'Payment pre-stage: awaiting payer to complete payment.');
					break;

				case 'I':
					$order->update_status('processing', 'Payment initiated, authentication in progress.');
					break;

				case 'A':
					if (!$order->has_status('completed')) {
						$order->payment_complete();
						$order->add_order_note('Payment approved via Himalayan Bank Gateway IPN.' . $card_info);
						$logger->info("Payment approved for order {$order->get_id()}", $context);
					}
					break;

				case 'V':
				case 'E':
				case 'C':
					$status_messages = [
						'V' => 'Payment voided',
						'E' => 'Payment error',
						'C' => 'Payment cancelled'
					];
					$message = $status_messages[$payment_status] ?? "Payment status: $payment_status";
					$order->update_status('cancelled', $message);
					$logger->info("Payment cancelled/voided for order {$order->get_id()}: $payment_status", $context);
					break;

				case 'S':
					if (!$order->has_status('completed')) {
						$order->payment_complete();
						$order->add_order_note('Payment settled via Himalayan Bank Gateway IPN.' . $card_info);
						$logger->info("Payment settled for order {$order->get_id()}", $context);
					}
					break;

				case 'R':
					$order->update_status('refunded', 'Payment refunded via Himalayan Bank Gateway IPN.');
					$logger->info("Payment refunded for order {$order->get_id()}", $context);
					break;

				case 'P':
					$order->update_status('on-hold', 'Payment pending approval via Himalayan Bank Gateway IPN.');
					break;

				case 'F':
					$order->update_status('failed', 'Payment rejected via Himalayan Bank Gateway IPN.');
					$logger->info("Payment failed for order {$order->get_id()}", $context);
					break;

				default:
					$logger->error("Unknown payment status received: $payment_status", $context);
					break;
			}
		}

		/**
		 * Validate IPN timestamps to ensure message freshness and authenticity
		 *
		 * @param array $data IPN data
		 * @return bool True if timestamps are valid
		 */
		private function validate_ipn_timestamps(array $data): bool
		{
			$logger = wc_get_logger();
			$context = array('source' => 'hbl-himalayan-bank-payment-gateway-ipn');
			$now = time();

			// Validate issued at time (iat)
			if (isset($data['iat'])) {
				if (!is_numeric($data['iat'])) {
					$logger->error('Invalid iat in IPN', $context);
					return false;
				}
				// Message should not be older than 1 hour
				if ($now - $data['iat'] > 3600) {
					$logger->error('IPN message is too old (iat check)', $context);
					return false;
				}
			}

			// Validate expiration time (exp)
			if (isset($data['exp'])) {
				if (!is_numeric($data['exp'])) {
					$logger->error('Invalid exp in IPN', $context);
					return false;
				}
				// Message should not be expired
				if ($now > $data['exp']) {
					$logger->error('IPN message has expired (exp check)', $context);
					return false;
				}
			}

			return true;
		}

		/**
		 * Send response to IPN request
		 *
		 * @param int $status_code HTTP status code
		 * @param array $payload Response payload
		 */
		private function respond_to_ipn(int $status_code, array $payload = []): void
		{
			status_header($status_code);

			if ($status_code === 200) {
				wp_send_json_success($payload);
			} else {
				echo 'Error';
			}

			exit;
		}
	}

	function hbl_add_himalaya_bank_payment_gateway($methods)
	{
		$methods[] = 'HBL_Himalayan_Bank_Payment_Gateway';

		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'hbl_add_himalaya_bank_payment_gateway');
}

/**
 * Helper function to verify secure salt for payment confirmation
 *
 * @param int $order_id The order ID
 * @param string $provided_salt The salt provided in the URL
 * @return bool True if salt is valid
 */
function hbl_verify_payment_salt($order_id, $provided_salt): bool
{
	if (empty($provided_salt) || empty($order_id)) {
		return false;
	}

	$order = wc_get_order($order_id);
	if (!$order) {
		return false;
	}

	$stored_salt = $order->get_meta('hbl_payment_secure_salt');
	if (empty($stored_salt)) {
		return false;
	}

	// Use hash_equals for constant-time comparison to prevent timing attacks
	return hash_equals($stored_salt, $provided_salt);
}

add_action('template_redirect', 'hbl_himalayan_bank_payment_gateway_handle_payment_redirect');
/**
 * @throws WC_Data_Exception
 */
function hbl_himalayan_bank_payment_gateway_handle_payment_redirect(): void
{
	global $post;
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$order_id = isset($_GET['orderNo']) ? sanitize_text_field(wc_clean(wp_unslash($_GET['orderNo']))) : null;
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$transaction_id = isset($_GET['controllerInternalId']) ? sanitize_text_field(wc_clean(wp_unslash($_GET['controllerInternalId']))) : null;
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$action = isset($_GET['action']) ? sanitize_text_field(wc_clean(wp_unslash($_GET['action']))) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$payment_nonce = isset($_GET['payment_nonce']) ? sanitize_text_field(wc_clean(wp_unslash($_GET['payment_nonce']))) : null;
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$provided_salt = isset($_GET['salt']) ? sanitize_text_field(wc_clean(wp_unslash($_GET['salt']))) : null;

	$logger = wc_get_logger();
	$context = array('source' => 'hbl-himalayan-bank-payment-gateway');

	if ($action && $order_id && $payment_nonce && is_checkout() && ! wp_verify_nonce($payment_nonce, 'payment_action')) {
		wc_add_notice(esc_html__('Security check failed, please try again.', 'hbl-himalayan-bank-payment-gateway'), 'error');
		wp_safe_redirect(wc_get_checkout_url());
		exit;
	}

	// Handle successful payment confirmation with salt verification
	if ($order_id && $transaction_id && is_order_received_page()) {
		$order = wc_get_order($order_id);

		if ($order && $provided_salt) {
			// Verify the secure salt
			if (hbl_verify_payment_salt($order_id, $provided_salt)) {
				$order->update_status('processing', 'Payment confirmed with valid security token');
				$order->set_transaction_id($transaction_id);
				$order->add_order_note('Payment verification successful via secure salt validation');
				$order->save();
				$logger->info("Payment confirmed for order {$order_id} with valid salt", $context);
			} else {
				$logger->error("Invalid salt provided for order {$order_id}. Potential security breach.", $context);
				wc_add_notice(esc_html__('Payment verification failed. Please contact support.', 'hbl-himalayan-bank-payment-gateway'), 'error');
			}
		}
	}

	// Handle custom success page with salt verification
	$success_page_id = get_option('woocommerce_hbl_himalayan_bank_payment_gateway_settings')['success_page'];
	if ($order_id && $success_page_id !== '' && $post && $post->ID === (int) $success_page_id) {
		$order = wc_get_order($order_id);

		if ($order && $provided_salt) {
			// Verify the secure salt
			if (hbl_verify_payment_salt($order_id, $provided_salt)) {
				$order->update_status('processing', 'Payment confirmed via custom success page with valid security token');
				if ($transaction_id) {
					$order->set_transaction_id($transaction_id);
				}
				$order->add_order_note('Payment verification successful via custom success page');
				$order->save();
				$logger->info("Payment confirmed for order {$order_id} via custom success page with valid salt", $context);
			} else {
				$logger->error("Invalid salt provided for order {$order_id} on custom success page. Potential security breach.", $context);
				wc_add_notice(esc_html__('Payment verification failed. Please contact support.', 'hbl-himalayan-bank-payment-gateway'), 'error');
			}
		}
	}

	if (! empty($action) && is_checkout()) {

		if (! $order_id || ! $order = wc_get_order($order_id)) {
			return;
		}
		$settings = get_option('woocommerce_hbl_himalayan_bank_payment_gateway_settings');

		$failure_message = $settings['failure_message'] ? esc_html($settings['failure_message']) : esc_html__('Payment failed. Please try again.', 'hbl-himalayan-bank-payment-gateway');
		$cancel_message  = $settings['cancel_message'] ? esc_html($settings['cancel_message']) : esc_html__(
			'Payment was cancelled. If this was a mistake, please try again.',
			'hbl-himalayan-bank-payment-gateway'
		);

		if ('fail' === $action) {
			$order->update_status('failed');
			wc_add_notice($failure_message, 'error');
		} elseif ('cancel' === $action) {
			$order->update_status('cancelled');
			wc_add_notice($cancel_message, 'notice');
		}

		wp_safe_redirect(wc_get_checkout_url());
		exit;
	}
}
