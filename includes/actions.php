<?php
/**
 * Hook into WP User Manager to validate recaptcha.
 *
 * @package     wpum-recaptcha
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the recaptcha field to the forms.
 *
 * @return void
 */
function wpumr_add_recaptcha_field() {

	$site_key = wpum_get_option( 'recaptcha_site_key' );
	$lang     = apply_filters( 'wpum_recaptcha_language', 'en' );

	?>
	<div class="g-recaptcha" data-sitekey="<?php echo $site_key; ?>" style="margin-bottom:20px;"></div>
	<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=<?php echo esc_attr( $lang ); ?>"></script>
	<?php

}

$recaptcha_display = wpum_get_option( 'recaptcha_location' );

if ( is_array( $recaptcha_display ) && in_array( 'login', $recaptcha_display ) ) {
	add_action( 'wpum_before_submit_button_login_form', 'wpumr_add_recaptcha_field' );
}

if ( is_array( $recaptcha_display ) && in_array( 'registration', $recaptcha_display ) ) {
	add_action( 'wpum_before_submit_button_registration_form', 'wpumr_add_recaptcha_field' );
}

/**
 * Hook into the forms validation system for the login and registration form
 * and then validate the recaptcha field.
 *
 * @param bool   $pass
 * @param array  $fields
 * @param array  $values
 * @param string $form
 *
 * @return bool|WP_Error
 */
function wpum_recaptcha_validate( $pass, $fields, $values, $form ) {

	$recaptcha_display = wpum_get_option( 'recaptcha_location' );

	if (
		$form == 'login' && is_array( $recaptcha_display ) && in_array( 'login', $recaptcha_display ) ||
		$form == 'registration' && is_array( $recaptcha_display ) && in_array( 'registration', $recaptcha_display )
	) {
		if ( isset( $_POST['g-recaptcha-response'] ) ) {
			$recaptcha              = new \ReCaptcha\ReCaptcha( wpum_get_option( 'recaptcha_secret_key' ), new \ReCaptcha\RequestMethod\WPPost() );
			$recaptcha_response_key = esc_html( $_POST['g-recaptcha-response'] );
			$resp = $recaptcha->verify( $recaptcha_response_key, $_SERVER['REMOTE_ADDR'] );
			if ( ! $resp->isSuccess() ) {
				error_log( __( 'WPUM Recaptcha validation failed:', 'wpum-recaptcha' ) . ' ' . implode( ', ', $resp->getErrorCodes() ) );
				return new WP_Error( 'recaptcha-error', esc_html__( 'Recaptcha validation failed.', 'wpum-recaptcha' ) );
			}
		}
	}

	return $pass;

}
add_filter( 'submit_wpum_form_validate_fields', 'wpum_recaptcha_validate', 10, 4 );
