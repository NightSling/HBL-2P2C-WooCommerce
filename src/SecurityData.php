<?php

namespace HBL\HimalayanBank;

class SecurityData {

	/**
	 * Merchant ID for test mode.
	 *
	 * @var string
	 */
	public static string $MerchantId = "9103332177";

	/**
	 * JWE Key Id.
	 *
	 * @var string
	 */
	public static string $EncryptionKeyId = "7664a2ed0dee4879bdfca0e8ce1ac313";

	/**
	 * Access Token.
	 *
	 * @var string
	 */
	public static string $AccessToken = "d64fcd5489eb42bebe46c5fcd0cf19be";

	/**
	 * Token Type - Used in JWS and JWE header.
	 *
	 * @var string
	 */
	public static string $TokenType = "JWT";

	/**
	 * JWS (JSON Web Signature) Signature Algorithm - This parameter identifies the cryptographic algorithm used to
	 * secure the JWS.
	 *
	 * @var string
	 */
	public static string $JWSAlgorithm = "PS256";

	/**
	 * JWE (JSON Web Encryption) Key Encryption Algorithm - This parameter identifies the cryptographic algorithm
	 * used to secure the JWE.
	 *
	 * @var string
	 */
	public static string $JWEAlgorithm = "RSA-OAEP";

	/**
	 * JWE (JSON Web Encryption) Content Encryption Algorithm - This parameter identifies the content encryption
	 * algorithm used on the plaintext to produce the encrypted ciphertext.
	 *
	 * @var string
	 */
	public static string $JWEEncrptionAlgorithm = "A128CBC-HS256";

	/**
	 * Merchant Signing Private Key is used to cryptographically sign and create the request JWS.
	 *
	 * @var string
	 */
	public static string $MerchantSigningPrivateKey = "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";

	/**
	 * PACO Encryption Public Key is used to cryptographically encrypt and create the request JWE.
	 *
	 * @var string
	 */
	public static string $PacoEncryptionPublicKey = "MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAviq4wrTmVMkRHouiHLUonJ1d6ss6nNreJ0JWpLwmTwAM7l35g8AFIvE8PqwWevtjuil9JZ1T1zwQTP8aM3s5/RzX5yFIhec/O14jib7Nmi4jACeJqDlHsnYzeCPw8WOhgmxWKHcORNLpn68jgnhLrKwh3Mooz/hXtIwGuNe/pYU7i/QaiuOjtmIcQ3yxJWjiHsllaogobZjbwMzwhp1fJ6ELmZp0FJvDrE8dn4UU9yzPFNzQ4gJzJAS/JKLXjfDw5mDQdw80vbzYuxksU0bc/3+DwY6hqaVJsP2AST7dCTR1wYzevzPxp0HMDmz1Ia/hSrmTPRhSa0qvxHMriVHUJvJeLTNI3cWM0RI9ukR7/v6vcf8ZwOZ+u7w4YfLpPCQFN7zGUN9Hho0pWBVYOstqsF5h/ZgBOlEHgSYY3CJdscV1+vKUvmFPiwkOdVxhc571RX56o+V71ZIGjXeYeqd3KNnND1JNsOn4hRPbk8Cl0e8CfZnEePfqtbFQGrzRU3GvSXscMb51TlvZu9i0toJdIJ4DiOCkUlB2sDI4x7N9ROOEbAD8uv68/jZqTM2paUNRN7Xvaa2LUCis3acadiyLt0tpuOT0sY2OejhLJshwNfTfc67gdtCJ3diddZWkXYpBgkMhuVj3TSx85sUklbGGJkzkwNsC0JhMSo7ZqbYxczECAwEAAQ==";

