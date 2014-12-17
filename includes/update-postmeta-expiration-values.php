<?php
/*
    Should take human readable date strings
    and turn them into unix timestamps
*/
// find posts that need to take some expiration action
global $wpdb;
// select all Posts / Pages that have "enable-expiration" set and have expiration date older than right now
$querystring = 'SELECT postmetadate.post_id, postmetadate.meta_value  
    FROM 
    ' .$wpdb->postmeta. ' AS postmetadate WHERE postmetadate.meta_key = "_cs-expire-date"';
$result = $wpdb->get_results($querystring);
// Act upon the results
if ( ! empty( $result ) )
{
    // Proceed with the updating process	      	        
    // step through the results
    foreach ( $result as $cur_post )
    {
        // do the date munging
        $unixTimestamp = getTimestampFromReadableDate( $cur_post->meta_value, 0 );
        // update it
        update_post_meta( $cur_post->post_id, '_cs-expire-date', $unixTimestamp, $cur_post->meta_value );
    } // end foreach
} // endif

// TODO: Pull these out into a static class so they can be used in multiple places
/*
    dateSTring      readalbe date / time string from user input field
    offsetHours     hours to add / remove from dateString-generated DateTime
    return          unit timestamp in UTC time (i.e., not 'local' time)
*/
function getTimestampFromReadableDate( $dateString, $offsetHours = 0 ) {
    // get datetime object from site timezone
    $datetime = new DateTime( $dateString, new DateTimeZone( wp_get_timezone_string() ) );
    // add the offsetHours
    // $date->add(new DateInterval('P10D'));
    $datetime->add( new DateInterval( "PT".$offsetHours."H" ) );
    // get the unix timestamp (adjusted for the site's timezone already)
    $timestamp = $datetime->format( 'U' );
    return $timestamp;    
}
/**
 * Returns the timezone string for a site, even if it's set to a UTC offset
 *
 * Adapted from http://www.php.net/manual/en/function.timezone-name-from-abbr.php#89155
 *
 * @return string valid PHP timezone string
 */
function wp_get_timezone_string() {
    // if site timezone string exists, return it
    if ( $timezone = get_option( 'timezone_string' ) )
        return $timezone;
    // get UTC offset, if it isn't set then return UTC
    if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) )
        return 'UTC';
    // adjust UTC offset from hours to seconds
    $utc_offset *= 3600;
    // attempt to guess the timezone string from the UTC offset
    if ( $timezone = timezone_name_from_abbr( '', $utc_offset, 0 ) ) {
        return $timezone;
    }
    // last try, guess timezone string manually
    $is_dst = date( 'I' );
    foreach ( timezone_abbreviations_list() as $abbr ) {
        foreach ( $abbr as $city ) {
            if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
                return $city['timezone_id'];
        }
    }
    // fallback to UTC
    return 'UTC';
}
?>