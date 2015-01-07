<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the dashboard.
 *
 * @link       http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since      2.0.6
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      2.0.6
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 * @author     Paul Kaiser <paul.kaiser@gmail.com>
 */
class Content_Scheduler {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    2.0.6
	 * @access   protected
	 * @var      Content_Scheduler_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    2.0.6
	 * @access   protected
	 * @var      string    $Content_Scheduler    The string used to uniquely identify this plugin.
	 */
	protected $Content_Scheduler;

	/**
	 * The current version of the plugin.
	 *
	 * @since    2.0.6
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;
	
	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the Dashboard and
	 * the public-facing side of the site.
	 *
	 * @since    2.0.6
	 */
	public function __construct() {

		$this->Content_Scheduler = 'content-scheduler';
		$this->version = '2.0.6';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Content_Scheduler_Loader. Orchestrates the hooks of the plugin.
	 * - Content_Scheduler_i18n. Defines internationalization functionality.
	 * - Content_Scheduler_Admin. Defines all hooks for the dashboard.
	 * - Content_Scheduler_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    2.0.6
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Content_Scheduler_Loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Content_Scheduler_i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the Dashboard.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Content_Scheduler_Admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/Content_Scheduler_Public.php';

		$this->loader = new Content_Scheduler_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Content_Scheduler_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    2.0.6
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Content_Scheduler_i18n();
		$plugin_i18n->set_domain( $this->get_Content_Scheduler() );

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the dashboard functionality
	 * of the plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Content_Scheduler_Admin( $this->get_Content_Scheduler(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

        // Versions referring to callbacks in plugin_admin
        // Adding Custom boxes to Write panels (for Post, Page, and Custom Post Types)
        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'ContentScheduler_add_custom_box_fn' );
        $this->loader->add_action('save_post', $plugin_admin, 'ContentScheduler_save_postdata_fn' );

        // Add column to Post / Page lists
        $this->loader->add_action ( 'manage_posts_custom_column', $plugin_admin, 'cs_show_expdate' );
        $this->loader->add_action ( 'manage_pages_custom_column', $plugin_admin, 'cs_show_expdate' );

        // Showing custom columns values in list views
        $this->loader->add_filter ('manage_posts_columns', $plugin_admin, 'cs_add_expdate_column' );
        $this->loader->add_filter ('manage_pages_columns', $plugin_admin, 'cs_add_expdate_column' );

        $this->loader->add_action( 'admin_head', $plugin_admin, 'admin_head' );
        $this->loader->add_action( 'admin_init', $plugin_admin, 'admin_init' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'admin_menu' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Content_Scheduler_Public( $this->get_Content_Scheduler(), $this->get_version() );		

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        // PUBLIC and ADMIN
        // NOTE: We have added this to the PUBLIC side only for now
        // TODO Test whether this is available when in Dashboard
        // add a cron action for expiration check
        if( is_multisite () ) {
            // we need to add our action hook for just the current site, using blogID in the name
            $blog_id = get_current_blog_id();
            $this->loader->add_action( 'contentscheduler' . $blog_id, $plugin_public, 'answer_expiration_event' );
        } else {
            // it's okay to just use normal action hook
            $this->loader->add_action ('contentscheduler', $plugin_public, 'answer_expiration_event' );            
        }
        // TODO Test whether this is available when in Dashboard
        $this->loader->add_filter('cron_schedules', $plugin_public, 'add_cs_cron_fn' );
        
        // Shortcodes
        // Ideally we'll add stuff to the loader so we can add special types of actions such as shortcodes
        // add_shortcode('cs_expiration', array( $this, 'handle_shortcode' ) );
        add_shortcode('cs_expiration', array( $plugin_public, 'handle_shortcode' ) );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    2.0.6
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * TODO We are using this method in Settings class also, so maybe should subclass something more generic
	 *
	 * @since     2.0.6
	 * @return    string    The name of the plugin.
	 */
	public function get_Content_Scheduler() {
		return $this->Content_Scheduler;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     2.0.6
	 * @return    Content_Scheduler_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * TODO We are using this method in Settings class also, so maybe should subclass something more generic
	 *
	 * @since     2.0.6
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
	
}
