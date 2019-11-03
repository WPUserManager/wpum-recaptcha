<?php

namespace ReCaptcha\RequestMethod;

use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod;
use ReCaptcha\RequestParameters;

/**
 * Sends wp_remote_post requests to the reCAPTCHA service.
 */
class WPPost implements RequestMethod {

	/**
	 * URL for reCAPTCHA siteverify API
	 *
	 * @var string
	 */
	private $siteVerifyUrl;

	/**
	 * Only needed if you want to override the defaults
	 *
	 * @param string $siteVerifyUrl URL for reCAPTCHA siteverify API
	 */
	public function __construct( $siteVerifyUrl = null ) {
		$this->siteVerifyUrl = ( is_null( $siteVerifyUrl ) ) ? ReCaptcha::SITE_VERIFY_URL : $siteVerifyUrl;
	}

	/**
	 * Submit the POST request with the specified parameters.
	 *
	 * @param RequestParameters $params Request parameters
	 *
	 * @return string Body of the reCAPTCHA response
	 */
	public function submit( RequestParameters $params ) {
		$args = array(
			'body'    => $params->toQueryString(),
			'headers' => array( 'Content-type' => 'application/x-www-form-urlencoded' ),
		);

		$response = wp_remote_post( $this->siteVerifyUrl, $args );

		$connection_failed = '{"success": false, "error-codes": ["' . ReCaptcha::E_CONNECTION_FAILED . '"]}';

		if ( is_wp_error( $response ) ) {
			return $connection_failed;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return $connection_failed;
		}

		return $body;
	}
}
