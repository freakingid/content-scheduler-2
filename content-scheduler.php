<?php
/*
Plugin Name: Content Scheduler
Plugin URI: http://paulekaiser.com/wordpress-plugins/content-scheduler/
Description: Set Posts and Pages to automatically expire. Upon expiration, delete, change categories, status, or unstick posts. Also notify admin and author of expiration.
Version: 2.0.0
Author: Paul Kaiser
Author URI: http://paulekaiser.com
License: GPL2
*/
/*  Copyright 2014  Paul Kaiser  (email : paul.kaiser@gmail.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
// avoid direct calls to this file, because now WP core and framework have been used
if ( !function_exists('is_admin') ) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}





// assign some constants if they didn't already get taken care of
define( 'PEK_CONTENT_SCHEDULER_VERSION', '2.0.0' );
define( 'PEK_CONTENT_SCHEDULER_DIR', plugin_dir_path( __FILE__ ) );
define( 'PEK_CONTENT_SCHEDULER_URL', plugin_dir_url( __FILE__ ) );
define( 'PEK_CONTENT_SCHEDULER_DEBUG', true );
if ( ! defined( 'WP_CONTENT_URL' ) )
	define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );





// Define our plugin's wrapper class
if ( !class_exists( "ContentScheduler" ) ) {
	class ContentScheduler {
	    var $settings, $options_page, $options;
	    
		function __construct() {
            $this->options = get_option('ContentScheduler_Options');

		    if ( is_admin() ) {
		        // Handle all things that are Dashboard-only
                // Load settings page
                if ( !class_exists( "Content_Scheduler_Settings" ) ) {
                    require( PEK_CONTENT_SCHEDULER_DIR . 'content-scheduler-settings.php' );
                }
                $this->settings = new Content_Scheduler_Settings();
                // Add any JavaScript and CSS needed just for my plugin
                // for the post editing screen
                add_action( "admin_enqueue_scripts", array( $this, 'cs_edit_scripts' ) );
                add_action( "admin_enqueue_scripts", array( $this, 'cs_edit_styles' ) );
                // Adding Custom boxes to Write panels (for Post, Page, and Custom Post Types)
                add_action('add_meta_boxes', array($this, 'ContentScheduler_add_custom_box_fn'));
                add_action('save_post', array($this, 'ContentScheduler_save_postdata_fn'));
                // Add column to Post / Page lists
                add_action ( 'manage_posts_custom_column', array( $this, 'cs_show_expdate' ) );
                add_action ( 'manage_pages_custom_column', array( $this, 'cs_show_expdate' ) );
                // Showing custom columns values in list views
                add_filter ('manage_posts_columns', array( $this, 'cs_add_expdate_column' ) );
                add_filter ('manage_pages_columns', array( $this, 'cs_add_expdate_column' ) );
            }
            
			add_action( 'init', array( $this, 'content_scheduler_init' ) );

			// admin_init and admin_menu only appear when we are in admin
			// could be in page edit, etc.
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            
			// add a cron action for expiration check
			// I think this is still valid, even after 3.4 changes
			// for now assume it is not a multisite
			// schedule hooks to 'contentscheduler' action, which does callback
			add_action ('contentscheduler', array( $this, 'answer_expiration_event') );
			// TODO
			// add_action ('contentschedulernotify', array( $this, 'answer_notification_event' ) );

			/*
			if ( $this->is_network_site() )
			{
				add_action ('content_scheduler'.$current_blog->blog_id, array( $this, 'answer_expiration_event') );
			}
			else
			{
				add_action ('content_scheduler', array( $this, 'answer_expiration_event') );
			}
			*/

			// Shortcodes
			add_shortcode('cs_expiration', array( $this, 'handle_shortcode' ) );

			// Filters
			add_filter('cron_schedules', array( $this, 'add_cs_cron_fn' ) );

			register_activation_hook( __FILE__, array($this, 'run_on_activate') );
			register_deactivation_hook( __FILE__, array($this, 'run_on_deactivate') );
		} // end ContentScheduler Constructor




        // ======================================================================
        // Plugin Activation / Deactivation work
        // ======================================================================
        /*
            Propagates pfunction to all blogs within our multisite setup.
            http://shibashake.com/wordpress-theme/write-a-plugin-for-wordpress-multi-site		
            If not multisite, then we just run pfunction for our single blog.
        */
        function network_propagate($pfunction, $networkwide) {
            global $wpdb;

            if (function_exists('is_multisite') && is_multisite()) {
                // check if it is a network activation - if so, run the activation function 
                // for each blog id
                if ($networkwide) {
                    $old_blog = $wpdb->blogid;
                    // Get all blog ids
                    $blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
                    foreach ($blogids as $blog_id) {
                        switch_to_blog($blog_id);
                        call_user_func($pfunction, $networkwide);
                    }
                    switch_to_blog($old_blog);
                    return;
                }	
            } 
            call_user_func($pfunction, $networkwide);
        }

        function run_on_activate( $network_wide )
        {
            $this->network_propagate( array( $this, '_activate' ), $networkwide );
        }
    
        // TODO: Still need to review what we do during activation
        function _activate() {
            /*
                1. Set some default options, with database migration if needed
            */

            // Build an array of each option and its default setting
            // exp-default is supposed to be a serialized array of hours, days, weeks
            $expiration_default = array( 'exp-hours' => '0', 'exp-days' => '0', 'exp-weeks' => '0' );
            $arr_defaults = array
            (
                "version" => PEK_CONTENT_SCHEDULER_VERSION,
                "exp-status" => "1",
                "exp-period" => "1",
                "chg-status" => "2",
                "chg-sticky" => "0",
                "chg-cat-method" => "0",
                "selcats" => "",
                "tags-to-add" => "",
                "notify-on" => "0",
                "notify-admin" => "0",
                "notify-author" => "0",
                "notify-expire" => "0",
                "min-level" => "level_1",
                "show-columns" => "0",
                "remove-cs-data" => "0",
                "exp-default" => $expiration_default
            );

            // Some database migration from older versions of Content Scheduler
            if( is_array( $this->options ) )
            {
                // If version newer than 0.9.7, we need to alter the name of our postmeta variables if there are earlier version settings in options
                if( !isset( $this->options['version'] ) || $this->options['version'] < '0.9.7' )
                {
                    // we do need to change existing postmeta variable names in the database
                    include 'includes/update-postmeta-names.php';
                }
                // If version newer than 0.9.8, we need to alter the name of our user_level values
                if( !isset( $this->options['version'] ) || $this->options['version'] < '0.9.8' )
                {
                    // we do need to change existing user-level access values in the database
                    include 'includes/update-minlevel-options.php';
                }
                // We need to check the "version" and, if it is less than 0.9.5 or non-existent, we need to convert english string values to numbers
                if( !isset( $this->options['version'] ) || $this->options['version'] < '0.9.5' )
                {
                    // we want to change options from english strings to numbers - this happened from 0.9.4 to 0.9.5
                    include 'includes/update-values-numbers.php';
                }
                // We need to update the version string to our current version
                $this->options['version'] = PEK_CONTENT_SCHEDULER_VERSION;
            }
            // make sure we have added any updated options
            if( $this->options ) {
                $new_options = array_replace( $arr_defaults, $this->options );
            } else {
                $new_options = $arr_defaults;
            }
            $this->options = $new_options; // just to be safe
            update_option('ContentScheduler_Options', $new_options);

            /*
                2. Register our expiration event into wp-cron schedule
            */
            $current_blog_id = get_current_blog_id();
            // Test for the event already existing before you schedule the event again
            // for expirations
            // NOTE: The following is only valid for multisite because of the current_blog_id var
            /*
            if( !wp_next_scheduled( 'contentscheduler'.$current_blog_id ) ) {
                // NOTE: For some reason there seems to be a problem on some systems where the hook must not contain underscores or uppercase characters.
                // We previously used content_scheduler_(blogid)
                // http://codex.wordpress.org/Function_Reference/wp_schedule_event
                wp_schedule_event( time(), 'contsched_usertime', 'contentscheduler'.$current_blog_id );
                // wp_schedule_event( time(), 'hourly', 'content_scheduler_'.$current_blog_id );
                // TODO
                // wp_schedule_event( time(), 'contsched_usertime', 'contentschedulernotify'.$current_blog_id );
            }
            */
            // non-multisite version
            if( !wp_next_scheduled( 'contentscheduler' ) ) {
                // NOTE: For some reason there seems to be a problem on some systems where the hook must not contain underscores or uppercase characters.
                // We previously used content_scheduler_(blogid)
                // http://codex.wordpress.org/Function_Reference/wp_schedule_event
                wp_schedule_event( time(), 'contsched_usertime', 'contentscheduler' );
                // wp_schedule_event( time(), 'hourly', 'content_scheduler_'.$current_blog_id );
                // TODO
                // wp_schedule_event( time(), 'contsched_usertime', 'contentschedulernotify'.$current_blog_id );
            }
        } // end activate_function

        function run_on_deactivate( $network_wide ) {
            $this->network_propagate( array( $this, '_deactivate' ), $networkwide );
        } // end run_on_activate()

        // TODO: Still need to review what we do during deactivation
        function _deactivate() {
            $current_blog_id = get_current_blog_id();
            // it is a networked site activation
            // NOTE: Multisite version
            /*
            // for expirations
            wp_clear_scheduled_hook('contentscheduler'.$current_blog_id);
            // for notifications
            wp_clear_scheduled_hook('contentschedulernotify'.$current_blog_id);
            */
            // non-multisite version
            // for expirations
            wp_clear_scheduled_hook('contentscheduler');
            // for notifications
            wp_clear_scheduled_hook('contentschedulernotify');

        } // end deactivate_function()




        // ======================================================================
        // Init / Admin Init callbacks
        // Note: there will be other init / admin_inits in Settings class
        // ======================================================================
        // init
        function content_scheduler_init() {
            // load language translation files
            $plugin_dir = basename(dirname(__FILE__)) . '/lang';
            load_plugin_textdomain( 'contentscheduler', PEK_CONTENT_SCHEDULER_DIR . 'lang', basename( dirname( __FILE__ ) ) .'/lang' );
        }
        function admin_init() {
        }
        function admin_menu() {
        }





        // ========================================================================
        // == JavaScript and CSS Enqueueing?
        // ========================================================================
        // enqueue the jQuery UI things we need for using the datepicker
        function cs_edit_scripts( $hook ) {
            // only use on new post and edit post pages
            if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
                return;
            }
            // enqueue javascripts here if needed
            // e.g., datepicker scripts
        } // end cs_edit_scripts()

        function cs_edit_styles( $hook ) {
            // post.php and post-new.php
            if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
                return;
            }
            // enqueue styles here if needed
            // e.g., datepicker CSS
        } // end cs_edit_styles()





        // =================================================================
        // == Functions for using Custom Controls / Panels
        // == in the Post / Page / Link writing panels
        // == e.g., a custom field for an expiration date, etc.
        // =================================================================
        // Adds a box to the main column on the Post, Page, and Custom Type edit screens
        // We'll rig so it only shows if user is min-level or above
        // a. Add the box
        function ContentScheduler_add_custom_box_fn()
        {
            global $current_user;
            // What is minimum level required to see CS?
            // $this->options = get_option('ContentScheduler_Options');
            $min_level = $this->options['min-level'];
        
            // What is current user's level?
            get_currentuserinfo();
        
            // 3.3 changed this for the better
            $allcaps = $current_user->allcaps;
        
            if( 1 != $allcaps[$min_level] )
            {
                return; // not authorized to see CS
            }
            // else - continue
            // Add the box to Post write panels
            add_meta_box( 'ContentScheduler_sectionid', 
                            __( 'Content Scheduler', 
                            'contentscheduler' ), 
                            array($this, 'ContentScheduler_custom_box_fn'), 
                            'post' );
            // Add the box to Page write panels
            add_meta_box( 'ContentScheduler_sectionid', 
                            __( 'Content Scheduler', 
                            'contentscheduler' ), 
                            array($this, 'ContentScheduler_custom_box_fn'), 
                            'page' );
            // Get a list of all custom post types
            // From: http://codex.wordpress.org/Function_Reference/get_post_types
            $args = array(
                'public'   => true,
                '_builtin' => false
            ); 
            $output = 'names'; // names or objects
            $operator = 'and'; // 'and' or 'or'
            $post_types = get_post_types( $args, $output, $operator );
            // Step through each public custom type and add the content scheduler box
            foreach ($post_types  as $post_type )
            {
                // echo '<p>'. $post_type. '</p>';
                add_meta_box( 'ContentScheduler_sectionid',
                                __( 'Content Scheduler',
                                'contentscheduler' ),
                                array( $this, 'ContentScheduler_custom_box_fn'),
                                $post_type );
            }
        }

        // b. Print / draw the box callback
        function ContentScheduler_custom_box_fn()
        {
            // need $post in global scope so we can get id?
            global $post;
            // Use nonce for verification
            wp_nonce_field( 'content_scheduler_values', 'ContentScheduler_noncename' );
            // Get the current value, if there is one
            $the_data = get_post_meta( $post->ID, '_cs-enable-schedule', true );
            // Checkbox for scheduling this Post / Page, or ignoring
            $items = array( "Disable", "Enable");
            foreach( $items as $item)
            {
                $checked = ( $the_data == $item ) ? ' checked="checked" ' : '';
                echo "<label><input ".$checked." value='$item' name='_cs-enable-schedule' id='cs-enable-schedule' type='radio' /> $item</label>  ";
            } // end foreach
            echo "<br />\n<br />\n";
            // Field for datetime of expiration
            $datestring = ( get_post_meta( $post->ID, '_cs-expire-date', true) );
            // Should we check for format of the date string? (not doing that presently)
            echo '<label for="cs-expire-date">' . __("Expiration date and hour", 'contentscheduler' ) . '</label><br />';
            echo '<input type="text" id="cs-expire-date" name="_cs-expire-date" value="'.$datestring.'" size="25" />';
            echo ' Input date and time as: Year-Month-Day Hour:00:00 e.g., 2010-11-25 08:00:00<br />';
        }

        // c. Save data from the box callback
        function ContentScheduler_save_postdata_fn( $post_id )
        {
            // verify this came from our screen and with proper authorization,
            // because save_post can be triggered at other times
            if( !empty( $_POST['ContentScheduler_noncename'] ) )
            {
                if ( !wp_verify_nonce( $_POST['ContentScheduler_noncename'], 'content_scheduler_values' ))
                {
                    return $post_id;
                }
            }
            else
            {
                return $post_id;
            }
            // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
            // to do anything
            if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
            {
                return $post_id;
            }
            // Check permissions, whether we're editing a Page or a Post
            if ( 'page' == $_POST['post_type'] )
            {
                if ( !current_user_can( 'edit_page', $post_id ) )
                return $post_id;
            }
            else
            {
                if ( !current_user_can( 'edit_post', $post_id ) )
                return $post_id;
            }
            // OK, we're authenticated: we need to find and save the data
            // First, let's make sure we'll do date operations in the right timezone for this blog
            $this->setup_timezone();
            // Checkbox for "enable scheduling"
            $enabled = ( empty( $_POST['_cs-enable-schedule'] ) ? 'Disable' : $_POST['_cs-enable-schedule'] );
            // Value should be either 'Enable' or 'Disable'; otherwise something is screwy
            if( $enabled != 'Enable' AND $enabled != 'Disable' )
            {
                // $enabled is something we don't expect
                // let's make it empty
                $enabled = 'Disable';
                // Now we're done with this function?
                return false;
            }
            // Textbox for "expiration date"
            $date = $_POST['_cs-expire-date'];
            if( strtolower( $date ) == 'default' )
            {
                // get the default value from the database
                // $this->options = get_option('ContentScheduler_Options');
                $default_expiration_array = $this->options['exp-default'];
                if( !empty( $default_expiration_array ) )
                {
                    $default_hours = $default_expiration_array['def-hours'];
                    $default_days = $default_expiration_array['def-days'];
                    $default_weeks = $default_expiration_array['def-weeks'];
                }
                else
                {
                    $default_hours = '0';
                    $default_days = '0';
                    $default_weeks = '0';
                }
            
                // we need to move weeks into days (7 days per week)
                $default_days += $default_weeks * 7;
                // if it is valid, get the published or scheduled datetime, add the default to it, and set it as the $date
                // post_date
                // does 'save' only exist when updating??
                if ( !empty( $_POST['save'] ) )
                {
                    if( $_POST['save'] == 'Update' )
                    {
                        $publish_date = $_POST['aa'] . '-' . $_POST['mm'] . '-' . $_POST['jj'] . ' ' . $_POST['hh'] . ':' . $_POST['mn'] . ':' . $_POST['ss'];
                    }
                    else
                    {
                        $publish_date = $_POST['post_date'];
                    }
                }
                else
                {
                    $publish_date = $_POST['post_date'];
                }
            
                if( $publish_date == '' )
                {
                    // $publish_date = date( 'Y-m-d H:i:s' ); // right now
                    $publish_date = time(); // right now	
                }
                else
                {
                    $publish_date = strtotime( $publish_date );
                }
                // time to add our default
                // we need $publish_date to be in unix timestamp format, like time()
                $expiration_date = $publish_date + ( $default_days * 24 * 60 * 60) + ( $default_hours * 60 * 60 );
                $expiration_date = date( 'Y-m-d H:i:s', $expiration_date );
                // now sub in the calculated date for 'default'
                $_POST['_cs-expire-date'] = $expiration_date;
            }
            else
            {
                // How can we check a myriad of date formats??
                // Right now we are mm/dd/yyyy
                if( ! $this->check_date_format( $date ) )
                {
                    // It was not a valid date format
                    // Normally, we would set to ''
                    $date = '';
                    // For debug, we will set to 'INVALID'
                    // $date = 'INVALID';
                }
            }
            // We probably need to store the date differently,
            // and handle timezone situation
            update_post_meta( $post_id, '_cs-enable-schedule', $enabled );
            update_post_meta( $post_id, '_cs-expire-date', $date );
            return true;
        }





        // =======================================================================
        // == SCHEDULING FUNCTIONS
        // =======================================================================
        // Specify a custom interval for wp-cron checking
        function add_cs_cron_fn($array)
        {
            // Normally, we'll set interval to like 3600 (one hour)
            // For testing, we can set it to like 120 (2 min)
            // 1. Check options for desired interval.
            // $options = get_option('ContentScheduler_Options');
            if( ! empty( $this->options['exp-period'] ) )
            {
                // we have a value, use it
                $period = $this->options['exp-period'];
            }
            else
            {
                // set our default of 1 minute
                $period = 1;
            }
            // We actually have to specify the interval in seconds
            $period = $period*60;
            // 2. use that for 'interval' below.
            $array['contsched_usertime'] = array(
                'interval' => $period,
                'display' => __('CS User Configured')
            );
            return $array;
        } // end add_hourly_cron_fn()





        // =======================================================
        // == Show CRON Settings
        // == Mostly for debug in Setting screen
        // =======================================================
        function cs_view_cron_settings()
        {
            // store all scheduled cron jobs in an array
            $cron = _get_cron_array();
            // get all registered cron recurrence options (hourly, etc.)
            $schedules = wp_get_schedules();
            $date_format = 'M j, Y @ G:i';
    ?>
    <div clas="wrap" id="cron-gui">
    <h2>Cron Events Scheduled</h2>
    <table class="widefat fixed">
        <thead>
        <tr>
            <th scope="col">Next Run (GMT/UTC)</th>
            <th scope="col">Schedule</th>
            <th scope="col">Hook Name</th>
        </tr>
        </thead>
        <tbody>
    <?php
            foreach( $cron as $timestamp => $cronhooks )
            {
                foreach( (array) $cronhooks as $hook => $events )
                {
                    foreach( (array) $events as $event )
                    {
    ?>
            <tr>
                <td>
                    <?php echo date_i18n( $date_format, wp_next_scheduled( $hook ) ); ?>
                </td>
                <td>
                    <?php 
                    if( $event['schedule'] )
                    {
                        echo $schedules[$event['schedule']]['display'];
                    }
                    else
                    {
                    ?>
                    One-time
                    <?php
                    }
    ?>
                </td>
                <td><?php echo $hook; ?></td>
            </tr>
    <?php
                    }
                }
            }
    ?>
        </tbody>
    </table>
    <h3>More Debug Info:</h3>
    <p><strong>NOTE: </strong>You will see <em>either</em> a Timezone String <em>or</em> a GMT Offset -- not both.</p>
    <ul>
        <li>PHP Version on this server: <?php echo phpversion(); ?></li>
        <li>WordPress core version: <?php bloginfo( 'version' ); ?></li>
        <li>WordPress Timezone String: <?php echo get_option('timezone_string'); ?></li>
        <li>WordPress GMT Offset: <?php echo get_option('gmt_offset'); ?></li>
        <li>WordPress Date Format: <?php echo get_option('date_format'); ?></li>
        <li>WordPress Time Format: <?php echo get_option('time_format'); ?></li>
    </ul>
    </div>
    <?php
        } // end cs_view_cron_settings()





        // =======================================================
        // == WP-CRON RESPONDERS
        // =======================================================
        // Respond to a call from wp-cron checking for expired Posts / Pages
        function answer_expiration_event()
        {
            if( PEK_CONTENT_SCHEDULER_VERSION ) {
                error_log( __FILE__ . " :: " . __FUNCTION__ . " Top" );
            }
            // we should get our options right now, and decide if we need to proceed or not.
            // $options = get_option('ContentScheduler_Options');
            // Do we need to process expirations?
            if( $this->options['exp-status'] != '0' )
            {				
                // We need to process expirations
                $this->process_expirations();
            } else {
                if( PEK_CONTENT_SCHEDULER_VERSION ) {
                    error_log( "Expiration status is HOLD, so we won't process" );
                }
            }

        }
        // Respond to a call from wp-cron checking for valid notification rules
        // NOTE: This is separate from expiration so we could use CS to just notify of aged posts
        function answer_notification_event()
        {
            error_log( __FILE__ . " :: " . __FUNCTION__ );
            error_log( 'called, but we are not doing anything in here yet' );
        }

        // ==========================================================
        // Process Expirations
        // ==========================================================
        function process_expirations()
        {
            if( PEK_CONTENT_SCHEDULER_VERSION ) {
                error_log( __FILE__ . " :: " . __FUNCTION__ . " Top" );
            }
            // Check database for posts meeting expiration criteria
            // Hand them off to appropriate functions
            include 'includes/process-expirations.php';
        } // end process_expirations()

        // ====================
        // Do whatever we need to do to expired POSTS
        function process_post($postid)
        {
            include "includes/process-post.php";
        } // end process_post()
        // ====================
        // Do whatever we need to do to expired PAGES
        function process_page($postid)
        {
            include "includes/process-page.php";
        } // end process_page()
        // ====================
        // Do whatever we need to do to expired CUSTOM POST TYPES
        function process_custom($postid)
        {
            // for now, we are just going to proceed with process_post
            include "includes/process-post.php";
        } // end process_custom()





        // ================================================================
        // == Conditionally Add Expiration date to Column views
        // ================================================================
        // a. add our column to the table
        function cs_add_expdate_column ($columns) {
            global $current_user;
            // Check to see if we really want to add our column
            // $options = get_option('ContentScheduler_Options');
            if( $this->options['show-columns'] == '1' )
            {
                // Check to see if current user has permissions to see
                // What is minimum level required to see CS?
                // must declare $current_user as global
                $min_level = $this->options['min-level'];
                // What is current user's level?
                get_currentuserinfo();
        
                $allcaps = $current_user->allcaps;
                if( 1 != $allcaps[$min_level] )
                {
                    return $columns; // not authorized to see CS, so we don't add our expiration column
                }
                // we're just adding our own item to the already existing $columns array
                $columns['cs-exp-date'] = __('Expires at:', 'contentscheduler');
            }
            return $columns;
        } // end cs_add_expdate_column()
    
        // b. print / draw our column in the table, for each item
        function cs_show_expdate ($column_name) {
                global $wpdb, $post, $current_user;
                // Check to see if we really want to add our column
                // $options = get_option('ContentScheduler_Options');
                if( $this->options['show-columns'] == '1' ) {
                    // Check to see if current user has permissions to see
                    // What is minimum level required to see CS?
                    // must declare $current_user as global
                    $min_level = $this->options['min-level'];
                    // What is current user's level?
                    get_currentuserinfo();
                    $allcaps = $current_user->allcaps;
                    if( 1 != $allcaps[$min_level] )
                    {
                        return; // not authorized to see CS
                    }
                    // else - continue
                    $id = $post->ID;
                    if ($column_name === 'cs-exp-date')
                    {
                        // get the expiration value for this post
                        $query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = \"_cs-expire-date\" AND post_id=$id";
                        // get the single returned value (can do this better?)
                        $ed = $wpdb->get_var($query);
                        // determine whether expiration is enabled or disabled
                        if( get_post_meta( $post->ID, '_cs-enable-schedule', true) != 'Enable' )
                        {
                            $ed .= "<br />\n";
                            $ed .= __( '(Expiration Disabled)', 'contentscheduler' );
                        } // end if
                        echo $ed;
                    } // end if
                } // end if
            } // end cs_show_expdate()





        // ==================================================================
        // == SHORTCODES
        // ==================================================================
        // By request, ability to show the expiration date / time in the post itself.
        // Do I need to make this ability conditional? That is:
        // (a) show shortcodes to anyone viewing the content
        // (b) show shortcodes to certain user role and above
        // (c) do not show shortcodes to anyone
        // For now, I am just going to add the shortcode handler, with no options (0.9.2)
        // === TEMPLATE TAG NOTE ===
        // We'll add a template tag that will also call this function for output.
        // [cs_expiration]
        function handle_shortcode( $attributes )
        {
            global $post;
            global $current_user;
            // Check to see if we have rights to see stuff
            // $options = get_option('ContentScheduler_Options');
            $min_level = $this->options['min-level'];
            get_currentuserinfo();
            $allcaps = $current_user->allcaps;
            if( 1 != $allcaps[$min_level] )
            {
                return; // not authorized to see CS
            }
            // else - continue
            // get the expiration timestamp
            $expirationdt = get_post_meta( $post->ID, '_cs-expire-date', true );
            if ( empty( $expirationdt ) )
            {
                return false;
            }
            // We'll need the following if / when we allow formatting of the timestamp
            /*
            // we'll default to formats selected in Settings > General
            extract( shortcode_atts( array(
                'dateformat' => get_option('date_format'),
                'timeformat' => get_option('time_format')
                ), $attributes ) );
            // We always show date and time together
            $format = $dateformat . ' ' . $timeformat;
            return date( "$format", $expirationdt );
            */
            $return_string = sprintf( __("Expires: %s", 'contentscheduler'), $expirationdt );
            return $return_string;
        }





        // =======================================================================
        // == GENERAL UTILITY FUNCTIONS
        // =======================================================================
		// 11/17/2010 3:06:27 PM -pk
		// NOTE: We could add another parameter, '$format,' to support different date formats
		function check_date_format($date) {
			// match the format of the date
			// in this case, it is ####-##-##
			if (preg_match ("/^([0-9]{4})-([0-9]{2})-([0-9]{2})\ ([0-9]{2}):([0-9]{2}):([0-9]{2})$/", $date, $parts))
			{
				// check whether the date is valid or not
				// $parts[1] = year; $parts[2] = month; $parts[3] = day
				// $parts[4] = hour; [5] = minute; [6] = second
				if(checkdate($parts[2],$parts[3],$parts[1]))
				{
					// NOTE: We are only checking the HOUR here, since we won't make use of Min and Sec anyway
					if( $parts[4] <= 23 )
					{
						// time (24-hour hour) is okay
						return true;
					}
					else
					{
						// not a valid 24-hour HOUR
						return false;
					}
				}
				else
				{
					// not a valid date by php checkdate()
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		// ================================================================
		// handle timezones
		function setup_timezone() {
	        if ( ! $wp_timezone = get_option( 'timezone_string' ) )
			{
	            return false;
	        }
			// 11/5/2010 10:14:14 AM -pk
			// Set the default timezone used by Content Scheduler
			date_default_timezone_set( $wp_timezone );
		}
	} // end ContentScheduler Class
} // End IF Class ContentScheduler




global $pk_ContentScheduler;
if (class_exists("ContentScheduler")) {
	$pk_ContentScheduler = new ContentScheduler();
}

// ========================================================================
// == TEMPLATE TAG
// == For displaying the expiration date / time of the current post.
// == Must be used within the loop
function cont_sched_show_expiration( $args = '' )
{
	// $args should be empty, fyi
	if( !isset( $pk_ContentScheduler ) )
	{
		echo "<!-- Content Scheduler template tag unable to generate output -->\n";
	}
	else
	{
		$output = $pk_ContentScheduler->handle_shortcode();
		echo $output;	
	}
}
?>