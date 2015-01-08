<?php
class Content_Scheduler_Migration_Utilities {
    private $_options;
    
    public function migrate( $current_options ) {
        $this->_options = $current_options;
        
        // If version is older than 2.0, we need to change the way we store expiration date metadata
        if( !isset( $current_options['version'] ) || $current_options['version'] < '2.0.0' ) {
            $this->_update_postmeta_expiration_values();
        }
        // If version newer than 0.9.7, we need to alter the name of our postmeta variables if there are earlier version settings in options
        if( !isset( $current_options['version'] ) || $current_options['version'] < '0.9.7' ) {
            $this->_update_postmeta_names();
        }
        // If version newer than 0.9.8, we need to alter the name of our user_level values
        if( !isset( $current_options['version'] ) || $current_options['version'] < '0.9.8' ) {
            $this->_update_minlevel_options();
        }
        // We need to check the "version" and, if it is less than 0.9.5 or non-existent, we need to convert english string values to numbers
        if( !isset( $current_options['version'] ) || $current_options['version'] < '0.9.5' ) {
            $this->_update_values_numbers();
        }
        
        return $this->_options;
    }
    
    private function _update_minlevel_options() {
        if( isset( $this->_options['min-level'] ) ) {
            $min_level = $this->_options['min-level'];
            $new_level = '';
            switch($min_level) {
                case 0:
                    $new_level = 'level_0';
                    break;
                case 1:
                    $new_level = 'level_1';
                    break;
                case 2:
                    $new_level = 'level_2';
                    break;
                case 3:
                    $new_level = 'level_3';
                    break;
                case 4:
                    $new_level = 'level_4';
                    break;
                case 5:
                    $new_level = 'level_5';
                    break;
                case 6:
                    $new_level = 'level_6';
                    break;
                case 7:
                    $new_level = 'level_7';
                    break;
                case 8:
                    $new_level = 'level_8';
                    break;
                case 9:
                    $new_level = 'level_9';
                    break;
                case 10:
                    $new_level = 'level_10';
                    break;
                default:
                    $new_level = 'level_1';
            }
            // now update the option in the database
            $this->_options['min-level'] = $new_level;
            update_option( 'ContentScheduler_Options', $this->_options );
            /*
            // we don't have to do update_blog_option because we switched blogs in activate()
            if( is_multisite () ) {
                $blog_id = get_current_blog_id();
                update_blog_option( $blog_id, 'ContentScheduler_Options', $this->_options );
            } else {
                update_option( 'ContentScheduler_Options', $this->_options );
            }
            */
        } // end if checking for existence of min-level option
    }
    
    /*
     * Should take human readable date strings and turn them into unix timestamps
     */
    private function _update_postmeta_expiration_values() {
    	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/DateUtilities.php';

        global $wpdb;
        // select all Posts / Pages that have "enable-expiration" set and have expiration date older than right now
        $querystring = 'SELECT postmetadate.post_id, postmetadate.meta_value  
            FROM 
            ' .$wpdb->postmeta. ' AS postmetadate WHERE postmetadate.meta_key = "_cs-expire-date"';
        $result = $wpdb->get_results($querystring);
        if ( ! empty( $result ) ) {
            foreach ( $result as $cur_post ) {
                $unixTimestamp = DateUtilities::getTimestampFromReadableDate( $cur_post->meta_value, 0 );
                /*
                 * // TODO
                 * This only runs during activation
                 * But best practice would be to not exit() but rather return false so we can stop activation process
                 */
                if( false === $unixTimestamp ) {
                    // failed to make timestamp from existing datetime in the database
                    exit( "Content Scheduler unable to migrate old expiration datetimes to timestamps." );
                }
                update_post_meta( $cur_post->post_id, '_cs-expire-date', $unixTimestamp, $cur_post->meta_value );
            }
        }
    }
    
    /*
     * Check for and update the following postmeta value names:
     * cs-expire-date > _cs-expire-date
     * cs-enable-schedule > _cs-enable-schedule
     */
    private function _update_postmeta_names() {
        global $wpdb;

        $wpdb->update( 
                    $wpdb->postmeta, 
                    array( 'meta_key' => '_cs-expire-date' ),
                    array( 'meta_key' => 'cs-expire-date' ),
                    '%s', 
                    '%s'
                    );
        $wpdb->update( 
                    $wpdb->postmeta, 
                    array( 'meta_key' => '_cs-enable-schedule' ),
                    array( 'meta_key' => 'cs-enable-schedule' ),
                    '%s', 
                    '%s'
                    );
    }
    
    /*
     * Update a number of options values from english strings / slugs to numbers
     */
    private function _update_values_numbers() {
        switch( $this->_options['exp-status'] ) {
            case 'Hold':
                $this->_options['exp-status'] = '0';
                break;
            case 'Delete':
                $this->_options['exp-status'] = '2';
                break;
            default:
                $this->_options['exp-status'] = '1';
        } // end switch
        switch( $this->_options['chg-status'] ) {
            case 'No Change':
                $this->_options['chg-status'] = '0';
                break;
            case 'Pending':
                $this->_options['chg-status'] = '1';
                break;
            case 'Private':
                $this->_options['chg-status'] = '3';
                break;
            default:
                $this->_options['chg-status'] = '2';
        }
        $this->_options['chg-sticky'] = ( 'No Change' == $this->_options['chg-sticky'] ) ? '0' : '1';
        switch( $this->_options['chg-cat-method'] ) {
            case 'Add selected':
                $this->_options['chg-cat-method'] = '1';
                break;
            case 'Remove selected':
                $this->_options['chg-cat-method'] = '2';
                break;
            case 'Match selected':
                $this->_options['chg-cat-method'] = '3';
                break;
            default:
                $this->_options['chg-cat-method'] = '0';
        }
        $this->_options['notify-on'] = ( 'Notification off' == $this->_options['notify-on'] ) ? '0' : '1';
        $this->_options['notify-admin'] = ( 'Do not notify admin' == $this->_options['notify-admin'] ) ? '0' : '1';
        $this->_options['notify-author'] = ( 'Do not notify author' == $this->_options['notify-author'] ) ? '0' : '1';
        $this->_options['notify-expire'] = ( 'Do not notify on expiration' == $this->_options['notify-expire'] ) ? '0' : '1';
        $this->_options['show-columns'] = ( 'Do not show expiration in columns' == $this->_options['show-columns'] ) ? '0' : '1';
        $this->_options['datepicker'] = ( 'Do not use datepicker' == $this->_options['datepicker'] ) ? '0' : '1';
        $this->_options['remove-cs-data'] = ( 'Do not remove data' == $this->_options['remove-cs-data'] ) ? '0' : '1';
    }
    
} // end class