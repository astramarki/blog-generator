<?php
/**
 * Test error logging configuration
 */

// Show current error reporting and logging settings
echo "PHP Error Reporting Settings:\n";
echo "error_reporting: " . error_reporting() . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "error_log: " . ini_get('error_log') . "\n";

// Test error log
error_log('AI Blog Generator TEST: This is a test error log entry at ' . date('Y-m-d H:i:s'));

// Check WordPress debug settings
if (defined('WP_DEBUG')) {
    echo "WP_DEBUG: " . (WP_DEBUG ? 'true' : 'false') . "\n";
}
if (defined('WP_DEBUG_LOG')) {
    echo "WP_DEBUG_LOG: " . (WP_DEBUG_LOG ? 'true' : 'false') . "\n";
}
if (defined('WP_DEBUG_DISPLAY')) {
    echo "WP_DEBUG_DISPLAY: " . (WP_DEBUG_DISPLAY ? 'true' : 'false') . "\n";
}

// Check common log file locations
$log_locations = [
    'PHP error_log' => ini_get('error_log'),
    'WordPress debug.log' => WP_CONTENT_DIR . '/debug.log',
    'Plugin debug.log' => __DIR__ . '/debug.log',
    'Plugin error.log' => __DIR__ . '/error.log',
];

echo "\nChecking log file locations:\n";
foreach ($log_locations as $name => $path) {
    if ($path && file_exists($path)) {
        echo "$name: EXISTS at $path\n";
        echo "Size: " . filesize($path) . " bytes\n";
        echo "Last modified: " . date('Y-m-d H:i:s', filemtime($path)) . "\n";
    } else {
        echo "$name: NOT FOUND ($path)\n";
    }
}

echo "\nTest completed.\n"; 