<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since      2.0.6
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/public
 * @author     Paul Kaiser <paul.kaiser@gmail.com>
 */
class Content_Scheduler_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 * @var      string    $Content_Scheduler    The ID of this plugin.
	 */
	private $Content_Scheduler;

	/**
	 * The version of this plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.6
	 * @var      string    $Content_Scheduler       The name of the plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct( $Content_Scheduler, $version ) {

		$this->Content_Scheduler = $Content_Scheduler;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    2.0.6
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Content_Scheduler_Public_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Content_Scheduler_Public_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		// wp_enqueue_style( $this->Content_Scheduler, plugin_dir_url( __FILE__ ) . 'css/plugin-name-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    2.0.6
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Content_Scheduler_Public_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Content_Scheduler_Public_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		// wp_enqueue_script( $this->Content_Scheduler, plugin_dir_url( __FILE__ ) . 'js/plugin-name-public.js', array( 'jquery' ), $this->version, false );

	}
	
    // ==================================================================
    // == SHORTCODES
    // ==================================================================
    // By request, ability to show the expiration date / time in the post itself.
    // [cs_expiration]
    public function handle_shortcode( $attributes ) {
        global $post;
        global $current_user;
        // Check to see if we have rights to see stuff
        $min_level = $this->options['min-level'];
        get_currentuserinfo();
        $allcaps = $current_user->allcaps;
        if( 1 != $allcaps[$min_level] )
        {
            return; // not authorized to see CS
        }
        // else - continue
        // get the expiration timestamp
        $timestamp = get_post_meta( $post->ID, '_cs-expire-date', true );
        if ( empty( $timestamp ) )
        {
            return false;
        } else {
            $expirationdt = DateUtilities::getReadableDateFromTimestamp( $timestamp );
            if( false === $expirationdt ) {
                // we were unable to get a date from the timestamp
                $expirationdt = "Unable to convert timestamp to readable date.";
            }
        }

        $return_string = sprintf( __("Expires: %s", 'contentscheduler'), $expirationdt );
        return $return_string;
    }

    // ==========================================================
    // == WP-CRON Related Stuff
    // ==========================================================
    // Specify a custom interval for wp-cron checking
    public function add_cs_cron_fn($array) {
        // we need to re-fetch options
        // had to add this when updating cron after saving period options
        $this->options = get_option('ContentScheduler_Options');
        // Normally, we'll set interval to like 3600 (one hour)
        // For testing, we can set it to like 120 (2 min)
        // 1. Check options for desired interval.
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
    }

    // =======================================================
    // == WP-CRON RESPONDERS
    // =======================================================
    // Respond to a call from wp-cron checking for expired Posts / Pages
    public function answer_expiration_event() {
        // Do we need to process expirations?
        if( $this->options['exp-status'] != '0' ) {				
            // We need to process expirations
            $this->process_expirations();
        }
    }

    // ==========================================================
    // Process Expirations
    // ==========================================================
    public function process_expirations() {
        // Check database for posts meeting expiration criteria
        // Hand them off to appropriate functions
        // include 'includes/process-expirations.php';
// find posts that need to take some expiration action
        global $wpdb;
        
        if( $this->debug ) {
            $details = get_blog_details( get_current_blog_id() );
        }

        // select all Posts / Pages that have "enable-expiration" set and have expiration date older than right now
        $querystring = 'SELECT postmetadate.post_id 
            FROM 
            ' .$wpdb->postmeta. ' AS postmetadate, 
            ' .$wpdb->postmeta. ' AS postmetadoit, 
            ' .$wpdb->posts. ' AS posts 
            WHERE postmetadoit.meta_key = "_cs-enable-schedule" 
            AND postmetadoit.meta_value = "Enable" 
            AND postmetadate.meta_key = "_cs-expire-date" 
            AND postmetadate.meta_value <= "' . time() . '" 
            AND postmetadate.post_id = postmetadoit.post_id 
            AND postmetadate.post_id = posts.ID 
            AND posts.post_status = "publish"';
        $result = $wpdb->get_results($querystring);
        // Act upon the results
        if ( ! empty( $result ) )
        {
            // stop, we don't want to notify UNLESS the post gets expired and set to expiration disabled
            if( $this->options['notify-on'] == '1' )
            {
                // build array of posts to send to do_notifications
                $posts_to_notify_on = array();
                foreach ( $result as $cur_post )
                {
                    $posts_to_notify_on[] = $cur_post->post_id;
                }
                // call the notification function
                $this->do_notifications($posts_to_notify_on, 'expired');
            } // end if for notification on expiration
            // Shortcut: If exp-status = "Delete" then let's just delete and get on with things.
            if( $this->options['exp-status'] == '2' )
            {
                // Delete all those posts
                foreach ( $result as $cur_post )
                {
                    // Move the item to the trash
                    wp_delete_post( $cur_post->post_id );
                } // end foreach
            }
            else
            {
                // Proceed with the updating process	      	        
                // step through the results
                foreach ( $result as $cur_post )
                {
                    // find out if it is a Page, Post, or what
                    $post_type = $wpdb->get_var( 'SELECT post_type FROM ' . $wpdb->posts .' WHERE ID = ' . $cur_post->post_id );
                    if ( $post_type == 'post' )
                    {
                        $this->process_post( $cur_post->post_id );
                    }
                    elseif ( $post_type == 'page' )
                    {
                        $this->process_page( $cur_post->post_id );
                    }
                    else
                    {
                        // it could be a custom post type
                        $this->process_custom( $cur_post->post_id );
                    } // end if
                } // end foreach
            } // end if (checking for DELETE)
        } // endif

    } // end process_expirations()

    // ====================
    // Do whatever we need to do to expired POSTS
    function process_post($postid) {
        // include "includes/process-post.php";
        // STICKINESS (Pages do not have STICKY ability)
        // Note: This is stored in the options table, and is not part of post_update
        // get the array of sticky posts
        // What do we want to do with stickiness?
        $sticky_change = $this->options['chg-sticky'];
        if( $sticky_change == '1' )
        {
            $sticky_posts = get_option( 'sticky_posts' );
            if( ! empty( $sticky_posts ) )
            {
                // Remove $postid from the $sticky_posts[] array
                foreach( $sticky_posts as $key => $stuck_id )
                {
                    if( $stuck_id == $postid )
                    {
                        // remove $key from $sticky_posts
                        unset( $sticky_posts[$key] );
                        break;
                    } // end if
                } // end foreach
                // Get the new array of stickies back into WP
                update_option('sticky_posts', $sticky_posts);
            } // end if
        } // end if

        // Now, make the array we would pass to wp_update_post
        // This is a local variable, so each time process_post is called, it will be new
        $update_post = array('ID' => $postid);
        // =============================================================
        // STATUS AND VISIBILITY
        switch( $this->options['chg-status'] )
        {
            case '0':
                // we do not need a post_status key
                break;
            case '1':
                $update_post['post_status'] = 'pending';
                break;
            case '2':
                $update_post['post_status'] = 'draft';
                break;
            case '3':
                $update_post['post_status'] = 'private';
                break;
            // default:
                // if it is anything else, let's make sure the post_status key is just gone from the array
                // NOTE: It would be better if we could just not make the array in the first place
                // unset( $update_post['post_status'] );
        } // end switch
        // =============================================================
        // TITLE
        switch( $this->options['chg-title'] )
        {
            case '0':
                // no title change
                break;
            case '1':
                // add text before current title
                if( !empty( $this->options['title-add'] ) ) {
                    $update_post['post_title'] = $this->options['title-add'] . ' ' . get_the_title( $postid );
                }
                break;
            case '2':
                // add text after current title
                if( !empty( $this->options['title-add'] ) ) {
                    $update_post['post_title'] = get_the_title( $postid ) . ' ' . $this->options['title-add'];
                }
                break;
        } // end switch
        // =============================================================
        // CATEGORIES
        // First, let's check and see if we want to do Category changing or not.
        if( $this->options['chg-cat-method'] != '0' )
        {
            // We do want category changes, so let's procees
            // list of categories we want to work with, as set in Content Scheduler > Options panel
            $category_switch = $this->options['selcats'];
            // list of categories the post is CURRENTLY in
            $current_category_objs = get_the_category($postid);
            // build a list of the post's current category ID's
            $current_category_ids = array();
            foreach( $current_category_objs as $object )
            {
                $current_category_ids[] = $object->term_id;
            } // end foreach
            switch( $this->options['chg-cat-method'] )
            {
                case '1':
                    // we want to have the current categories
                    // PLUS the selected categories
                    $category_switch = array_merge( $current_category_ids, $category_switch );
                    $category_switch = array_unique( $category_switch );
                    break;
                case '2':
                    // we want to have the current categories
                    // MINUS the selected categories
                    $category_switch = array_diff( $current_category_ids, $category_switch );
                    break;
                case '3':
                    // we want the categories to MATCH the selected categories
                    // $category_switch is already set just fine
                    break;
                default:
                    unset( $update_post['post_category'] );
            } // end switch
            // set the 'post_category' part of update_post array
            $update_post['post_category'] = $category_switch;
        } // end if - checking chg-cat-method
        // =============================================================
        // TAGS (Check to see if the post type support post_tag first)			
        $proceed = false;
        // Get the post type (we're using this same file for Posts and Custom Post Types)
        $post_type = get_post_type( $postid );
        if( ! empty( $post_type ) )
        {
            // See if the post_type is built-in Post
            if( $post_type == 'post' )
            {
                $proceed = true;
            }
            else
            {
                // If it is not built-in Post, then we need to find out its taxonomies (does it support post_tag)
                // Get the post type's capabilities
                $post_type_object = get_post_type_object( $post_type );
                // Get the array of supported taxonomies for this post_type
                $supported_taxos = $post_type_object->taxonomies;
                // Find out if post_tag is in $supported_taxos
                if( in_array( 'post_tag', $supported_taxos ) )
                {
                    $proceed = true;
                } // end if for post_tag support
            } // end if for $post_type != post
            
            if( $proceed == true )
            {
                // First, check to see if we even want to do tags
                // TODO shouldn't we check this up above before that checking of post type??
                $tags_to_add = $this->options['tags-to-add']; // this is a comma-delimited string
                if( '' != $tags_to_add ) {
                  // we have some tags to work with
                  $tags_setting_list = explode( ",", $tags_to_add );
                    // make sure we just have a comma-separated list of alphanumeric entries
                    $tags_setting_list = filter_var_array( $tags_setting_list, FILTER_SANITIZE_STRING );
                    // init arrays used for final operations
                    $tags_to_add = array();
                    $tags_to_remove = array();
                    $final_tag_list = array();
                    // process the array by:
                    // a. remove spaces from items
                    // b. checking for "-" or "+" as first character
                    // -- i. Adding to appropriate array if there is such a character
                    foreach( $tags_setting_list as $cur_tag )
                    {
                      // trim any space from front and back
                      $cur_tag = trim( $cur_tag );
                      // we'll do trim() again on the + and - items, since there might be whitespace after the +/-
                      // check to see what the first character of the tag is
                      $first_char = substr( $cur_tag, 0, 1 );
                      switch( $first_char )
                      {
                        case '-':
                          $tags_to_remove[] = trim( substr( $cur_tag, 1 ) );
                          break;
                        case '+':
                          $tags_to_add[] = trim( substr( $cur_tag, 1 ) );
                        default:
                          $tags_to_add[] = trim( $cur_tag );
                      } // end switch
                    } // end foreach
        
                    // get the current tags list for this post
                    $cur_post_tags = get_the_tags( $postid ); // returns an array of objects
                    if( !empty( $cur_post_tags ) )
                    {
                        // Make a new array to keep just the current tag list in
                        $new_cur_post_tags = array();
                        foreach( $cur_post_tags as $tag_object )
                        {
                            $new_cur_post_tags[] = $tag_object->name;
                        }
                        // Remove tags from current list
                        if( !empty( $tags_to_remove ) ) {
                          $new_cur_post_tags = array_diff( $new_cur_post_tags, $tags_to_remove );
                        }
                        // Add tags to current list
          if( !empty( $tags_to_add ) ) {
            $new_cur_post_tags = array_merge( $new_cur_post_tags, $tags_to_add );
          }
          // now build final tag list. this could be better
          $final_tag_list = $new_cur_post_tags;
                    }
                    else
                    {
                        // there were no current tags in the post, so we're just adding
                        $final_tag_list = $tags_to_add;
                    } // end if checking for empty current post tag list

        // now I need all those tags comma delimited again (did we have to go into the array and back out of it to handle the duplicates?)
        $final_tag_list = implode( ", ", $final_tag_list );
        // add the tag list to our $update_post
        $update_post['tags_input'] = $final_tag_list;
                } // end if for having tags_to_add
            } // endif for $proceed == true
        } // end if for post_type existing

        // =============================================================
        // NOW ACTUALLY UPDATE THE POST RECORD
        // Use the update array to actually update the post
        if( !wp_update_post( $update_post ) )
        {
            error_log( "Content Scheduler issue trying to wp_update_post" );
        }
        else
        {
            // update the post_meta so it won't end up here in our update query again
            // We're not changing the expiration date, so we can look back and know when it expired.
            update_post_meta( $postid, '_cs-enable-schedule', 'Disable' );
            // Now we should kick off notification
            
        }
    } // end process_post()
    // ====================
    // Do whatever we need to do to expired PAGES
    function process_page($postid) {
        // include "includes/process-page.php";
        // Now, make the array we would pass to wp_update_post
        // This is a local variable, so each time process_post is called, it will be new
        $update_post = array('ID' => $postid);
        // Get the Post's ID into the update array
        // $update_post['ID'] = $postid;

        // STATUS AND VISIBILITY
        switch( $this->options['chg-status'] )
        {
            case '0':
                // we do not need a post_status key
                break;
            case '1':
                $update_post['post_status'] = 'pending';
                break;
            case '2':
                $update_post['post_status'] = 'draft';
                break;
            case '3':
                $update_post['post_status'] = 'private';
                break;
            // default:
                // if it is anything else, let's make sure the post_status key is just gone from the array
                // NOTE: It would be better if we could just not make the array in the first place
                // unset( $update_post['post_status'] );
        } // end switch
        // =============================================================
        // TITLE
        switch( $this->options['chg-title'] )
        {
            case '0':
                // no title change
                break;
            case '1':
                // add text before current title
                if( !empty( $this->options['title-add'] ) ) {
                    $update_post['post_title'] = $this->options['title-add'] . ' ' . get_the_title( $postid );
                }
                break;
            case '2':
                // add text after current title
                if( !empty( $this->options['title-add'] ) ) {
                    $update_post['post_title'] = get_the_title( $postid ) . ' ' . $this->options['title-add'];
                }
                break;
        } // end switch
        
        // ==========
        // Pages don't have Categories
        // Pages don't have Tags
        // Pages could have Parent Page changes (in a future version)
        // Pages culd have Template changes (in a future version)
        // Use the update array to actually update the post
        if( !wp_update_post( $update_post ) )
        {
            error_log( "Content Scheduler issue trying to wp_update_post" );
        }
        else
        {
            // update the post_meta so it won't end up here in our update query again
            // We're not changing the expiration date, so we can look back and know when it expired.
            update_post_meta( $postid, '_cs-enable-schedule', 'Disable' );
            // Now we should kick off notification
            
        }

    } // end process_page()
    // ====================
    // Do whatever we need to do to expired CUSTOM POST TYPES
    function process_custom($postid) {
        // for now, we are just going to proceed with process_post
        $this->pricess_post( $postid );
    } // end process_custom()

    /*
        @var posts_to_notify    Array of post ID's triggering notification
        @var why_notify         String indicating why we're calling notification
    */
    function do_notifications( $posts_to_notify, $why_notify='expired' ) {
        // include "includes/send-notifications.php";
        // $options = get_option('ContentScheduler_Options');
        // $why_notify should be:
        // a. 'expired' -- came from process_expiration
        // b. 'notified' -- came from process_notification
        // We can use this info to customize the message.
        // We're going to change the value of that same variable and use it in the subject line
        switch( $why_notify )
        {
            case "expired":
                /* translators: flag explaining why notification is occuring */
                $why_notify = __('Expiration', 'contentscheduler');
                break;
            case "notified":
                /* translators: flag explaining why notification is occuring */
                $why_notify = __('Pending', 'contentscheduler');
                break;
            default:
                /* translators: flag explaining why notification is occuring */
                $why_notify = __('Mysterious', 'contentscheduler');
        } // end switch
        // Determine who we need to notify:
        // notify_whom
        // a. 'admin'
        // b. 'author'
        // c. 'both'
        // TODO this is hoaky and needs improvement
        $notify_whom = '';
        if( $this->options['notify-admin'] == '1' )
        {
            if( $this->options['notify-author'] == '1' )
            {
                $notify_whom = 'both';
            }
            else
            {
                $notify_whom = 'admin';
            }
        }
        elseif( $this->options['notify-author'] == '1' )
        {
            $notify_whom = 'author';
        } // end if
        // Now, make sure we really have people to notify, otherwise get out of here.
        if( $notify_whom == '' )
        {
            return;
        }
        // get the admin_email, to use repeatedly inside this foreach
        $site_admin_email = "Admin <".get_option('admin_email').">";
        foreach( $posts_to_notify as $cur_post )
        {				
            // cur_post is just a number	
            // get post data
            $post_data = get_post( $cur_post, ARRAY_A );
            // get the author ID
            $auth_id = $post_data['post_author'];
            // get the author email address
            $auth_info = get_userdata( $auth_id );
            $auth_email = "Author <" . $auth_info->user_email . ">";
            // get the post ID
            $post_id = $post_data['ID'];
            // get the post title
            $post_title = $post_data['post_title'];
            // get / create the post viewing URL
            $post_view_url = $post_data['guid'];
            // get / create the post editing url
            // $post_edit_url = "Fake Post Editing URL";
            // get the post expiration date
            // $post_expiration_date = ( get_post_meta( $post_data['ID'], '_cs-expire-date', true) );
            $post_expiration_date_timestamp = ( get_post_meta( $post_data['ID'], '_cs-expire-date', true) );
            $post_expiration_date = DateUtilities::getReadableDateFromTimestamp( $post_expiration_date_timestamp );
            if( false === $post_expiration_date ) {
                // we were unable to get a date from the timestamp
                $post_expiration_date = "Unable to convert timestamp to readable date.";
            }
            
            // pack it up into our array
            // make a new item array
            $new_item['ID'] = $post_id;
            $new_item['post_title'] = $post_title;
            $new_item['view_url'] = $post_view_url;
            // $new_item['edit_url'] = $post_edit_url;
            $new_item['expiration_date'] = $post_expiration_date;
            if( $notify_whom == 'author' OR $notify_whom == 'both' )
            {
                // add the post to the notification list
                $notify_list[$auth_email][] = $new_item;
            }
            // if we are notifying admin, we can add it to their array
            if( $notify_whom == 'admin' OR $notify_whom == 'both' )
            {
                // See if the site_admin_email matches the author email.
                // If it does, the admin is already being notified, so we should not continue
                if( $site_admin_email != $auth_email )
                {
                    // add the post to the notification list for the admin user
                    $notify_list[$site_admin_email][] = $new_item;
                }
            } // end if
        } // end foreach
        // Now we need to step through each of $notify_list[ {email_address} ]
        // and compile a message for each unique email_address
        // then send it and repeat
        //
        // step through each element of $notify_list
        $blog_name = htmlspecialchars_decode( get_bloginfo('name'), ENT_QUOTES );
        foreach( $notify_list as $usr_email=>$user ) {
            // reset $usr_msg
            $usr_msg = sprintf( __("The following notifications come from the website: %s\n", 'contentscheduler'), $blog_name );
            // tell them why they are receiving the notification
            $usr_msg .= __("Reason for notification:\n", 'contentscheduler');
            if( $why_notify == 'Expiration' )
            {
                $usr_msg .= __("These notifications indicate items Content Scheduler has automatically applied expiration changes to.\n", 'contentscheduler');
            }
            else
            {
                $usr_msg .= __("These notifications indicate items expiring soon OR items that have expired but have not had any automatic changes applied.\n", 'contentscheduler');
            } // end if
            $usr_msg .= "====================\n";
            // get this user's email address -- it is the key for the current element, $user
            if( ! empty( $usr_email ) ) {
                // step through elements in the user's array
                foreach( $user as $post ) {						
                    $usr_msg .= sprintf( __('The Post / Page entitled %1$s, with the post_id of %2$d, has an expiration date of %3$s', 'contentscheduler'), $post['post_title'], $post['ID'], $post['expiration_date'] );
                    $usr_msg .= "\n";
                    $usr_msg .= sprintf( __('Unless the content is deleted, it can be viewed at %s.', 'contentscheduler'), $post['view_url'] );
                    $usr_msg .= "\n=====\n";
                } // end foreach stepping through list of posts for a user
                // send $msg to $user_email
                // Build subject line
                /* translators: Type of notification, then blog name */
                $subject = sprintf( __('%1$s Notification from %2$s', 'contentscheduler'), $why_notify, $blog_name );
                // Send the message
                // TODO get this in a try block and actually handle errors
                if( wp_mail( $usr_email, $subject, $usr_msg ) == 1 ) {
                    // SUCCESS
                    error_log( "Email sent successfully" );
                } else {
                    // FAILED
                    error_log( "Email failed to send" );
                }
            } else {
                // usr_email was empty
                error_log( "user_email is empty; did not attempt to send email" );
            }
        } // end foreach stepping through list of users to notify
    }
}
