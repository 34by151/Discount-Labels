<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Email_Helpers {

    /**
	 * Checks if the given email address(es) matches the ones specified on the coupon.
	 *
	 * @param array $check_emails Array of customer email addresses.
	 * @param array $restrictions Array of allowed email addresses.
	 * @return bool
	 */
    public static function is_emails_allowed( $check_emails, $restrictions ) {
        foreach ( $check_emails as $check_email ) {
			// With a direct match we return true.
			if ( in_array( $check_email, $restrictions, true ) ) {
				return true;
			}

			// Go through the allowed emails and return true if the email matches a wildcard.
			foreach ( $restrictions as $restriction ) {
				// Convert to PHP-regex syntax.
				$regex = '/^' . str_replace( '*', '(.+)?', $restriction ) . '$/';
				preg_match( $regex, $check_email, $match );
				if ( ! empty( $match ) ) {
					return true;
				}
			}
		}

		// No matches, this one isn't allowed.
		return false;
    }

    public static function get_domains( $emails ) {
        if ( empty( $emails ) ) {
            return array();
        }

        if ( is_string( $emails ) ) {
            $emails = array_map( 'trim', explode( ',', trim( $emails ) ) );
        }

        $emails = array_filter( array_map( 'strtolower', $emails ), 'is_email' );
        if ( empty( $emails ) ) {
            return array();
        }

        $domains = array();
        foreach ( $emails as $email ) {
            $domain = self::get_domain( $email );
            if ( ! empty( $domain ) ) {
                $domains[] = $domain;
            }
        }
        return $domains;
    }

    public static function get_domain( $email ) {
        if ( empty( $email ) || ! is_email( $email ) ) {
            return false;
        }

        // Split out the local and domain parts.
	    list( $local, $domain ) = explode( '@', $email, 2 );

        return $domain;
    }

    public static function get_tlds( $emails ) {
        if ( empty( $emails ) ) {
            return array();
        }

        if ( is_string( $emails ) ) {
            $emails = array_map( 'trim', explode( ',', trim( $emails ) ) );
        }

        $emails = array_filter( array_map( 'strtolower', $emails ), 'is_email' );
        if ( empty( $emails ) ) {
            return array();
        }

        $tlds = array();
        foreach ( $emails as $email ) {
            $tld = self::get_tld( $email );
            if ( ! empty( $tld ) ) {
                if ( is_array( $tld ) ) {
                    $tlds = array_merge( $tlds, $tld );
                } else {
                    $tlds[] = $tld;
                }
            }
        }

        return ! empty( $tlds ) ? array_unique( $tlds ) : $tlds;
    }

    public static function get_tld( $email ) {
        if ( empty( $email ) || ! is_email( $email ) ) {
            return false;
        }

        // Split out the local and domain parts.
	    list( $local, $domain ) = explode( '@', $email, 2 );
        if ( empty( $domain ) ) {
            return false;
        }

        // Split the domain into subs.
	    $subs = explode( '.', $domain );
        if ( 2 > count( $subs ) ) {
            return false;
        }

        if ( 1 < count( $subs ) ) {
            unset( $subs[0] );
        }

        if( 1 < count( $subs ) ) {
            return array( end( $subs ), implode( '.', $subs ) );
        }

        return implode( '.', $subs );
    }

}