	/**
	 * PACO Signing Public Key is used to cryptographically verify the response JWS signature.
	 *
	 * @var string
	 */
	public static string $PacoSigningPublicKey = "MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAkEOCDQxCbyv/n1jadyDDL9KLRddF7W2eVNf7GwVeqlq3CVor0QHiU+yweO3b622NZAPDBy/GFeJJH5lwdJUbYojFWtHUqYN7/HoTHF50KhAbLMhnllsULuyVgG1l3m9xSjRJtQSaIZP5jF4LSM+m69Xd7U2qoTczMOaNZ36yWZzxN/OUQMjb2cWeZCLhVPf6zJwA35kC57NK2n1DDvvyFvLnh9gBd8EOkJuT9us1r01Ya3XpFHhXy1fTg9bmWXDMwMm5stnhmGOF2d6Uv4rYGqk67nRzX0ZEGrWW6X0tzeQESkQShx0algKIXeM/2RBfit1QHDHhI70CYTqt1eG05Cpr5u7FdvD4pk8fqfW8xJsmoZisQNQnov0oriUqrB1wZvWL8+calfoX0nxWMVlP37LspA6O2+dlnjFxpDQSjnfWVFyS6fKvr8jXWI6KG6L11J+yAXY4KjqGK+wEnH2yf8tK8NLkIAWNstlUQrycEkk4mP6ElKwkOMpRND0ArG1cG0uMx+VXd1vrWG6UePa+GHmgHbgLSkjI3hpz3wbpE5cbp73dbIgryeC0AeLY7kKDt7pMQpkg3gNxcvTGXjZYc1TQ5siuD1RBJUR5Lv/P8jjyQnB4D67AEuL1pw5acKQ3tfOEF+iuzzzV5zeSj5T5rYR1GpuPOqTz97AWSxawDUsCAwEAAQ==";

	/**
	 * Merchant Decryption Private Key used to cryptographically decrypt the response JWE.
	 * @var string
	 */
	public static string $MerchantDecryptionPrivateKey = "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";

	/**
	 * Retrieves the plugin options from the database.
	 *
	 * @return array The array of settings for the Himalayan Bank Payment Gateway.
	 */
	public static function options(): array {
		return get_option( 'woocommerce_hbl_himalayan_bank_payment_gateway_settings' );
	}

	/**
	 * Determines if the plugin is in test mode.
	 *
	 * @return string Returns 'yes' if the plugin is in test mode, otherwise 'no'.
	 */
	public static function is_test(): string {
		return self::options()['enabled_test_mode'] === 'yes';
	}

	/**
	 * Retrieves the merchant ID.
	 *
	 * @return string The merchant ID, depending on whether the plugin is in test mode.
	 */
	public static function get_merchant_id(): string {
		return self::is_test() ? self::$MerchantId : self::options()['merchant_id'];
	}

	/**
	 * Retrieves the encryption key ID.
	 *
	 * @return string The encryption key ID, depending on whether the plugin is in test mode.
	 */
	public static function get_encryption_key_id(): string {
		return self::is_test() ? self::$EncryptionKeyId : self::options()['encryption_key'];
	}

	/**
	 * Retrieves the access token.
	 *
	 * @return string The access token, depending on whether the plugin is in test mode.
	 */
	public static function get_access_token(): string {
		return self::is_test() ? self::$AccessToken : self::options()['access_token'];
	}

	/**
	 * Retrieves the merchant signing private key.
	 *
	 * @return string The merchant signing private key, depending on whether the plugin is in test mode.
	 */
	public static function get_merchant_signing_private_key(): string {
		return self::is_test() ? self::$MerchantSigningPrivateKey : self::options()['merchant_sign_private_key'];
	}

	/**
	 * Retrieves the PACO encryption public key.
	 *
	 * @return string The PACO encryption public key, depending on whether the plugin is in test mode.
	 */
	public static function get_paco_encryption_public_key(): string {
		return self::is_test() ? self::$PacoEncryptionPublicKey : self::options()['paco_encrypt_public_key'];
	}

	/**
	 * Retrieves the PACO signing public key.
	 *
	 * @return string The PACO signing public key, depending on whether the plugin is in test mode.
	 */
	public static function get_paco_signing_public_key(): string {
		return self::is_test() ? self::$PacoSigningPublicKey : self::options()['paco_sign_public_key'];
	}

	/**
	 * Retrieves the merchant decryption private key.
	 *
	 * @return string The merchant decryption private key, depending on whether the plugin is in test mode.
	 */
	public static function get_merchant_decryption_private_key(): string {
		return self::is_test() ? self::$MerchantDecryptionPrivateKey : self::options()['merchant_decrypt_private_key'];
	}
}