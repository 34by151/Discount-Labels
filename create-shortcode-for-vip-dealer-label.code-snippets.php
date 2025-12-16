<?php

/**
 * Create shortcode for VIP/Dealer label
 *
 * Create shortcode for VIP/Dealer label
 */
// Create shortcode for VIP/Dealer label
function aim_user_role_label_shortcode($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '';
    }
    
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;
    
    // Convert roles to lowercase for case-insensitive comparison
    $user_roles_lower = array_map('strtolower', $user_roles);
    
    $label_text = '';
    
    // Check for VIP or Dealer roles (case insensitive)
    if (in_array('vip', $user_roles_lower)) {
        $label_text = 'VIP Pricing Store Wide';
    } elseif (in_array('dealer', $user_roles_lower)) {
        $label_text = 'Dealer Pricing Store Wide';
    }
    
    // Only display if user has VIP or Dealer role
    if (!empty($label_text)) {
        return '<div class="aim-user-role-label" style="text-align: center; padding: 10px 0; width: 100%;"><span>' . esc_html($label_text) . '</span></div>';
    }
    
    return '';
}

// Register the shortcode
add_shortcode('aim_role_label', 'aim_user_role_label_shortcode');
