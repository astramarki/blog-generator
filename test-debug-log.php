<?php
/**
 * Test Debug Log File Creation
 */

$debug_log = __DIR__ . '/debug-transaction.log';

echo "Testing debug log file creation...\n";
echo "Debug log path: $debug_log\n";

// Test writing to the log file
$test_message = date( 'Y-m-d H:i:s' ) . " - TEST: Debug logging system is working\n";
$write_result = file_put_contents( $debug_log, $test_message, FILE_APPEND );

if ( $write_result !== false ) {
    echo "✅ Successfully wrote to debug log file ($write_result bytes)\n";
    
    // Read back the content
    if ( file_exists( $debug_log ) ) {
        echo "✅ Debug log file exists\n";
        echo "Recent entries:\n";
        echo file_get_contents( $debug_log );
    } else {
        echo "❌ Debug log file does not exist after writing\n";
    }
} else {
    echo "❌ Failed to write to debug log file\n";
    echo "Check permissions for directory: " . dirname( $debug_log ) . "\n";
}

echo "\nNow try generating a blog post and then check this file for debug entries.\n"; 