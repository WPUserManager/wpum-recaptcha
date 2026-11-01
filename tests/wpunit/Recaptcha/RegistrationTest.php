<?php
/**
 * The registration form, posted through the handler WPUM runs for a real request.
 */
class RegistrationTest extends AddonTestCase {

	public function test_a_good_token_registers_the_member() {
		$result = $this->register( self::GOOD_TOKEN );

		$this->assertSame( array(), $result->errors );
		$this->assertTrue( $this->account_exists( $result->email ) );
		$this->assertStringContainsString( 'registration=success', $result->redirect );
	}

	public function test_google_is_asked_once_with_the_token_the_secret_and_the_visitors_ip() {
		$this->register( self::GOOD_TOKEN );

		$this->assertCount( 1, $this->google_requests );

		$sent = $this->google_requests[0]['sent'];
		$this->assertSame( self::GOOD_TOKEN, $sent['response'] );
		$this->assertSame( self::SECRET_KEY, $sent['secret'] );
		$this->assertSame( '203.0.113.7', $sent['remoteip'] );
	}

	public function test_the_request_to_google_is_a_form_post() {
		$this->register( self::GOOD_TOKEN );

		$args = $this->google_requests[0]['args'];
		$this->assertSame( 'POST', $args['method'] );
		$this->assertSame( 'application/x-www-form-urlencoded', $args['headers']['Content-type'] );
	}

	public function test_only_google_is_contacted() {
		$this->register( self::GOOD_TOKEN );

		$this->assertSame( array(), $this->other_requests );
	}

	public function test_a_token_google_rejects_stops_the_registration() {
		$result = $this->register( self::BAD_TOKEN );

		$this->assertNotRegistered( $result );
		$this->assertCount( 1, $this->google_requests );
	}

	/**
	 * An unticked box: the widget's field is on the form, and it's empty.
	 */
	public function test_an_empty_token_stops_the_registration_without_asking_google() {
		$result = $this->register( '' );

		$this->assertNotRegistered( $result );
		$this->assertCount( 0, $this->google_requests );
	}

	/**
	 * Not the addon's error, on PHP 8: the array becomes a warning before it becomes a bad token.
	 * Either way, nothing is created.
	 */
	public function test_a_token_posted_as_an_array_gets_nowhere() {
		$result = $this->register( array( self::GOOD_TOKEN ) );

		$this->assertFalse( $this->account_exists( $result->email ) );
		$this->assertNull( $result->redirect );
		$this->assertNotEmpty( $result->errors );
	}

	public function test_the_wrong_secret_stops_the_registration() {
		wpum_update_option( 'recaptcha_secret_key', 'not-the-secret' );

		$this->assertNotRegistered( $this->register( self::GOOD_TOKEN ) );
	}

	/**
	 * The library refuses to run with no secret. WPUM shows its exception as the form's error.
	 */
	public function test_no_secret_stops_the_registration() {
		wpum_update_option( 'recaptcha_secret_key', '' );

		$this->assertNotRegistered( $this->register( self::GOOD_TOKEN ), 'No secret provided' );
		$this->assertCount( 0, $this->google_requests );
	}

	/**
	 * @dataProvider broken_google
	 */
	public function test_no_answer_from_google_stops_the_registration( $how ) {
		$this->google = $how;

		$result = $this->register( self::GOOD_TOKEN );

		$this->assertNotRegistered( $result );
		$this->assertCount( 1, $this->google_requests );
	}

	public function test_a_form_the_site_did_not_choose_needs_no_token() {
		$this->choose_forms( array( 'login' ) );

		$result = $this->register( null );

		$this->assertSame( array(), $result->errors );
		$this->assertTrue( $this->account_exists( $result->email ) );
		$this->assertCount( 0, $this->google_requests );
	}

	public function test_with_nothing_chosen_a_bad_token_is_ignored() {
		$this->choose_forms( array() );

		$result = $this->register( self::BAD_TOKEN );

		$this->assertTrue( $this->account_exists( $result->email ) );
		$this->assertCount( 0, $this->google_requests );
	}
}
