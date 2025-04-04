<?php
/**
 * Plugin Name: Copypress Rest API
 * Description: A plugin to post management API for integration with external applications with image upload functionality.
 * Version: 1.1
 * Requires at least: 6.4
 * Requires PHP: 7.3
 * Author: CopyPress
 * Author URI: 
 * License: GPLv2 or later
 * Text Domain: copypress-rest-api
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'COPYREAP_REST_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COPYREAP_REST_API_TEXT_DOMAIN', 'copypress-rest-api' );

// Include necessary files
require_once COPYREAP_REST_API_PLUGIN_DIR . 'includes/class-copypress-rest-api-validation.php';
require_once COPYREAP_REST_API_PLUGIN_DIR . 'includes/class-copypress-rest-api-endpoints.php';
require_once COPYREAP_REST_API_PLUGIN_DIR . 'includes/class-copypress-rest-api-image.php';
require_once COPYREAP_REST_API_PLUGIN_DIR . 'includes/class-copypress-jwt-token.php';
