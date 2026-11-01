<?php
/**
 * What the E2E suite needs from the wp-env site: a stand-in for Google's siteverify service,
 * and WP-CLI commands to set the site up (wp wpumrc-e2e <command>).
 *
 * The stand-in only runs once global setup has set the wpumrc_e2e option, so it does nothing in
 * WPUnit, which has its own.
 */

define( 'WPUMRC_E2E_SITE_KEY', 'e2e-site-key' );
define( 'WPUMRC_E2E_SECRET_KEY', 'e2e-secret-key' );

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( 'https://www.google.com/recaptcha/api/siteverify' !== $url || ! get_option( 'wpumrc_e2e' ) ) {
			return $pre;
		}

		update_option( 'wpumrc_e2e_google_calls', (int) get_option( 'wpumrc_e2e_google_calls' ) + 1 );

		parse_str( $args['body'], $sent );

		if ( isset( $sent['response'] ) && 'e2e-google-is-down' === $sent['response'] ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}

		$accepted = isset( $sent['secret'], $sent['response'] )
			&& WPUMRC_E2E_SECRET_KEY === $sent['secret']
			&& 'e2e-good-token' === $sent['response'];

		return array(
			'headers'  => array(),
			'body'     => $accepted ? '{"success":true}' : '{"success":false,"error-codes":["invalid-input-response"]}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Set up and inspect the E2E site.
 */
class WPUMRC_E2E_Command {

	/**
	 * The password every member gets.
	 */
	const PASSWORD = 'TestPass123!';

	/**
	 * Turn the stand-in on, save the keys and choose both forms.
	 */
	public function setup() {
		update_option( 'wpumrc_e2e', 1 );
		update_option( 'wpumrc_e2e_google_calls', 0 );
		update_option( 'users_can_register', 1 );

		wpum_update_option( 'recaptcha_site_key', WPUMRC_E2E_SITE_KEY );
		wpum_update_option( 'recaptcha_secret_key', WPUMRC_E2E_SECRET_KEY );
		wpum_update_option( 'recaptcha_location', array( 'registration', 'login' ) );
	}

	/**
	 * Set the Google reCAPTCHA display location setting, as the settings panel saves it.
	 *
	 * ## OPTIONS
	 *
	 * <forms>
	 * : Comma-separated forms, or "none".
	 */
	public function locations( $args ) {
		wpum_update_option( 'recaptcha_location', 'none' === $args[0] ? false : explode( ',', $args[0] ) );
	}

	/**
	 * Create (or recreate) a member.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login. The email is <login>@example.com.
	 */
	public function create_member( $args ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$login    = $args[0];
		$existing = get_user_by( 'login', $login );
		if ( $existing ) {
			wp_delete_user( $existing->ID );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $login . '@example.com',
				'user_pass'  => self::PASSWORD,
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( $user_id );
		}

		WP_CLI::line( (string) $user_id );
	}

	/**
	 * Print the ID of the account with this email, or 0.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Email address.
	 */
	public function account( $args ) {
		$user = get_user_by( 'email', $args[0] );

		WP_CLI::line( (string) ( $user ? $user->ID : 0 ) );
	}

	/**
	 * Print how many times the site has asked Google since the count was last reset.
	 */
	public function google_calls() {
		WP_CLI::line( (string) (int) get_option( 'wpumrc_e2e_google_calls' ) );
	}

	/**
	 * Reset the count of requests to Google.
	 */
	public function reset_google_calls() {
		update_option( 'wpumrc_e2e_google_calls', 0 );
	}
}

WP_CLI::add_command( 'wpumrc-e2e', 'WPUMRC_E2E_Command' );
