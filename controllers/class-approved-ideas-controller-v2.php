<?php
/**
 * Approved Ideas Controller V2
 *
 * Handles AJAX requests and logic for the Approved Ideas V2 page.
 * Integrates with Blog Generator Controller V2 for generation workflow.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
use AI_Blog_Generator\Controllers\Blog_Generator_Controller_V2;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Services\Generation_Queue;
use AI_Blog_Generator\Utilities\Generation_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approved Ideas Controller V2 Class
 *
 * Manages AJAX operations for approved ideas workflow.
 */
class Approved_Ideas_Controller_V2 {

	/**
	 * Blog Ideas model instance.
	 *
	 * @var Blog_Ideas_Model_V2
	 */
	private $ideas_model;

	/**
	 * Blog Generator controller instance.
	 *
	 * @var Blog_Generator_Controller_V2
	 */
	private $generator_controller;

	/**
	 * Generation Queue service instance.
	 *
	 * @var Generation_Queue
	 */
	private $generation_queue;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Temporarily minimal constructor to isolate issue
		Logger::info( 'approved_ideas_controller_init_start', 'Starting Approved Ideas Controller V2 initialization' );
		
		try {
			// Initialize models and services
			$this->ideas_model = new Blog_Ideas_Model_V2();
			Logger::info( 'approved_ideas_model_created', 'Blog Ideas Model V2 created successfully' );
			
			// Initialize generation queue
			$this->generation_queue = new Generation_Queue();
			Logger::info( 'generation_queue_created', 'Generation Queue service created successfully' );

			Logger::info( 'approved_ideas_controller_init', 'Approved Ideas Controller V2 initialized' );
		} catch ( \Exception $e ) {
			Logger::error( 'approved_ideas_controller_init_error', 'Failed to initialize Approved Ideas Controller V2', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
		}
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Get approved ideas
		add_action( 'wp_ajax_ai_blog_v2_get_approved_ideas', [ $this, 'ajax_get_approved_ideas' ] );
		
		// Submit idea for generation
		add_action( 'wp_ajax_ai_blog_v2_submit_for_generation', [ $this, 'ajax_submit_for_generation' ] );
		
		// Deny idea
		add_action( 'wp_ajax_ai_blog_v2_deny_idea', [ $this, 'ajax_deny_idea' ] );
		
		// Get generation progress
		add_action( 'wp_ajax_ai_blog_v2_get_generation_progress', [ $this, 'ajax_get_generation_progress' ] );
		
		// Get bulk idea status updates (for targeted refresh)
		add_action( 'wp_ajax_ai_blog_v2_get_idea_status_updates', [ $this, 'ajax_get_idea_status_updates' ] );
		
		// Edit idea details
		add_action( 'wp_ajax_ai_blog_v2_edit_idea', [ $this, 'ajax_edit_idea' ] );
		
		// Get generation log for live tailing
		add_action( 'wp_ajax_ai_blog_v2_get_generation_log', [ $this, 'ajax_get_generation_log' ] );
		
		// Cancel generation
		add_action( 'wp_ajax_ai_blog_v2_cancel_generation', [ $this, 'ajax_cancel_generation' ] );
		
		// Get queue status
		add_action( 'wp_ajax_ai_blog_v2_get_queue_status', [ $this, 'ajax_get_queue_status' ] );
		
		// Get statistics
		add_action( 'wp_ajax_ai_blog_v2_get_approved_statistics', [ $this, 'ajax_get_approved_statistics' ] );

		Logger::debug( 'ajax_hooks_registered', 'All approved ideas AJAX hooks registered' );
	}

