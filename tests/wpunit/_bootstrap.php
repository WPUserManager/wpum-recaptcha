<?php
require_once __DIR__ . '/AddonTestCase.php';

// See create-wpum-tables.php: from here on the tests set the location themselves.
remove_all_filters( 'wpum_get_option_recaptcha_location' );

// Load the form classes without creating the forms, which hooks their handlers: WPUM loads
// them the first time a form is shown or posted.
if ( defined( 'WPUM_PLUGIN_DIR' ) ) {
	if ( ! class_exists( 'WPUM_Form' ) ) {
		require_once WPUM_PLUGIN_DIR . 'includes/abstracts/class-wpum-form.php';
	}
	if ( ! class_exists( 'WPUM_Form_Login' ) ) {
		require_once WPUM_PLUGIN_DIR . 'includes/forms/class-wpum-form-login.php';
	}
	if ( ! class_exists( 'WPUM_Form_Registration' ) ) {
		require_once WPUM_PLUGIN_DIR . 'includes/forms/class-wpum-form-registration.php';
	}
}
