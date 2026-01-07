<?php
/**
 * Plugin Name: Nikah-Bandhan – Dynamic Matrimonial Biodata System for WordPress
 * Description: A dynamic matrimonial biodata system for WordPress with admin approval, advanced search, and detailed single profile view.
 * Version: 0.1
 * Author: Showrab Mojumdar
 * Plugin URI: https://github.com/ShowrabM/Nikah-Bandhan
 * Author URI: https://www.onvirtualworld.com
 */

/*
Full Plugin Description

Nikah-Bandhan is a flexible and powerful matrimonial biodata plugin for WordPress, designed for Indian and community-based matchmaking websites. It allows users to create and submit matrimonial biodata, while administrators maintain full control through an approval system.

The plugin supports multiple communities and traditions, making it suitable for Muslim, Hindu, Christian, Jain, Sikh, and inter-community matrimonial platforms.

Key Features

- Dynamic Biodata Management
  - Frontend biodata submission
  - Fully customizable profile fields
- Admin Approval System
  - Review and approve profiles before publishing
  - Prevent fake or incomplete entries
- Advanced Search & Filtering
  - Search by age, gender, location, religion, caste/community, education, profession, and more
- Single Biodata View
  - Clean and structured individual profile pages
  - Mobile-friendly layout
- Privacy & Control
  - Control visibility of contact information
  - Suitable for family-managed profiles
- Theme & Plugin Compatible
  - Built using WordPress standards
  - Works with most themes and page builders

Ideal For

- Matrimonial & Biodata Websites
- Community-Based Matchmaking Platforms
- Religious & Social Organizations
- Family-Managed Matrimony Services
- Local & Regional Matrimonial Portals
*/

if (!defined('ABSPATH')) exit;

define('OVW_MAT_VER', '1.0.0');
define('OVW_MAT_PATH', plugin_dir_path(__FILE__));
define('OVW_MAT_URL', plugin_dir_url(__FILE__));

require_once OVW_MAT_PATH . 'includes/helpers.php';
require_once OVW_MAT_PATH . 'includes/class-installer.php';
require_once OVW_MAT_PATH . 'includes/class-admin.php';
require_once OVW_MAT_PATH . 'includes/class-blocks.php';
require_once OVW_MAT_PATH . 'includes/class-rest.php';
require_once OVW_MAT_PATH . 'includes/class-shortcodes.php';

register_activation_hook(__FILE__, ['OVW_MAT_Installer', 'activate']);

add_action('plugins_loaded', function() {
    // Run updates and migrations only if version changes
    if (get_option('ovw_mat_version') !== OVW_MAT_VER) {
        OVW_MAT_Installer::ensure_tables();
        update_option('ovw_mat_version', OVW_MAT_VER);
    }

    new OVW_MAT_Admin();
    new OVW_MAT_Blocks();
    new OVW_MAT_Rest();
    new OVW_MAT_Shortcodes();
});

// Prevent stray output from breaking REST JSON responses.
add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    return $served;
}, 0, 4);
