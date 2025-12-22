<?php
/**
 * Snippet Name: ArtInMetal Header Discount Message
 * Description: Display active discount messages in the site header based on pricing rules
 * Author: Custom Development
 * Version: 1.2.1
 *
 * Instructions:
 * 1. Copy this entire code
 * 2. Go to WordPress Admin > Code Snippets > Add New
 * 3. Paste the code
 * 4. Set "Run snippet everywhere" or "Only run on site front-end"
 * 5. Activate the snippet
 *
 * Requirements:
 * - Discount Rules and Dynamic Pricing for WooCommerce plugin must be active
 * - Astra theme (or change the hook on line 34)
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display discount message in header
 *
 * Hook: wp_body_open (Consistent across all pages)
 * Alternative hooks: astra_header_after, astra_body_top, astra_masthead_content
 */
add_action( 'wp_body_open', 'aim_display_header_discount_message', 10 );

function aim_display_header_discount_message() {
    // Only run on frontend, not admin
    if ( is_admin() ) {
        return;
    }

    // Check if discount plugin is active
    if ( ! function_exists( 'WCCS' ) || ! class_exists( 'WCCS_Conditions_Provider' ) ) {
        return;
    }

    // Check if user is dealer role - dealers see NO messages
    $current_user_roles = array();
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        if ( ! empty( $current_user->roles ) ) {
            // Get user roles (convert to lowercase for case-insensitive matching)
            $current_user_roles = array_map( 'strtolower', $current_user->roles );

            // If user is dealer, don't show any messages
            if ( in_array( 'dealer', $current_user_roles ) ) {
                return;
            }
        }
    }

    // Get the active discount message (pass user roles for VIP handling)
    $message = aim_get_active_discount_message( $current_user_roles );

    // Display message if available
    if ( ! empty( $message ) ) {
        echo '<div class="aim-header-discount-message">';
        echo '<p class="aim-discount-text">' . esc_html( $message ) . '</p>';
        echo '</div>';
    }
}

/**
 * Get the active discount message based on pricing rules
 *
 * Priority: 20% > 15% > 10%
 *
 * Role Rules:
 * - Dealer: No messages (handled before calling this function)
 * - VIP: Can see 20% and 15% messages, but NOT 10%
 * - Others: Can see all messages
 *
 * @param array $user_roles Current user's roles (lowercase)
 * @return string|null The discount message or null if none active
 */
function aim_get_active_discount_message( $user_roles = array() ) {
    try {
        // Get all active pricing rules
        $pricings = WCCS_Conditions_Provider::get_pricings( array(
            'status'  => 1,      // Only active rules
            'number'  => -1,     // Get all
            'orderby' => 'ordering',
            'order'   => 'ASC'
        ) );

        if ( empty( $pricings ) ) {
            return null;
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

        // Check if user is VIP (VIP users should not see 10% discount)
        $is_vip = in_array( 'vip', $user_roles );

        // Check each discount in priority order
        foreach ( $discount_priorities as $discount_name => $message ) {
            // Skip 10% discount for VIP users
            if ( $is_vip && $discount_name === 'Sale 10%' ) {
                continue;
            }

            foreach ( $pricings as $pricing ) {
                // Check if this is the discount we're looking for
                if ( strcasecmp( trim( $pricing->name ), trim( $discount_name ) ) !== 0 ) {
                    continue;
                }

                // Validate the pricing rule is truly active

                // 1. Check usage limit if exists
                if ( ! empty( $pricing->usage_limit ) && class_exists( 'WCCS_Usage_Validator' ) ) {
                    if ( ! WCCS_Usage_Validator::check_rule_usage_limit( $pricing ) ) {
                        continue; // Usage limit reached
                    }
                }

                // 2. Validate date/time conditions
                if ( ! empty( $pricing->date_time ) ) {
                    $match_mode = isset( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one';

                    if ( ! $date_time_validator->is_valid_date_times( $pricing->date_time, $match_mode ) ) {
                        continue; // Not within valid date/time range
                    }
                }

                // 3. Validate conditions (user role, cart contents, etc.)
                if ( ! empty( $pricing->conditions ) ) {
                    $conditions_match_mode = isset( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all';

                    if ( ! $condition_validator->is_valid_conditions( $pricing, $conditions_match_mode ) ) {
                        continue; // Conditions not met
                    }
                }

                // If we reach here, this discount is valid and active

                // Get the end date if available
                $end_date = aim_get_earliest_end_date( $pricing->date_time );

                // Append end date to message if it exists
                if ( ! empty( $end_date ) ) {
                    $message .= ' - Ends ' . $end_date;
                }

                // Return immediately (priority order ensures highest discount wins)
                return $message;
            }
        }

    } catch ( Exception $e ) {
        // Silently fail - don't break the site if something goes wrong
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'AIM Header Discount Message Error: ' . $e->getMessage() );
        }
        return null;
    }

    // No active discounts found
    return null;
}

/**
 * Extract and format the earliest end date from pricing rule date_time array
 *
 * @param array $date_times Array of date/time conditions from pricing rule
 * @return string|null Formatted end date (e.g., "December 31, 2025") or null if none
 */
function aim_get_earliest_end_date( $date_times ) {
    if ( empty( $date_times ) || ! is_array( $date_times ) ) {
        return null;
    }

    $earliest_timestamp = null;

    // Loop through all date/time conditions
    foreach ( $date_times as $date_time_wrapper ) {
        if ( empty( $date_time_wrapper ) || ! is_array( $date_time_wrapper ) ) {
            continue;
        }

        // Handle nested array structure - the plugin wraps date_time entries in another array
        // Check if this is a wrapper array containing date objects
        if ( isset( $date_time_wrapper[0] ) && is_array( $date_time_wrapper[0] ) ) {
            // This is a nested array, process each inner entry
            foreach ( $date_time_wrapper as $date_time ) {
                if ( empty( $date_time ) || ! is_array( $date_time ) ) {
                    continue;
                }

                // Check if there's an end date
                if ( ! empty( $date_time['end']['time'] ) ) {
                    $end_date_string = $date_time['end']['time'];

                    // Convert to timestamp
                    $timestamp = strtotime( $end_date_string );

                    if ( $timestamp !== false ) {
                        // Keep track of the earliest end date
                        if ( $earliest_timestamp === null || $timestamp < $earliest_timestamp ) {
                            $earliest_timestamp = $timestamp;
                        }
                    }
                }
            }
        } else {
            // Direct structure (not nested), check for end date directly
            if ( ! empty( $date_time_wrapper['end']['time'] ) ) {
                $end_date_string = $date_time_wrapper['end']['time'];

                // Convert to timestamp
                $timestamp = strtotime( $end_date_string );

                if ( $timestamp !== false ) {
                    // Keep track of the earliest end date
                    if ( $earliest_timestamp === null || $timestamp < $earliest_timestamp ) {
                        $earliest_timestamp = $timestamp;
                    }
                }
            }
        }
    }

    // If we found an end date, format it
    if ( $earliest_timestamp !== null ) {
        // Format as "December 31, 2025"
        return date_i18n( 'F j, Y', $earliest_timestamp );
    }

    return null;
}

/**
 * Add CSS styling to match header text
 */
add_action( 'wp_head', 'aim_header_discount_message_styles', 100 );

function aim_header_discount_message_styles() {
    // Only output CSS on frontend
    if ( is_admin() ) {
        return;
    }

    // Check if discount plugin is active
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
