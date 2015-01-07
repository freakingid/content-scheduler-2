<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since      2.0.6
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      2.0.6
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/includes
 * @author     Paul Kaiser <paul.kaiser@gmail.com>
 */
class Content_Scheduler_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate( $network_wide ) {
        if (function_exists('is_multisite') && is_multisite()) {
            if ( $network_wide ) {
                // at this time, we want to disallow network activation for CS
                deactivate_plugins( plugin_basename( __FILE__ ), TRUE, TRUE );
                header( 'Location: ' . network_admin_url( 'plugins.php?deactivate=true' ) );
            }
            // once we need network activation, here's the code
            // Note, we do not want network activation to be a thing for CS right now
            // TODO there's a better way to go through all blogs in network
            global $wpdb;
            $old_blog = $wpdb->blogid;
            // Get all blog ids
            $blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                Content_Scheduler_Deactivator::deactivate_current_site( $network_wide );
            }
            switch_to_blog($old_blog);
            return;
        }
        // if we get here, this is not even a multisite installation
        deactivate_current_site( false );
	}
	
    // Clear our expiration events from wp-cron schedule
    public static function deactivate_current_site( $network_wide ) {
        if( is_multisite() ) {
            $blog_id = get_current_blog_id();
            if( $network_wide ) {
                // anything specific to do for network deactivation                
            }
            // non-network multisite deactivation
            wp_clear_scheduled_hook( 'contentscheduler' . $blog_id );
        } else {
            // non multisite deactivation
            wp_clear_scheduled_hook( 'contentscheduler' );
        }
    }
}


        
        
        
        