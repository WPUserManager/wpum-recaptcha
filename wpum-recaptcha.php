<?php
/*
Plugin Name: WPUM Recaptcha
Plugin URI:  https://wpusermanager.com
Description: Addon for WP User Manager, stop spam registrations on your website for free.
Version:     2.0.1
Author:      Alessandro Tesoro
Author URI:  https://wpusermanager.com/
License:     GPLv3+
Text Domain: wpum-recaptcha
Domain Path: /languages
*/

/**
 * WPUM Recaptcha.
 *
 * Copyright (c) 2018 Alessandro Tesoro
 *
 * WPUM Recaptcha. is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPUM Recaptcha. is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * @author     Alessandro Tesoro
 * @version    2.0.0
 * @copyright  (c) 2018 Alessandro Tesoro
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 * @package    wpum-recaptcha
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPUM_Recaptcha' ) ) :

	/**
	 * Main WPUM_Recaptcha class.
	 */
	final class WPUM_Recaptcha {

		/**
		 * WPUMR Instance.
		 *
		 * @var WPUMR() the WPUM Instance
		 */
		protected static $_instance;

		/**
		 * Main WPUMR Instance.
		 *
		 * Ensures that only one instance of WPUMR exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @return WPUMR
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Get things up and running.
		 */
		public function __construct() {

			// Verify the plugin meets WP and PHP requirements.
			$this->plugin_can_run();

			// Verify the addon can run first. If not, disable the addon automagically.
			$this->addon_can_run();

			// Plugin is activated now proceed.
			$this->setup_constants();
			$this->autoload();
			$this->includes();
			$this->init_hooks();

		}

		/**
		 * Autoload composer and other required classes.
		 *
		 * @return void
		 */
		private function autoload() {
			require __DIR__ . '/vendor/autoload.php';
		}

		/**
		 * Load required files for the addon.
		 *
		 * @return void
		 */
		public function includes() {
			require_once WPUMR_PLUGIN_DIR . 'includes/settings.php';
			require_once WPUMR_PLUGIN_DIR . 'includes/actions.php';
		}

		/**
		 * Setup plugin constants
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPUMR_VERSION' ) ) {
				define( 'WPUMR_VERSION', '2.0.1' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'WPUMR_PLUGIN_DIR' ) ) {
				define( 'WPUMR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'WPUMR_PLUGIN_URL' ) ) {
				define( 'WPUMR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'WPUMR_PLUGIN_FILE' ) ) {
				define( 'WPUMR_PLUGIN_FILE', __FILE__ );
			}

			// Plugin Slug.
			if ( ! defined( 'WPUMR_SLUG' ) ) {
				define( 'WPUMR_SLUG', plugin_basename( __FILE__ ) );
			}

		}

		/**
		 * Hook in our actions and filters.
		 *
		 * @return void
		 */
		private function init_hooks() {
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );
		}

		/**
		 * Load plugin textdomain.
		 *
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'wpum-recaptcha', false, basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Verify the plugin meets the WP and php requirements.
		 *
		 * @return boolean
		 */
		public function plugin_can_run() {

			$this->autoload();

			$requirements_check = new WP_Requirements_Check( array(
				'title' => 'WPUM Recaptcha',
				'php'   => '5.5',
				'wp'    => '4.7',
				'file'  => __FILE__,
			) );

			return $requirements_check->passes();

		}

		/**
		 * Verify that the current environment is supported.
		 *
		 * @return boolean
		 */
		private function addon_can_run() {

			$this->autoload();

			$requirements_check = new WPUM_Extension_Activation(
				array(
					'title'        => 'WPUM Recaptcha',
					'wpum_version' => '2.0.0',
					'file'         => __FILE__,
				)
			);

			return $requirements_check->passes();

		}

	}

endif;

/**
 * Start the addon.
 *
 * @return object
 */
function WPUMR() {
	return WPUM_Recaptcha::instance();
}

WPUMR();
