<?php
/**
 * Fix existing posts with ApexCharts code issues
 * This script cleans up fusion_code blocks in existing posts
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

// Ensure the plugin is loaded
if ( ! class_exists( 'AI_Blog_Generator' ) ) {
	die( "AI Blog Generator plugin is not active.\n" );
}

// Get the main plugin instance to use its cleaning methods
$plugin = AI_Blog_Generator::instance();

echo "=== Fix Existing ApexCharts Code ===\n\n";

// Query posts that might contain fusion_code blocks
$args = [
	'post_type' => 'post',
	'post_status' => ['publish', 'draft', 'future'],
	'posts_per_page' => -1,
	'meta_query' => [
		[
			'key' => 'ai_generated',
			'value' => '1',
			'compare' => '='
		]
	]
];

$query = new WP_Query( $args );

echo "Found {$query->post_count} AI-generated posts to check.\n\n";

$fixed_count = 0;
$error_count = 0;

// Process each post
while ( $query->have_posts() ) {
	$query->the_post();
	$post_id = get_the_ID();
	$post_title = get_the_title();
	$content = get_the_content( null, false );
	
	// Check if post contains fusion_code blocks
	if ( strpos( $content, '[fusion_code]' ) === false ) {
		continue;
	}
	
	echo "Processing: {$post_title} (ID: {$post_id})...\n";
	
	// Get the raw post content
	$post = get_post( $post_id );
	$original_content = $post->post_content;
	
	// Use the plugin's deep clean method via reflection (since it's private)
	$reflection = new ReflectionClass( $plugin );
	$method = $reflection->getMethod( 'deep_clean_fusion_code' );
	$method->setAccessible( true );
	$cleaned_content = $method->invoke( $plugin, $original_content );
	
	// Check if content changed
	if ( $cleaned_content !== $original_content ) {
		// Update the post
		$result = wp_update_post( [
			'ID' => $post_id,
			'post_content' => $cleaned_content
		], true );
		
		if ( is_wp_error( $result ) ) {
			echo "  ERROR: " . $result->get_error_message() . "\n";
			$error_count++;
		} else {
			echo "  FIXED: Cleaned fusion_code blocks\n";
			$fixed_count++;
			
			// Count what was fixed
			$p_tags_removed = substr_count( $original_content, '<p>' ) - substr_count( $cleaned_content, '<p>' );
			$br_tags_removed = substr_count( $original_content, '<br' ) - substr_count( $cleaned_content, '<br' );
			$entities_decoded = ( strpos( $original_content, '&gt;' ) !== false || strpos( $original_content, '&lt;' ) !== false );
			
			if ( $p_tags_removed > 0 ) {
				echo "    - Removed {$p_tags_removed} <p> tags\n";
			}
			if ( $br_tags_removed > 0 ) {
				echo "    - Removed {$br_tags_removed} <br> tags\n";
			}
			if ( $entities_decoded ) {
				echo "    - Decoded HTML entities\n";
			}
			
			// Check if script tags were added
			preg_match_all( '/\[fusion_code\](.*?)\[\/fusion_code\]/s', $original_content, $original_blocks );
			preg_match_all( '/\[fusion_code\](.*?)\[\/fusion_code\]/s', $cleaned_content, $cleaned_blocks );
			
			$script_tags_added = 0;
			for ( $i = 0; $i < count( $original_blocks[1] ); $i++ ) {
				if ( isset( $cleaned_blocks[1][$i] ) ) {
					$had_script = strpos( $original_blocks[1][$i], '<script' ) !== false;
					$has_script = strpos( $cleaned_blocks[1][$i], '<script' ) !== false;
					if ( ! $had_script && $has_script ) {
						$script_tags_added++;
					}
				}
			}
			
			if ( $script_tags_added > 0 ) {
				echo "    - Added <script> tags to {$script_tags_added} code blocks\n";
			}
		}
	} else {
		echo "  SKIPPED: No changes needed\n";
	}
	
	echo "\n";
}

wp_reset_postdata();

echo "\n=== Summary ===\n";
echo "Total posts checked: {$query->post_count}\n";
echo "Posts fixed: {$fixed_count}\n";
echo "Errors: {$error_count}\n";

// Also run a test to ensure the cleaning is working
echo "\n=== Testing Deep Clean Function ===\n";
$test_content = '[fusion_code]&lt;p&gt;var options = {&lt;/p&gt;
&lt;p&gt;  chart: {&lt;br /&gt;
    type: &apos;line&apos;&lt;br /&gt;
  },&lt;/p&gt;
&lt;p&gt;  series: [{&lt;br /&gt;
    data: [1, 2, 3]&lt;br /&gt;
  }]&lt;/p&gt;
&lt;p&gt;};&lt;/p&gt;
&lt;p&gt;var chart = new ApexCharts(document.querySelector(&quot;#chart&quot;), options);&lt;/p&gt;
&lt;p&gt;chart.render();&lt;/p&gt;[/fusion_code]';

echo "Test input (with HTML entities and tags):\n";
echo substr( $test_content, 0, 200 ) . "...\n\n";

$cleaned = $method->invoke( $plugin, $test_content );
echo "Cleaned output:\n";
echo $cleaned . "\n\n";

echo "Done!\n"; 