<?php
/**
 * WordPress Development Configuration Settings
 * 
 * Add these constants to your wp-config.php file to enable
 * error display during development of the AI Blog Generator plugin.
 * 
 * IMPORTANT: Never use these settings on a production site!
 */

// Enable WordPress debugging
define( 'WP_DEBUG', true );

// Display errors on screen
define( 'WP_DEBUG_DISPLAY', true );

// Log errors to wp-content/debug.log
define( 'WP_DEBUG_LOG', true );

// Use development versions of core JS and CSS files
define( 'SCRIPT_DEBUG', true );

// Save database queries for analysis
define( 'SAVEQUERIES', true );

// Additional PHP error settings (optional)
@ini_set( 'display_errors', 'On' );
@ini_set( 'error_reporting', E_ALL );

/**
 * Example usage in your wp-config.php:
 * 
 * 1. Find the line that says "That's all, stop editing!" in wp-config.php
 * 2. Add the constants above that line
 * 3. Save the file and refresh your site
 * 
 * To disable debugging, either:
 * - Set WP_DEBUG to false: define( 'WP_DEBUG', false );
 * - Or comment out the debug lines
 * 
 * Error locations:
 * - Screen: Errors will appear directly on your WordPress pages
 * - Log file: /wp-content/debug.log
 * - Plugin log: /wp-content/plugins/blog-generator/debug.log
 */ 