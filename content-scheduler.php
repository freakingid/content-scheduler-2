<?php
/**
 * bootstrapping
 * @link            http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since           2.0.6
 * @package         Content_Scheduler
 *
 * @wordpress-plugin
 * Plugin Name: Content Scheduler
 * Plugin URI: http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * Description: Set Posts and Pages to automatically expire. Upon expiration, delete, change categories, status, or unstick posts. Also notify admin and author of expiration.
 * Version: 2.0.6
 * Author: Paul Kaiser
 * Author URI: http://paulekaiser.com
 * License: GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     content-scheduler
 * Domain Path:     /lang
*/

// avoid direct calls to this file, because now WP core and framework have been used
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Activation; Network activation included
function activate_content_scheduler( $network_wide ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/Content_Scheduler_Activator.php';
    Content_Scheduler_Activator::activate( $network_wide );
}

// Deactivation; Network deactivation included
function deactivate_content_scheduler( $network_wide ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/Content_Scheduler_Deactivator.php';
    ContentSchedulContent_Scheduler_DeactivatorerDeactivator::deactivate( $network_wide );
}

register_activation_hook( __FILE__, 'activate_content_scheduler' );
register_deactivation_hook( __FILE__, 'deactivate_content_scheduler' );

/**
 * Core class
 * i18n; dashboard hooks; public-facing hooks
 */
require plugin_dir_path( __FILE__ ) . 'includes/Content_Scheduler.php';

/**
 * Kick things off
 */
function run_content_scheduler() {
    $plugin = new ContentScheduler();
    $plugin->run();
}
run_content_scheduler();