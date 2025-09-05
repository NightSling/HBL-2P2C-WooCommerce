<?php

namespace HBL\HimalayanBank;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Handles payment processing for transactions.
 *
 * This class is responsible for preparing and executing payment requests,
 * including standard payments and payments with JOSE (JSON Object Signing and Encryption) standards.
 * It utilizes the Guzzle HTTP client for communication with the payment API.
 */
class Payment extends ActionRequest
{

	/**
	 * Executes a JOSE payment transaction with form data.
	 *
	 * This method prepares the JOSE payment request with dynamic parameters provided through the method arguments,
	 * including transaction amount, currency, and URLs for various transaction outcomes. It handles the encryption
	 * and signing of the request, as well as the decryption and verification of the response. Utilizes Guzzle for the API call.
	 *
	 * @param string $orderNo The order number for the transaction.
	 * @param string $curr The currency code for the transaction amount.
	 * @param float $amt The transaction amount.
	 * @param string $threeD Flag indicating if 3D Secure is requested.
	 * @param string $success_url URL to redirect to on transaction success.
	 * @param string $failed_url URL to redirect to on transaction failure.
	 * @param string $cancel_url URL to redirect to on transaction cancellation.
	 * @param string $backend_url Backend URL for server-to-server communication.
	 * @param array $product_detail Details of the product being purchased.
	 *
	 * @return string The decrypted response body from the payment API.
	 * @throws GuzzleException If there is an error in the HTTP request.
	 * @throws Exception If there is an error in processing the JOSE payload.
	 */
	public function ExecuteFormJose(
		$orderNo,
		$curr,
		$amt,
		$threeD,
		$success_url,
		$failed_url,
		$cancel_url,
		$backend_url,
		$product_detail
	): string {
		$now = Carbon::now();

		$productDescription = apply_filters('hbl_himalayan_bank_product_description', "desc for '$orderNo'", $orderNo);
		if (empty($product_detail)) {
			$product_detail = [
				[
					"purchaseItemType"        => "ticket",
					"referenceNo"             => "2322460376026",
					"purchaseItemDescription" => "Bundled insurance",
					"purchaseItemPrice"       => [
						"amountText"    => "000000000100",
						"currencyCode"  => "NPR",
						"decimalPlaces" => 2,
						"amount"        => 1
					],
					"subMerchantID"           => "string",
					"passengerSeqNo"          => 1
				]
			];
		} 
		$request = [
			"apiRequest"                => [
				"requestMessageID" => $this->Guid(),
				"requestDateTime"  => $now->utc()->format('Y-m-d\TH:i:s.v\Z'),
				"language"         => "en-US",
			],
			"officeId"                  => SecurityData::get_merchant_id(),
			"orderNo"                   => "$orderNo",
			"productDescription"        => $productDescription,
			/* "paymentType"               => "CC", */
			"paymentCategory"           => "ECOM",
			"preferredPaymentTypes" => [
				"CC",
				"CC-VI",
				"CC-CA",
				"CC-AX",
				"CC-UP",
				"CC-JC",
				"QR",
				"WALLET",
				"IMBANK"
			],
			"autoRedirectDelayTimer" => 3,
			/* "storeCardDetails"          => [ */
			/* 	"storeCardFlag"      => "N", */
			/* 	"storedCardUniqueID" => "{{guid}}", */
			/* ], */
			/* "installmentPaymentDetails" => [ */
			/* 	"ippFlag"           => "N", */
			/* 	"installmentPeriod" => 0, */
			/* 	"interestType"      => null, */
			/* ], */
			/* "mcpFlag"                   => "N", */
			"request3dsFlag"            => $threeD,
			"transactionAmount"         => [
				"amountText"    => str_pad(($amt == null ? 0 : $amt) * 100, 12, "0", STR_PAD_LEFT),
				"currencyCode"  => $curr,
				"decimalPlaces" => 2,
				"amount"        => $amt,
			],
			"notificationURLs"          => [
				"confirmationURL" => $success_url,
				"failedURL"       => $failed_url,
				"cancellationURL" => $cancel_url,
				"backendURL"      => $backend_url,
			],
			/* "deviceDetails"             => [ */
			/* 	"browserIp"        => "1.0.0.1", */
			/* 	"browser"          => "Postman Browser", */
			/* 	"browserUserAgent" => "PostmanRuntime/7.26.8 - not from header", */
			/* 	"mobileDeviceFlag" => "N", */
			/* ], */
			"purchaseItemize"             => $product_detail,
			"customFieldList"           => [
				[
					"fieldName"  => "TestField",
					"fieldValue" => "This is test",
				],
			],
		];

		$payload = [
			"request"       => $request,
			"iss"           => SecurityData::get_access_token(),
			"aud"           => "PacoAudience",
			"CompanyApiKey" => SecurityData::get_access_token(),
			"iat"           => $now->unix(),
			"nbf"           => $now->unix(),
			"exp"           => $now->addHour()->unix(),
		];

		$stringPayload = wp_json_encode($payload);
		$signingKey    = $this->GetPrivateKey(SecurityData::get_merchant_signing_private_key());
		$encryptingKey = $this->GetPublicKey(SecurityData::get_paco_encryption_public_key());
		$logger        = wc_get_logger();
		$context       = array('source' => 'hbl-himalayan-bank-payment-gateway');
		$logger?->info('Payload: ' . $stringPayload, $context);

		$body = $this->EncryptPayload($stringPayload, $signingKey, $encryptingKey);

		//third-party http client https://github.com/guzzle/guzzle
		$response = $this->client->post('api/1.0/Payment/prePaymentUi', [
			'headers' => [
				'Accept'        => 'application/jose',
				'CompanyApiKey' => SecurityData::get_access_token(),
				'Content-Type'  => 'application/jose; charset=utf-8',
			],
			'body'    => $body,
		]);

		$token                    = $response->getBody()->getContents();
		$decryptingKey            = $this->GetPrivateKey(SecurityData::get_merchant_decryption_private_key());
		$signatureVerificationKey = $this->GetPublicKey(SecurityData::get_paco_signing_public_key());

		return $this->DecryptToken($token, $decryptingKey, $signatureVerificationKey);
	}
}
