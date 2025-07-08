<?php
/**
 * Blog Controller Class
 *
 * Handles all blog-related HTTP requests and AJAX operations.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Models\Log_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Ajax_Handler;
use AI_Blog_Generator\Services\Post_Publisher;
use AI_Blog_Generator\Services\Scheduler_Service;
use AI_Blog_Generator\Services\Background_Processor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blog Controller Class
 */
class Blog_Controller {

	use Ajax_Handler;

	/**
	 * Blog model instance.
	 *
	 * @var Blog_Model
	 */
	private $blog_model;

	/**
	 * Idea model instance.
	 *
	 * @var Idea_Model
	 */
	private $idea_model;

	/**
	 * Cost model instance.
	 *
	 * @var Cost_Model
	 */
	private $cost_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->blog_model = new Blog_Model();
		$this->idea_model = new Idea_Model();
		$this->cost_model = new Cost_Model();
	}

	/**
	 * Register AJAX handlers for this controller.
	 */
	public function register_ajax_handlers() {
		// Idea management
		add_action( 'wp_ajax_ai_blog_approve_idea', [ $this, 'ajax_approve_idea' ] );
		add_action( 'wp_ajax_ai_blog_deny_idea', [ $this, 'ajax_deny_idea' ] );
		add_action( 'wp_ajax_ai_blog_bulk_approve_ideas', [ $this, 'ajax_bulk_approve_ideas' ] );
		add_action( 'wp_ajax_ai_blog_bulk_deny_ideas', [ $this, 'ajax_bulk_deny_ideas' ] );

		// Blog generation and management
		add_action( 'wp_ajax_ai_blog_generate_blog', [ $this, 'ajax_generate_blog' ] );
		add_action( 'wp_ajax_ai_blog_publish_blog', [ $this, 'ajax_publish_blog' ] );
		add_action( 'wp_ajax_ai_blog_schedule_blog', [ $this, 'ajax_schedule_blog' ] );
		add_action( 'wp_ajax_ai_blog_update_blog_status', [ $this, 'ajax_update_blog_status' ] );
		add_action( 'wp_ajax_ai_blog_get_blog_statistics', [ $this, 'ajax_get_blog_statistics' ] );

		// Approved blogs management
		add_action( 'wp_ajax_ai_blog_get_approved_ideas', [ $this, 'ajax_get_approved_ideas' ] );
		add_action( 'wp_ajax_ai_blog_get_idea_for_edit', [ $this, 'ajax_get_idea_for_edit' ] );
		add_action( 'wp_ajax_ai_blog_update_approved_idea', [ $this, 'ajax_update_approved_idea' ] );
		add_action( 'wp_ajax_ai_blog_generate_from_approved_idea', [ $this, 'ajax_generate_from_approved_idea' ] );

		// Background generation
		add_action( 'wp_ajax_ai_blog_start_background_generation', [ $this, 'ajax_start_background_generation' ] );
		add_action( 'wp_ajax_ai_blog_get_generation_status', [ $this, 'ajax_get_generation_status' ] );
		add_action( 'wp_ajax_ai_blog_cancel_generation', [ $this, 'ajax_cancel_generation' ] );
		add_action( 'wp_ajax_ai_blog_bulk_start_background_generation', [ $this, 'ajax_bulk_start_background_generation' ] );

		// Queue management
		add_action( 'wp_ajax_ai_blog_move_idea_to_top', [ $this, 'ajax_move_idea_to_top' ] );
		add_action( 'wp_ajax_ai_blog_clear_queue', [ $this, 'ajax_clear_queue' ] );
		add_action( 'wp_ajax_ai_blog_process_queue_now', [ $this, 'ajax_process_queue_now' ] );
		add_action( 'wp_ajax_ai_blog_bulk_schedule_posts', [ $this, 'ajax_bulk_schedule_posts' ] );
		add_action( 'wp_ajax_ai_blog_write_debug', [ $this, 'ajax_write_debug' ] );
		add_action( 'wp_ajax_ai_blog_execute_fallback_generation', [ $this, 'ajax_execute_fallback_generation' ] );
		
		// Drafted posts handlers
		add_action( 'wp_ajax_ai_blog_get_drafted_posts', [ $this, 'ajax_get_drafted_posts' ] );
		add_action( 'wp_ajax_ai_blog_publish_post', [ $this, 'ajax_publish_post' ] );
		add_action( 'wp_ajax_ai_blog_schedule_post', [ $this, 'ajax_schedule_post' ] );
		add_action( 'wp_ajax_ai_blog_unschedule_post', [ $this, 'ajax_unschedule_post' ] );
		add_action( 'wp_ajax_ai_blog_bulk_publish_posts', [ $this, 'ajax_bulk_publish_posts' ] );
		add_action( 'wp_ajax_ai_blog_delete_posts', [ $this, 'ajax_delete_posts' ] );
		add_action( 'wp_ajax_ai_blog_download_prompts', [ $this, 'ajax_download_prompts' ] );
	}

	/**
	 * Approve an idea.
	 */
	public function approve_idea() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return; // verify_ajax_security already sends the error response
			}

			// Validate required parameters
			$params = $this->validate_ajax_params( [ 'idea_id' ] );
			if ( false === $params ) {
				return; // validate_ajax_params already sends the error response
			}

			$idea_id = absint( $params['idea_id'] );
			if ( ! $idea_id ) {
				$this->send_ajax_error( 
					__( 'Invalid idea ID.', 'ai-blog-generator' ),
					[ 'provided_id' => $params['idea_id'] ],
					'approve_idea',
					'invalid_id'
				);
				return;
			}

			$this->log_info( 'approve_idea_start', 'Starting idea approval process', [
				'idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );

			// Get the idea
			$idea = $this->idea_model->get( $idea_id );
			if ( ! $idea ) {
				$this->send_ajax_error( 
					__( 'Idea not found.', 'ai-blog-generator' ),
					[ 'idea_id' => $idea_id ],
					'approve_idea',
					'idea_not_found'
				);
				return;
			}

			$this->log_debug( 'idea_found', 'Idea retrieved for approval', [
				'idea_id' => $idea_id,
				'idea_data_type' => gettype( $idea ),
				'idea_is_array' => is_array( $idea ),
				'idea_keys' => is_array( $idea ) ? array_keys( $idea ) : 'not_array',
				'idea_title' => isset( $idea['title'] ) ? $idea['title'] : 'no_title',
				'current_status' => isset( $idea['status'] ) ? $idea['status'] : 'no_status'
			] );

			// Update idea status
			$updated = $this->idea_model->update( $idea_id, [
				'status' => 'approved',
				'updated_at' => current_time( 'mysql' ),
			] );

			if ( ! $updated ) {
				$this->log_error( 'approve_idea_update_failed', 'Failed to update idea status in database', [
					'idea_id' => $idea_id,
					'database_error' => $this->idea_model->get_last_error()
				] );
				
				$this->send_ajax_error( 
					__( 'Failed to approve idea.', 'ai-blog-generator' ),
					[ 'idea_id' => $idea_id ],
					'approve_idea',
					'database_update_failed'
				);
				return;
			}

			$this->log_info( 'idea_approved_success', 'Idea approved successfully', [
				'idea_id' => $idea_id,
				'idea_title' => $idea['title'],
				'approved_by' => get_current_user_id(),
				'previous_status' => $idea['status']
			] );

			// Get updated statistics
			$stats = $this->idea_model->get_statistics();

			$this->log_debug( 'statistics_retrieved', 'Updated statistics retrieved', [
				'statistics' => $stats
			] );

			$this->send_ajax_success( [
				'statistics' => $stats,
				'idea_id' => $idea_id,
				'new_status' => 'approved'
			], __( 'Idea approved successfully.', 'ai-blog-generator' ), 'approve_idea' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'approve_idea', [
				'idea_id' => $idea_id ?? 'unknown'
			] );
		}

		$this->end_timer( $start_time, 'approve_idea' );
	}

	/**
	 * Deny an idea.
	 */
	public function deny_idea() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return; // verify_ajax_security already sends the error response
			}

			// Validate required parameters
			$params = $this->validate_ajax_params( [ 'idea_id' ] );
			if ( false === $params ) {
				return; // validate_ajax_params already sends the error response
			}

			$idea_id = absint( $params['idea_id'] );
			if ( ! $idea_id ) {
				$this->send_ajax_error( 
					__( 'Invalid idea ID.', 'ai-blog-generator' ),
					[ 'provided_id' => $params['idea_id'] ],
					'deny_idea',
					'invalid_id'
				);
				return;
			}

			$this->log_info( 'deny_idea_start', 'Starting idea denial process', [
				'idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );

			// Get the idea
			$idea = $this->idea_model->get( $idea_id );
			if ( ! $idea ) {
				$this->send_ajax_error( 
					__( 'Idea not found.', 'ai-blog-generator' ),
					[ 'idea_id' => $idea_id ],
					'deny_idea',
					'idea_not_found'
				);
				return;
			}

			$this->log_debug( 'idea_found_for_denial', 'Idea retrieved for denial', [
				'idea_id' => $idea_id,
				'idea_data_type' => gettype( $idea ),
				'idea_is_array' => is_array( $idea ),
				'idea_keys' => is_array( $idea ) ? array_keys( $idea ) : 'not_array',
				'idea_title' => isset( $idea['title'] ) ? $idea['title'] : 'no_title',
				'current_status' => isset( $idea['status'] ) ? $idea['status'] : 'no_status'
			] );

			// Update idea status
			$updated = $this->idea_model->update( $idea_id, [
				'status' => 'denied',
				'updated_at' => current_time( 'mysql' ),
			] );

			if ( ! $updated ) {
				$this->log_error( 'deny_idea_update_failed', 'Failed to update idea status in database', [
					'idea_id' => $idea_id,
					'database_error' => $this->idea_model->get_last_error()
				] );
				
				$this->send_ajax_error( 
					__( 'Failed to deny idea.', 'ai-blog-generator' ),
					[ 'idea_id' => $idea_id ],
					'deny_idea',
					'database_update_failed'
				);
				return;
			}

			$this->log_info( 'idea_denied_success', 'Idea denied successfully', [
				'idea_id' => $idea_id,
				'idea_title' => isset( $idea['title'] ) ? $idea['title'] : 'no_title',
				'denied_by' => get_current_user_id(),
				'previous_status' => isset( $idea['status'] ) ? $idea['status'] : 'no_status'
			] );

			// Get updated statistics
			$stats = $this->idea_model->get_statistics();

			$this->log_debug( 'statistics_retrieved_for_denial', 'Updated statistics retrieved', [
				'statistics' => $stats
			] );

			$this->send_ajax_success( [
				'statistics' => $stats,
				'idea_id' => $idea_id,
				'new_status' => 'denied'
			], __( 'Idea denied successfully.', 'ai-blog-generator' ), 'deny_idea' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'deny_idea', [
				'idea_id' => $idea_id ?? 'unknown'
			] );
		}

		$this->end_timer( $start_time, 'deny_idea' );
	}

	/**
	 * AJAX handler: Generate a blog post from an idea.
	 */
	public function ajax_generate_blog() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		// Check budget
		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		if ( ! $budget_manager->can_generate() ) {
			wp_send_json_error( [ 
				'message' => __( 'Budget limit reached. Cannot generate new posts.', 'ai-blog-generator' ),
				'budget_exceeded' => true,
			] );
		}

		// Check daily limit
		$today_count = $this->blog_model->count_generated_today();
		$posts_per_day = get_option( 'ai_blog_generator_posts_per_day', 2 );
		
		if ( $today_count >= $posts_per_day ) {
			wp_send_json_error( [ 
				'message' => __( 'Daily generation limit reached.', 'ai-blog-generator' ),
				'daily_limit_reached' => true,
			] );
		}

		// Get the idea
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea || $idea['status'] !== 'approved' ) {
			wp_send_json_error( [ 'message' => __( 'Idea not found or not approved.', 'ai-blog-generator' ) ] );
		}

		// Log generation start
		Logger::info( 'Starting blog generation', [
			'idea_id' => $idea_id,
			'title' => $idea['title'],
			'action' => 'generate_blog',
		] );

		// Trigger generation (async process)
		$generator = new \AI_Blog_Generator\Services\Content_Generator();
		$result = $generator->generate_blog_post( $idea_id );

		if ( is_wp_error( $result ) ) {
			Logger::error( 'Blog generation failed', [
				'idea_id' => $idea_id,
				'error' => $result->get_error_message(),
				'action' => 'generate_blog',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Generation failed: %s', 'ai-blog-generator' ), 
					$result->get_error_message() 
				) 
			] );
		}

		// Handle array response from generate_blog_post
		if ( is_array( $result ) ) {
			if ( ! $result['success'] ) {
				Logger::error( 'Blog generation failed', [
					'idea_id' => $idea_id,
					'error' => $result['message'],
					'action' => 'generate_blog',
				] );
				wp_send_json_error( [ 
					'message' => sprintf( 
						__( 'Generation failed: %s', 'ai-blog-generator' ), 
						$result['message'] 
					) 
				] );
			}
			
			$blog_id = $result['blog_id'];
			$message = $result['message'] ?? __( 'Blog generation completed successfully.', 'ai-blog-generator' );
		} else {
			// Fallback for unexpected response format
			$blog_id = $result;
			$message = __( 'Blog generation completed successfully.', 'ai-blog-generator' );
		}

		wp_send_json_success( [
			'message' => $message,
			'blog_id' => $blog_id,
		] );
	}

	/**
	 * Publish a blog post immediately.
	 */
	public function publish_blog() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		
		if ( ! $blog_id || ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid blog or post ID.', 'ai-blog-generator' ) ] );
		}

		// Get the blog
		$blog = $this->blog_model->get( $blog_id );
		if ( ! $blog || $blog->post_id != $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Blog not found.', 'ai-blog-generator' ) ] );
		}

		// Publish the post
		$publisher = new Post_Publisher();
		$result = $publisher->publish_now( $post_id );

		if ( is_wp_error( $result ) ) {
			Logger::error( 'Failed to publish blog', [
				'blog_id' => $blog_id,
				'post_id' => $post_id,
				'error' => $result->get_error_message(),
				'action' => 'publish_blog',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Publishing failed: %s', 'ai-blog-generator' ), 
					$result->get_error_message() 
				) 
			] );
		}

		// Update blog status
		$this->blog_model->update( $blog_id, [
			'status' => 'published',
			'updated_at' => current_time( 'mysql' ),
		] );

		// Log the action
		Logger::info( 'Blog published', [
			'blog_id' => $blog_id,
			'post_id' => $post_id,
			'action' => 'publish_blog',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Blog published successfully.', 'ai-blog-generator' ),
			'post_url' => get_permalink( $post_id ),
		] );
	}

	/**
	 * Schedule a blog post.
	 */
	public function schedule_blog() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$scheduled_time = isset( $_POST['scheduled_time'] ) ? sanitize_text_field( $_POST['scheduled_time'] ) : '';
		
		if ( ! $blog_id || ! $post_id || empty( $scheduled_time ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid input data.', 'ai-blog-generator' ) ] );
		}

		// Validate time format and ensure future date
		$scheduled_timestamp = strtotime( $scheduled_time );
		if ( ! $scheduled_timestamp || $scheduled_timestamp <= current_time( 'timestamp' ) ) {
			wp_send_json_error( [ 'message' => __( 'Scheduled time must be in the future.', 'ai-blog-generator' ) ] );
		}

		// Get the blog
		$blog = $this->blog_model->get( $blog_id );
		if ( ! $blog || $blog->post_id != $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Blog not found.', 'ai-blog-generator' ) ] );
		}

		// Schedule the post
		$scheduled_timestamp = strtotime( $scheduled_time );
		$updated_post = wp_update_post( [
			'ID' => $post_id,
			'post_status' => 'future',
			'post_date' => date( 'Y-m-d H:i:s', $scheduled_timestamp ),
			'post_date_gmt' => get_gmt_from_date( date( 'Y-m-d H:i:s', $scheduled_timestamp ) )
		], true );

		if ( is_wp_error( $updated_post ) ) {
			Logger::error( 'Failed to schedule blog', [
				'blog_id' => $blog_id,
				'post_id' => $post_id,
				'scheduled_time' => $scheduled_time,
				'error' => $updated_post->get_error_message(),
				'action' => 'schedule_blog',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Scheduling failed: %s', 'ai-blog-generator' ), 
					$updated_post->get_error_message() 
				) 
			] );
		}

		// Update blog with scheduled time
		$this->blog_model->update( $blog_id, [
			'status' => 'scheduled',
			'scheduled_time' => date( 'Y-m-d H:i:s', $scheduled_timestamp ),
			'updated_at' => current_time( 'mysql' ),
		] );

		// Log the action
		Logger::info( 'Blog scheduled', [
			'blog_id' => $blog_id,
			'post_id' => $post_id,
			'scheduled_time' => $scheduled_time,
			'action' => 'schedule_blog',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Blog scheduled successfully.', 'ai-blog-generator' ),
			'scheduled_date' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scheduled_timestamp ),
		] );
	}

	/**
	 * Update blog status.
	 */
	public function update_blog_status() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
		
		$valid_statuses = [ 'draft', 'scheduled', 'published', 'failed' ];
		if ( ! $blog_id || ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid blog ID or status.', 'ai-blog-generator' ) ] );
		}

		// Get the blog
		$blog = $this->blog_model->get( $blog_id );
		if ( ! $blog ) {
			wp_send_json_error( [ 'message' => __( 'Blog not found.', 'ai-blog-generator' ) ] );
		}

		// Update status
		$updated = $this->blog_model->update( $blog_id, [
			'status' => $status,
			'updated_at' => current_time( 'mysql' ),
		] );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update blog status.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Blog status updated', [
			'blog_id' => $blog_id,
			'old_status' => $blog->status,
			'new_status' => $status,
			'action' => 'update_blog_status',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Blog status updated successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Get blog statistics.
	 */
	public function get_blog_statistics() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get period from request
		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'month';
		$valid_periods = [ 'day', 'week', 'month', 'year' ];
		
		if ( ! in_array( $period, $valid_periods, true ) ) {
			$period = 'month';
		}

		// Gather statistics
		$stats = [
			'ideas' => $this->idea_model->get_statistics(),
			'blogs' => $this->blog_model->get_statistics( $period ),
			'costs' => $this->cost_model->get_period_summary( $period ),
			'performance' => $this->get_performance_metrics( $period ),
		];

		wp_send_json_success( $stats );
	}

	/**
	 * Generate blog post from approved idea.
	 *
	 * @param int $idea_id The approved idea ID.
	 * @return int|WP_Error Blog ID on success, WP_Error on failure.
	 */
	public function generate_from_approved_idea( $idea_id ) {
		$start_time = $this->start_timer();

		try {
			$this->log_info( 'generate_from_approved_idea_start', 'Starting blog generation from approved idea', [
				'idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );

			// Validate idea ID
			if ( ! $idea_id ) {
				$this->log_info( 'generate_from_approved_idea_start', 'No idea ID found!');
				return new \WP_Error( 'invalid_idea_id', __( 'Invalid idea ID provided.', 'ai-blog-generator' ) );
			}

			
			// Get the idea
			$this->log_info( 'generate_from_approved_idea_start', 'Initializing Idea Model For Generation');
			$idea = $this->idea_model->get( $idea_id );
			$this->log_info( 'generate_from_approved_idea_start', 'Idea Model Initialized!');
			
			// Debug idea object
			$this->log_info( 'idea_retrieved', 'Idea object retrieved', [
				'idea_id' => $idea_id,
				'idea_type' => gettype( $idea ),
				'idea_exists' => ! empty( $idea ),
				'idea_data' => $idea,
				'status_value' => isset( $idea->status ) ? $idea->status : 'NOT SET',
				'status_type' => isset( $idea->status ) ? gettype( $idea->status ) : 'UNDEFINED',
				'status_empty' => isset( $idea->status ) ? empty( $idea->status ) : 'UNDEFINED',
				'status_trimmed' => isset( $idea->status ) ? trim( $idea->status ) : 'NOT SET'
			] );
			
			if ( ! $idea ) {
				$this->log_info( 'generate_from_approved_idea_start', 'Idea not found!');
				return new \WP_Error( 'idea_not_found', __( 'Idea not found.', 'ai-blog-generator' ) );
			}

			// Verify idea is approved or already generating (to handle race conditions)
					if ( ! in_array( $idea['status'], [ 'approved', 'generating' ], true ) ) {
			$this->log_info( 'generate_from_approved_idea_not_approved', 'Idea not in valid generation status!', [
				'idea_id' => $idea_id,
				'current_status' => $idea['status'],
				'required_statuses' => [ 'approved', 'generating' ],
				'idea_data' => $idea,
				'idea_data_type' => gettype( $idea ),
				'idea_is_array' => is_array( $idea )
			]);
			return new \WP_Error( 'idea_not_approved', sprintf( 
				__( 'Idea must be approved or already generating. Current status: %s', 'ai-blog-generator' ), 
				$idea['status'] 
			) );
		}

			// Check budget constraints
			$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
			if ( ! $budget_manager->can_generate() ) {
				$this->log_warning( 'budget_limit_reached', 'Budget limit reached, cannot generate blog', [
					'idea_id' => $idea_id,
					'current_budget' => $budget_manager->get_monthly_usage()
				] );
				return new \WP_Error( 'budget_exceeded', __( 'Monthly budget limit reached. Cannot generate new posts.', 'ai-blog-generator' ) );
			}

			// Check daily limit
			$today_count = $this->blog_model->count_generated_today();
			$posts_per_day = get_option( 'ai_blog_generator_posts_per_day', 2 );
			$this->log_info( 'generate_from_approved_idea_start', 'Counting Generated Today');
			if ( $today_count >= $posts_per_day ) {
				$this->log_warning( 'daily_limit_reached', 'Daily generation limit reached', [
					'idea_id' => $idea_id,
					'today_count' => $today_count,
					'limit' => $posts_per_day
				] );
				return new \WP_Error( 'daily_limit_reached', __( 'Daily generation limit reached.', 'ai-blog-generator' ) );
			}

			$this->log_info( 'generation_constraints_passed', 'Budget and daily limits passed', [
				'idea_id' => $idea_id,
				'today_count' => $today_count,
				'daily_limit' => $posts_per_day
			] );

			// Initialize content generator
			$this->log_info( 'generate_from_approved_idea_start', 'Initializing Content Generator');
			$generator = new \AI_Blog_Generator\Services\Content_Generator();
			$this->log_info( 'generate_from_approved_idea_start', 'Content Generator Initialized!');
			// Generate blog post from idea
			$this->log_info( 'generate_from_approved_idea_start', 'Generating Blog Post');
			$result = $generator->generate_blog_post( $idea_id );
			
			if ( is_wp_error( $result ) ) {
				$this->log_error( 'generation_failed', 'Blog generation failed', [
					'idea_id' => $idea_id,
					'error' => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
					'error_data' => $result->get_error_data()
				] );
				
				// Roll back idea status to approved if generation failed
				$idea_model->update( $idea_id, [ 'status' => 'approved' ] );
				$this->log_info( 'idea_status_rolled_back', 'Idea status rolled back to approved after generation failure', [
					'idea_id' => $idea_id
				] );
				
				return $result;
			}
			
			$this->log_info( 'generate_from_approved_idea_start', 'Blog Post Generated!', [
				'idea_id' => $idea_id,
				'result_type' => gettype( $result ),
				'is_array' => is_array( $result ),
				'result_keys' => is_array( $result ) ? array_keys( $result ) : []
			]);

			// Handle array response from generate_blog_post
			if ( is_array( $result ) ) {
				if ( ! $result['success'] ) {
					$this->log_error( 'generation_failed', 'Blog generation failed', [
						'idea_id' => $idea_id,
						'error' => $result['message']
					] );
					return new \WP_Error( 'generation_failed', $result['message'] );
				}
				
				// Extract blog_id from successful result
				$blog_id = $result['blog_id'];
			} else {
				// Fallback for unexpected response format
				$blog_id = $result;
			}

			$this->log_info( 'generate_from_approved_idea_success', 'Blog generation completed successfully', [
				'idea_id' => $idea_id,
				'blog_id' => $blog_id,
				'generation_method' => 'from_approved_idea',
				'post_id' => isset( $result['post_id'] ) ? $result['post_id'] : null
			] );

			$this->end_timer( $start_time, 'generate_from_approved_idea' );

			return $blog_id;

		} catch ( \Exception $e ) {
			$this->log_error( 'generate_from_approved_idea_exception', 'Exception during blog generation from approved idea', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			$this->end_timer( $start_time, 'generate_from_approved_idea' );
			
			return new \WP_Error( 'generation_exception', sprintf( 
				__( 'Generation failed: %s', 'ai-blog-generator' ), 
				$e->getMessage() 
			) );
		}
	}

	/**
	 * Bulk approve ideas.
	 */
	public function bulk_approve_ideas() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_bulk_ideas', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_ids = isset( $_POST['idea_ids'] ) && is_array( $_POST['idea_ids'] ) 
			? array_map( 'absint', $_POST['idea_ids'] ) 
			: [];
			
		if ( empty( $idea_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No ideas selected.', 'ai-blog-generator' ) ] );
		}

		$approved_count = 0;
		$failed_ids = [];

		foreach ( $idea_ids as $idea_id ) {
			$updated = $this->idea_model->update( $idea_id, [
				'status' => 'approved',
				'updated_at' => current_time( 'mysql' ),
			] );
			
			if ( $updated ) {
				$approved_count++;
			} else {
				$failed_ids[] = $idea_id;
			}
		}

		// Log the action
		Logger::info( 'Bulk ideas approved', [
			'total_selected' => count( $idea_ids ),
			'approved_count' => $approved_count,
			'failed_ids' => $failed_ids,
			'action' => 'bulk_approve_ideas',
			'user_id' => get_current_user_id(),
		] );

		if ( $approved_count === 0 ) {
			wp_send_json_error( [ 'message' => __( 'Failed to approve any ideas.', 'ai-blog-generator' ) ] );
		}

		$message = sprintf(
			/* translators: %d: number of approved ideas */
			_n( '%d idea approved successfully.', '%d ideas approved successfully.', $approved_count, 'ai-blog-generator' ),
			$approved_count
		);

		if ( ! empty( $failed_ids ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of failed ideas */
				_n( '%d idea failed.', '%d ideas failed.', count( $failed_ids ), 'ai-blog-generator' ),
				count( $failed_ids )
			);
		}

		wp_send_json_success( [
			'message' => $message,
			'approved_count' => $approved_count,
			'statistics' => $this->idea_model->get_statistics(),
		] );
	}

	/**
	 * Bulk deny ideas.
	 */
	public function bulk_deny_ideas() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_bulk_ideas', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_ids = isset( $_POST['idea_ids'] ) && is_array( $_POST['idea_ids'] ) 
			? array_map( 'absint', $_POST['idea_ids'] ) 
			: [];
			
		if ( empty( $idea_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No ideas selected.', 'ai-blog-generator' ) ] );
		}

		$denied_count = 0;
		$failed_ids = [];

		foreach ( $idea_ids as $idea_id ) {
			$updated = $this->idea_model->update( $idea_id, [
				'status' => 'denied',
				'updated_at' => current_time( 'mysql' ),
			] );
			
			if ( $updated ) {
				$denied_count++;
			} else {
				$failed_ids[] = $idea_id;
			}
		}

		// Log the action
		Logger::info( 'Bulk ideas denied', [
			'total_selected' => count( $idea_ids ),
			'denied_count' => $denied_count,
			'failed_ids' => $failed_ids,
			'action' => 'bulk_deny_ideas',
			'user_id' => get_current_user_id(),
		] );

		if ( $denied_count === 0 ) {
			wp_send_json_error( [ 'message' => __( 'Failed to deny any ideas.', 'ai-blog-generator' ) ] );
		}

		$message = sprintf(
			/* translators: %d: number of denied ideas */
			_n( '%d idea denied successfully.', '%d ideas denied successfully.', $denied_count, 'ai-blog-generator' ),
			$denied_count
		);

		if ( ! empty( $failed_ids ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of failed ideas */
				_n( '%d idea failed.', '%d ideas failed.', count( $failed_ids ), 'ai-blog-generator' ),
				count( $failed_ids )
			);
		}

		wp_send_json_success( [
			'message' => $message,
			'denied_count' => $denied_count,
			'statistics' => $this->idea_model->get_statistics(),
		] );
	}

	/**
	 * Bulk schedule posts.
	 */
	public function bulk_schedule_posts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get all draft posts
		$drafts = $this->blog_model->get_by_status( 'draft' );
		if ( empty( $drafts ) ) {
			wp_send_json_error( [ 'message' => __( 'No draft posts to schedule.', 'ai-blog-generator' ) ] );
		}

		$scheduler = new Scheduler_Service();
		$scheduled_count = 0;
		$failed_count = 0;

		// Schedule posts distributed over the next few days
		$posts_per_day = get_option( 'ai_blog_posts_per_day', 1 );
		$current_date = current_time( 'Y-m-d' );
		$posts_scheduled_today = 0;
		
		foreach ( $drafts as $index => $draft ) {
			// Calculate which day this post should be scheduled for
			$days_offset = floor( $index / $posts_per_day );
			$schedule_date = date( 'Y-m-d', strtotime( $current_date . ' + ' . $days_offset . ' days' ) );
			
			// Get random time for that day
			$schedule_time = $schedule_date . ' ' . $scheduler->get_random_publish_time();
			
			// Schedule the post
			$scheduled_timestamp = strtotime( $schedule_time );
			$updated_post = wp_update_post( [
				'ID' => $draft->post_id,
				'post_status' => 'future',
				'post_date' => date( 'Y-m-d H:i:s', $scheduled_timestamp ),
				'post_date_gmt' => get_gmt_from_date( date( 'Y-m-d H:i:s', $scheduled_timestamp ) )
			], true );
			
			if ( ! is_wp_error( $updated_post ) ) {
				// Update blog record
				$this->blog_model->update( $draft->id, [
					'status' => 'scheduled',
					'scheduled_time' => $schedule_time,
					'updated_at' => current_time( 'mysql' ),
				] );
				$scheduled_count++;
			} else {
				$failed_count++;
				Logger::error( 'Failed to schedule post in bulk operation', [
					'blog_id' => $draft->id,
					'post_id' => $draft->post_id,
					'error' => $updated_post->get_error_message(),
				] );
			}
		}

		// Log the action
		Logger::info( 'Bulk posts scheduled', [
			'total_drafts' => count( $drafts ),
			'scheduled_count' => $scheduled_count,
			'failed_count' => $failed_count,
			'action' => 'bulk_schedule_posts',
			'user_id' => get_current_user_id(),
		] );

		if ( $scheduled_count === 0 ) {
			wp_send_json_error( [ 'message' => __( 'Failed to schedule any posts.', 'ai-blog-generator' ) ] );
		}

		$message = sprintf(
			/* translators: %d: number of scheduled posts */
			_n( '%d post scheduled successfully.', '%d posts scheduled successfully.', $scheduled_count, 'ai-blog-generator' ),
			$scheduled_count
		);

		if ( $failed_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of failed posts */
				_n( '%d post failed.', '%d posts failed.', $failed_count, 'ai-blog-generator' ),
				$failed_count
			);
		}

		wp_send_json_success( [
			'message' => $message,
			'scheduled_count' => $scheduled_count,
		] );
	}

	/**
	 * Move an idea to the top of the queue.
	 */
	public function move_idea_to_top() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		// Get the idea
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea || $idea['status'] !== 'approved' ) {
			wp_send_json_error( [ 'message' => __( 'Idea not found or not approved.', 'ai-blog-generator' ) ] );
		}

		// Update the idea's created_at to make it appear first
		$updated = $this->idea_model->update( $idea_id, [
			'created_at' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'updated_at' => current_time( 'mysql' ),
		] );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to reorder idea.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Idea moved to top of queue', [
			'idea_id' => $idea_id,
			'title' => $idea['title'],
			'action' => 'move_idea_to_top',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Idea moved to top of queue.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Clear the approved ideas queue.
	 */
	public function clear_queue() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get all approved ideas
		$approved_ideas = $this->idea_model->get_by_status( 'approved' );
		$cleared_count = 0;

		foreach ( $approved_ideas as $idea ) {
			$updated = $this->idea_model->update( $idea->id, [
				'status' => 'denied',
				'updated_at' => current_time( 'mysql' ),
			] );
			
			if ( $updated ) {
				$cleared_count++;
			}
		}

		// Log the action
		Logger::info( 'Approved ideas queue cleared', [
			'cleared_count' => $cleared_count,
			'action' => 'clear_queue',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of cleared ideas */
				_n( '%d idea removed from queue.', '%d ideas removed from queue.', $cleared_count, 'ai-blog-generator' ),
				$cleared_count
			),
			'cleared_count' => $cleared_count,
		] );
	}

	/**
	 * Process the queue immediately.
	 */
	public function process_queue_now() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Check budget
		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		if ( ! $budget_manager->can_generate() ) {
			wp_send_json_error( [ 
				'message' => __( 'Budget limit reached. Cannot process queue.', 'ai-blog-generator' ),
				'budget_exceeded' => true,
			] );
		}

		// Trigger the cron job immediately
		$processor = new \AI_Blog_Generator\Services\Queue_Processor();
		$result = $processor->process_queue();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Queue processing failed: %s', 'ai-blog-generator' ), 
					$result->get_error_message() 
				) 
			] );
		}

		wp_send_json_success( [
			'message' => __( 'Queue processing started.', 'ai-blog-generator' ),
			'processed' => $result,
		] );
	}

	/**
	 * Get performance metrics for a period.
	 *
	 * @param string $period The period to analyze ('week', 'month', 'year').
	 * @return array Performance metrics.
	 */
	private function get_performance_metrics( $period = 'month' ) {
		// Implementation for getting performance metrics
		// This is a placeholder for future enhancement
		return [
			'success_rate' => round( $success_rate, 2 ),
			'avg_generation_time' => round( $avg_generation_time ?: 0 ),
			'avg_cost_per_post' => round( $avg_cost ?: 0, 4 ),
			'total_attempts' => $total_attempts,
			'successful_posts' => $successful,
		];
	}

	/**
	 * AJAX handler to start background generation for an idea
	 */
	public function ajax_start_background_generation() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		// Initialize background processor
		$processor = new Background_Processor();

		// Check if already in progress
		if ( $processor->is_generation_in_progress( $idea_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Generation already in progress for this idea.', 'ai-blog-generator' ) ] );
		}

		// Get the idea to validate
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea || $idea['status'] !== 'approved' ) {
			wp_send_json_error( [ 'message' => __( 'Idea not found or not approved.', 'ai-blog-generator' ) ] );
		}

		// Start background generation
		$started = $processor->start_generation( $idea_id );

		if ( ! $started ) {
			wp_send_json_error( [ 'message' => __( 'Failed to start background generation.', 'ai-blog-generator' ) ] );
		}

		wp_send_json_success( [
			'message' => __( 'Background generation started successfully.', 'ai-blog-generator' ),
			'idea_id' => $idea_id,
			'status' => 'pending'
		] );
	}

	/**
	 * AJAX handler to get generation status for one or more ideas
	 */
	public function ajax_get_generation_status() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		$processor = new Background_Processor();

		// Check if single idea ID provided
		if ( isset( $_POST['idea_id'] ) ) {
			$idea_id = absint( $_POST['idea_id'] );
			$status = $processor->get_generation_status( $idea_id );
			
			wp_send_json_success( [
				'status' => $status,
				'idea_id' => $idea_id
			] );
		}

		// Check if multiple idea IDs provided
		if ( isset( $_POST['idea_ids'] ) && is_array( $_POST['idea_ids'] ) ) {
			$idea_ids = array_map( 'absint', $_POST['idea_ids'] );
			$statuses = $processor->get_multiple_generation_statuses( $idea_ids );
			
			wp_send_json_success( [
				'statuses' => $statuses
			] );
		}

		// If no IDs provided, return all running generations
		$running = $processor->get_running_generations();
		
		wp_send_json_success( [
			'running_generations' => $running,
			'statistics' => $processor->get_generation_statistics()
		] );
	}

	/**
	 * AJAX handler to cancel a generation in progress
	 */
	public function ajax_cancel_generation() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		$processor = new Background_Processor();
		$cancelled = $processor->cancel_generation( $idea_id );

		if ( $cancelled ) {
			wp_send_json_success( [
				'message' => __( 'Generation cancelled successfully.', 'ai-blog-generator' ),
				'idea_id' => $idea_id
			] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to cancel generation.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to start background generation for multiple ideas
	 */
	public function ajax_bulk_start_background_generation() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_ids = isset( $_POST['idea_ids'] ) && is_array( $_POST['idea_ids'] ) 
			? array_map( 'absint', $_POST['idea_ids'] ) 
			: [];
			
		if ( empty( $idea_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No ideas selected.', 'ai-blog-generator' ) ] );
		}

		$processor = new Background_Processor();
		$started_count = 0;
		$failed_count = 0;
		$already_running_count = 0;
		$results = [];

		foreach ( $idea_ids as $idea_id ) {
			// Check if already in progress
			if ( $processor->is_generation_in_progress( $idea_id ) ) {
				$already_running_count++;
				$results[ $idea_id ] = 'already_running';
				continue;
			}

			// Get the idea to validate
			$idea = $this->idea_model->get( $idea_id );
			if ( ! $idea || $idea['status'] !== 'approved' ) {
				$failed_count++;
				$results[ $idea_id ] = 'invalid';
				continue;
			}

			// Start background generation
			$started = $processor->start_generation( $idea_id );
			
			if ( $started ) {
				$started_count++;
				$results[ $idea_id ] = 'started';
			} else {
				$failed_count++;
				$results[ $idea_id ] = 'failed';
			}
		}

		$message = sprintf(
			/* translators: %1$d: started count, %2$d: total count */
			_n( 
				'%1$d of %2$d generation started.', 
				'%1$d of %2$d generations started.', 
				$started_count, 
				'ai-blog-generator' 
			),
			$started_count,
			count( $idea_ids )
		);

		if ( $already_running_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: already running count */
				_n( '%d was already running.', '%d were already running.', $already_running_count, 'ai-blog-generator' ),
				$already_running_count
			);
		}

		wp_send_json_success( [
			'message' => $message,
			'started_count' => $started_count,
			'failed_count' => $failed_count,
			'already_running_count' => $already_running_count,
			'results' => $results
		] );
	}

	/**
	 * AJAX handler to get approved ideas for the approved blogs page
	 */
	public function ajax_get_approved_ideas() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get approved ideas
		$ideas = $this->idea_model->get_by_status( 'approved' );
		
		// Get generation statuses for all ideas
		$processor = new Background_Processor();
		$idea_ids = wp_list_pluck( $ideas, 'id' );
		$generation_statuses = $processor->get_multiple_generation_statuses( $idea_ids );

		wp_send_json_success( [
			'ideas' => $ideas,
			'generation_statuses' => $generation_statuses,
			'total_count' => count( $ideas )
		] );
	}

	/**
	 * AJAX handler to get idea data for editing
	 */
	public function ajax_get_idea_for_edit() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		// Get the idea
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea ) {
			wp_send_json_error( [ 'message' => __( 'Idea not found.', 'ai-blog-generator' ) ] );
		}

		wp_send_json_success( $idea );
	}

	/**
	 * AJAX handler to update an approved idea
	 */
	public function ajax_update_approved_idea() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		// Get current idea
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea ) {
			wp_send_json_error( [ 'message' => __( 'Idea not found.', 'ai-blog-generator' ) ] );
		}

		// Prepare update data
		$update_data = [
			'title' => sanitize_text_field( $_POST['title'] ?? $idea['title'] ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? $idea['description'] ),
			'category_id' => isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : $idea['category_id'],
			'persona_id' => isset( $_POST['persona_id'] ) ? absint( $_POST['persona_id'] ) : $idea['persona_id'],
			'updated_at' => current_time( 'mysql' )
		];

		// Update the idea
		$updated = $this->idea_model->update( $idea_id, $update_data );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update idea.', 'ai-blog-generator' ) ] );
		}

		$response = [
			'message' => __( 'Idea updated successfully.', 'ai-blog-generator' ),
			'idea_id' => $idea_id
		];

		// Check if should generate after update
		$generate_after = isset( $_POST['generate_after'] ) && $_POST['generate_after'] === '1';
		if ( $generate_after ) {
			$processor = new Background_Processor();
			$started = $processor->start_generation( $idea_id );
			
			if ( $started ) {
				$response['message'] = __( 'Idea updated and generation started.', 'ai-blog-generator' );
				$response['generation_started'] = true;
			} else {
				$response['message'] = __( 'Idea updated, but failed to start generation.', 'ai-blog-generator' );
				$response['generation_started'] = false;
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX handler to generate from approved idea (legacy support)
	 */
	public function ajax_generate_from_approved_idea() {
		// This method now redirects to the background generation system
		$this->ajax_start_background_generation();
	}

	/**
	 * AJAX handler to write debug messages to log file
	 */
	public function ajax_write_debug() {
		// Get the debug message
		$message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
		
		if ( empty( $message ) ) {
			wp_die();
		}
		
		// Write to debug log file
		$log_file = AI_BLOG_GENERATOR_DEBUG_LOG;
		$log_entry = $message . "\n";
		
		// Append to log file
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
		
		wp_die(); // Just end the request
	}

	/**
	 * AJAX handler to get drafted posts with WordPress sync
	 */
	public function ajax_get_drafted_posts() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'get_drafted_posts', 'Fetching drafted posts' );

			// Get all non-published posts from our database
			$all_posts = $this->blog_model->get_all_with_details( [], 'gp.created_at DESC' );
			
			// Log the initial database statuses
			$db_status_counts = [];
			foreach ( $all_posts as $post ) {
				$status = $post->status ?? 'null';
				if ( ! isset( $db_status_counts[ $status ] ) ) {
					$db_status_counts[ $status ] = 0;
				}
				$db_status_counts[ $status ]++;
			}
			
			Logger::info( 'get_drafted_posts_debug', 'Database status counts before sync', [
				'total_count' => count( $all_posts ),
				'status_breakdown' => $db_status_counts
			] );
			
			Logger::info( 'get_drafted_posts_debug', 'Total posts fetched from database', [
				'total_count' => count( $all_posts )
			] );
			
			// Format posts for response and sync with WordPress
			$formatted_posts = [];
			$draft_count = 0;
			$scheduled_count = 0;
			$skipped_published = 0;
			$skipped_deleted = 0;
			$skipped_other = 0;
			
			foreach ( $all_posts as $post ) {
				// Get the actual WordPress post
				$wp_post = get_post( $post->post_id );
				
				if ( ! $wp_post ) {
					// Post was deleted in WordPress, update our record
					$this->blog_model->update( $post->id, [
						'status' => 'deleted'
					] );
					$skipped_deleted++;
					continue;
				}
				
				// Sync status with WordPress
				$actual_status = 'draft';
				$scheduled_time = null;
				
				// First check if post is trashed - these should be excluded
				if ( $wp_post->post_status === 'trash' ) {
					// Post is trashed, update our record and skip
					$this->blog_model->update( $post->id, [
						'status' => 'trashed'
					] );
					$skipped_other++;
					Logger::info( 'get_drafted_posts_debug', 'Skipping trashed post', [
						'post_id' => $post->post_id,
						'title' => $wp_post->post_title
					] );
					continue;
				} elseif ( $wp_post->post_status === 'publish' ) {
					// Post was published, update our record and skip
					$this->blog_model->update( $post->id, [
						'status' => 'published'
					] );
					$skipped_published++;
					continue;
				} elseif ( $wp_post->post_status === 'future' ) {
					// Post is scheduled
					$actual_status = 'scheduled';
					$scheduled_time = $wp_post->post_date;
					$scheduled_count++;
					
					// Update our record to sync
					$this->blog_model->update( $post->id, [
						'status' => 'scheduled',
						'scheduled_time' => $scheduled_time
					] );
				} elseif ( $wp_post->post_status === 'draft' || $wp_post->post_status === 'auto-draft' ) {
					// Post is a draft
					$actual_status = 'draft';
					$draft_count++;
					
					// Update our record to sync
					$this->blog_model->update( $post->id, [
						'status' => 'draft'
					] );
				} else {
					// Other status (private, pending, etc.) - skip
					$skipped_other++;
					Logger::info( 'get_drafted_posts_debug', 'Skipping post with other status', [
						'post_id' => $post->post_id,
						'status' => $wp_post->post_status,
						'title' => $wp_post->post_title
					] );
					continue;
				}
				
				$categories = get_the_category( $post->post_id );
				$formatted_posts[] = [
					'id' => $post->id,
					'post_id' => $post->post_id,
					'idea_id' => $post->idea_id,
					'post_title' => $wp_post->post_title,
					'idea_title' => $post->idea_title,
					'cost' => $post->cost,
					'created_at' => $post->created_at,
					'scheduled_time' => $scheduled_time,
					'status' => $actual_status,
					'categories' => $categories,
					'edit_link' => get_edit_post_link( $post->post_id ),
					'preview_link' => get_preview_post_link( $post->post_id )
				];
			}
			
			Logger::info( 'get_drafted_posts_debug', 'Post filtering results', [
				'total_fetched' => count( $all_posts ),
				'returned_posts' => count( $formatted_posts ),
				'draft_count' => $draft_count,
				'scheduled_count' => $scheduled_count,
				'skipped_published' => $skipped_published,
				'skipped_deleted' => $skipped_deleted,
				'skipped_other' => $skipped_other
			] );

			// Get statistics (these are now accurate after syncing)
			$statistics = [
				'drafts' => $draft_count,
				'scheduled' => $scheduled_count,
				'published' => $this->blog_model->count_published_today(),
				'totalCost' => $this->blog_model->get_total_cost( [ 'status' => ['draft', 'scheduled'] ] )
			];

			wp_send_json_success( [
				'posts' => $formatted_posts,
				'statistics' => $statistics
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'get_drafted_posts_error', 'Error fetching drafted posts', [
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to load drafted posts.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to publish a single post
	 */
	public function ajax_publish_post() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			
			if ( ! $blog_id || ! $post_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid blog or post ID.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'publish_post', 'Publishing post', [
				'blog_id' => $blog_id,
				'post_id' => $post_id
			] );

			// Get the blog record
			$blog = $this->blog_model->get( $blog_id );
			if ( ! $blog || $blog->post_id != $post_id ) {
				wp_send_json_error( [ 'message' => __( 'Blog not found.', 'ai-blog-generator' ) ] );
			}

			// Publish the post
			$updated_post = wp_update_post( [
				'ID' => $post_id,
				'post_status' => 'publish'
			], true );

			if ( is_wp_error( $updated_post ) ) {
				Logger::error( 'publish_post_error', 'Failed to publish post', [
					'blog_id' => $blog_id,
					'post_id' => $post_id,
					'error' => $updated_post->get_error_message()
				] );
				wp_send_json_error( [ 'message' => $updated_post->get_error_message() ] );
			}

			// Update blog status
			$this->blog_model->update( $blog_id, [
				'status' => 'published',
				'updated_at' => current_time( 'mysql' )
			] );

			// Get updated statistics
			$statistics = [
				'drafts' => $this->blog_model->count( [ 'status' => 'draft' ] ),
				'scheduled' => $this->blog_model->count( [ 'status' => 'scheduled' ] ),
				'published' => $this->blog_model->count_published_today(),
				'totalCost' => $this->blog_model->get_total_cost( [ 'status' => 'draft' ] )
			];

			wp_send_json_success( [
				'message' => __( 'Post published successfully.', 'ai-blog-generator' ),
				'post_url' => get_permalink( $post_id ),
				'statistics' => $statistics
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'publish_post_exception', 'Exception during post publish', [
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to publish post.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to schedule a single post
	 */
	public function ajax_schedule_post() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$scheduled_time = isset( $_POST['scheduled_time'] ) ? sanitize_text_field( $_POST['scheduled_time'] ) : '';
			
			if ( ! $blog_id || ! $post_id || empty( $scheduled_time ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid input data.', 'ai-blog-generator' ) ] );
			}

			// Convert datetime-local format to timestamp
			$scheduled_timestamp = strtotime( $scheduled_time );
			if ( ! $scheduled_timestamp || $scheduled_timestamp <= current_time( 'timestamp' ) ) {
				wp_send_json_error( [ 'message' => __( 'Scheduled time must be in the future.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'schedule_post', 'Scheduling post', [
				'blog_id' => $blog_id,
				'post_id' => $post_id,
				'scheduled_time' => $scheduled_time
			] );

			// Get the blog record
			$blog = $this->blog_model->get( $blog_id );
			if ( ! $blog || $blog->post_id != $post_id ) {
				wp_send_json_error( [ 'message' => __( 'Blog not found.', 'ai-blog-generator' ) ] );
			}

			// Schedule the post
			$scheduled_timestamp = strtotime( $scheduled_time );
			$updated_post = wp_update_post( [
				'ID' => $post_id,
				'post_status' => 'future',
				'post_date' => date( 'Y-m-d H:i:s', $scheduled_timestamp ),
				'post_date_gmt' => get_gmt_from_date( date( 'Y-m-d H:i:s', $scheduled_timestamp ) )
			], true );

			if ( is_wp_error( $updated_post ) ) {
				Logger::error( 'Failed to schedule blog', [
					'blog_id' => $blog_id,
					'post_id' => $post_id,
					'scheduled_time' => $scheduled_time,
					'error' => $updated_post->get_error_message(),
					'action' => 'schedule_blog',
				] );
				wp_send_json_error( [ 
					'message' => sprintf( 
						__( 'Scheduling failed: %s', 'ai-blog-generator' ), 
						$updated_post->get_error_message() 
					) 
				] );
			}

			// Update blog with scheduled time
			$this->blog_model->update( $blog_id, [
				'status' => 'scheduled',
				'scheduled_time' => date( 'Y-m-d H:i:s', $scheduled_timestamp ),
				'updated_at' => current_time( 'mysql' )
			] );

			// Get updated statistics
			$statistics = [
				'drafts' => $this->blog_model->count( [ 'status' => 'draft' ] ),
				'scheduled' => $this->blog_model->count( [ 'status' => 'scheduled' ] ),
				'published' => $this->blog_model->count_published_today(),
				'totalCost' => $this->blog_model->get_total_cost( [ 'status' => 'draft' ] )
			];

			wp_send_json_success( [
				'message' => __( 'Post scheduled successfully.', 'ai-blog-generator' ),
				'scheduled_date' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scheduled_timestamp ),
				'statistics' => $statistics
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'schedule_post_exception', 'Exception during post schedule', [
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to schedule post.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to unschedule a single post
	 */
	public function ajax_unschedule_post() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			
			if ( ! $blog_id || ! $post_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid blog or post ID.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'unschedule_post', 'Unscheduling post', [
				'blog_id' => $blog_id,
				'post_id' => $post_id
			] );

			// Get the blog record
			$blog = $this->blog_model->get( $blog_id );
			if ( ! $blog || $blog->post_id != $post_id ) {
				wp_send_json_error( [ 'message' => __( 'Blog not found.', 'ai-blog-generator' ) ] );
			}

			// Unschedule the post
			$updated_post = wp_update_post( [
				'ID' => $post_id,
				'post_status' => 'draft'
			], true );

			if ( is_wp_error( $updated_post ) ) {
				Logger::error( 'unschedule_post_error', 'Failed to unschedule post', [
					'blog_id' => $blog_id,
					'post_id' => $post_id,
					'error' => $updated_post->get_error_message()
				] );
				wp_send_json_error( [ 'message' => $updated_post->get_error_message() ] );
			}

			// Update blog status
			$this->blog_model->update( $blog_id, [
				'status' => 'draft',
				'updated_at' => current_time( 'mysql' )
			] );

			// Get updated statistics
			$statistics = [
				'drafts' => $this->blog_model->count( [ 'status' => 'draft' ] ),
				'scheduled' => $this->blog_model->count( [ 'status' => 'scheduled' ] ),
				'published' => $this->blog_model->count_published_today(),
				'totalCost' => $this->blog_model->get_total_cost( [ 'status' => 'draft' ] )
			];

			wp_send_json_success( [
				'message' => __( 'Post unscheduled successfully.', 'ai-blog-generator' ),
				'post_url' => get_permalink( $post_id ),
				'statistics' => $statistics
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'unschedule_post_exception', 'Exception during post unschedule', [
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to unschedule post.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to bulk publish posts
	 */
	public function ajax_bulk_publish_posts() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$blog_ids = isset( $_POST['blog_ids'] ) && is_array( $_POST['blog_ids'] ) 
				? array_map( 'absint', $_POST['blog_ids'] ) 
				: [];
				
			if ( empty( $blog_ids ) ) {
				wp_send_json_error( [ 'message' => __( 'No posts selected.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'bulk_publish_posts', 'Bulk publishing posts', [
				'blog_ids' => $blog_ids,
				'count' => count( $blog_ids )
			] );

			$published_count = 0;
			$failed_count = 0;

			foreach ( $blog_ids as $blog_id ) {
				// Get the blog record
				$blog = $this->blog_model->get( $blog_id );
				if ( ! $blog ) {
					$failed_count++;
					continue;
				}

				// Publish the post
				$updated_post = wp_update_post( [
					'ID' => $blog->post_id,
					'post_status' => 'publish'
				], true );

				if ( is_wp_error( $updated_post ) ) {
					$failed_count++;
					Logger::error( 'bulk_publish_error', 'Failed to publish post in bulk', [
						'blog_id' => $blog_id,
						'post_id' => $blog->post_id,
						'error' => $updated_post->get_error_message()
					] );
					continue;
				}

				// Update blog status
				$this->blog_model->update( $blog_id, [
					'status' => 'published',
					'updated_at' => current_time( 'mysql' )
				] );

				$published_count++;
			}

			// Get updated statistics
			$statistics = [
				'drafts' => $this->blog_model->count( [ 'status' => 'draft' ] ),
				'scheduled' => $this->blog_model->count( [ 'status' => 'scheduled' ] ),
				'published' => $this->blog_model->count_published_today(),
				'totalCost' => $this->blog_model->get_total_cost( [ 'status' => 'draft' ] )
			];

			$message = sprintf(
				/* translators: %1$d: published count, %2$d: total count */
				_n( 
					'%1$d of %2$d post published.', 
					'%1$d of %2$d posts published.', 
					$published_count, 
					'ai-blog-generator' 
				),
				$published_count,
				count( $blog_ids )
			);

			wp_send_json_success( [
				'message' => $message,
				'published' => $published_count,
				'failed' => $failed_count,
				'statistics' => $statistics
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'bulk_publish_exception', 'Exception during bulk publish', [
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to publish posts.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to delete posts
	 */
	public function ajax_delete_posts() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$blog_ids = isset( $_POST['blog_ids'] ) && is_array( $_POST['blog_ids'] ) 
				? array_map( 'absint', $_POST['blog_ids'] ) 
				: [];
				
			if ( empty( $blog_ids ) ) {
				wp_send_json_error( [ 'message' => __( 'No posts selected.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'delete_posts', 'Deleting posts', [
				'blog_ids' => $blog_ids,
				'count' => count( $blog_ids )
			] );

			$deleted_count = 0;
			$failed_count = 0;

			foreach ( $blog_ids as $blog_id ) {
				// Get the blog record
				$blog = $this->blog_model->get( $blog_id );
				if ( ! $blog ) {
					$failed_count++;
					continue;
				}

				// Delete the WordPress post
				$deleted = wp_delete_post( $blog->post_id, false ); // Move to trash
				
				if ( ! $deleted ) {
					$failed_count++;
					Logger::error( 'delete_post_error', 'Failed to delete post', [
						'blog_id' => $blog_id,
						'post_id' => $blog->post_id
					] );
					continue;
				}

				// Delete the blog record
				$this->blog_model->delete( $blog_id );
				$deleted_count++;
			}

			// Get updated statistics
			$statistics = [
				'drafts' => $this->blog_model->count( [ 'status' => 'draft' ] ),
				'scheduled' => $this->blog_model->count( [ 'status' => 'scheduled' ] ),
				'published' => $this->blog_model->count_published_today(),
				'totalCost' => $this->blog_model->get_total_cost( [ 'status' => 'draft' ] )
			];

			$message = sprintf(
				/* translators: %1$d: deleted count, %2$d: total count */
				_n( 
					'%1$d of %2$d post deleted.', 
					'%1$d of %2$d posts deleted.', 
					$deleted_count, 
					'ai-blog-generator' 
				),
				$deleted_count,
				count( $blog_ids )
			);

			wp_send_json_success( [
				'message' => $message,
				'deleted' => $deleted_count,
				'failed' => $failed_count,
				'statistics' => $statistics
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'delete_posts_exception', 'Exception during delete posts', [
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to delete posts.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler to download prompts file
	 */
	public function ajax_download_prompts() {
		try {
			// Verify security
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
			
			if ( ! $idea_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
			}

			Logger::info( 'download_prompts', 'Downloading prompts file', [
				'idea_id' => $idea_id
			] );

			// Build the path to the prompts file
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/ai-blog-generator-logs/generations';
			$filename = $idea_id . '_prompts.txt';
			$filepath = $log_dir . '/' . $filename;

			// Check if file exists
			if ( ! file_exists( $filepath ) ) {
				wp_send_json_error( [ 'message' => __( 'Prompts file not found. The prompts file may not have been created for this idea.', 'ai-blog-generator' ) ] );
			}

			// Read the file content
			$content = file_get_contents( $filepath );
			
			if ( $content === false ) {
				wp_send_json_error( [ 'message' => __( 'Failed to read prompts file.', 'ai-blog-generator' ) ] );
			}

			// Get idea details for better filename
			$idea = $this->idea_model->get( $idea_id );
			$safe_title = '';
			if ( $idea && isset( $idea['title'] ) ) {
				// Create a safe filename from the idea title
				$safe_title = sanitize_title( $idea['title'] );
				$safe_title = substr( $safe_title, 0, 50 ); // Limit length
			}

			// Create download filename
			$download_filename = 'prompts_' . $idea_id;
			if ( ! empty( $safe_title ) ) {
				$download_filename .= '_' . $safe_title;
			}
			$download_filename .= '.txt';

			wp_send_json_success( [
				'content' => $content,
				'filename' => $download_filename
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'download_prompts_exception', 'Exception during prompts download', [
				'idea_id' => $idea_id ?? 'unknown',
				'error' => $e->getMessage()
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to download prompts file.', 'ai-blog-generator' ) ] );
		}
	}
} 
 
 
