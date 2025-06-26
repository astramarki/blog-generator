<?php
/**
 * Test Generation Queue Functionality
 * 
 * This script tests the generation queue to ensure it properly handles
 * multiple simultaneous generations up to the configured limit.
 * 
 * Usage: Run this script from the plugin directory
 */

// Load WordPress
require_once( '../../../../wp-load.php' );

// Load plugin files
require_once( __DIR__ . '/ai-blog-generator.php' );

use AI_Blog_Generator\Services\Generation_Queue;
use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
use AI_Blog_Generator\Utilities\Logger;

// Initialize services
$queue = new Generation_Queue();
$ideas_model = new Blog_Ideas_Model_V2();

echo "=== AI Blog Generator - Generation Queue Test ===\n\n";

// 1. Check current queue status
echo "1. Current Queue Status:\n";
$status = $queue->get_queue_status();
echo "   - Active generations: " . $status['active_count'] . " / " . Generation_Queue::MAX_CONCURRENT . "\n";
echo "   - Queued ideas: " . $status['queue_length'] . "\n";
echo "   - Available slots: " . $status['available_slots'] . "\n\n";

// 2. List active generations
if ( ! empty( $status['active_generations'] ) ) {
	echo "2. Active Generations:\n";
	foreach ( $status['active_generations'] as $gen ) {
		$duration = round( $gen['duration'] / 60, 1 );
		echo "   - ID: {$gen['id']} | Title: {$gen['title']} | Status: {$gen['status']} | Duration: {$duration} min\n";
	}
	echo "\n";
} else {
	echo "2. No active generations\n\n";
}

// 3. List queued ideas
if ( ! empty( $status['queued_ideas'] ) ) {
	echo "3. Queued Ideas:\n";
	foreach ( $status['queued_ideas'] as $idea ) {
		echo "   - Position {$idea['position']}: ID {$idea['id']} - {$idea['title']}\n";
	}
	echo "\n";
} else {
	echo "3. No ideas in queue\n\n";
}

// 4. Check transients directly
echo "4. Direct Transient Check:\n";
$active_transient = get_transient( 'ai_blog_active_generations' );
$queue_transient = get_transient( 'ai_blog_generation_queue_status' );
echo "   - Active generations transient: " . ( $active_transient ? count( $active_transient ) . " items" : "empty" ) . "\n";
echo "   - Queue status transient: " . ( $queue_transient ? count( $queue_transient ) . " items" : "empty" ) . "\n\n";

// 5. Check for stuck generations
echo "5. Checking for Stuck Generations:\n";
$generating_ideas = $ideas_model->get_by_status( 'generating' );
$stuck_count = 0;
foreach ( $generating_ideas as $idea ) {
	$updated_time = strtotime( $idea['updated_at'] );
	$stuck_minutes = round( ( time() - $updated_time ) / 60, 1 );
	
	if ( $stuck_minutes > 15 ) {
		$stuck_count++;
		echo "   - STUCK: ID {$idea['id']} - {$idea['title']} (stuck for {$stuck_minutes} minutes)\n";
	}
}
if ( $stuck_count === 0 ) {
	echo "   - No stuck generations found\n";
}
echo "\n";

// 6. Test cleanup functionality
if ( $stuck_count > 0 ) {
	echo "6. Testing Cleanup:\n";
	echo "   - Running get_active_count() to trigger cleanup...\n";
	$count_before = $queue->get_active_count();
	echo "   - Active count after cleanup: $count_before\n";
	
	// Re-check stuck generations
	$generating_ideas_after = $ideas_model->get_by_status( 'generating' );
	$stuck_after = 0;
	foreach ( $generating_ideas_after as $idea ) {
		$updated_time = strtotime( $idea['updated_at'] );
		if ( ( time() - $updated_time ) / 60 > 15 ) {
			$stuck_after++;
		}
	}
	echo "   - Stuck generations after cleanup: $stuck_after\n\n";
} else {
	echo "6. Cleanup test skipped (no stuck generations)\n\n";
}

// 7. Recommendations
echo "7. Recommendations:\n";
if ( $status['active_count'] >= Generation_Queue::MAX_CONCURRENT ) {
	echo "   - Queue is at maximum capacity. New generations will be queued.\n";
} else {
	echo "   - Queue has " . $status['available_slots'] . " available slots for new generations.\n";
}

if ( $stuck_count > 0 ) {
	echo "   - Found stuck generations. These should be automatically cleaned up on the next queue check.\n";
}

echo "\n=== Test Complete ===\n";

// Optional: Clear stuck generations manually
if ( isset( $_GET['clear'] ) && $_GET['clear'] === 'yes' ) {
	echo "\n[MANUAL CLEAR REQUESTED]\n";
	echo "Clearing all transients...\n";
	delete_transient( 'ai_blog_active_generations' );
	delete_transient( 'ai_blog_generation_queue_status' );
	echo "Transients cleared. Re-run the test to verify.\n";
} 