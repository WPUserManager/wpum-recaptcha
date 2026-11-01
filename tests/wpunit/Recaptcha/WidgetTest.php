<?php
/**
 * What visitors get: the widget on the forms, and never the secret.
 */
class WidgetTest extends AddonTestCase {

	/**
	 * @return string
	 */
	protected function widget() {
		return $this->render( 'wpumr_add_recaptcha_field' );
	}

	public function test_the_widget_carries_the_site_key() {
		$widgets = $this->xpath( $this->widget() )->query( '//div[@class="g-recaptcha"]' );

		$this->assertSame( 1, $widgets->length );
		$this->assertSame( self::SITE_KEY, $widgets->item( 0 )->getAttribute( 'data-sitekey' ) );
	}

	public function test_the_site_key_is_escaped() {
		wpum_update_option( 'recaptcha_site_key', 'a"key' );

		$widgets = $this->xpath( $this->widget() )->query( '//div[@class="g-recaptcha"]' );

		$this->assertSame( 'a"key', $widgets->item( 0 )->getAttribute( 'data-sitekey' ) );
	}

	public function test_it_loads_googles_script_in_english_by_default() {
		$scripts = $this->xpath( $this->widget() )->query( '//script' );

		$this->assertSame( 1, $scripts->length );
		$this->assertSame( 'https://www.google.com/recaptcha/api.js?hl=en', $scripts->item( 0 )->getAttribute( 'src' ) );
	}

	public function test_the_language_can_be_filtered() {
		add_filter(
			'wpum_recaptcha_language',
			function () {
				return 'fr';
			}
		);

		$this->assertStringContainsString( 'api.js?hl=fr"', $this->widget() );
	}

	public function test_a_filtered_language_cannot_break_out_of_the_attribute() {
		add_filter(
			'wpum_recaptcha_language',
			function () {
				return '"><img src=x>';
			}
		);

		$this->assertSame( 0, $this->xpath( $this->widget() )->query( '//img' )->length );
	}

	/**
	 * The addon reads the location once, while it loads, and the suite boots with both chosen.
	 * E2E covers a site that chose one form, or none.
	 */
	public function test_it_hooks_into_both_forms() {
		$this->assertSame( 10, has_action( 'wpum_before_submit_button_login_form', 'wpumr_add_recaptcha_field' ) );
		$this->assertSame( 10, has_action( 'wpum_before_submit_button_registration_form', 'wpumr_add_recaptcha_field' ) );
	}

	public function test_the_login_form_has_the_widget_ahead_of_the_button() {
		$html = do_shortcode( '[wpum_login_form]' );

		$found = $this->xpath( $html )->query( '//form[@id="wpum-submit-login-form"]//div[@class="g-recaptcha"]/following-sibling::input[@name="submit_login"]' );

		$this->assertSame( 1, $found->length );
	}

	public function test_the_registration_form_has_the_widget_ahead_of_the_button() {
		$html = do_shortcode( '[wpum_register]' );

		$found = $this->xpath( $html )->query( '//form[@id="wpum-submit-registration-form"]//div[@class="g-recaptcha"]/following-sibling::input[@name="submit_registration"]' );

		$this->assertSame( 1, $found->length );
	}

	public function test_the_secret_is_never_on_the_page() {
		$html = do_shortcode( '[wpum_login_form]' ) . do_shortcode( '[wpum_register]' );

		$this->assertStringContainsString( self::SITE_KEY, $html, 'The control: the site key is there.' );
		$this->assertStringNotContainsString( self::SECRET_KEY, $html );
	}

	public function test_a_refused_form_shows_the_error_again_with_the_widget() {
		$this->register( self::BAD_TOKEN );

		$html = do_shortcode( '[wpum_register]' );

		$this->assertStringContainsString( self::ERROR, $html );
		$this->assertSame( 1, $this->xpath( $html )->query( '//div[@class="g-recaptcha"]' )->length );
	}
}
