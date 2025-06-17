<?php
// Script to add the missing ajax_execute_fallback_generation method

$file = 'controllers/class-blog-controller.php';
$content = file_get_contents($file);

// Check if method already exists
if (strpos($content, 'ajax_execute_fallback_generation') !== false) {
    echo "Method ajax_execute_fallback_generation already exists!\n";
    exit;
}

// Find the last closing brace of the class
$lastBrace = strrpos($content, '}');
$secondLastBrace = strrpos($content, '}', $lastBrace - strlen($content) - 1);

// The method to insert
$method = '
	/**
	 * AJAX handler to execute fallback generation
	 * Used when WordPress cron is not working properly
	 */
	public function ajax_execute_fallback_generation() {
		// Verify nonce - using special fallback nonce
		$nonce = isset( $_POST[\'nonce\'] ) ? $_POST[\'nonce\'] : \'\';
		if ( ! wp_verify_nonce( $nonce, \'ai_blog_fallback_generation\' ) ) {
			// Also check regular admin nonce as backup
			if ( ! check_ajax_referer( \'ai_blog_admin_nonce\', \'nonce\', false ) ) {
				wp_send_json_error( [ \'message\' => __( \'Security check failed.\', \'ai-blog-generator\' ) ] );
			}
		}

		// Validate input
		$idea_id = isset( $_POST[\'idea_id\'] ) ? absint( $_POST[\'idea_id\'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ \'message\' => __( \'Invalid idea ID.\', \'ai-blog-generator\' ) ] );
		}

		// Log the fallback execution
		$debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . \'debug-transaction.log\';
		file_put_contents( $debug_log, date( \'Y-m-d H:i:s\' ) . " - FALLBACK_AJAX: Executing fallback generation for idea_id: $idea_id\n", FILE_APPEND );

		// Execute the generation
		$processor = new \AI_Blog_Generator\Services\Background_Processor();
		$processor->process_generation( $idea_id );

		wp_send_json_success( [
			\'message\' => __( \'Fallback generation executed.\', \'ai-blog-generator\' ),
			\'idea_id\' => $idea_id
		] );
	}';

// Insert the method before the last closing brace of the class
$newContent = substr($content, 0, $secondLastBrace) . $method . "\n" . substr($content, $secondLastBrace);

// Write back
file_put_contents($file, $newContent);

echo "Successfully added ajax_execute_fallback_generation method!\n"; 