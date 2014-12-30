<?php

/**
 * Fired during plugin activation
 *
 * @link       http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since      2.0.6
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      2.0.6
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 * @author     Paul Kaiser <paul.kaiser@gmail.com>
 */
class Content_Scheduler_Activator {
    // TODO there is a lot of throwing $network_wide around going on; fix that

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate( $network_wide ) {
        if (function_exists('is_multisite') && is_multisite()) {
            if ( $network_wide ) {
                // TODO there's a better way to go through all blogs in network
                global $wpdb;            
                $old_blog = $wpdb->blogid;
                // Get all blog ids
                $blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
                foreach ($blogids as $blog_id) {
                    switch_to_blog($blog_id);
                    $this->_activate( $network_wide );
                }
                switch_to_blog($old_blog);
                return;
            }
        }
        // if we get here, this is not even a multisite installation
        $this->_activate( false );
	}

    private function _activate( $network_wide ) {
    
        $this->_set_default_options( $network_wide );
        
        $this->_register_wpcron_events( $network_wide );
    }

    // Set some default options, with database migration if needed
    private function _set_default_options( $network_wide ) {
    	// load migration utilities class
    	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Content_Scheduler_Migration_Utilities.php';

        $options = get_option('ContentScheduler_Options');
        
        if( is_multisite() ) {
            $blog_id = get_current_blog_id();
            if( $network_wide ) {
                // do anything specific to network activation
            }
        }
        
        // Build an array of each option and its default setting
        // exp-default is supposed to be a serialized array of hours, days, weeks
        $expiration_default = array( 'exp-hours' => '0', 'exp-days' => '0', 'exp-weeks' => '0' );
        $arr_defaults = array
        (
            "version" => PEK_CONTENT_SCHEDULER_VERSION,
            "exp-status" => "1",
            "exp-period" => "60",
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
            "datepicker" => "1",
            "show-columns" => "0",
            "remove-cs-data" => "0",
            "exp-default" => $expiration_default,
            "chg-title" => "0",
            "title-add" => ""
        );

        // TODO break database migrations into one separate class
        // Some database migration from older versions of Content Scheduler
        if( is_array( $options ) ) {
            // We need to update the version string to our current version(??)
            // $options['version'] = PEK_CONTENT_SCHEDULER_VERSION;
            // we need to check / run migrations on existing options from database
            $options = Content_Scheduler_Migration_Utilities::migrate( $options );
            // we need to merge existing options with default options, which might have new / unavailable values            
            // $new_options = array_replace( $arr_defaults, $options ); // array_replace only in PHP 5.3+
            foreach( $options as $key => $val ) {
                $arr_defaults[$key] = $val;
            }
        }
        // one way or the other, arr_defaults is now munged into what we want (yes, naming seems odd)
        $options = $arr_defaults;
        // push munged options to database
        update_option('ContentScheduler_Options', $options);    
    }
    
    // Register our expiration and notification events into wp-cron schedule
    private function _register_wpcron_events( $network_wide ) {
        if( is_multisite () ) {
            $blog_id = get_current_blog_id();
            if ( !wp_next_scheduled( 'contentscheduler' . $blog_id ) ) {
                wp_schedule_event( time(), 'contsched_usertime', 'contentscheduler' . $blog_id );
            }
        } else {
            if ( !wp_next_scheduled( 'contentscheduler' ) ) {
                wp_schedule_event( time(), 'contsched_usertime', 'contentscheduler' );
            }
        }    
    }
} // end class