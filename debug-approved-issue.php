<?php
/**
 * Debug script for approved ideas loading issue
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

function debug_log($message) {
    $log_file = __DIR__ . '/debug-transaction.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[DEBUG] $timestamp: $message\n", FILE_APPEND | LOCK_EX);
    echo "$timestamp: $message\n";
}

debug_log("=== DEBUGGING APPROVED IDEAS ISSUE ===");

// 1. Check database table exists
global $wpdb;
$table_name = $wpdb->prefix . 'ai_blog_ideas';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
debug_log("Table '$table_name' exists: " . ($table_exists ? "YES" : "NO"));

if ($table_exists) {
    // 2. Check all idea statuses
    $statuses = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table_name GROUP BY status", ARRAY_A);
    debug_log("All idea statuses in database:");
    foreach ($statuses as $status) {
        debug_log("  - {$status['status']}: {$status['count']} ideas");
    }
    
    // 3. Get specific approved ideas
    $approved_ideas = $wpdb->get_results("SELECT id, title, status, created_at FROM $table_name WHERE status = 'approved' LIMIT 5", ARRAY_A);
    debug_log("Direct DB query found " . count($approved_ideas) . " approved ideas");
    
    foreach ($approved_ideas as $idea) {
        debug_log("  - ID {$idea['id']}: {$idea['title']} (created: {$idea['created_at']})");
    }
}

// 4. Test the Idea Model
debug_log("Testing Idea Model...");
try {
    require_once __DIR__ . '/models/class-idea-model.php';
    $idea_model = new AI_Blog_Generator\Models\Idea_Model();
    
    $model_ideas = $idea_model->get_by_status('approved');
    debug_log("Idea Model returned " . count($model_ideas) . " approved ideas");
    
    if (count($model_ideas) > 0) {
        debug_log("First model idea details:");
        $first_idea = $model_ideas[0];
        if (is_object($first_idea)) {
            debug_log("  - Type: object, ID: {$first_idea->id}, Title: {$first_idea->title}");
        } elseif (is_array($first_idea)) {
            debug_log("  - Type: array, ID: {$first_idea['id']}, Title: {$first_idea['title']}");
        } else {
            debug_log("  - Type: " . gettype($first_idea));
        }
    }
} catch (Exception $e) {
    debug_log("Idea Model ERROR: " . $e->getMessage());
}

// 5. Test the AJAX handler directly
debug_log("Testing AJAX handler simulation...");
try {
    // Simulate the AJAX call
    $_POST['action'] = 'ai_blog_get_approved_ideas';
    $_POST['nonce'] = wp_create_nonce('ai_blog_admin_nonce');
    
    // Check current user capabilities
    debug_log("Current user ID: " . get_current_user_id());
    debug_log("User can manage_options: " . (current_user_can('manage_options') ? 'YES' : 'NO'));
    
    // Test nonce verification
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'ai_blog_admin_nonce');
    debug_log("Nonce verification result: " . ($nonce_valid ? 'VALID' : 'INVALID'));
    
    // Load the controller
    require_once __DIR__ . '/controllers/class-blog-controller.php';
    $controller = new AI_Blog_Generator\Controllers\Blog_Controller();
    
    debug_log("Blog Controller loaded successfully");
    
} catch (Exception $e) {
    debug_log("AJAX handler test ERROR: " . $e->getMessage());
}

// 6. Check WordPress admin area setup
debug_log("WordPress admin checks:");
debug_log("  - is_admin(): " . (is_admin() ? 'YES' : 'NO'));
debug_log("  - DOING_AJAX: " . (defined('DOING_AJAX') && DOING_AJAX ? 'YES' : 'NO'));
debug_log("  - ajaxurl global should be: " . admin_url('admin-ajax.php'));

debug_log("=== DEBUG COMPLETE ===");
debug_log("Next steps:");
debug_log("1. Check the approved-blogs page source for wp_nonce field");
debug_log("2. Check browser console for JavaScript errors");
debug_log("3. Test the AJAX call manually in browser dev tools");
debug_log("4. Verify WordPress user permissions");
?> 