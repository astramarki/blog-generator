<?php
/**
 * Direct Generation Test - Bypass Cron Issues
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

echo "=== DIRECT GENERATION TEST ===\n";

$debug_log = __DIR__ . '/debug-transaction.log';

// Get the first approved idea
global $wpdb;
$table_name = $wpdb->prefix . 'ai_blog_ideas';
$test_idea = $wpdb->get_row("SELECT * FROM $table_name WHERE status = 'approved' LIMIT 1", ARRAY_A);

if (!$test_idea) {
    echo "No approved ideas found for testing\n";
    exit;
}

$idea_id = $test_idea['id'];
echo "Testing with idea ID: $idea_id\n";
echo "Idea title: " . $test_idea['title'] . "\n\n";

// Test direct generation
try {
    echo "1. Testing Background Processor...\n";
    $processor = new \AI_Blog_Generator\Services\Background_Processor();
    
    // Check if already in progress
    $in_progress = $processor->is_generation_in_progress($idea_id);
    echo "   Already in progress: " . ($in_progress ? "YES" : "NO") . "\n";
    
    if ($in_progress) {
        echo "   Cleaning up existing lock...\n";
        delete_transient("ai_blog_generation_lock_{$idea_id}");
        delete_transient("ai_blog_generation_status_{$idea_id}");
    }
    
    echo "\n2. Calling process_generation directly (bypass cron)...\n";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - DIRECT TEST: Starting direct generation for idea $idea_id\n", FILE_APPEND);
    
    // Call process_generation directly to bypass cron issues
    $processor->process_generation($idea_id);
    
    echo "   Direct generation call completed!\n";
    echo "   Check debug-transaction.log for detailed progress\n\n";
    
    echo "3. Checking final status...\n";
    
    // Check final idea status
    $final_idea = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $idea_id), ARRAY_A);
    echo "   Final idea status: " . $final_idea['status'] . "\n";
    
    // Check if blog post was created
    $blog_table = $wpdb->prefix . 'ai_blog_generated_posts';
    $blog_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $blog_table WHERE idea_id = %d ORDER BY created_at DESC LIMIT 1", $idea_id), ARRAY_A);
    
    if ($blog_post) {
        echo "   Blog post created: YES (ID: " . $blog_post['id'] . ")\n";
        echo "   WordPress post ID: " . $blog_post['wp_post_id'] . "\n";
    } else {
        echo "   Blog post created: NO\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - DIRECT TEST ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

echo "\n=== TEST COMPLETE ===\n";
echo "Check the debug-transaction.log file for detailed execution logs\n";
?> 