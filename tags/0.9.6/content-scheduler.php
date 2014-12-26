<?php
/*
Plugin Name: Content Scheduler
Plugin URI: http://structureweb.co/wordpress-plugins/content-scheduler/
Description: Set Posts and Pages to automatically expire. Upon expiration, delete, change categories, status, or unstick posts. Also notify admin and author of expiration.
Version: 0.9.6
Author: Paul Kaiser
Author URI: http://structureweb.co
License: GPL2
*/

/*  Copyright 2011  Paul Kaiser  (email : paul.kaiser@gmail.com)

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
if ( !function_exists('add_action') ) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// assign some constants if they didn't already get taken care of
if ( ! defined( 'WP_CONTENT_URL' ) )
	define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );








// Define our plugin's wrapper class
if ( !class_exists( "ContentScheduler" ) )
{
	class ContentScheduler
	{
		function ContentScheduler()
		{
			// Constructor
			register_activation_hook( __FILE__, array($this, 'run_on_activate') );
			register_deactivation_hook( __FILE__, array($this, 'run_on_deactivate') );

			// =============================================================
			// Actions
			// =============================================================
			add_action( 'init', array($this, 'content_scheduler_init') );
			// Adding Admin inits
			add_action('admin_init', array($this, 'init_admin'));
			// Add any JavaScript and CSS needed just for my plugin
			add_action( "admin_print_scripts-post-new.php", array($this, 'cs_edit_scripts') );
			add_action( "admin_print_scripts-post.php", array($this, 'cs_edit_scripts') );
			add_action( "admin_print_styles-post-new.php", array($this, 'cs_edit_styles') );
			add_action( "admin_print_styles-post.php", array($this, 'cs_edit_styles') );
			// adds our plugin options page
			add_action('admin_menu', array($this, 'ContentScheduler_addoptions_page_fn'));
			// add a cron action for expiration check and notification check
			if ( $this->is_network_site() )
			{
				add_action ('content_scheduler'.$current_blog->blog_id, array( $this, 'answer_expiration_event') );
				add_action ('content_scheduler_notify'.$current_blog->blog_id, array( $this, 'answer_notification_event') );
			}
			else
			{
				add_action ('content_scheduler', array( $this, 'answer_expiration_event') );
				add_action ('content_scheduler_notify', array( $this, 'answer_notification_event') );
			}
			// ==========
			// Adding Custom boxes (Meta boxes) to Write panels (for Post, Page, and Custom Post Types)
			add_action('add_meta_boxes', array($this, 'ContentScheduler_add_custom_box_fn'));
			// Do something with the data entered in the Write panels fields
			add_action('save_post', array($this, 'ContentScheduler_save_postdata_fn'));
			
			// ==========
			// Add column to Post / Page lists
			add_action ( 'manage_posts_custom_column', array( $this, 'cs_show_expdate' ) );
			add_action ( 'manage_pages_custom_column', array( $this, 'cs_show_expdate' ) );
			
			// =============================================================
			// Shortcodes
			// =============================================================
			add_shortcode('cs_expiration', array( $this, 'handle_shortcode' ) );

			// =============================================================
			// Filters
			// =============================================================
			add_filter('cron_schedules', array( $this, 'add_cs_cron_fn' ) );

			// Showing custom columns in list views
			add_filter ('manage_posts_columns', array( $this, 'cs_add_expdate_column' ) );
			add_filter ('manage_pages_columns', array( $this, 'cs_add_expdate_column' ) );
		} // end ContentScheduler Constructor








		// =============================================================
		// == Administration Init Stuff
		// =============================================================
		function init_admin()
		{
			// register_setting
			// add_settings_section
			// add_settings_field
			// form field drawing callback functions
			include "includes/init-admin.php";
		} // end init_admin()
		function content_scheduler_init()
		{
			$plugin_dir = basename(dirname(__FILE__)) . '/lang';
			load_plugin_textdomain( 'contentscheduler', null, $plugin_dir );
		}








// ========================================================================
// == Options Field Drawing Functions for add_settings
// ========================================================================
		// Determine expiration status: are we doing it, or not?
		// exp-status
		function draw_set_expstatus_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('0', __("Hold", 'contentscheduler'), __("Do nothing upon expiration.", 'contentscheduler') ),
							array('2', __("Delete", 'contentscheduler'), __("Move to trash upon expiration.", 'contentscheduler') ),
							array('1', __("Apply changes", 'contentscheduler'), __("Apply the changes below upon expiration.", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['exp-status'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[exp-status]' type='radio' /> $item[1] &mdash; $item[2]</label><br />";
			} // end foreach
		} // end draw_set_expstatus_fn()


		// 12/30/2010 3:03:11 PM -pk
		// Get the number of minutes they want wp-cron to wait between expiration checks.
		function draw_set_expperiod_fn()
		{
			// get this plugin's options from the database
			// This should have a default value of '1'
			$options = get_option('ContentScheduler_Options');
			$input_field = "<input id='exp-period' name='ContentScheduler_Options[exp-period]' size='10' type='text' value='{$options['exp-period']}' />";
			printf( __("Wait %s minutes between expiration checks."), $input_field);
			echo "<br />\n";
		} // end draw_set_expperiod_fn()

		
		// How do we change "Status?"
		// chg-status
		function draw_set_chgstatus_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('0', __("No Change", 'contentscheduler'), __("Do not change status.", 'contentscheduler') ),
							array('1', __("Pending", 'contentscheduler'), __("Change status to Pending.", 'contentscheduler') ),
							array('2', __("Draft", 'contentscheduler'), __("Change status to Draft.", 'contentscheduler') ),
							array('3', __("Private", 'contentscheduler'), __("Change visibility to Private.", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['chg-status'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[chg-status]' type='radio' /> $item[1] &mdash; $item[2]</label><br />";
			} // end foreach
		} // end draw_set_chgstatus_fn()
		
		// How do we change "Stickiness" (Stick post to home page)
		// chg-sticky
		function draw_set_chgsticky_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('0', __("No Change", 'contentscheduler'), __("Do not unstick posts.", 'contentscheduler')),
							array('1', __("Unstick", 'contentscheduler'), __("Unstick posts.", 'contentscheduler'))
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['chg-sticky'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[chg-sticky]' type='radio' /> $item[1] &mdash; $item[2]</label><br />";
			} // end foreach
		} // end draw_set_chgsticky_fn()
		
		// How do we apply the category changes below?
		// chg-cat-method
		function draw_set_chgcatmethod_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('0',  __("No Change", 'contentscheduler'),  __("Make no category changes.", 'contentscheduler')),
							array('1',  __("Add selected", 'contentscheduler'),  __("Add posts to selected categories.", 'contentscheduler')),
							array('2',  __("Remove selected", 'contentscheduler'),  __("Remove posts from selected categories.", 'contentscheduler')),
							array('3',  __("Match selected", 'contentscheduler'),  __("Make posts exist only in selected categories.", 'contentscheduler'))
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['chg-cat-method'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[chg-cat-method]' type='radio' /> $item[1] &mdash; $item[2]</label><br />";
			} // end foreach
		} // end draw_set_chgcatmethod_fn()
		
		// What categories do we have available to change to?
		// chg-categories
		function draw_set_categories_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// Draw a checkbox for each category
			$categories = get_categories( array('hide_empty' => 0) );
			foreach ( $categories as $category )
			{
				// See if we need a checkbox or not
				if( !empty( $options['selcats'] ) )
				{
					$checked = checked( 1, in_array( $category->term_id, $options['selcats'] ), false );
				}
				else
				{
					$checked = '';
				}
				$box = "<input name='ContentScheduler_Options[selcats][]' id='$category->category_nicename' type='checkbox' value='$category->term_id' class='' ".$checked." /> $category->name<br />\n";
				echo $box;
			} // end foreach
		} // end draw_set_categories_fn()
		
		// What tags do we want added to content types that support tags?
		// tags-to-add
		// Be sure to check the content type for post_tags support before attempting to add
		function draw_add_tags_fn()
		{
			// get this plugin's options from the database
			// This should have a default value of '1'
			$options = get_option('ContentScheduler_Options');
			$input_field = "<input id='tags-to-add' name='ContentScheduler_Options[tags-to-add]' size='40' type='text' value='{$options['tags-to-add']}' />";
			printf( __("%s%sComma-delimited list, e.g., 'news, martial arts, old content'%s(leave blank to add no tags.)%s", 'contentscheduler'), $input_field, '<br />', '<br />', '<br />');
		} // end draw_add_tags_fn()
		
		// Notification Settings
		// Notification on or off?
		function draw_notify_on_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Notification on", 'contentscheduler'), __("Notify when expiration date is reached, even if 'Expiration status' is set to 'Hold.'", 'contentscheduler')),
							array('0', __("Notification off", 'contentscheduler'), __("Do not notify.", 'contentscheduler'))
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['notify-on'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[notify-on]' type='radio' /> $item[1] &mdash; $item[2]</label><br />";
			} // end foreach
		} // draw_notify_on_fn()
		
		// Notify the site admin?
		function draw_notify_admin_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Notify Admin", 'contentscheduler') ),
							array('0', __("Do not notify Admin", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['notify-admin'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[notify-admin]' type='radio' /> $item[1]</label><br />";
			} // end foreach
		} // end draw_notify_admin_fn()
		
		// Notify the content author?
		function draw_notify_author_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Notify Author", 'contentscheduler') ),
							array('0', __("Do not notify Author", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['notify-author'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[notify-author]' type='radio' /> $item[1]</label><br />";
			} // end foreach
		} // end draw_notify_author_fn
		
		// Set minimum level to see Content Scheduler fields and shortcodes
		// http://codex.wordpress.org/Roles_and_Capabilities#Roles
		function draw_min_level_fn()
		{
			$options = get_option('ContentScheduler_Options');
			$items = array(
							array("super admin", 10),
							array("administrator", 8),
							array("editor", 5),
							array("author", 2),
							array("contributor", 1),
							array("subscriber", 0)
							);
			echo "<select id='min-level' name='ContentScheduler_Options[min-level]'>\n";

			foreach( $items as $item )
			{
				$checked = ($options['min-level'] == $item[1] ) ? ' selected="selected" ' : ' ';
				echo "<option".$checked." value='$item[1]'>$item[0]</option>\n";
			}
			echo "</select>\n";
		} // end draw_min_level_fn()
		
		// Notify upon expiration?
		function draw_notify_expire_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Notify on expiration", 'contentscheduler'), __("Notify when expiration changes posts / pages.", 'contentscheduler') ),
							array('0', __("Do not notify on expiration", 'contentscheduler'), __("Do not notify when expiration changes posts / pages.", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['notify-expire'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[notify-expire]' type='radio' /> $item[1] &mdash; $item[2]</label><br />";
			} // end foreach
		} // draw_notify_expire_fn()

		// Notify number of days before expiration?
		function draw_notify_before_fn()
		{
			// get this plugin's options from the database
			// This should have a default value of '0'
			$options = get_option('ContentScheduler_Options');
			$input_field = "<input id='notify-before' name='ContentScheduler_Options[notify-before]' size='10' type='text' value='{$options['notify-before']}' />";
			printf( __("Notify %s days before expiration.%s", 'contentscheduler'), $input_field, '<br />');
		} // end draw_notify_before_fn()
		
		// Show expiration date in columnar lists?
		function draw_show_columns_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Show expiration in columns", 'contentscheduler') ),
							array('0', __("Do not show expiration in columns", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['show-columns'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[show-columns]' type='radio' /> $item[1]</label><br />";
			} // end foreach
		} // end draw_show_columns_fn
		
		// Use jQuery datepicker for the date field?
		function draw_show_datepicker_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Use datepicker", 'contentscheduler') ),
							array('0', __("Do not use datepicker", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['datepicker'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[datepicker]' type='radio' /> $item[1]</label><br />";
			} // end foreach
		} // end draw_show_datepicker_fn
		
		// Remove all CS data upon uninstall?
		function draw_remove_data_fn()
		{
			// get this plugin's options from the database
			$options = get_option('ContentScheduler_Options');
			// make array of radio button items
			$items = array(
							array('1', __("Remove all data", 'contentscheduler') ),
							array('0', __("Do not remove data", 'contentscheduler') )
							);
			// Step through and spit out each item as radio button
			foreach( $items as $item )
			{
				$checked = ($options['remove-cs-data'] == $item[0] ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item[0]' name='ContentScheduler_Options[remove-cs-data]' type='radio' /> $item[1]</label><br />";
			} // end foreach
		} // end draw_remove_data_fn()

		// version as read-only?
		function draw_plugin_version()
		{
			$options = get_option('ContentScheduler_Options');
  			echo "<input type='text' name='ContentScheduler_Options[version]' value='$options[version]' readonly='readonly' />";
		} // end draw_plugin_version()






// ========================================================================
// == JavaScript and CSS Enqueueing?
// ========================================================================
		// enqueue the jQuery UI things we need for using the datepicker
		function cs_edit_scripts()
		{
			if (function_exists('wp_enqueue_script' ) )
			{
				// Check option 'datepicker'
				$options = get_option('ContentScheduler_Options');
				if( $options['datepicker'] == '1' )
				{
					// Get the path to our plugin directory, and then append the js/whatever.js
					// Path for Any+Time solution
					$anytime_path = plugins_url('/js/anytime/anytimec.js', __FILE__);
					$csanytime_path = plugins_url('/js/anytime/cs-anypicker.js', __FILE__);
					// Any of these solutions require jquery
					wp_enqueue_script('jquery');
					// enqueue the Any+Time script
					wp_enqueue_script('anytime', $anytime_path, array('jquery') );
					// enqueue the script for our field (does this have to come AFTER the field is in the HTML?)
					wp_enqueue_script('csanytime', $csanytime_path, array('jquery','anytime') );
					// DONE with scripts for date-time picker
				}
			}
		} // end cs_edit_scripts()
		
		function cs_edit_styles()
		{
			if (function_exists('wp_enqueue_style') )
			{
				// Check option 'datepicker'
				$options = get_option('ContentScheduler_Options');
				if( $options['datepicker'] == '1' )
				{
					// Styles for the jQuery Any+Time datepicker plugin
					$anytime_path = plugins_url('/js/anytime/anytimec.css', __FILE__);
					wp_register_style('anytime', $anytime_path);
					wp_enqueue_style('anytime');
				}
			}
		} // end cs_edit_styles()








// ====================================================================
// == Administration Menus Stuff
// ====================================================================
		function ContentScheduler_addoptions_page_fn()
		{
			// Make sure we should be here
			if (!function_exists('current_user_can') || !current_user_can('manage_options') )
			return;
			
			// Add our plugin options page
			if ( function_exists( 'add_options_page' ) )
			{
				add_options_page(
					__('Content Scheduler Options Page', 'contentscheduler'),
					__('Content Scheduler', 'contentscheduler'),
					'manage_options',
					'ContentScheduler_options',
					array('ContentScheduler', 'ContentScheduler_drawoptions_fn' ) );
			}
		} // end admin_menus()
		
		// Show our Options page in Admin
		function ContentScheduler_drawoptions_fn()
		{
			// Get our current options out of the database? -pk
			$ContentScheduler_Options = get_option('ContentScheduler_Options');
			?>
			<div class="wrap">
				<?php screen_icon("options-general"); ?>
				<h2>Content Scheduler <?php echo $ContentScheduler_Options['version']; ?></h2>
				<form action="options.php" method="post">
				<?php
				// nonces - hidden fields - auto via the SAPI
				settings_fields('ContentScheduler_Options_Group');
				// spits out fields defined by settings_fields and settings_sections
				do_settings_sections('ContentScheduler_Page_Title');
				?>
					<p class="submit">
					<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'contentscheduler'); ?>" />
					</p>
				</form>
			</div>
			<?php
		} // end sample_form()
		
		// Prints an overview under the Settings Section Title
		// Could be used for help strings, whatever.
		// I think I've seen some plugins do jQuery accordian to hide / show help.
		function draw_overview()
		{
			// This shows things under the title of Expiration Settings
			echo "<p>";
			_e( 'Indicate whether to process content on expiration, and whether to delete it or make certain changes to it.', 'contentscheduler' );
			echo "</p>\n";
			
		} // end overview_settings()
		
		function draw_overview_not()
		{
			// This shows things under the title of Notification Settings
			echo "<p>";
			_e( 'Indicate whether to send notifications about content expiration, who to notify, and when they should be notified.', 'contentscheduler' );
			echo "</p>\n";
		} // end draw_overview_not()
		
		function draw_overview_disp()
		{
			// This shows things under the title of Display Settings
			echo "<p>";
			_e( 'Control how Content Scheduler custom input areas display in the WordPress admin area. Also indicate if deleting the plugin should remove its options and post metadata.', 'contentscheduler' );
			echo "</p>\n";
		} // end draw_overview_disp()

// ==========================================================================
// == Set options to defaults - used during plugin activation
		// define default option settings

		// ====================
		function run_on_activate()
		{
			global $wpdb;
			// See if this is a multisite install
			if ( function_exists( 'is_multisite' ) && is_multisite() )
			{
				// See if the plugin is being activated for the entire network of blogs
				if ( isset( $_GET['networkwide'] ) && ( $_GET['networkwide'] == 1 ) )
				{
					// Save the current blog id
					$orig_blog = $wpdb->blogid;
					// Loop through all existing blogs (by id)
					$all_blogs = $wpdb->get_col( $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs") );
					foreach ($all_blogs as $blog_id)
					{
						switch_to_blog( $blog_id );
						$this->activate_function( $blog_id );
					} // end foreach
					// switch back to the original blog
					switch_to_blog( $orig_blog );
					return;
				} // end if
			} // end if
			// Seems like it is not a multisite install OR it is a single blog of a multisite
			$this->activate_function('');
		} // end run_on_activate()
		
		// tied to the run_on_activate function above
		function activate_function( $current_blog_id = '' )
		{
			$this->setup_timezone();
			// Let's see about setting some default options
			$options = get_option('ContentScheduler_Options');
			// Build an array of each option and its default setting
			$arr_defaults = array
			(
			    "version" => "0.9.6",
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
			    "notify-before" => "0",
			    "min-level" => "2",
			    "show-columns" => "0",
			    "datepicker" => "0",
			    "remove-cs-data" => "0"
			);
			
			// check to see if we need to set defaults
			// first condition is that the 'restore defaults' checkbox is on (we don't have that yet.)
			// OR condition is that defaults haven't even been set
			if( !is_array( $options )  )
			{
				// We can safely set options to defaults
				update_option('ContentScheduler_Options', $arr_defaults);
			}
			else
			{
				// we found some ContentScheduler_Options in the database
				// We need to check the "version" and, if it is less than 0.9.5 or non-existent, we need to convert english string values to numbers
				if( !isset( $options['version'] ) || $options['version'] < '0.9.5' )
				{
					// we want to change options from english strings to numbers
					switch( $options['exp-status'] )
					{
						case 'Hold':
							$options['exp-status'] = '0';
							break;
						case 'Delete':
							$options['exp-status'] = '2';
							break;
						default:
							$options['exp-status'] = '1';
					} // end switch
					
					switch( $options['chg-status'] )
					{
						case 'No Change':
							$options['chg-status'] = '0';
							break;
						case 'Pending':
							$options['chg-status'] = '1';
							break;
						case 'Private':
							$options['chg-status'] = '3';
							break;
						default:
							$options['chg-status'] = '2';
					}
					
					/*
					$r = (1 == $v) ? 'Yes' : 'No'; // $r is set to 'Yes'
					$r = (3 == $v) ? 'Yes' : 'No'; // $r is set to 'No'
					*/
					$options['chg-sticky'] = ( 'No Change' == $options['chg-sticky'] ) ? '0' : '1';
					
					switch( $options['chg-cat-method'] )
					{
						case 'Add selected':
							$options['chg-cat-method'] = '1';
							break;
						case 'Remove selected':
							$options['chg-cat-method'] = '2';
							break;
						case 'Match selected':
							$options['chg-cat-method'] = '3';
							break;
						default:
							$options['chg-cat-method'] = '0';
					}
					
					$options['notify-on'] = ( 'Notification off' == $options['notify-on'] ) ? '0' : '1';
					
					$options['notify-admin'] = ( 'Do not notify admin' == $options['notify-admin'] ) ? '0' : '1';
					
					$options['notify-author'] = ( 'Do not notify author' == $options['notify-author'] ) ? '0' : '1';
					
					$options['notify-expire'] = ( 'Do not notify on expiration' == $options['notify-expire'] ) ? '0' : '1';
					
					$options['show-columns'] = ( 'Do not show expiration in columns' == $options['show-columns'] ) ? '0' : '1';
					
					$options['datepicker'] = ( 'Do not use datepicker' == $options['datepicker'] ) ? '0' : '1';
					
					$options['remove-cs-data'] = ( 'Do not remove data' == $options['remove-cs-data'] ) ? '0' : '1';
					
					// don't forget to do array_replace when we're done?? Or what?
					// This whole block should perhaps be placed in a function
				}
				
				// make sure we have added any updated options
				if (!function_exists('array_replace'))
				{
					// we're before php 5.3.0, and need to use our array_replace
					$new_options = $this->array_replace( $arr_defaults, $options );
				}
				else
				{
					// go ahead and use php 5.3.0 array_replace
					$new_options = array_replace( $arr_defaults, $options );
				}
				
				update_option('ContentScheduler_Options', $new_options);
			}

			// We need to get our expiration event into the wp-cron schedules somehow
			if( $current_blog_id != '' )
			{
				// it is a networked site activation
				// for expirations
				wp_schedule_event( time(), 'contsched_usertime', 'content_scheduler_'.$current_blog_id );
				// for notifications
				wp_schedule_event( time(), 'daily', 'content_scheduler_notify_'.$current_blog_id );
			}
			else
			{
				// it is not a networked site activation, or a single site within a network
				// for expirations
				wp_schedule_event( time(), 'contsched_usertime', 'content_scheduler' );
				// for notifications
				wp_schedule_event( time(), 'daily', 'content_scheduler_notify' );
			}
		} // end activate_function
		
		// ====================
		function run_on_deactivate()
		{
			global $wpdb;
			// See if this is a multisite install
			if ( function_exists( 'is_multisite' ) && is_multisite() )
			{
				// See if the plugin is being activated for the entire network of blogs
				if ( isset( $_GET['networkwide'] ) && ( $_GET['networkwide'] == 1 ) )
				{
					// Save the current blog id
					$orig_blog = $wpdb->blogid;
					// Loop through all existing blogs (by id)
					$all_blogs = $wpdb->get_col( $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs") );
					foreach ($all_blogs as $blog_id)
					{
						switch_to_blog( $blog_id );
						$this->deactivate_function( $blog_id );
					} // end foreach
					// switch back to the original blog
					switch_to_blog( $orig_blog );
					return;
				} // end if
			} // end if
			// Seems like it is not a multisite install OR it is a single blog of a multisite
			$this->deactivate_function('');
		} // end run_on_activate()

		function deactivate_function( $current_blog_id )
		{
			if( $current_blog_id != '' )
			{
				// it is a networked site activation
				// for expirations
				wp_clear_scheduled_hook('content_scheduler_'.$current_blog_id);
				// for notifications
				wp_clear_scheduled_hook('content_scheduler_notify_'.$current_blog_id);
			}
			else
			{
				// for expirations
				wp_clear_scheduled_hook('content_scheduler');
				// for notifications
				wp_clear_scheduled_hook('content_scheduler_notify');
			}
		} // end deactivate_function()








// ==========================================================================
// == Validation Function for Options
// ====================================================
		// Validates values from the ADMIN ContentScheduler_Options group
		function validate_settings($input)
		{
			// We need a value for exp-period
			if ( empty( $input['exp-period'] ) )
			{
				$input['exp-period'] = 1; // 1 minute
			}
			else
			{
				// exp-period was not empty, so we need to make sure it is an integer
				if ( ! sprintf("%u", $input['exp-period']) == $input['exp-period'] )
				{
					// exp-period was not an integer, so let's return an error
					// First, let's set it to an acceptable value
					$input['exp-period'] = 1; // 1 minute
					
					add_settings_error('ContentScheduler_Options',
						'settings_updated',
						__('Only positive integers are accepted for "Expiration period".', 'contentscheduler'),
						'error');
				}
			}
			
			// Make sure tags are alphanumeric and that is all
			// Testing tags-to-add, which should be a comma-delimited list of alphanumerics
			if ( !empty( $input['tags-to-add'] ) )
			{
				$input['tags-to-add'] = filter_var( $input['tags-to-add'], FILTER_SANITIZE_STRING );
			}
				
			// Make sure notify-before is an integer value
			if ( empty( $input['notify-before'] ) )
			{
				$input['notify-before'] = '0';
				// return $input;
			}
			else
			{
				// if it's not empty, let's keep moving
				if ( ! sprintf("%u", $input['notify-before']) == $input['notify-before'])
				{
					// register an error, officially
					add_settings_error('ContentScheduler_Options',
						'settings_updated',
						__('Only positive integers are accepted for "Notify before expiration".', 'contentscheduler'),
						'error');
				}
			}
			// if we had an error, do we still return? Or not?
			return $input;
		} // end validate_settings()








// =================================================================
// == Functions for using Custom Controls / Panels
// == in the Post / Page / Link writing panels
// == e.g., a custom field for an expiration date, etc.
// =================================================================
		// Adds a box to the main column on the Post and Page edit screens
		// This is the function called by the action hook
		// 3/21/2011 12:36:13 PM -pk
		// Should now add to custom post types, as well
		// 3/25/2011 11:41:48 AM -pk
		// We'll rig so it only shows if user is min-level or above
		function ContentScheduler_add_custom_box_fn()
		{
			global $current_user;
		
			// What is minimum level required to see CS?
			$options = get_option('ContentScheduler_Options');
			$min_level = $options['min-level'];
			// What is current user's level?
			get_currentuserinfo();
			$user_level = $current_user->data->user_level;
			if( $user_level < $min_level )
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
		} // end myplugin_add_custom_box()
		
		// Prints the box content
		function ContentScheduler_custom_box_fn()
		{
			// need $post in global scope so we can get id?
			global $post;

			// Use nonce for verification
			wp_nonce_field( plugin_basename(__FILE__), 'ContentScheduler_noncename' );
			// Get the current value, if there is one
			$the_data = get_post_meta( $post->ID, 'cs-enable-schedule', true );
			// Checkbox for scheduling this Post / Page, or ignoring
			$items = array( "Disable", "Enable");
			foreach( $items as $item)
			{
				$checked = ( $the_data == $item ) ? ' checked="checked" ' : '';
				echo "<label><input ".$checked." value='$item' name='cs-enable-schedule' id='cs-enable-schedule' type='radio' /> $item</label>  ";
			} // end foreach
			echo "<br />\n<br />\n";
			// Field for datetime of expiration
			$datestring = ( get_post_meta( $post->ID, 'cs-expire-date', true) );
			// Should we check for format of the date string? (not doing that presently)
			echo '<label for="cs-expire-date">' . __("Expiration date and hour", 'contentscheduler' ) . '</label><br />';
			echo '<input type="text" id="cs-expire-date" name="cs-expire-date" value="'.$datestring.'" size="25" />';
			echo ' Input date and time as: Year-Month-Day Hour:00:00 e.g., 2010-11-25 08:00:00<br />';

		} // end ContentScheduler_custom_box_fn()
		
		// When the post is saved, saves our custom data
		function ContentScheduler_save_postdata_fn( $post_id )
		{
			// verify this came from our screen and with proper authorization,
			// because save_post can be triggered at other times
			if ( !wp_verify_nonce( $_POST['ContentScheduler_noncename'], plugin_basename(__FILE__) ))
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
			// Checkbox for "enable scheduling"
			$enabled = $_POST['cs-enable-schedule'];
			// Value should be either 'Enabled' or 'Disabled'; otherwise something is screwy
			if( $enabled != 'Enable' AND $enabled != 'Disable' )
			{
				// $enabled is something we don't expect
				// let's make it empty
				$enabled = 'Disabled';
			}
			// Textbox for "expiration date"
			$date = $_POST['cs-expire-date'];
			// How can we check a myriad of date formats??
			// Right now we are mm/dd/yyyy
			if( ! $this->check_date_format( $date ) )
			{
				// It was not a valid date format
				// Normally, we would set to ''
				$date = '';
				// For debug, we will set to 'INVALID'
				$date = 'INVALID';
			}
			// We probably need to store the date differently,
			// and handle timezone situation
			update_post_meta( $post_id, 'cs-enable-schedule', $enabled );
			update_post_meta( $post_id, 'cs-expire-date', $date );
			
			return true;
		} // end ContentScheduler_save_postdata()








// =======================================================================
// == SCHEDULING FUNCTIONS
// =======================================================================
		// we want cron to run once per hour
		// This is hooked in our constructor
		function add_cs_cron_fn($array)
		{
			// Normally, we'll set interval to like 3600 (one hour)
			// For testing, we can set it to like 120 (2 min)
			// We're ading 'contsched_usertime' item to the array of crons
			// 12/30/2010 11:03:28 AM
			// We want to let this be a settable option.
			// Do we do that here?? I think so.
			// 1. Check options for desired interval.
			$options = get_option('ContentScheduler_Options');
			if( ! empty( $options['exp-period'] ) )
			{
				// we have a value, use it
				$period = $options['exp-period'];
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
	// == WP-CRON RESPONDERS
	// =======================================================
		// ====================
		// Respond to an hourly call from wp-cron checking for expired Posts / Pages
		function answer_expiration_event()
		{
			// we should get our options right now, and decide if we need to proceed or not.
			$options = get_option('ContentScheduler_Options');
						
			// Do we need to process expirations?
			if( $options['exp-status'] != '0' )
			{				
				// We need to process expirations
				$this->process_expirations();
			} // end if
		}
		// ====================
		// Respond to a daily call from wp-cron checking for notification needs
		function answer_notification_event()
		{
			// we should get our options right now, and decide if we need to proceed or not.
			$options = get_option('ContentScheduler_Options');

			// Do we need to process notifications?
			if( $options['notify-on'] != '0' )
			{
				// We need to process notifications
				// Note, these are only notifications that occur as a warning
				// BEFORE expiration
				$this->process_notifications();
			} // end if
		} // end answer_notification_event()
		
		// ==========================================================
		// Process Expirations
		// ==========================================================
		function process_expirations()
		{
			// Check database for posts meeting expiration criteria
			// Hand them off to appropriate functions
			include 'includes/process-expirations.php';
		} // end process_expirations()
		
		
		
		
		
		
		
		
		
		// =============================================================
		// Process Notifications
		// =============================================================
		function process_notifications()
		{
			// Check database for posts meeting notification-only criteria
			// Hand them off to appropriate functions
			include 'includes/process-notifications.php';			
		} // end process_notifications()








		// =============================================================
		// == Perform NOTIFICATIONs
		// =============================================================
		// This function takes an array of arrays.
		// The arrays contain a post_id and an array of user_ids
		// The function then compiles one email per user and sends it by email.
		// This method takes one item in the outside array per Post.
		function do_notifications( $posts_to_notify, $why_notify )
		{
			// notify people of expiration or pending expiration
			include 'includes/send-notifications.php';	
		} // end do_notifications()








// 11/23/2010 11:45:27 AM -pk
// Somehow, we need to retrieve the OPTIONS only Once, and then act upon them.
// For now, let's just write some code and see what happens.
// I am thinking these process_ functions could all be handed options, though
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
		// add our column to the table
		function cs_add_expdate_column ($columns)
		{
			// Check to see if we really want to add our column
			$options = get_option('ContentScheduler_Options');
			if( $options['show-columns'] == '1' )
			{
				// we're just adding our own item to the already existing $columns array
			  	$columns['cs-exp-date'] = __('Expires at:', 'contentscheduler');
			}
		  	return $columns;
		} // end cs_add_expdate_column()

		// fill our column in the table, for each item
		function cs_show_expdate ($column_name)
		{
			global $wpdb, $post;
			// Check to see if we really want to add our column
			$options = get_option('ContentScheduler_Options');
			if( $options['show-columns'] == '1' )
			{
				$id = $post->ID;
				if ($column_name === 'cs-exp-date')
				{
					// get the expiration value for this post
					$query = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = \"cs-expire-date\" AND post_id=$id";
					// get the single returned value (can do this better?)
					$ed = $wpdb->get_var($query);
					// determine whether expiration is enabled or disabled
					if( get_post_meta( $post->ID, 'cs-enable-schedule', true) != 'Enable' )
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
		
		
// [cs_expiration]
// We removed the ability to format your timestamp, since the timestamp is just a string
function handle_shortcode( $attributes )
{
	global $post;
	global $current_user;

	// Check to see if we have rights to see stuff
	$options = get_option('ContentScheduler_Options');
	$min_level = $options['min-level'];
	
	get_currentuserinfo();
	
	$user_level = $current_user->data->user_level;
	
	if( $user_level < $min_level )
	{
		// we're NOT okay to proceed
		return "";
	}
	// else we can proceed

	// get the expiration timestamp
    $expirationdt = get_post_meta( $post->ID, 'cs-expire-date', true );
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
	$return_string = printf(__("Expires: %s", 'contentscheduler'), $expirationdt);
	return $return_string;
}

// =======================================================================
// == GENERAL UTILITY FUNCTIONS
// =======================================================================
// 3/28/2011 12:21:31 AM -pk
// Added for pre 5.3 php compatibility
// NOTE there is a function of the same name in php 5.3.0+, but this one is within our Class
function array_replace( &$array, &$array1 )
{
  $args = func_get_args();
  $count = func_num_args();

  for ($i = 0; $i < $count; ++$i) {
    if (is_array($args[$i])) {
      foreach ($args[$i] as $key => $val) {
        $array[$key] = $val;
      }
    }
    else {
      trigger_error(
        __FUNCTION__ . '(): Argument #' . ($i+1) . ' is not an array',
        E_USER_WARNING
      );
      return NULL;
    }
  }

  return $array;
}

		// 11/17/2010 3:06:27 PM -pk
		// NOTE: We could add another parameter, '$format,' to support different date formats
		function check_date_format($date)
		{
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
		// Detect WordPress Network Install
		function is_network_site()
		{
			if ( function_exists( 'is_multisite' ) && is_multisite() )
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		
		// ===============================================================
		// Logging for development
		/*
		function log_to_file($filename, $msg)
		{
			// open file
			$fd = fopen($filename, "a");
			// append date/time to message
			$this->setup_timezone();
			$str = "[" . date("Y/m/d H:i:s") . "] " . $msg;
			// write string
			fwrite($fd, $str . "\n");
			// close file
			fclose($fd);
		}
		*/







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








// Instantiating the Class
// 10/27/2010 8:27:59 AM -pk
// NOTE that instantiating is OUTSIDE of the Class
if (class_exists("ContentScheduler")) {
	$pk_ContentScheduler = new ContentScheduler();
}
?>