	/**
	 * AJAX handler to get approved ideas.
	 */
	public function ajax_get_approved_ideas() {
		Logger::info( 'ajax_get_ideas_start', 'Getting approved ideas via AJAX' );
		
		// Run cleanup before fetching ideas
		$cleanup_result = $this->cleanup_stuck_generations();
		if ( $cleanup_result['cleaned'] > 0 ) {
			Logger::info( 'ajax_cleanup_performed', 'Automatic cleanup performed before fetching ideas', [
				'cleaned_count' => $cleanup_result['cleaned']
			] );
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for get approved ideas' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for get approved ideas' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			// Get approved and generating ideas
			$approved_ideas = $this->ideas_model->get_by_status( 'approved' );
			$generating_ideas = $this->ideas_model->get_by_status( 'generating' );
			$generated_ideas = $this->ideas_model->get_by_status( 'generated' );
			
			// Debug logging to see what we're loading
			Logger::info( 'ajax_ideas_loaded', 'Ideas loaded by status', [
				'approved_count' => count( $approved_ideas ),
				'generating_count' => count( $generating_ideas ),
				'generated_count' => count( $generated_ideas ),
				'approved_ids' => array_column( $approved_ideas, 'id' ),
				'generating_ids' => array_column( $generating_ideas, 'id' ),
				'generated_ids' => array_column( $generated_ideas, 'id' )
			] );
			
			// Combine all relevant ideas
			$ideas = array_merge( $approved_ideas, $generating_ideas, $generated_ideas );
			
			// Sort by updated date (most recent first)
			usort( $ideas, function( $a, $b ) {
				return strtotime( $b['updated_at'] ) - strtotime( $a['updated_at'] );
			} );

			// Get statistics
			$statistics = $this->get_statistics();

			Logger::info( 'ajax_get_approved_ideas_success', 'Approved ideas retrieved successfully', [
				'total_ideas' => count( $ideas ),
				'approved_count' => count( $approved_ideas ),
				'generating_count' => count( $generating_ideas ),
				'generated_count' => count( $generated_ideas )
			] );

			$response_data = [
				'ideas' => $ideas,
				'statistics' => $statistics
			];
			
			// Include cleanup information if any cleanup was performed
			if ( isset( $cleanup_result ) && $cleanup_result['cleaned'] > 0 ) {
				$response_data['cleanup_performed'] = $cleanup_result;
			}
			
			wp_send_json_success( $response_data );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_approved_ideas_error', 'Error retrieving approved ideas', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			wp_send_json_error( 'Failed to retrieve approved ideas: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to submit idea for generation.
	 */
	public function ajax_submit_for_generation() {
		$idea_ids = $_POST['idea_ids'] ?? [];
		
		// Handle both single idea and bulk submissions
		if ( ! is_array( $idea_ids ) ) {
			$idea_ids = [ intval( $_POST['idea_id'] ?? 0 ) ];
		}
		$idea_ids = array_map( 'intval', $idea_ids );
		$idea_ids = array_filter( $idea_ids, function( $id ) { return $id > 0; } );

		Logger::info( 'ajax_submit_generation_start', 'Submitting ideas for generation via AJAX', [
			'idea_ids' => $idea_ids,
			'count' => count( $idea_ids )
		] );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for submit generation' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for submit generation' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Validate idea IDs
		if ( empty( $idea_ids ) ) {
			Logger::error( 'ajax_invalid_idea_ids', 'No valid idea IDs for generation submission' );
			wp_send_json_error( 'No valid ideas selected' );
		}

		try {
			// Add ideas to generation queue
			$queue_result = $this->generation_queue->add_to_queue( $idea_ids );
			
			Logger::info( 'ajax_queue_result', 'Ideas added to generation queue', $queue_result );
			
			// Get updated idea data for immediate UI update
			$updated_ideas = [];
			foreach ( array_merge( $queue_result['started'], $queue_result['queued'] ) as $idea_id ) {
				$idea = $this->ideas_model->get_idea( $idea_id );
				if ( $idea ) {
					$updated_ideas[] = $idea;
				}
			}
			
			// Get queue status
			$queue_status = $this->generation_queue->get_queue_status();
			
			// Prepare response message
			$message_parts = [];
			if ( count( $queue_result['started'] ) > 0 ) {
				$message_parts[] = count( $queue_result['started'] ) . ' idea(s) started generating';
			}
			if ( count( $queue_result['queued'] ) > 0 ) {
				$message_parts[] = count( $queue_result['queued'] ) . ' idea(s) added to queue';
			}
			if ( count( $queue_result['errors'] ) > 0 ) {
				$message_parts[] = count( $queue_result['errors'] ) . ' idea(s) failed';
			}
			
			$message = implode( ', ', $message_parts ) ?: 'Processing generation request';
			
			if ( $queue_result['success'] || count( $queue_result['started'] ) > 0 || count( $queue_result['queued'] ) > 0 ) {
				wp_send_json_success( [
					'message' => $message,
					'queue_result' => $queue_result,
					'ideas' => $updated_ideas,
					'queue_status' => $queue_status,
					'status_updated' => true
				] );
			} else {
				// All ideas failed
				$error_messages = array_map( function( $error ) {
					return "ID {$error['idea_id']}: {$error['error']}";
				}, $queue_result['errors'] );
				
				wp_send_json_error( 'Failed to queue ideas: ' . implode( '; ', $error_messages ) );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_submit_generation_error', 'Error submitting ideas for generation', [
				'idea_ids' => $idea_ids,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			wp_send_json_error( 'Failed to submit ideas for generation: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to deny an idea.
	 */
	public function ajax_deny_idea() {
		$idea_id = intval( $_POST['idea_id'] ?? 0 );

		Logger::info( 'ajax_deny_idea_start', 'Denying idea via AJAX', [
			'idea_id' => $idea_id
		] );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for deny idea' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for deny idea' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Validate idea ID
		if ( empty( $idea_id ) ) {
			Logger::error( 'ajax_invalid_idea_id', 'Invalid idea ID for denial', [
				'idea_id' => $idea_id
			] );
			wp_send_json_error( 'Invalid idea ID' );
		}

		try {
			// Check if idea exists
			$idea = $this->ideas_model->get_idea( $idea_id );
			if ( ! $idea ) {
				Logger::error( 'ajax_idea_not_found', 'Idea not found for denial', [
					'idea_id' => $idea_id
				] );
				wp_send_json_error( 'Idea not found' );
			}

			// Cannot deny ideas that are currently generating
			if ( $idea['status'] === 'generating' ) {
				Logger::error( 'ajax_cannot_deny_generating', 'Cannot deny idea that is currently generating', [
					'idea_id' => $idea_id
				] );
				wp_send_json_error( 'Cannot deny idea that is currently being generated' );
			}

			// Update idea status to denied
			$result = $this->ideas_model->update_status( $idea_id, 'denied' );

			if ( $result ) {
				Logger::info( 'ajax_idea_denied', 'Idea denied successfully', [
					'idea_id' => $idea_id
				] );

				wp_send_json_success( [
					'message' => 'Idea denied successfully',
					'idea_id' => $idea_id
				] );
			} else {
				Logger::error( 'ajax_deny_failed', 'Failed to deny idea', [
					'idea_id' => $idea_id
				] );

				wp_send_json_error( 'Failed to deny idea' );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_deny_idea_error', 'Error denying idea', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			wp_send_json_error( 'Failed to deny idea: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to get generation progress.
	 */
	public function ajax_get_generation_progress() {
		$idea_id = intval( $_POST['idea_id'] ?? 0 );

		Logger::debug( 'ajax_get_progress_start', 'Getting generation progress via AJAX', [
			'idea_id' => $idea_id
		] );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for get progress' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for get progress' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Validate idea ID
		if ( empty( $idea_id ) ) {
			Logger::error( 'ajax_invalid_idea_id', 'Invalid idea ID for progress check', [
				'idea_id' => $idea_id
			] );
			wp_send_json_error( 'Invalid idea ID' );
		}

		try {
			// Temporarily return basic progress data
			$idea = $this->ideas_model->get_idea( $idea_id );
			
			if ( ! $idea ) {
				wp_send_json_error( 'Idea not found' );
				return;
			}

			$progress = [
				'status' => $idea['status'],
				'percentage' => $idea['status'] === 'generating' ? 50 : 0,
				'step' => $idea['generation_status'] ?? 'N/A'
			];

			Logger::debug( 'ajax_progress_retrieved', 'Generation progress retrieved', [
				'idea_id' => $idea_id,
				'status' => $progress['status'],
				'percentage' => $progress['percentage']
			] );

			wp_send_json_success( $progress );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_progress_error', 'Error getting generation progress', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );

			wp_send_json_error( 'Failed to get generation progress: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to get status updates for specific ideas.
	 * This is used for targeted refresh to avoid reloading the entire table.
	 */
	public function ajax_get_idea_status_updates() {
		Logger::debug( 'ajax_get_status_updates_start', 'Getting idea status updates via AJAX' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for get status updates' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for get status updates' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get idea IDs from request (can be single ID or array of IDs)
		$idea_ids = $_POST['idea_ids'] ?? [];
		if ( ! is_array( $idea_ids ) ) {
			$idea_ids = [ intval( $idea_ids ) ];
		}
		$idea_ids = array_map( 'intval', $idea_ids );
		$idea_ids = array_filter( $idea_ids, function( $id ) { return $id > 0; } );

		if ( empty( $idea_ids ) ) {
			Logger::error( 'ajax_no_idea_ids', 'No valid idea IDs provided for status updates' );
			wp_send_json_error( 'No valid idea IDs provided' );
		}

		Logger::debug( 'ajax_status_updates_requested', 'Status updates requested for specific ideas', [
			'idea_ids' => $idea_ids,
			'count' => count( $idea_ids )
		] );

		try {
			$updated_ideas = [];
			$updated_statistics = false;

			foreach ( $idea_ids as $idea_id ) {
				// Get fresh idea data
				$idea = $this->ideas_model->get_idea( $idea_id );
				if ( $idea ) {
					$updated_ideas[] = $idea;
				}
			}

			// Only update statistics if we have generating ideas (since that's when they change)
			$has_generating = false;
			foreach ( $updated_ideas as $idea ) {
				if ( $idea['status'] === 'generating' ) {
					$has_generating = true;
					break;
				}
			}

			if ( $has_generating ) {
				$statistics = $this->get_statistics();
				$updated_statistics = true;
			}

			Logger::debug( 'ajax_status_updates_retrieved', 'Status updates retrieved successfully', [
				'idea_count' => count( $updated_ideas ),
				'statistics_updated' => $updated_statistics
			] );

			$response_data = [
				'ideas' => $updated_ideas
			];

			if ( $updated_statistics ) {
				$response_data['statistics'] = $statistics;
			}

			wp_send_json_success( $response_data );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_status_updates_error', 'Error getting idea status updates', [
				'idea_ids' => $idea_ids,
				'error' => $e->getMessage()
			] );

			wp_send_json_error( 'Failed to get status updates: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to edit idea details.
	 */
	public function ajax_edit_idea() {
		$idea_id = intval( $_POST['idea_id'] ?? 0 );
		$title = sanitize_text_field( $_POST['title'] ?? '' );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );
		$persona_id = intval( $_POST['persona_id'] ?? 0 );

		Logger::info( 'ajax_edit_idea_start', 'Editing idea via AJAX', [
			'idea_id' => $idea_id,
			'title' => $title
		] );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for edit idea' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for edit idea' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Validate inputs
		if ( empty( $idea_id ) || empty( $title ) || empty( $description ) ) {
			Logger::error( 'ajax_invalid_input', 'Invalid input for edit idea', [
				'idea_id' => $idea_id,
				'title_empty' => empty( $title ),
				'description_empty' => empty( $description )
			] );
			wp_send_json_error( 'All fields are required' );
		}

		try {
			// Check if idea exists and is editable
			$idea = $this->ideas_model->get_idea( $idea_id );
			if ( ! $idea ) {
				Logger::error( 'ajax_idea_not_found', 'Idea not found for editing', [
					'idea_id' => $idea_id
				] );
				wp_send_json_error( 'Idea not found' );
			}

			if ( $idea['status'] === 'generating' ) {
				Logger::error( 'ajax_cannot_edit_generating', 'Cannot edit idea that is currently generating', [
					'idea_id' => $idea_id
				] );
				wp_send_json_error( 'Cannot edit idea that is currently being generated' );
			}

			// Update the idea
			$updated_data = [
				'title' => $title,
				'description' => $description,
				'persona_id' => $persona_id > 0 ? $persona_id : null,
				'updated_at' => current_time( 'mysql' )
			];

			$result = $this->ideas_model->update_idea( $idea_id, $updated_data );

			if ( $result ) {
				Logger::info( 'ajax_idea_edited', 'Idea edited successfully', [
					'idea_id' => $idea_id,
					'updated_data' => $updated_data
				] );

				wp_send_json_success( [
					'message' => 'Idea updated successfully',
					'idea_id' => $idea_id
				] );
			} else {
				Logger::error( 'ajax_edit_failed', 'Failed to edit idea', [
					'idea_id' => $idea_id
				] );

				wp_send_json_error( 'Failed to update idea' );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_edit_idea_error', 'Error editing idea', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			wp_send_json_error( 'Failed to edit idea: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to get generation log.
	 */
	public function ajax_get_generation_log() {
		$idea_id = intval( $_POST['idea_id'] ?? 0 );
		$last_line = intval( $_POST['last_line'] ?? 0 );

		Logger::debug( 'ajax_get_log_start', 'Getting generation log via AJAX', [
			'idea_id' => $idea_id,
			'last_line' => $last_line
		] );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for get log' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for get log' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		if ( empty( $idea_id ) ) {
			Logger::error( 'ajax_invalid_idea_id', 'Invalid idea ID for log retrieval', [
				'idea_id' => $idea_id
			] );
			wp_send_json_error( 'Invalid idea ID' );
		}

		try {
			// Get the log file path
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/ai-blog-generator-logs';
			$log_file = $log_dir . '/generation-' . $idea_id . '.log';
			
			// Check if log file exists
			if ( ! file_exists( $log_file ) ) {
				// Try debug-transaction.log as fallback
				$debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
				if ( file_exists( $debug_log ) ) {
					$log_file = $debug_log;
				} else {
					wp_send_json_success( [
						'lines' => [],
						'last_line' => 0,
						'complete' => false,
						'message' => 'Log file not found yet. Generation may not have started.'
					] );
					return;
				}
			}
			
			// Read log file
			$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			$total_lines = count( $lines );
			
			// Get new lines since last read
			$new_lines = [];
			if ( $last_line < $total_lines ) {
				$new_lines = array_slice( $lines, $last_line );
			}
			
			// Format lines with colors based on content
			$formatted_lines = [];
			foreach ( $new_lines as $line ) {
				$type = 'info';
				
				if ( stripos( $line, 'error' ) !== false || stripos( $line, 'fatal' ) !== false ) {
					$type = 'error';
				} elseif ( stripos( $line, 'warning' ) !== false || stripos( $line, 'warn' ) !== false ) {
					$type = 'warning';
				} elseif ( stripos( $line, 'success' ) !== false || stripos( $line, 'complete' ) !== false ) {
					$type = 'success';
				} elseif ( stripos( $line, 'debug' ) !== false ) {
					$type = 'debug';
				}
				
				$formatted_lines[] = [
					'text' => $line,
					'type' => $type
				];
			}
			
			// Check if generation is complete
			$idea = $this->ideas_model->get_idea( $idea_id );
			$is_complete = $idea && ! in_array( $idea['status'], [ 'generating', 'queued' ], true );
			
			wp_send_json_success( [
				'lines' => $formatted_lines,
				'last_line' => $total_lines,
				'complete' => $is_complete,
				'status' => $idea['status'] ?? 'unknown',
				'generation_status' => $idea['generation_status'] ?? null
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_log_error', 'Error getting generation log', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );

			wp_send_json_error( 'Failed to get generation log: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to cancel generation.
	 */
	public function ajax_cancel_generation() {
		$idea_id = intval( $_POST['idea_id'] ?? 0 );

		Logger::info( 'ajax_cancel_generation_start', 'Canceling generation via AJAX', [
			'idea_id' => $idea_id
		] );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for cancel generation' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for cancel generation' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		if ( empty( $idea_id ) ) {
			Logger::error( 'ajax_invalid_idea_id', 'Invalid idea ID for cancel generation', [
				'idea_id' => $idea_id
			] );
			wp_send_json_error( 'Invalid idea ID' );
		}

		try {
			// Cancel generation through queue service
			$result = $this->generation_queue->cancel_generation( $idea_id );

			if ( $result ) {
				Logger::info( 'ajax_generation_canceled', 'Generation canceled successfully', [
					'idea_id' => $idea_id
				] );

				// Get updated idea data
				$updated_idea = $this->ideas_model->get_idea( $idea_id );

				wp_send_json_success( [
					'message' => 'Generation canceled successfully',
					'idea_id' => $idea_id,
					'updated_idea' => $updated_idea
				] );
			} else {
				Logger::error( 'ajax_cancel_failed', 'Failed to cancel generation', [
					'idea_id' => $idea_id
				] );

				wp_send_json_error( 'Failed to cancel generation' );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_cancel_generation_error', 'Error canceling generation', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			wp_send_json_error( 'Failed to cancel generation: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to get queue status.
	 */
	public function ajax_get_queue_status() {
		Logger::debug( 'ajax_get_queue_status_start', 'Getting queue status via AJAX' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for get queue status' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for get queue status' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			// Get queue status from service
			$queue_status = $this->generation_queue->get_queue_status();
			
			Logger::debug( 'ajax_queue_status_retrieved', 'Queue status retrieved', $queue_status );
			
			wp_send_json_success( $queue_status );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_queue_status_error', 'Error getting queue status', [
				'error' => $e->getMessage()
			] );

			wp_send_json_error( 'Failed to get queue status: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to get statistics.
	 */
	public function ajax_get_approved_statistics() {
		Logger::debug( 'ajax_get_statistics_start', 'Getting approved ideas statistics via AJAX' );

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_blog_admin_nonce' ) ) {
			Logger::error( 'ajax_nonce_failed', 'Nonce verification failed for get statistics' );
			wp_send_json_error( 'Security verification failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::error( 'ajax_permission_denied', 'User lacks permission for get statistics' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			$statistics = $this->get_statistics();

			Logger::debug( 'ajax_statistics_retrieved', 'Statistics retrieved successfully', $statistics );

			wp_send_json_success( $statistics );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_statistics_error', 'Error getting statistics', [
				'error' => $e->getMessage()
			] );

			wp_send_json_error( 'Failed to get statistics: ' . $e->getMessage() );
		}
	}

	/**
	 * Get statistics for approved ideas page.
	 *
	 * @return array Statistics data.
	 */
	private function get_statistics() {
		Logger::debug( 'get_statistics_start', 'Calculating approved ideas statistics' );

		try {
			$statistics = [
				'approved' => $this->ideas_model->count_by_status( 'approved' ),
				'generating' => $this->ideas_model->count_by_status( 'generating' ),
				'generated' => $this->ideas_model->count_by_status( 'generated' ),
				'denied' => $this->ideas_model->count_by_status( 'denied' )
			];

			Logger::debug( 'statistics_calculated', 'Statistics calculated successfully', $statistics );

			return $statistics;

		} catch ( \Exception $e ) {
			Logger::error( 'get_statistics_error', 'Error calculating statistics', [
				'error' => $e->getMessage()
			] );

			// Return default statistics on error
			return [
				'approved' => 0,
				'generating' => 0,
				'generated' => 0,
				'denied' => 0
			];
		}
	}

	/**
	 * Render the approved ideas page.
	 */
	public function render_page() {
		Logger::info( 'render_approved_ideas_page', 'Rendering Approved Ideas V2 page' );
		
		// DISABLED: Automatic cleanup disabled to prevent automatic generation resets
		// Users must manually cancel stuck generations if needed
		// Old cleanup code removed to prevent automatic retry behavior
		
		// Enqueue scripts and styles
		$this->enqueue_assets();

		// Include the view file
		$view_file = AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/approved-ideas-view-v2.php';
		
		if ( file_exists( $view_file ) ) {
			include $view_file;
			Logger::debug( 'view_file_included', 'Approved ideas view file included successfully' );
		} else {
			Logger::error( 'view_file_missing', 'Approved ideas view file not found', [
				'file_path' => $view_file
			] );
			echo '<div class="notice notice-error"><p>View file not found.</p></div>';
		}
	}

	/**
	 * Enqueue necessary assets for the page.
	 */
	private function enqueue_assets() {
		Logger::debug( 'enqueue_assets_start', 'Enqueuing approved ideas assets' );

		// Enqueue Bootstrap (if not already loaded)
		wp_enqueue_style( 
			'bootstrap', 
			'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
			[],
			'5.1.3'
		);

		wp_enqueue_script( 
			'bootstrap', 
			'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
			[ 'jquery' ],
			'5.1.3',
			true
		);

		// Enqueue Font Awesome
		wp_enqueue_style( 
			'font-awesome', 
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
			[],
			'6.0.0'
		);

		// Enqueue custom JavaScript
		wp_enqueue_script(
			'approved-ideas-v2',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/approved-ideas-v2.js',
			[ 'jquery', 'bootstrap' ],
			AI_BLOG_GENERATOR_VERSION,
			true
		);

		// Localize script data
		$script_data = $this->get_localized_script_data();
		wp_localize_script( 'approved-ideas-v2', 'ai_blog_admin', $script_data );

		Logger::debug( 'assets_enqueued', 'All approved ideas assets enqueued successfully' );
	}

	/**
	 * Get localized script data.
	 *
	 * @return array Localized data for JavaScript.
	 */
	private function get_localized_script_data() {
		$personas = [];
		$categories = [];

		// Get personas for display
		try {
			$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
			$all_personas = $persona_model->get_all();
			
			foreach ( $all_personas as $persona ) {
				// Handle both object and array formats for compatibility
				if ( is_object( $persona ) ) {
					$personas[ $persona->id ] = $persona->name;
				} else if ( is_array( $persona ) ) {
					$personas[ $persona['id'] ] = $persona['name'];
				}
			}
		} catch ( \Exception $e ) {
			Logger::warning( 'personas_load_failed', 'Failed to load personas for script data', [
				'error' => $e->getMessage()
			] );
		}

		// Get categories for display
		$wp_categories = get_categories( [ 'hide_empty' => false ] );
		foreach ( $wp_categories as $category ) {
			$categories[ $category->term_id ] = $category->name;
		}

		return [
			'nonce' => wp_create_nonce( 'ai_blog_admin_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'adminUrl' => admin_url(),
			'personas' => $personas,
			'categories' => $categories,
			'strings' => [
				'confirm_generation' => __( 'Are you sure you want to generate a post for this idea?', 'ai-blog-generator' ),
				'confirm_deny' => __( 'Are you sure you want to deny this idea?', 'ai-blog-generator' ),
				'generating' => __( 'Generating...', 'ai-blog-generator' ),
				'loading' => __( 'Loading...', 'ai-blog-generator' ),
				'success' => __( 'Success!', 'ai-blog-generator' ),
				'error' => __( 'Error!', 'ai-blog-generator' )
			],
			'settings' => [
				'auto_refresh_interval' => 5000,
				'max_concurrent_generations' => 3
			]
		];
	}

	/**
	 * Get the menu page details for WordPress admin.
	 *
	 * @return array Menu page configuration.
	 */
	public static function get_menu_config() {
		return [
			'page_title' => __( 'Approved Ideas V2', 'ai-blog-generator' ),
			'menu_title' => __( 'Approved Ideas V2', 'ai-blog-generator' ),
			'capability' => 'manage_options',
			'menu_slug' => 'ai-blog-generator-approved-ideas-v2',
			'icon' => 'dashicons-yes-alt',
			'position' => 25
		];
	}

	/**
	 * Clean up stuck generations automatically.
	 * This runs when the page loads to reset ideas stuck in generating status.
	 * 
	 * @return array Cleanup results
	 */
	private function cleanup_stuck_generations() {
		Logger::info( 'cleanup_stuck_generations_start', 'Starting automatic cleanup of stuck generations' );
		
		try {
			// Get all ideas with 'generating' status
			$generating_ideas = $this->ideas_model->get_by_status( 'generating' );
			
			if ( empty( $generating_ideas ) ) {
				Logger::debug( 'cleanup_no_stuck_ideas', 'No generating ideas found during cleanup' );
				return [
					'cleaned' => 0,
					'message' => 'No stuck generations found'
				];
			}
			
			$cleaned_count = 0;
			$cutoff_time = time() - ( 10 * 60 ); // 10 minutes ago
			
			foreach ( $generating_ideas as $idea ) {
				// Check if the idea has been stuck for more than 10 minutes
				$updated_timestamp = strtotime( $idea['updated_at'] );
				
				if ( $updated_timestamp < $cutoff_time ) {
					// Also check if there's an active generation lock
					$has_active_lock = get_transient( "ai_blog_generation_lock_{$idea['id']}" );
					
					// If no active lock or lock is old, reset the idea
					if ( ! $has_active_lock ) {
						Logger::info( 'cleanup_resetting_stuck_idea', 'Resetting stuck idea', [
							'idea_id' => $idea['id'],
							'title' => $idea['title'],
							'stuck_duration_minutes' => round( ( time() - $updated_timestamp ) / 60, 1 ),
							'generation_status' => $idea['generation_status'] ?? 'unknown'
						] );
						
						// Reset status to approved
						$result = $this->ideas_model->update_status( $idea['id'], 'approved', [
							'generation_status' => null
						] );
						
						if ( $result ) {
							// Clean up any related transients
							delete_transient( "ai_blog_generation_lock_{$idea['id']}" );
							delete_transient( "ai_blog_generation_status_{$idea['id']}" );
							delete_transient( "ai_blog_generation_timeout_{$idea['id']}" );
							delete_transient( "ai_blog_gen_status_{$idea['id']}" ); // Old transient format
							
							$cleaned_count++;
							
							Logger::info( 'cleanup_idea_reset_success', 'Successfully reset stuck idea', [
								'idea_id' => $idea['id']
							] );
						}
					} else {
						Logger::debug( 'cleanup_idea_has_active_lock', 'Idea has active generation lock, skipping', [
							'idea_id' => $idea['id'],
							'lock_value' => $has_active_lock
						] );
					}
				} else {
					Logger::debug( 'cleanup_idea_too_recent', 'Idea generation too recent, skipping', [
						'idea_id' => $idea['id'],
						'minutes_since_update' => round( ( time() - $updated_timestamp ) / 60, 1 )
					] );
				}
			}
			
			if ( $cleaned_count > 0 ) {
				Logger::info( 'cleanup_completed', 'Cleanup completed, reset stuck ideas', [
					'total_generating' => count( $generating_ideas ),
					'cleaned_count' => $cleaned_count
				] );
			}
			
			return [
				'cleaned' => $cleaned_count,
				'message' => $cleaned_count > 0 
					? "Reset {$cleaned_count} stuck generation" . ( $cleaned_count > 1 ? 's' : '' )
					: 'No stuck generations found'
			];
			
		} catch ( \Exception $e ) {
			Logger::error( 'cleanup_stuck_generations_error', 'Error during automatic cleanup', [
				'error' => $e->getMessage()
			] );
			
			return [
				'cleaned' => 0,
				'message' => 'Cleanup error: ' . $e->getMessage()
			];
		}
	}
} 