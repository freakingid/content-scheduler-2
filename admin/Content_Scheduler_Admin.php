<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       http://paulekaiser.com/wordpress-plugins/content-scheduler/
 * @since      2.0.6
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/admin
 */

/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    Content_Scheduler
 * @subpackage Content_Scheduler/admin
 * @author     Paul Kaiser <paul.kaiser@gmail.com>
 */
class Content_Scheduler_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 * @var      string    $Content_Scheduler    The ID of this plugin.
	 */
	private $_Content_Scheduler;

	/**
	 * The version of this plugin.
	 *
	 * @since    2.0.6
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $_version;
	
	/**
	 * The options for this plugin.
	 *
	 * @since   2.0.6
	 * @access  private
	 * @var     array   $options    Array of options settings.
	 */
	private $_options;
	
	/**
	 * The Settings class reference.
	 *
	 * @since   2.0.6
	 * @access  private
	 * @var     class object    $settings   Instance of Settings class.
	 */
	private $_settings;
	
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.6
	 * @var      string    $Content_Scheduler   The name of this plugin.
	 * @var      string    $version             The version of this plugin.
	 */
	public function __construct( $Content_Scheduler, $version ) {

		$this->_Content_Scheduler = $Content_Scheduler;
		$this->_version = $version;
		$this->_load_admin_dependencies();
		
        // TODO if we're going to have get_Content_Scheduler and get_version used in different classes, they should subclass some generic
        $this->_settings = new Content_Scheduler_Settings( $this->get_Content_Scheduler(), $this->get_version() );
        $this->_options = get_option('ContentScheduler_Options');

	}

    /*
     * Load any classes required for admin class functioning
     */
	private function _load_admin_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Content_Scheduler_Settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/DateUtilities.php';
	}
	
	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    2.0.6
	 */
	public function enqueue_styles( $hook ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Content_Scheduler_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Content_Scheduler_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
        wp_enqueue_style( 'cs_styles' );

        // do we want the datepicker?
        if( $this->_options['datepicker'] == '1' ) {
            // post.php and post-new.php
            if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
                return;
            }
            // enqueue styles here if needed
            wp_enqueue_style( 'jquery-ui-datepicker', plugins_url() . "/content-scheduler/js/jquery-ui.min.css", array(), '1.11.2', 'all' );
            wp_enqueue_style( 'datetimepicker', plugins_url() . "/content-scheduler/js/jquery-ui-timepicker-addon.css", array( 'jquery-ui-datepicker' ), '1.6.0', 'all' );
        } // endif checking for datepicker option
            
		// wp_enqueue_style( $this->Content_Scheduler, plugin_dir_url( __FILE__ ) . 'css/plugin-name-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the dashboard.
	 *
	 * @since    2.0.6
	 */
	public function enqueue_scripts( $hook ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Content_Scheduler_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Content_Scheduler_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
        wp_enqueue_script( 'postbox' );

        // do we want the datepicker?
        if( $this->_options['datepicker'] == '1' ) {
            // only use on new post and edit post pages
            if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
                return;
            }
            // enqueue javascripts here if needed
            wp_enqueue_script(
                'datetimepicker', 
                plugins_url() . "/content-scheduler/js/jquery-ui-timepicker-addon.min.js", 
                array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-slider' ), 
                '1.6.0', 
                true );
        } // endif checking for datepicker option

		// wp_enqueue_script( $this->Content_Scheduler, plugin_dir_url( __FILE__ ) . 'js/plugin-name-admin.js', array( 'jquery' ), $this->version, false );

	}

    // =================================================================
    // == Functions for using Custom Controls / Panels
    // == in the Post / Page / Link writing panels
    // == e.g., a custom field for an expiration date, etc.
    // =================================================================
    // Adds a box to the main column on the Post, Page, and Custom Type edit screens
    // We'll rig so it only shows if user is min-level or above
    // a. Add the box
    public function ContentScheduler_add_custom_box_fn() {
        global $current_user;
        // What is minimum level required to see CS?
        $min_level = $this->_options['min-level'];
    
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
    public function ContentScheduler_custom_box_fn() {
        // need $post in global scope so we can get id?
        global $post;
        // Use nonce for verification
        wp_nonce_field( 'content_scheduler_values', 'ContentScheduler_noncename' );
        // Get the current value, if there is one
        $the_data = get_post_meta( $post->ID, '_cs-enable-schedule', true );
        $the_data = ( empty( $the_data ) ? 'Disable' : $the_data );
        // Checkbox for scheduling this Post / Page, or ignoring
        $items = array( "Disable", "Enable");
        foreach( $items as $item) {
            $checked = ( $the_data == $item ) ? ' checked="checked" ' : '';
            echo "<label><input ".$checked." value='$item' name='_cs-enable-schedule' id='cs-enable-schedule' type='radio' /> $item</label>  ";
        } // end foreach
        echo "<br />\n<br />\n";
        // Field for datetime of expiration
        // should be unix timestamp at this point, in UTC
        // for display, we need to convert this to local time and then format
        
        // datestring is the original human-readable form
        // $datestring = ( get_post_meta( $post->ID, '_cs-expire-date', true) );
        // timestamp should just be a unix timestamp
        $timestamp = ( get_post_meta( $post->ID, '_cs-expire-date', true) );
        if( !empty( $timestamp ) ) {
            // we need to convert that into human readable so we can put it into our field
            $datestring = DateUtilities::getReadableDateFromTimestamp( $timestamp );
            if( false === $datestring ) {
                // we couldn't get readable date
                $datestring = '';
            }
        } else {
            $datestring = '';
        }
        // Should we check for format of the date string? (not doing that presently)
        echo '<label for="cs-expire-date">' . __("Expiration date and hour", 'contentscheduler' ) . '</label><br />';
        echo '<input type="text" id="cs-expire-date" name="_cs-expire-date" value="'.$datestring.'" size="25" />';
        echo '<br />Input date and time in any valid Date and Time format.';
    }

    // c. Save data from the box callback
    public function ContentScheduler_save_postdata_fn( $post_id ) {
        // verify this came from our screen and with proper authorization,
        // because save_post can be triggered at other times
        if( !empty( $_POST['ContentScheduler_noncename'] ) ) {
            if ( !wp_verify_nonce( $_POST['ContentScheduler_noncename'], 'content_scheduler_values' )) {
                return $post_id;
            }
        } else {
            return $post_id;
        }
        // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
        // to do anything
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return $post_id;
        }
        // Check permissions, whether we're editing a Page or a Post
        if ( 'page' == $_POST['post_type'] ) {
            if ( !current_user_can( 'edit_page', $post_id ) )
            return $post_id;
        } else {
            if ( !current_user_can( 'edit_post', $post_id ) )
            return $post_id;
        }
        
        // OK, we're authenticated: we need to find and save the data
        // First, let's make sure we'll do date operations in the right timezone for this blog
        // $this->setup_timezone();
        // Checkbox for "enable scheduling"
        $enabled = ( empty( $_POST['_cs-enable-schedule'] ) ? 'Disable' : $_POST['_cs-enable-schedule'] );
        // Value should be either 'Enable' or 'Disable'; otherwise something is screwy
        if( $enabled != 'Enable' AND $enabled != 'Disable' ) {
            // $enabled is something we don't expect
            // let's make it empty
            $enabled = 'Disable';
            // Now we're done with this function?
            return false;
        }
        // Textbox for "expiration date"
        $dateString = $_POST['_cs-expire-date'];            
        $offsetHours = 0;
        // if it is empty then set it to tomorrow
        // we just want to pass an offset into getTimestampFromReadableDate since that is where our DateTime is made
        if( empty( $dateString ) ) {
            // set it to now + 24 hours
            $offsetHours = 24;
        }
        // TODO handle datemath if field reads "default"
        if( trim( strtolower( $dateString ) ) == 'default' ) {
            // get the default value from the database
            $default_expiration_array = $this->_options['exp-default'];
            if( !empty( $default_expiration_array ) ) {
                $default_hours = $default_expiration_array['def-hours'];
                $default_days = $default_expiration_array['def-days'];
                $default_weeks = $default_expiration_array['def-weeks'];
            } else {
                $default_hours = '0';
                $default_days = '0';
                $default_weeks = '0';
            }
        
            // we need to move weeks into days and days into hours
            $default_hours += ( $default_weeks * 7 + $default_days ) * 24 * 60 * 60;
            
            // if it is valid, get the published or scheduled datetime, add the default to it, and set it as the $date
            if ( !empty( $_POST['save'] ) ) {
                if( $_POST['save'] == 'Update' ) {
                    $publish_date = $_POST['aa'] . '-' . $_POST['mm'] . '-' . $_POST['jj'] . ' ' . $_POST['hh'] . ':' . $_POST['mn'] . ':' . $_POST['ss'];
                } else {
                    $publish_date = $_POST['post_date'];
                }
                // convert publish_date string into unix timestamp
                $publish_date = DateUtilities::getTimestampFromReadableDate( $publish_date );
                if( false === $publish_date ) {
                    // unable to parse into unix timestamp
                    $publish_date = time(); // current unix timestamp; best default so far;
                }
            } else {
                $publish_date = time(); // current unix timestamp
                // no conversion from string needed
            }
            
            // time to add our default
            // we need $publish_date to be in unix timestamp format, like time()
            $expiration_date = $publish_date + $default_hours * 60 * 60;
            $_POST['_cs-expire-date'] = $expiration_date;
        } else {
            $expiration_date = DateUtilities::getTimestampFromReadableDate( $dateString, $offsetHours );
        }
        // We probably need to store the date differently,
        // and handle timezone situation
        if( false === $expiration_date ) {
            // we could not convert to timestamp; we want to:
            // a. display error message
            // b. stop the updating of Content Scheduler meta
            // we could have an error class but that doesn't seem necessary yet
            $this->get_timestamp_error( $dateString );

            // Purposefully do not update CS-related post-meta
            
            /**
             * Add error notice to appear in dashboard
             */
            // TODO or do I need to access our $loader->add_action???
            // add_action( 'admin_notices', array( $this, 'content_scheduler_admin_notices') );
        } else {
            update_post_meta( $post_id, '_cs-enable-schedule', $enabled );
            update_post_meta( $post_id, '_cs-expire-date', $expiration_date );
        }
    }
    
    // TODO maybe this should go into DateTime static class instead? or an Error class
    // TODO i18n please
    protected function get_timestamp_error( $dateString ) {
        add_settings_error(
            'cs-expire-date',
            'cs-expire-date',
            'Unable to convert the string "' . $dateString . '" to a unix timestamp for saving.',
            'error'
          );
        set_transient( 'settings_errors', get_settings_errors(), 30 );
    }
    
    /**
     * Writes error to screen if expiration date string cannot be parsed to unix timestamp for saving
     */
    public function content_scheduler_admin_notices() {
        // If there are no errors, then we'll exit the function
        if ( ! ( $errors = get_transient( 'settings_errors' ) ) ) {
            return;
        }

        // Otherwise, build the list of errors that exist in the settings errors
        $message = '<div id="acme-message" class="error below-h2"><p><ul>';
            foreach ( $errors as $error ) {
                $message .= '<li>' . $error['message'] . '</li>';
            }
        $message .= '</ul></p></div><!-- #error -->';

        // Write them out to the screen
        echo $message;

        // Clear the transient and unhook any other notices so we don't see duplicate messages
        delete_transient( 'settings_errors' );
        remove_action( 'admin_notices', 'content_scheduler_admin_notices' );
    }

    // ================================================================
    // == Conditionally Add Expiration date to Column views
    // ================================================================
    // a. add our column to the table
    public function cs_add_expdate_column ($columns) {
        global $current_user;
        // Check to see if we really want to add our column
        if( $this->_options['show-columns'] == '1' )
        {
            // Check to see if current user has permissions to see
            // What is minimum level required to see CS?
            // must declare $current_user as global
            $min_level = $this->_options['min-level'];
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
    public function cs_show_expdate ($column_name) {
            global $wpdb, $post, $current_user;
            // Check to see if we really want to add our column
            if( $this->_options['show-columns'] == '1' ) {
                // Check to see if current user has permissions to see
                // What is minimum level required to see CS?
                // must declare $current_user as global
                $min_level = $this->_options['min-level'];
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
                    // $ed = $wpdb->get_var($query);
                    $timestamp = $wpdb->get_var($query);
                    if( !empty( $timestamp ) ) {
                        // convert
                        $ed = DateUtilities::getReadableDateFromTimestamp( $timestamp );
                        if( empty( $ed ) ) {
                            $ed = "Date misunderstood";
                        }
                    } else {
                        $ed = "No date set";
                    }
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

    public function admin_head() {
        global $pagenow;            
        // for inline scripts in head, etc.
        // only use on new post and edit post pages
        if ( 'post.php' != $pagenow && 'post-new.php' != $pagenow ) {
            return;
        }
        // only add if datepicker is enabled
        if( $this->_options['datepicker'] == '1' ) {
        ?>
        <script type="text/javascript">
        jQuery(function(){
            jQuery( '#cs-expire-date' ).datetimepicker()
            });
        </script>
        <?php
        } // endif
    }
    
    public function admin_init() {
        // anything for Admin class
        // anything for Settings class
        $this->_settings->admin_init();
    }
    
    public function admin_menu() {
        // anything for Admin class
        // anything for Settings class
        $this->_settings->admin_menu();
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
		return $this->_Content_Scheduler;
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
		return $this->_version;
	}    
}
