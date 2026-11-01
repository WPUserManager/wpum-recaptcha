<?php
/**
 * Smoke tests: the addon loads, starts on WPUM's hook and its version is consistent.
 */
class SmokeTest extends AddonTestCase {

	public function test_wpum_is_active() {
		$this->assertTrue( function_exists( 'WPUM' ) );
	}

	public function test_addon_class_and_global_function_exist() {
		$this->assertTrue( class_exists( 'WPUM_Recaptcha' ) );
		$this->assertSame( WPUMR(), WPUM_Recaptcha::instance() );
	}

	public function test_constants_are_defined() {
		$this->assertTrue( defined( 'WPUMR_VERSION' ) );
		$this->assertDirectoryExists( WPUMR_PLUGIN_DIR );
		$this->assertFileExists( WPUMR_PLUGIN_FILE );
		$this->assertSame( 'wpum-recaptcha/wpum-recaptcha.php', WPUMR_SLUG );
		$this->assertStringEndsWith( '/wpum-recaptcha/', WPUMR_PLUGIN_URL );
	}

	public function test_addon_starts_on_wpums_hook() {
		$this->assertSame( 10, has_action( 'after_wpum_init', array( WPUMR(), 'init' ) ) );
	}

	/**
	 * Every place the version lives must agree, or sites see the update over and over.
	 */
	public function test_version_is_the_same_everywhere() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$header = get_plugin_data( WPUMR_PLUGIN_FILE, false, false );
		$this->assertSame( $header['Version'], WPUMR_VERSION, 'Plugin header vs WPUMR_VERSION' );

		$readme = get_file_data( WPUMR_PLUGIN_DIR . 'readme.txt', array( 'stable' => 'Stable tag' ) );
		$this->assertSame( WPUMR_VERSION, $readme['stable'], 'readme.txt Stable tag' );

		$package = json_decode( file_get_contents( WPUMR_PLUGIN_DIR . 'package.json' ), true ); // phpcs:ignore
		$this->assertSame( WPUMR_VERSION, $package['version'], 'package.json version' );
		$this->assertSame( 'wpum-recaptcha', $package['name'], 'package.json name names the release zip' );
	}

	public function test_version_is_in_the_changelog() {
		$readme = file_get_contents( WPUMR_PLUGIN_DIR . 'readme.txt' ); // phpcs:ignore

		$this->assertStringContainsString( '= ' . WPUMR_VERSION . ' (', $readme );
	}

	public function test_the_pot_file_ships_with_the_addon() {
		$this->assertFileExists( WPUMR_PLUGIN_DIR . 'languages/wpum-recaptcha.pot' );
	}

	public function test_requirements_pass_on_the_test_site() {
		$this->assertTrue( WPUMR()->plugin_can_run() );
		$this->assertTrue( $this->call_method( WPUMR(), 'addon_can_run' ) );
	}

	public function test_the_addons_own_files_are_loaded() {
		$this->assertTrue( function_exists( 'wpumr_add_recaptcha_field' ) );
		$this->assertTrue( function_exists( 'wpumur_register_settings' ) );
		$this->assertTrue( class_exists( 'ReCaptcha\RequestMethod\WPPost' ) );
		$this->assertInstanceOf( 'ReCaptcha\RequestMethod', new \ReCaptcha\RequestMethod\WPPost() );
	}

	/**
	 * The Pro addon replaces this one. The check is whether its class exists, so this defines
	 * one, which lasts for the rest of the run: nothing else depends on it, because the addon
	 * only asks while it starts.
	 */
	public function test_it_stands_aside_for_the_pro_addon() {
		$addon = WPUMR();

		$this->assertFalse( $this->call_method( $addon, 'pro_addon_enabled' ) );
		$this->assertFalse( has_action( 'admin_notices', array( $addon, 'pro_addon_enabled_notice' ) ) );

		eval( 'final class WPUM_Recaptcha_Pro {}' ); // phpcs:ignore

		$this->assertTrue( $this->call_method( $addon, 'pro_addon_enabled' ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( $addon, 'pro_addon_enabled_notice' ) ) );
	}

	/**
	 * WPUM starts on plugins_loaded at priority 0 and the addon starts from inside that, so its
	 * own plugins_loaded hook at priority 0 is added too late to run, and WordPress never learns
	 * where the addon's translations are. WPUM's own domain is the control.
	 *
	 * @group known-bug
	 */
	public function test_the_text_domain_loads_while_wordpress_boots() {
		global $wp_textdomain_registry;

		$prop = new \ReflectionProperty( $wp_textdomain_registry, 'custom_paths' );
		$prop->setAccessible( true );
		$paths = $prop->getValue( $wp_textdomain_registry );

		$this->assertArrayHasKey( 'wp-user-manager', $paths );
		$this->assertArrayHasKey( 'wpum-recaptcha', $paths );
	}
}
