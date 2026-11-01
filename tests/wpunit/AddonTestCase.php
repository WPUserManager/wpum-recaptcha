<?php
/**
 * Base test case for the reCAPTCHA addon tests.
 *
 * Provides common setup: bootstraps WPUM, creates its custom tables and default registration
 * form, stands in for Google's siteverify service, and posts the login and registration forms
 * the way a real request does.
 *
 * Nothing here reaches the network: every HTTP request stops at fake_http().
 */

/**
 * Thrown from the wp_redirect filter, so a handler's redirect and exit can be tested.
 *
 * An Error rather than an Exception: the forms redirect inside a try { } catch ( Exception $e ),
 * which would swallow an Exception and report the redirect as a form error.
 */
class AddonRedirectException extends \Error {

	/**
	 * @var string
	 */
	public $location;

	public function __construct( $location ) {
		parent::__construct( 'Redirected to ' . $location );
		$this->location = $location;
	}
}

abstract class AddonTestCase extends \Codeception\TestCase\WPTestCase {

	/**
	 * The password every member gets, strong enough for WPUM's password check.
	 */
	const PASSWORD = 'CurrentP@ssw0rd!123';

	const SITE_KEY   = 'test-site-key';
	const SECRET_KEY = 'test-secret-key';

	/**
	 * The one token the stand-in for Google accepts.
	 */
	const GOOD_TOKEN = 'a-token-google-accepts';
	const BAD_TOKEN  = 'a-token-google-rejects';

	const SITE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * What the addon shows when reCAPTCHA says no.
	 */
	const ERROR = 'Recaptcha validation failed.';

	/**
	 * How the stand-in for Google behaves: see fake_http().
	 *
	 * @var string
	 */
	protected $google = 'online';

	/**
	 * What was sent to Google during the test: each request's parsed body and arguments.
	 *
	 * @var array
	 */
	protected $google_requests = array();

	/**
	 * Every other URL something tried to reach.
	 *
	 * @var array
	 */
	protected $other_requests = array();

	/**
	 * How many logins worked during the test.
	 *
	 * @var int
	 */
	protected $logins = 0;

	/**
	 * How many passwords WordPress turned down during the test.
	 *
	 * @var int
	 */
	protected $failed_logins = 0;

	public function _setUp() {
		parent::_setUp();

		// Reset the $wpum_options global: WP rolls back the DB between tests but PHP globals persist.
		global $wpum_options;
		$wpum_options = get_option( 'wpum_settings' ) ?: array();

		// Fail, not skip: a skipped suite would still pass.
		if ( ! function_exists( 'WPUM' ) ) {
			$this->fail( 'WPUM plugin is not active.' );
		}

		if ( ! class_exists( 'WPUM_Recaptcha' ) ) {
			$this->fail( 'WPUM reCAPTCHA addon is not active.' );
		}

		if ( ! function_exists( 'wpum_recaptcha_validate' ) ) {
			$this->fail( 'The addon did not load: it needs its Composer dependencies, WPUM and no Pro addon.' );
		}

		$this->ensure_tables();
		$this->ensure_default_data();

		update_option( 'users_can_register', 1 );

		// Logging in and out would otherwise try to send headers.
		add_filter( 'send_auth_cookies', '__return_false' );

		// Registration emails aren't under test.
		add_filter( 'pre_wp_mail', '__return_true', 999 );

		$this->google          = 'online';
		$this->google_requests = array();
		$this->other_requests  = array();
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 10, 3 );

		$this->logins        = 0;
		$this->failed_logins = 0;
		add_action(
			'wp_login',
			function () {
				$this->logins++;
			}
		);
		add_action(
			'wp_login_failed',
			function () {
				$this->failed_logins++;
			}
		);

		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

		if ( ! is_array( get_option( 'wpum_settings' ) ) ) {
			update_option( 'wpum_settings', array() );
		}

		wpum_update_option( 'recaptcha_site_key', self::SITE_KEY );
		wpum_update_option( 'recaptcha_secret_key', self::SECRET_KEY );
		$this->choose_forms( array( 'registration', 'login' ) );

		wpum_update_option( 'login_page', array( (string) $this->create_page( 'Login' ) ) );
		wpum_update_option( 'registration_page', array( (string) $this->create_page( 'Register' ) ) );

