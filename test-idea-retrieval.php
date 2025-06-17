<?php
/**
 * Test idea retrieval from database
 */

require_once( '../../../wp-load.php' );
require_once( 'ai-blog-generator.php' );

// Test idea ID (replace with actual ID)
$test_idea_id = isset( $argv[1] ) ? intval( $argv[1] ) : 123;

echo "Testing idea retrieval for ID: $test_idea_id\n\n";

// Test 1: Direct database query
global $wpdb;
$table = $wpdb->prefix . 'ai_blog_ideas';

echo "=== Test 1: Direct Database Query ===\n";
$sql = $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $test_idea_id );
echo "SQL: $sql\n";

$direct_result = $wpdb->get_row( $sql );
echo "Result type: " . gettype( $direct_result ) . "\n";
if ( $direct_result ) {
    echo "Status field: " . ( isset( $direct_result->status ) ? $direct_result->status : 'NOT SET' ) . "\n";
    echo "Status type: " . ( isset( $direct_result->status ) ? gettype( $direct_result->status ) : 'UNDEFINED' ) . "\n";
    echo "Status length: " . ( isset( $direct_result->status ) ? strlen( $direct_result->status ) : 'N/A' ) . "\n";
    echo "Full data:\n";
    print_r( $direct_result );
} else {
    echo "No record found\n";
}

echo "\n=== Test 2: Using Idea Model ===\n";
$idea_model = new \AI_Blog_Generator\Models\Idea_Model();
$idea = $idea_model->get( $test_idea_id );

echo "Result type: " . gettype( $idea ) . "\n";
if ( $idea ) {
    echo "Status field: " . ( isset( $idea->status ) ? $idea->status : 'NOT SET' ) . "\n";
    echo "Status type: " . ( isset( $idea->status ) ? gettype( $idea->status ) : 'UNDEFINED' ) . "\n";
    echo "Status comparison:\n";
    echo "  status === 'approved': " . ( $idea->status === 'approved' ? 'TRUE' : 'FALSE' ) . "\n";
    echo "  status == 'approved': " . ( $idea->status == 'approved' ? 'TRUE' : 'FALSE' ) . "\n";
    echo "  trim(status) === 'approved': " . ( trim( $idea->status ) === 'approved' ? 'TRUE' : 'FALSE' ) . "\n";
    echo "Full data:\n";
    print_r( $idea );
} else {
    echo "No record found\n";
}

echo "\n=== Test 3: Check all approved ideas ===\n";
$sql = "SELECT id, status, LENGTH(status) as status_length FROM $table WHERE status = 'approved' LIMIT 5";
$approved_ideas = $wpdb->get_results( $sql );
echo "Found " . count( $approved_ideas ) . " approved ideas\n";
foreach ( $approved_ideas as $approved_idea ) {
    echo "ID: {$approved_idea->id}, Status: '{$approved_idea->status}', Length: {$approved_idea->status_length}\n";
}

echo "\nTest complete!\n"; 