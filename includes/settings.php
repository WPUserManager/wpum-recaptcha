<?php
/**
 * Register options for the addon.
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
 * Register new settings for the addon.
 *
 * @param array $settings
 * @return void
 */
function wpumur_register_settings( $settings ) {
	$settings['misc'][] = array(
		'id'   => 'recaptcha_site_key',
		'name' => __( 'Google reCAPTCHA site key', 'wpum-recaptcha' ),
		'desc' => __( 'Enter your site key.', 'wpum-recaptcha' ) . ' ' . sprintf( __( 'Get your reCAPTCHA keys from Google', 'wpum-recaptcha' ), 'https://www.google.com/recaptcha/' ),
		'type' => 'text',
	);
	$settings['misc'][] = array(
		'id'   => 'recaptcha_secret_key',
		'name' => __( 'Google reCAPTCHA secret key', 'wpum-recaptcha' ),
		'desc' => __( 'Enter your site secret key.', 'wpum-recaptcha' ) . ' ' . sprintf( __( 'Get your reCAPTCHA keys from Google', 'wpum-recaptcha' ), 'https://www.google.com/recaptcha/' ),
		'type' => 'text',
	);
	$settings['misc'][] = array(
		'id'       => 'recaptcha_location',
		'name'     => __( 'Google reCAPTCHA display location:', 'wpum-recaptcha' ),
		'desc'     => __( 'Select in which forms you wish to display the recaptcha field.', 'wpum-recaptcha' ),
		'type'     => 'multiselect',
		'multiple' => true,
		'class'    => 'select2',
		'options'  => array(
			[
				'value' => 'registration',
				'label' => esc_html__( 'Registration form', 'wpum-recaptcha' ),
			],
			[
				'value' => 'login',
				'label' => esc_html__( 'Login form', 'wpum-recaptcha' ),
			],
		),
	);
	return $settings;
}
add_action( 'wpum_registered_settings', 'wpumur_register_settings' );
