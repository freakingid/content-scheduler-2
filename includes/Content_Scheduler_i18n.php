<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since      2.0.6
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      2.0.6
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 * @author     Paul Kaiser <paul.kaiser@gmail.com>
 */
class Content_Scheduler_i18n {

	/**
	 * The domain specified for this plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 * @var      string    $domain    The domain identifier for this plugin.
	 */
	private $domain;

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    2.0.6
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			$this->domain,
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/lang/'
		);

	}

	/**
	 * Set the domain equal to that of the specified domain.
	 *
	 * @since    2.0.6
	 * @param    string    $domain    The domain that represents the locale of this plugin.
	 */
	public function set_domain( $domain ) {
		$this->domain = $domain;
	}

}
