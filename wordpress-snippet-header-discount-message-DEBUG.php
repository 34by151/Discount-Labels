<?php
/**
 * DEBUG VERSION - ArtInMetal Header Discount Message
 * This version includes debugging output to help troubleshoot
 *
 * TEMPORARY: Use this to diagnose issues, then switch back to regular version
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display discount message in header
 */
add_action( 'wp_body_open', 'aim_display_header_discount_message_debug', 10 );

function aim_display_header_discount_message_debug() {
    // Only run on frontend, not admin
    if ( is_admin() ) {
        return;
    }

    // Check if discount plugin is active
    if ( ! function_exists( 'WCCS' ) || ! class_exists( 'WCCS_Conditions_Provider' ) ) {
        echo '<!-- DEBUG: Discount plugin not active -->';
        return;
    }

    // Check if user is dealer role - dealers see NO messages
    $current_user_roles = array();
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        if ( ! empty( $current_user->roles ) ) {
            // Get user roles (convert to lowercase for case-insensitive matching)
            $current_user_roles = array_map( 'strtolower', $current_user->roles );

            echo '<!-- DEBUG: User roles: ' . implode( ', ', $current_user_roles ) . ' -->';

            // If user is dealer, don't show any messages
            if ( in_array( 'dealer', $current_user_roles ) ) {
                echo '<!-- DEBUG: User is dealer, no message shown -->';
                return;
            }
        }
    } else {
        echo '<!-- DEBUG: User not logged in -->';
    }

    // Get the active discount message (pass user roles for VIP handling)
    $result = aim_get_active_discount_message_debug( $current_user_roles );

    // Display debug info
    echo '<!-- DEBUG: Message result: ' . ( ! empty( $result['message'] ) ? $result['message'] : 'NULL' ) . ' -->';
    echo '<!-- DEBUG: End date found: ' . ( ! empty( $result['end_date'] ) ? $result['end_date'] : 'NONE' ) . ' -->';
    echo '<!-- DEBUG: Date time data: ' . print_r( $result['date_time_raw'], true ) . ' -->';

    // Display message if available
    if ( ! empty( $result['message'] ) ) {
        echo '<div class="aim-header-discount-message">';
        echo '<p class="aim-discount-text">' . esc_html( $result['message'] ) . '</p>';
        echo '</div>';
    } else {
        echo '<!-- DEBUG: No message to display -->';
    }
}

/**
 * Get the active discount message with debug info
 */
function aim_get_active_discount_message_debug( $user_roles = array() ) {
    $debug_result = array(
        'message' => null,
        'end_date' => null,
        'date_time_raw' => null,
    );

    try {
        // Get all active pricing rules
        $pricings = WCCS_Conditions_Provider::get_pricings( array(
            'status'  => 1,
            'number'  => -1,
            'orderby' => 'ordering',
            'order'   => 'ASC'
        ) );

        echo '<!-- DEBUG: Found ' . count( $pricings ) . ' pricing rules -->';

        if ( empty( $pricings ) ) {
            return $debug_result;
        }

        // Initialize pricing handler with validators
        $pricing_handler = new WCCS_Pricing( $pricings );

        // Get the date/time validator
        $date_time_validator = WCCS()->WCCS_Date_Time_Validator;

        // Get the condition validator
        $condition_validator = WCCS()->WCCS_Condition_Validator;

        // Define discount priorities (highest to lowest)
        $discount_priorities = array(
            'Sale 20%' => '20% Discount on all ArtInMetal products Store Wide',
            'Sale 15%' => '15% Discount on all ArtInMetal products Store Wide',
            'Sale 10%' => '10% Discount on all ArtInMetal products Store Wide',
        );

        // Check if user is VIP
        $is_vip = in_array( 'vip', $user_roles );
        echo '<!-- DEBUG: Is VIP: ' . ( $is_vip ? 'YES' : 'NO' ) . ' -->';

        // Check each discount in priority order
        foreach ( $discount_priorities as $discount_name => $message ) {
            echo '<!-- DEBUG: Checking for "' . $discount_name . '" -->';

            // Skip 10% discount for VIP users
            if ( $is_vip && $discount_name === 'Sale 10%' ) {
                echo '<!-- DEBUG: Skipping 10% for VIP user -->';
                continue;
            }

            foreach ( $pricings as $pricing ) {
                // Check if this is the discount we're looking for
                if ( strcasecmp( trim( $pricing->name ), trim( $discount_name ) ) !== 0 ) {
                    continue;
                }

                echo '<!-- DEBUG: Found pricing rule: "' . $pricing->name . '" (ID: ' . $pricing->id . ') -->';

                // Validate the pricing rule is truly active

                // 1. Check usage limit if exists
                if ( ! empty( $pricing->usage_limit ) && class_exists( 'WCCS_Usage_Validator' ) ) {
                    if ( ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
                        echo '<!-- DEBUG: Usage limit reached -->';
                        continue;
                    }
                }

                // 2. Validate date/time conditions
                if ( ! empty( $pricing->date_time ) ) {
                    echo '<!-- DEBUG: Date/time data exists: ' . json_encode( $pricing->date_time ) . ' -->';

                    $match_mode = isset( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one';

                    if ( ! $date_time_validator->is_valid_date_times( $pricing->date_time, $match_mode ) ) {
                        echo '<!-- DEBUG: Date/time validation failed -->';
                        continue;
                    }

                    echo '<!-- DEBUG: Date/time validation passed -->';
                } else {
                    echo '<!-- DEBUG: No date/time conditions set -->';
                }

                // 3. Validate conditions
                if ( ! empty( $pricing->conditions ) ) {
                    $conditions_match_mode = isset( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all';

                    if ( ! $condition_validator->is_valid_conditions( $pricing, $conditions_match_mode ) ) {
                        echo '<!-- DEBUG: Conditions validation failed -->';
                        continue;
                    }

                    echo '<!-- DEBUG: Conditions validation passed -->';
                }

                // If we reach here, this discount is valid and active
                echo '<!-- DEBUG: Discount is valid and active! -->';

                // Get the end date if available
                $end_date = aim_get_earliest_end_date_debug( $pricing->date_time );

                echo '<!-- DEBUG: Extracted end date: ' . ( $end_date ? $end_date : 'NONE' ) . ' -->';

                // Append end date to message if it exists
                if ( ! empty( $end_date ) ) {
                    $message .= ' - Ends ' . $end_date;
                }

                $debug_result['message'] = $message;
                $debug_result['end_date'] = $end_date;
                $debug_result['date_time_raw'] = $pricing->date_time;

                return $debug_result;
            }
        }

    } catch ( Exception $e ) {
        echo '<!-- DEBUG: Exception: ' . $e->getMessage() . ' -->';
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'AIM Header Discount Message Error: ' . $e->getMessage() );
        }
    }

    return $debug_result;
}

