<?php
/**
 * The filter on its own: what it does with forms and results that aren't its business.
 */
class ValidationFilterTest extends AddonTestCase {

	/**
	 * Call the filter the way WPUM does, with a token posted.
	 *
	 * @param mixed  $pass
	 * @param string $form
	 * @param string $token
	 *
	 * @return bool|\WP_Error
	 */
	protected function validate( $pass, $form, $token ) {
		$_POST['g-recaptcha-response'] = $token;

		return wpum_recaptcha_validate( $pass, array(), array(), $form );
	}

	public function test_it_filters_form_validation() {
		$this->assertSame( 10, has_filter( 'submit_wpum_form_validate_fields', 'wpum_recaptcha_validate' ) );
	}

	/**
	 * @dataProvider chosen_forms
	 */
	public function test_a_good_token_hands_the_result_back_unchanged( $form ) {
		$this->assertTrue( $this->validate( true, $form, self::GOOD_TOKEN ) );
	}

	/**
	 * @dataProvider chosen_forms
	 */
	public function test_a_bad_token_is_a_wp_error_with_the_addons_message( $form ) {
		$result = $this->validate( true, $form, self::BAD_TOKEN );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'recaptcha-error', $result->get_error_code() );
		$this->assertSame( self::ERROR, $result->get_error_message() );
	}

	public function chosen_forms() {
		return array(
			'login'        => array( 'login' ),
			'registration' => array( 'registration' ),
		);
	}

	/**
	 * A good token must not wipe out what an earlier filter said.
	 */
	public function test_an_error_from_an_earlier_filter_survives_a_good_token() {
		$earlier = new \WP_Error( 'earlier', 'An earlier filter said no.' );

		$this->assertSame( $earlier, $this->validate( $earlier, 'login', self::GOOD_TOKEN ) );
	}

	/**
	 * @dataProvider other_forms
	 */
	public function test_other_forms_are_left_alone( $form ) {
		$this->assertTrue( $this->validate( true, $form, self::BAD_TOKEN ) );
		$this->assertCount( 0, $this->google_requests );
	}

	public function other_forms() {
		return array(
			'profile'           => array( 'profile' ),
			'password'          => array( 'password' ),
			'privacy'           => array( 'privacy' ),
			'password recovery' => array( 'password-recovery' ),
		);
	}

	public function test_the_form_the_site_did_not_choose_is_left_alone() {
		$this->choose_forms( array( 'login' ) );

		$this->assertTrue( $this->validate( true, 'registration', self::BAD_TOKEN ) );
		$this->assertCount( 0, $this->google_requests );
	}
}
