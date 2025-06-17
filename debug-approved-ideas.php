<?php
/**
 * Debug script to test approved ideas loading
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Write to debug log
function write_debug($message) {
	$log_file = __DIR__ . '/debug-transaction.log';
	$log_entry = "[DEBUG] " . date('Y-m-d H:i:s') . ": " . $message . "\n";
	file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
	echo $log_entry;
}

write_debug("=== APPROVED IDEAS DEBUG START ===");

// Load the idea model
require_once __DIR__ . '/models/class-idea-model.php';

try {
	$idea_model = new AI_Blog_Generator\Models\Idea_Model();
	write_debug("Idea model loaded successfully");
	
	// Test get_by_status method
	$ideas = $idea_model->get_by_status('approved');
	write_debug("Found " . count($ideas) . " approved ideas");
	
	if (count($ideas) > 0) {
		write_debug("First idea: " . json_encode($ideas[0], JSON_PRETTY_PRINT));
	}
	
	// Test direct database query
	global $wpdb;
	$table_name = $wpdb->prefix . 'ai_blog_ideas';
	
	$sql = "SELECT * FROM {$table_name} WHERE status = 'approved' ORDER BY created_at ASC LIMIT 10";
	$direct_results = $wpdb->get_results($sql, ARRAY_A);
	
	write_debug("Direct DB query found " . count($direct_results) . " approved ideas");
	
	if (count($direct_results) > 0) {
		write_debug("First direct result: " . json_encode($direct_results[0], JSON_PRETTY_PRINT));
	}
	
	// Check for any ideas at all
	$all_ideas = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status", ARRAY_A);
	write_debug("All ideas by status: " . json_encode($all_ideas, JSON_PRETTY_PRINT));
	
} catch (Exception $e) {
	write_debug("ERROR: " . $e->getMessage());
	write_debug("Stack trace: " . $e->getTraceAsString());
}

write_debug("=== APPROVED IDEAS DEBUG END ==="); 