<?php
class DateUtilities {
        // TODO: Pull these out into a static class so they can be used in multiple places
        /*
            unixTimestamp       timestamp NOT adjusted for WordPress local time
            return something date and time as one string following WP site formatting settings
        */
        public static function getReadableDateFromTimestamp( $unixTimestamp ) {
            // get datetime object from unix timestamp
            $datetime = new DateTime( "@$unixTimestamp", new DateTimeZone( 'UTC' ) );
            // set the timezone to the site timezone
            $datetime->setTimezone( new DateTimeZone( DateUtilities::wp_get_timezone_string() ) );
            // return the unix timestamp adjusted to reflect the site's timezone
            // return $timestamp + $datetime->getOffset();
            $localTimestamp = $unixTimestamp + $datetime->getOffset();
            $dateString = date_i18n( get_option( 'date_format' ), $localTimestamp );
            $timeString = date( get_option( 'time_format' ), $localTimestamp );
            // put together and return
            return $dateString . " " . $timeString;
        }
        /*
            dateSTring      readalbe date / time string from user input field
            offsetHours     hours to add / remove from dateString-generated DateTime
            return          unit timestamp in UTC time (i.e., not 'local' time)
        */
        public static function getTimestampFromReadableDate( $dateString, $offsetHours = 0 ) {
            // get datetime object from site timezone
            $datetime = new DateTime( $dateString, new DateTimeZone( DateUtilities::wp_get_timezone_string() ) );
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
        private static function wp_get_timezone_string() {
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

}
/*
Foo::aStaticMethod();
$classname = 'Foo';
$classname::aStaticMethod(); // As of PHP 5.3.0
DateUtilities::getReadableDateFromTimestamp();
DateUtilities::getTimestampFromReadableDate();
*/
?>