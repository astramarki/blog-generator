<?php
require_once '../../../wp-load.php';

echo "=== TESTING GENERATION PROCESS ===\n\n";

// Get first approved idea
global $wpdb;
$table = $wpdb->prefix . 'ai_blog_ideas';
$idea = $wpdb->get_row("SELECT * FROM $table WHERE status = 'approved' LIMIT 1", ARRAY_A);

if (!$idea) {
    echo "No approved ideas found\n";
    exit;
}

$idea_id = $idea['id'];
echo "Testing with idea ID: $idea_id\n";
echo "Title: " . $idea['title'] . "\n\n";

// Test Background Processor
echo "1. Testing Background Processor...\n";
try {
    $processor = new AI_Blog_Generator\Services\Background_Processor();
    echo "   ✓ Background Processor created\n";
    
    // Check if generation is in progress
    $in_progress = $processor->is_generation_in_progress($idea_id);
    echo "   Lock check: " . ($in_progress ? "LOCKED" : "FREE") . "\n";
    
    // Try to start generation
    echo "   Starting generation...\n";
    $started = $processor->start_generation($idea_id);
    echo "   Result: " . ($started ? "SUCCESS" : "FAILED") . "\n";
    
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n2. Checking debug log...\n";
$log_content = file_get_contents(__DIR__ . '/debug-transaction.log');
echo "Log content:\n" . ($log_content ?: "(empty)") . "\n"; 