<?php
/**
 * Verify that early transactions have been removed from Content_Generator
 */

require_once 'services/class-content-generator.php';

echo "Checking Content_Generator for early transaction starts...\n\n";

$file_content = file_get_contents('services/class-content-generator.php');

// Check if the old transaction code has been removed
$old_pattern = '/All idea validations passed, starting transaction.*?start_transaction\(\);/s';
if (preg_match($old_pattern, $file_content)) {
    echo "❌ ERROR: Old transaction code still exists!\n";
    echo "The early transaction start has NOT been removed.\n";
} else {
    echo "✅ Good: Old transaction code has been removed.\n";
}

// Check if the new transaction code exists
$new_pattern = '/Start transaction only for critical database operations/';
if (preg_match($new_pattern, $file_content)) {
    echo "✅ Good: New transaction code (for critical operations only) is present.\n";
} else {
    echo "❌ ERROR: New transaction code is missing!\n";
}

// Check if transaction_started variable is declared at function start
$var_pattern = '/function generate_blog_post.*?\$transaction_started = false;/s';
if (preg_match($var_pattern, $file_content)) {
    echo "✅ Good: \$transaction_started variable is properly declared.\n";
} else {
    echo "❌ ERROR: \$transaction_started variable declaration is missing!\n";
}

// Count how many times start_transaction is called in generate_blog_post
preg_match('/function generate_blog_post\s*\([^)]*\)\s*{(.*?)^[\t ]*}/ms', $file_content, $matches);
if (isset($matches[1])) {
    $function_body = $matches[1];
    $transaction_calls = substr_count($function_body, '->start_transaction()');
    echo "\n📊 Transaction calls in generate_blog_post(): $transaction_calls\n";
    
    if ($transaction_calls === 1) {
        echo "✅ Good: Only one transaction start (for critical operations).\n";
    } elseif ($transaction_calls === 0) {
        echo "❓ Warning: No transaction starts found. Make sure critical operations are protected.\n";
    } else {
        echo "❌ ERROR: Multiple transaction starts found ($transaction_calls)!\n";
    }
}

echo "\n";

// Also check Database_Manager for retry logic
echo "Checking Database_Manager for retry logic...\n";
$db_content = file_get_contents('models/class-database-manager.php');

if (strpos($db_content, 'database_insert_retry') !== false) {
    echo "✅ Good: Database retry logic is present in insert().\n";
} else {
    echo "❌ ERROR: Database retry logic is missing from insert()!\n";
}

if (strpos($db_content, 'database_update_retry') !== false) {
    echo "✅ Good: Database retry logic is present in update().\n";
} else {
    echo "❌ ERROR: Database retry logic is missing from update()!\n";
}

echo "\nVerification complete.\n"; 