<?php
/**
 * The addon's settings, on WPUM's Misc tab.
 */
class SettingsTest extends AddonTestCase {

	/**
	 * The addon's settings, by ID.
	 *
	 * @return array
	 */
	protected function settings() {
		$settings = wpumur_register_settings( array() );

		return array_column( $settings['misc'], null, 'id' );
	}

	public function test_it_filters_the_registered_settings() {
		$this->assertSame( 10, has_filter( 'wpum_registered_settings', 'wpumur_register_settings' ) );
	}

	public function test_it_adds_its_three_settings_to_the_misc_tab() {
		$this->assertSame(
			array( 'recaptcha_site_key', 'recaptcha_secret_key', 'recaptcha_location' ),
			array_keys( $this->settings() )
		);
	}

	public function test_the_keys_are_text_fields() {
		$settings = $this->settings();

		$this->assertSame( 'text', $settings['recaptcha_site_key']['type'] );
		$this->assertSame( 'text', $settings['recaptcha_secret_key']['type'] );
	}

	public function test_the_location_is_a_multiselect_of_the_two_forms() {
		$setting = $this->settings()['recaptcha_location'];

		$this->assertSame( 'multiselect', $setting['type'] );
		$this->assertTrue( $setting['multiple'] );
		$this->assertSame( array( 'registration', 'login' ), array_column( $setting['options'], 'value' ) );
	}

	public function test_other_settings_are_kept() {
		$settings = wpumur_register_settings(
			array(
				'misc'  => array( array( 'id' => 'adminbar_roles' ) ),
				'login' => array( array( 'id' => 'login_method' ) ),
			)
		);

		$this->assertCount( 4, $settings['misc'] );
		$this->assertSame( 'adminbar_roles', $settings['misc'][0]['id'] );
		$this->assertSame( array( array( 'id' => 'login_method' ) ), $settings['login'] );
	}

	/**
	 * The URL is passed to sprintf(), but the translatable text has no %s for it.
	 *
	 * @group known-bug
	 */
	public function test_the_descriptions_link_to_google() {
		$settings = $this->settings();

		$this->assertStringContainsString( 'https://www.google.com/recaptcha/', $settings['recaptcha_site_key']['desc'] );
		$this->assertStringContainsString( 'https://www.google.com/recaptcha/', $settings['recaptcha_secret_key']['desc'] );
	}
}
