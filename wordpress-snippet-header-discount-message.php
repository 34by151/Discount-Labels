<?php
/**
 * Snippet Name: ArtInMetal Header Discount Message
 * Description: Display active discount messages in the site header based on pricing rules
 * Author: Custom Development
 * Version: 1.0.0
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
 * Hook: astra_header_after (Change this if needed)
 * Alternative hooks: astra_masthead_content, wp_body_open, astra_body_top
 */
add_action( 'astra_header_after', 'aim_display_header_discount_message', 10 );

function aim_display_header_discount_message() {
    // Only run on frontend, not admin
    if ( is_admin() ) {
        return;
    }

    // Check if discount plugin is active
    if ( ! function_exists( 'WCCS' ) || ! class_exists( 'WCCS_Conditions_Provider' ) ) {
        return;
    }

    // Check if user is logged in and has excluded roles
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        if ( ! empty( $current_user->roles ) ) {
            // Get user roles (convert to lowercase for case-insensitive matching)
            $user_roles = array_map( 'strtolower', $current_user->roles );

            // Exclude dealer and vip roles (case insensitive)
            $excluded_roles = array( 'dealer', 'vip' );

            // If user has any excluded role, don't show message
            if ( ! empty( array_intersect( $user_roles, $excluded_roles ) ) ) {
                return;
            }
        }
    }

    // Get the active discount message
    $message = aim_get_active_discount_message();

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
 * @return string|null The discount message or null if none active
 */
function aim_get_active_discount_message() {
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

        // Check each discount in priority order
        foreach ( $discount_priorities as $discount_name => $message ) {
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
