<?php
/**
 * The login form, posted through the handler WPUM runs for a real request.
 */
class LoginTest extends AddonTestCase {

	public function test_a_good_token_and_the_right_password_log_the_member_in() {
		$user_id = $this->create_member();

		$result = $this->login( 'a_member', self::PASSWORD, self::GOOD_TOKEN );

		$this->assertSame( array(), $result->errors );
		$this->assertSame( $user_id, get_current_user_id() );
		$this->assertSame( 1, $this->logins );
		$this->assertNotNull( $result->redirect );
	}

	public function test_google_is_asked_once_with_the_token_and_the_secret() {
		$this->create_member();

		$this->login( 'a_member' );

		$this->assertCount( 1, $this->google_requests );
		$this->assertSame( self::GOOD_TOKEN, $this->google_requests[0]['sent']['response'] );
		$this->assertSame( self::SECRET_KEY, $this->google_requests[0]['sent']['secret'] );
	}

	public function test_a_token_google_rejects_stops_the_right_password() {
		$this->create_member();

		$this->assertNotLoggedIn( $this->login( 'a_member', self::PASSWORD, self::BAD_TOKEN ) );
	}

	/**
	 * An unticked box: the widget's field is on the form, and it's empty.
	 */
	public function test_an_empty_token_stops_the_right_password_without_asking_google() {
		$this->create_member();

		$this->assertNotLoggedIn( $this->login( 'a_member', self::PASSWORD, '' ) );
		$this->assertCount( 0, $this->google_requests );
	}

	public function test_a_login_without_a_response_is_refused() {
		$this->create_member();

		$this->assertNotLoggedIn( $this->login( 'a_member', self::PASSWORD, null ) );
		$this->assertCount( 0, $this->google_requests );
	}

	/**
	 * Nobody gets to try a password until reCAPTCHA has passed.
	 */
	public function test_a_rejected_token_means_the_password_is_never_checked() {
		$this->create_member();

		$result = $this->login( 'a_member', 'NotMyP@ssw0rd!', self::BAD_TOKEN );

		$this->assertNotLoggedIn( $result );
		$this->assertSame( 0, $this->failed_logins );
	}

	public function test_a_good_token_does_not_excuse_a_wrong_password() {
		$this->create_member();

		$result = $this->login( 'a_member', 'NotMyP@ssw0rd!', self::GOOD_TOKEN );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertSame( 0, $this->logins );
		$this->assertSame( 1, $this->failed_logins );
		$this->assertNull( $result->redirect );
		$this->assertNotEmpty( $result->errors );
		$this->assertNotContains( self::ERROR, $result->errors );
	}

	public function test_the_wrong_secret_stops_the_login() {
		$this->create_member();
		wpum_update_option( 'recaptcha_secret_key', 'not-the-secret' );

		$this->assertNotLoggedIn( $this->login( 'a_member' ) );
	}

	/**
	 * The library refuses to run with no secret. WPUM shows its exception as the form's error.
	 */
	public function test_no_secret_stops_the_login() {
		$this->create_member();
		wpum_update_option( 'recaptcha_secret_key', '' );

		$this->assertNotLoggedIn( $this->login( 'a_member' ), 'No secret provided' );
	}

	/**
	 * @dataProvider broken_google
	 */
	public function test_no_answer_from_google_stops_the_login( $how ) {
		$this->create_member();
		$this->google = $how;

		$this->assertNotLoggedIn( $this->login( 'a_member' ) );
		$this->assertCount( 1, $this->google_requests );
	}

	public function test_a_form_the_site_did_not_choose_needs_no_token() {
		$user_id = $this->create_member();
		$this->choose_forms( array( 'registration' ) );

		$result = $this->login( 'a_member', self::PASSWORD, null );

		$this->assertSame( array(), $result->errors );
		$this->assertSame( $user_id, get_current_user_id() );
		$this->assertCount( 0, $this->google_requests );
	}
}