		$this->reset_form_instances();
	}

	public function _tearDown() {
		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();
		wp_set_current_user( 0 );

		$this->reset_form_instances();

		parent::_tearDown();
	}

	/**
	 * Stands in for every HTTP request, so none reaches the network.
	 *
	 * Google's answers, like the real service's: success only for the right token and secret.
	 * $this->google breaks it: unreachable, empty, garbage or no-verdict.
	 *
	 * @param false|array|\WP_Error $pre
	 * @param array                 $args
	 * @param string                $url
	 *
	 * @return array|\WP_Error
	 */
	public function fake_http( $pre, $args, $url ) {
		if ( self::SITE_VERIFY_URL !== $url ) {
			$this->other_requests[] = $url;

			return new \WP_Error( 'http_request_failed', 'The tests never reach the network.' );
		}

		parse_str( $args['body'], $sent );
		$this->google_requests[] = array(
			'sent' => $sent,
			'args' => $args,
		);

		switch ( $this->google ) {
			case 'unreachable':
				return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
			case 'empty':
				return $this->http_response( '', 503 );
			case 'garbage':
				return $this->http_response( '<html><body>Bad gateway</body></html>', 502 );
			case 'no-verdict':
				return $this->http_response( '{"hostname":"example.test"}' );
		}

		if ( ! isset( $sent['secret'] ) || self::SECRET_KEY !== $sent['secret'] ) {
			return $this->http_response( '{"success":false,"error-codes":["invalid-input-secret"]}' );
		}

		if ( ! isset( $sent['response'] ) || self::GOOD_TOKEN !== $sent['response'] ) {
			return $this->http_response( '{"success":false,"error-codes":["invalid-input-response"]}' );
		}

		return $this->http_response( '{"success":true,"hostname":"example.test"}' );
	}

	/**
	 * A response in the shape wp_remote_post() returns.
	 *
	 * @param string $body
	 * @param int    $code
	 *
	 * @return array
	 */
	protected function http_response( $body, $code = 200 ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Every way Google can fail to give an answer. None of them may let a form through.
	 *
	 * @return array
	 */
	public function broken_google() {
		return array(
			'it can\'t be reached'       => array( 'unreachable' ),
			'an empty reply'             => array( 'empty' ),
			'a reply that isn\'t JSON'   => array( 'garbage' ),
			'JSON with no success in it' => array( 'no-verdict' ),
		);
	}

	/**
	 * Set the Google reCAPTCHA display location setting, as the settings panel saves it.
	 *
	 * @param array $forms
	 */
	protected function choose_forms( $forms ) {
		wpum_update_option( 'recaptcha_location', $forms );
	}

	/**
	 * Forget the form singletons, so the next form starts from scratch.
	 */
	protected function reset_form_instances() {
		if ( class_exists( 'WPUM_Form_Login' ) ) {
			$prop = new \ReflectionProperty( 'WPUM_Form_Login', 'instance' );
			$prop->setAccessible( true );
			$current = $prop->getValue();

			if ( $current ) {
				remove_action( 'wp', array( $current, 'process' ) );
			}

			$prop->setValue( null, null );
		}

		if ( class_exists( 'WPUM_Form_Registration' ) ) {
			$prop = new \ReflectionProperty( 'WPUM_Form_Registration', '_instance' );
			$prop->setAccessible( true );
			$current = $prop->getValue();

			// Unhook the old instance, or the next one validates every field twice.
			if ( $current ) {
				remove_action( 'wp', array( $current, 'process' ) );
				remove_action( 'wpum_registration_form_field', array( $current, 'render_registration_form_fields' ), 10 );
				foreach ( array( 'validate_emails', 'validate_password', 'validate_username', 'validate_honeypot', 'validate_role', 'validate_repeater' ) as $validator ) {
					remove_filter( 'submit_wpum_form_validate_fields', array( $current, $validator ), 10 );
				}
			}

			$prop->setValue( null, null );
		}
	}

	/**
	 * Post the registration form through the handler WPUM runs for a real request.
	 *
	 * @param string|array|null $token What the widget posts. Null leaves it out, as a form with
	 *                                 no widget does.
	 *
	 * @return object {
	 *     @type string      $email    The email address that was registered.
	 *     @type string|null $redirect Where the handler redirected to, or null.
	 *     @type array       $errors   The form's error messages.
	 * }
	 */
	protected function register( $token = self::GOOD_TOKEN ) {
		$email = 'member' . wp_rand() . '@example.com';

		$values = array(
			'wpum_form'           => 'registration',
			'step'                => '0',
			'registration_nonce'  => wp_create_nonce( 'verify_registration_form' ),
			'submit_registration' => 'Register',
			'user_email'          => $email,
			'user_password'       => self::PASSWORD,
			'robo'                => '',
			'privacy'             => '1',
		);

		$this->reset_form_instances();
		$result = $this->post_form( \WPUM_Form_Registration::instance(), $values, $token );

		$result->email = $email;

		return $result;
	}

	/**
	 * Post the login form through the handler WPUM runs for a real request.
	 *
	 * @param string            $username
	 * @param string            $password
	 * @param string|array|null $token See register().
	 *
	 * @return object {
	 *     @type string|null $redirect Where the handler redirected to, or null.
	 *     @type array       $errors   The form's error messages.
	 * }
	 */
	protected function login( $username, $password = self::PASSWORD, $token = self::GOOD_TOKEN ) {
		$values = array(
			'wpum_form'    => 'login',
			'step'         => '0',
			'submit_login' => 'Login',
			'username'     => $username,
			'password'     => $password,
			'remember'     => '',
		);

		$this->reset_form_instances();

		return $this->post_form( \WPUM_Form_Login::instance(), $values, $token );
	}

	/**
	 * Post a form. Slashed, as WordPress slashes all request data before handlers see it.
	 *
	 * @param \WPUM_Form        $form
	 * @param array             $values
	 * @param string|array|null $token
	 *
	 * @return object
	 */
	protected function post_form( $form, $values, $token ) {
		if ( null !== $token ) {
			$values['g-recaptcha-response'] = $token;
		}

		$_POST    = wp_slash( $values );
		$_REQUEST = $_POST;

		$redirect = $this->catch_redirect( array( $form, 'process' ) );

		$prop = new \ReflectionProperty( 'WPUM_Form', 'errors' );
		$prop->setAccessible( true );

		return (object) array(
			'redirect' => $redirect,
			'errors'   => $prop->getValue( $form ),
		);
	}

	/**
	 * Create a member with a known password.
	 *
	 * @param string $login
	 *
	 * @return int
	 */
	protected function create_member( $login = 'a_member' ) {
		return self::factory()->user->create(
			array(
				'user_login' => $login,
				'user_pass'  => self::PASSWORD,
				'role'       => 'subscriber',
			)
		);
	}

	/**
	 * Whether an account with this email is really in the database, past any object cache.
	 *
	 * @param string $email
	 *
	 * @return bool
	 */
	protected function account_exists( $email ) {
		global $wpdb;

		return '1' === $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email = %s", $email ) ); // phpcs:ignore
	}

	/**
	 * Assert a registration was refused: no account, no redirect, and the error on the form.
	 *
	 * @param object $result What register() returned.
	 * @param string $error
	 */
	protected function assertNotRegistered( $result, $error = self::ERROR ) {
		$this->assertFalse( $this->account_exists( $result->email ), 'The account should not have been created.' );
		$this->assertNull( $result->redirect, 'The form should not have moved on.' );
		$this->assertContains( $error, $result->errors );
	}

	/**
	 * Assert a login was refused: nobody logged in, no redirect, and the error on the form.
	 *
	 * @param object $result What login() returned.
	 * @param string $error
	 */
	protected function assertNotLoggedIn( $result, $error = self::ERROR ) {
		$this->assertSame( 0, get_current_user_id(), 'Nobody should be logged in.' );
		$this->assertSame( 0, $this->logins, 'The login should not have happened.' );
		$this->assertNull( $result->redirect, 'The form should not have moved on.' );
		$this->assertContains( $error, $result->errors );
	}

	/**
	 * Call a protected or private method.
	 *
	 * @param object $object
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	protected function call_method( $object, $method, $args = array() ) {
		$ref = new \ReflectionMethod( $object, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( $object, $args );
	}

	/**
	 * Run a callback that redirects, and return where it redirected to.
	 *
	 * @param callable $callback
	 *
	 * @return string|null The redirect location, or null if it didn't redirect.
	 */
	protected function catch_redirect( $callback ) {
		$filter = function ( $location ) {
			throw new AddonRedirectException( (string) $location );
		};
		add_filter( 'wp_redirect', $filter, PHP_INT_MAX );

		try {
			call_user_func( $callback );
		} catch ( AddonRedirectException $e ) {
			return $e->location;
		} finally {
			remove_filter( 'wp_redirect', $filter, PHP_INT_MAX );
		}

		return null;
	}

	/**
	 * Render a callback's output.
	 *
	 * @param callable $callback
	 *
	 * @return string
	 */
	protected function render( $callback ) {
		ob_start();
		try {
			call_user_func( $callback );
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Parse HTML for XPath queries.
	 *
	 * @param string $html
	 *
	 * @return \DOMXPath
	 */
	protected function xpath( $html ) {
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new \DOMXPath( $doc );
	}

	/**
	 * Create a published page.
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return int
	 */
	protected function create_page( $title, $content = '' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Ensure all WPUM custom tables exist.
	 */
	protected function ensure_tables() {
		$tables = array(
			new \WPUM_DB_Table_Fields(),
			new \WPUM_DB_Table_Field_Meta(),
			new \WPUM_DB_Table_Fields_Groups(),
			new \WPUM_DB_Table_Registration_Forms(),
			new \WPUM_DB_Table_Registration_Forms_Meta(),
			new \WPUM_DB_Table_Search_Fields(),
		);

		foreach ( $tables as $table ) {
			if ( ! $table->exists() ) {
				$table->create();
			}
		}
	}

	/**
	 * Ensure the default fields and registration form are installed.
	 */
	protected function ensure_default_data() {
		if ( ! empty( WPUM()->registration_forms->get_forms() ) ) {
			return;
		}

		wpum_install_default_field_group();
		wpum_install_registration_form( wpum_install_fields() );

		// get_forms() caches the forms list.
		wp_cache_flush();
	}
}