/**
 * Extract and format the earliest end date - DEBUG VERSION
 */
function aim_get_earliest_end_date_debug( $date_times ) {
    if ( empty( $date_times ) || ! is_array( $date_times ) ) {
        echo '<!-- DEBUG END DATE: date_times is empty or not array -->';
        return null;
    }

    echo '<!-- DEBUG END DATE: Processing ' . count( $date_times ) . ' date/time entries -->';

    $earliest_timestamp = null;

    // Loop through all date/time conditions
    foreach ( $date_times as $index => $date_time ) {
        if ( empty( $date_time ) || ! is_array( $date_time ) ) {
            echo '<!-- DEBUG END DATE: Entry ' . $index . ' is empty or not array -->';
            continue;
        }

        echo '<!-- DEBUG END DATE: Entry ' . $index . ' structure: ' . json_encode( $date_time ) . ' -->';

        // Check if there's an end date
        if ( ! empty( $date_time['end']['time'] ) ) {
            $end_date_string = $date_time['end']['time'];
            echo '<!-- DEBUG END DATE: Found end time string: ' . $end_date_string . ' -->';

            // Convert to timestamp
            $timestamp = strtotime( $end_date_string );
            echo '<!-- DEBUG END DATE: Converted to timestamp: ' . $timestamp . ' -->';

            if ( $timestamp !== false ) {
                // Keep track of the earliest end date
                if ( $earliest_timestamp === null || $timestamp < $earliest_timestamp ) {
                    $earliest_timestamp = $timestamp;
                    echo '<!-- DEBUG END DATE: New earliest timestamp: ' . $earliest_timestamp . ' -->';
                }
            }
        } else {
            echo '<!-- DEBUG END DATE: Entry ' . $index . ' has no end.time value -->';
        }
    }

    // If we found an end date, format it
    if ( $earliest_timestamp !== null ) {
        $formatted = date_i18n( 'F j, Y', $earliest_timestamp );
        echo '<!-- DEBUG END DATE: Final formatted date: ' . $formatted . ' -->';
        return $formatted;
    }

    echo '<!-- DEBUG END DATE: No end date found in any entry -->';
    return null;
}

/**
 * Add CSS styling to match header text
 */
add_action( 'wp_head', 'aim_header_discount_message_styles', 100 );

function aim_header_discount_message_styles() {
    if ( is_admin() ) {
        return;
    }

    if ( ! function_exists( 'WCCS' ) ) {
        return;
    }

    ?>
    <style type="text/css">
        .aim-header-discount-message {
            background: transparent;
            text-align: center;
            margin: 0;
            padding: 10px 20px;
        }

        .aim-discount-text {
            margin: 0;
            padding: 0;
            font-family: inherit;
            font-size: inherit;
            color: inherit;
            line-height: inherit;
        }

        /* Optional: Add slight styling for visibility */
        .aim-header-discount-message {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .aim-discount-text {
            font-weight: 500;
        }
    </style>
    <?php
}
