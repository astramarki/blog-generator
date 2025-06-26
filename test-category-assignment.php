<?php
/**
 * Test Category Assignment for Generated Posts
 * 
 * This script tests that blog posts are properly assigned to all their categories
 * from the ai_blog_idea_categories table when generated.
 * 
 * Usage: Run this script from the plugin directory
 */

// Load WordPress
require_once( '../../../../wp-load.php' );

// Load plugin files
require_once( __DIR__ . '/ai-blog-generator.php' );

use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;

// Initialize model
$ideas_model = new Blog_Ideas_Model_V2();

echo "=== AI Blog Generator - Category Assignment Test ===\n\n";

// 1. Get a sample of ideas with their categories
echo "1. Checking Ideas with Categories:\n";
$ideas = $ideas_model->get_by_status( 'generated' );

if ( empty( $ideas ) ) {
	echo "   No generated ideas found. Checking approved ideas...\n";
	$ideas = $ideas_model->get_by_status( 'approved' );
}

if ( empty( $ideas ) ) {
	echo "   No ideas found in database.\n";
	exit;
}

$sample_count = min( 5, count( $ideas ) );
echo "   Found " . count( $ideas ) . " ideas. Checking first $sample_count...\n\n";

foreach ( array_slice( $ideas, 0, $sample_count ) as $idea ) {
	echo "   Idea ID: {$idea['id']} - {$idea['title']}\n";
	echo "   - Status: {$idea['status']}\n";
	echo "   - Category IDs from DB: " . ( ! empty( $idea['category_ids'] ) ? implode( ', ', $idea['category_ids'] ) : 'None' ) . "\n";
	echo "   - Category Names: " . ( ! empty( $idea['category_names'] ) ? implode( ', ', $idea['category_names'] ) : 'None' ) . "\n";
	
	// If generated, check the actual WordPress post
	if ( $idea['status'] === 'generated' ) {
		global $wpdb;
		
		// Get the post ID from the generated posts table
		$post_id = $wpdb->get_var( $wpdb->prepare( 
			"SELECT post_id FROM {$wpdb->prefix}ai_blog_generated_posts WHERE idea_id = %d", 
			$idea['id'] 
		) );
		
		if ( $post_id ) {
			echo "   - WordPress Post ID: $post_id\n";
			
			// Get the categories assigned to the post
			$post_categories = wp_get_post_categories( $post_id );
			echo "   - Assigned Category IDs: " . ( ! empty( $post_categories ) ? implode( ', ', $post_categories ) : 'None' ) . "\n";
			
			// Get category names
			if ( ! empty( $post_categories ) ) {
				$cat_names = [];
				foreach ( $post_categories as $cat_id ) {
					$cat = get_category( $cat_id );
					if ( $cat ) {
						$cat_names[] = $cat->name;
					}
				}
				echo "   - Assigned Category Names: " . implode( ', ', $cat_names ) . "\n";
			}
			
			// Check if all idea categories are assigned to the post
			if ( ! empty( $idea['category_ids'] ) && ! empty( $post_categories ) ) {
				$missing_cats = array_diff( $idea['category_ids'], $post_categories );
				if ( empty( $missing_cats ) ) {
					echo "   ✓ All idea categories are properly assigned to the post!\n";
				} else {
					echo "   ✗ Missing categories: " . implode( ', ', $missing_cats ) . "\n";
				}
			}
		} else {
			echo "   - No WordPress post found for this generated idea\n";
		}
	}
	
	echo "\n";
}

// 2. Check the idea_categories table directly
echo "2. Direct Check of idea_categories Table:\n";
$categories_table = $wpdb->prefix . 'ai_blog_idea_categories';

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$categories_table'" ) === $categories_table;
if ( ! $table_exists ) {
	echo "   ✗ Table $categories_table does not exist!\n";
} else {
	echo "   ✓ Table $categories_table exists\n";
	
	// Get a sample of category assignments
	$sample_assignments = $wpdb->get_results( 
		"SELECT ic.*, i.title as idea_title, t.name as category_name 
		 FROM $categories_table ic
		 LEFT JOIN {$wpdb->prefix}ai_blog_ideas i ON ic.blog_idea_id = i.id
		 LEFT JOIN {$wpdb->terms} t ON ic.category_id = t.term_id
		 LIMIT 10"
	);
	
	if ( ! empty( $sample_assignments ) ) {
		echo "   Sample category assignments:\n";
		foreach ( $sample_assignments as $assignment ) {
			echo "   - Idea '{$assignment->idea_title}' (ID: {$assignment->blog_idea_id}) → Category '{$assignment->category_name}' (ID: {$assignment->category_id})\n";
		}
	} else {
		echo "   - No category assignments found in the table\n";
	}
}

echo "\n3. Recommendations:\n";
echo "   - If categories are missing from posts, regenerate the affected posts\n";
echo "   - The fix ensures all future generated posts will have proper categories\n";
echo "   - Existing posts may need manual category assignment if they were generated before the fix\n";

echo "\n=== Test Complete ===\n"; 