<?php
// Debug script to check approved ideas
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

echo "=== CHECKING APPROVED IDEAS IN DATABASE ===\n";

// Check the table exists
$table_name = $wpdb->prefix . 'ai_blog_ideas';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

echo "Table '$table_name' exists: " . ($table_exists ? "YES" : "NO") . "\n";

if ($table_exists) {
    // Get all statuses
    $statuses = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table_name GROUP BY status", ARRAY_A);
    echo "All idea statuses:\n";
    foreach ($statuses as $status) {
        echo "  - {$status['status']}: {$status['count']} ideas\n";
    }
    
    // Get approved ideas specifically
    $approved = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'approved' LIMIT 5", ARRAY_A);
    echo "\nApproved ideas found: " . count($approved) . "\n";
    
    if (count($approved) > 0) {
        echo "First approved idea:\n";
        print_r($approved[0]);
    }
    
    // Test the model
    echo "\n=== TESTING IDEA MODEL ===\n";
    require_once __DIR__ . '/models/class-idea-model.php';
    
    try {
        $idea_model = new AI_Blog_Generator\Models\Idea_Model();
        $model_ideas = $idea_model->get_by_status('approved');
        echo "Model returned " . count($model_ideas) . " approved ideas\n";
        
        if (count($model_ideas) > 0) {
            echo "First model idea:\n";
            print_r($model_ideas[0]);
        }
    } catch (Exception $e) {
        echo "Model error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== DONE ===\n"; 