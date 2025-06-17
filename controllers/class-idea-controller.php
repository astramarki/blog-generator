<?php
/**
 * Idea Controller Class
 *
 * Handles idea generation and management operations.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Services\Idea_Generator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idea Controller Class
 */
class Idea_Controller {

	/**
	 * Idea model instance.
	 *
	 * @var Idea_Model
	 */
	private $idea_model;

	/**
	 * Context model instance.
	 *
	 * @var Context_Model
	 */
	private $context_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->idea_model = new Idea_Model();
		$this->context_model = new Context_Model();
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Idea generation
		add_action( 'wp_ajax_ai_blog_generate_ideas', [ $this, 'generate_ideas' ] );
		add_action( 'wp_ajax_ai_blog_regenerate_idea', [ $this, 'regenerate_idea' ] );
		
		// Idea management
		add_action( 'wp_ajax_ai_blog_update_idea', [ $this, 'update_idea' ] );
		add_action( 'wp_ajax_ai_blog_delete_idea', [ $this, 'delete_idea' ] );
		add_action( 'wp_ajax_ai_blog_update_idea_category', [ $this, 'update_idea_category' ] );
		
		// Bulk operations
		add_action( 'wp_ajax_ai_blog_bulk_deny_ideas', [ $this, 'bulk_deny_ideas' ] );
		add_action( 'wp_ajax_ai_blog_bulk_delete_ideas', [ $this, 'bulk_delete_ideas' ] );
		
		// Data retrieval
		add_action( 'wp_ajax_ai_blog_get_idea_details', [ $this, 'get_idea_details' ] );
		add_action( 'wp_ajax_ai_blog_get_ideas_by_status', [ $this, 'get_ideas_by_status' ] );
		
		// Save selected ideas after confirmation
		add_action( 'wp_ajax_ai_blog_save_selected_ideas', [ $this, 'save_selected_ideas' ] );
	}

	/**
	 * Generate new ideas.
	 */
	public function generate_ideas() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get parameters
		$count = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 5;
		if ( $count < 1 || $count > 10 ) {
			$count = 5;
		}

		$custom_prompt = isset( $_POST['custom_prompt'] ) ? sanitize_textarea_field( $_POST['custom_prompt'] ) : '';
		$context_id = isset( $_POST['context_id'] ) ? absint( $_POST['context_id'] ) : 0;

		// Check if idea generation is enabled
		if ( ! get_option( 'ai_blog_generator_enable_idea_generation', true ) ) {
			wp_send_json_error( [ 'message' => __( 'Idea generation is disabled.', 'ai-blog-generator' ) ] );
		}

		// Check if generation is paused due to budget
		if ( get_option( 'ai_blog_generator_generation_paused', false ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Generation is paused due to budget limits.', 'ai-blog-generator' ),
				'budget_exceeded' => true,
			] );
		}

		// Log generation start
		Logger::info( 'idea_generation_start', 'Starting idea generation via AJAX', [
			'count' => $count,
			'has_custom_prompt' => ! empty( $custom_prompt ),
			'context_id' => $context_id,
			'user_id' => get_current_user_id(),
		] );

		try {
			// Step 1: Get all existing blog post titles
			$existing_posts = get_posts( [
				'post_type' => 'post',
				'post_status' => ['publish', 'draft', 'future', 'pending'],
				'numberposts' => -1,
				'fields' => 'post_title',
			] );
			$existing_titles = array_map( function( $post ) {
				return $post->post_title;
			}, $existing_posts );

			// Step 2: Get all existing blog ideas from database
			$existing_ideas = $this->idea_model->find_all( [], 'created_at DESC' );
			$existing_idea_titles = array_map( function( $idea ) {
				return $idea->title;
			}, $existing_ideas );

			// Step 3: Compile contexts specifically for idea generation
			$content_generator = new \AI_Blog_Generator\Services\Content_Generator();
			$contexts = $content_generator->compile_contexts_for_ideas( [
				'max_contexts_per_type' => 3, // Limit to most important contexts for ideas
				'priority_threshold' => 25,   // Only use higher priority contexts for ideas
			] );
			
			if ( empty( $contexts ) ) {
				throw new \Exception( __( 'No active contexts found. Please configure contexts first.', 'ai-blog-generator' ) );
			}

			// Step 4: Get all active personas for AI selection
			$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
			$personas = $persona_model->get_active_personas();
			
			// Format personas for API
			$formatted_personas = [];
			foreach ( $personas as $persona ) {
				$formatted_personas[] = $persona_model->format( $persona );
			}
			
			Logger::info( 'personas_included_in_generation', 'Including personas in idea generation', [
				'persona_count' => count( $formatted_personas ),
				'persona_names' => array_column( $formatted_personas, 'name' )
			] );

			// Step 5: Generate ideas using Anthropic service
			$anthropic_service = new \AI_Blog_Generator\Services\Anthropic_Service();
			$result = $anthropic_service->generate_blog_ideas( 
				$contexts, 
				$existing_titles, 
				$existing_idea_titles,
				$count,
				$custom_prompt,
				$formatted_personas
			);

			if ( ! $result['success'] ) {
				throw new \Exception( $result['message'] );
			}

			// Add generated categories and personas to ideas
			$processed_ideas = [];
			foreach ( $result['ideas'] as $idea ) {
				$category_id = $this->get_or_create_category( $idea['category'] ?? '' );
				$processed_idea = [
					'title' => $idea['title'],
					'description' => $idea['description'],
					'category' => $idea['category'] ?? '',
					'category_id' => $category_id,
					'keyword' => $idea['keyword'] ?? $idea['primary_keyword'] ?? '',
				];
				
				// Add persona information if available
				if ( isset( $idea['persona_id'] ) && $idea['persona_id'] > 0 ) {
					$processed_idea['persona_id'] = $idea['persona_id'];
					
					// Find persona name for display
					foreach ( $formatted_personas as $persona ) {
						if ( $persona['id'] == $idea['persona_id'] ) {
							$processed_idea['persona_name'] = $persona['name'];
							break;
						}
					}
				}
				
				$processed_ideas[] = $processed_idea;
			}

			Logger::info( 'idea_generation_success', 'Ideas generated successfully via API', [
				'count' => count( $processed_ideas ),
				'cost' => $result['cost'] ?? 0,
				'tokens_used' => $result['tokens_used'] ?? 0,
			] );

			// Return ideas for confirmation modal (don't save yet)
			wp_send_json_success( [
				'ideas' => $processed_ideas,
				'cost' => $result['cost'] ?? 0,
				'tokens_used' => $result['tokens_used'] ?? 0,
				'message' => sprintf(
					/* translators: %d: number of ideas generated */
					_n( '%d idea generated for review.', '%d ideas generated for review.', count( $processed_ideas ), 'ai-blog-generator' ),
					count( $processed_ideas )
				),
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'idea_generation_failed', 'Idea generation failed', [
				'error' => $e->getMessage(),
				'count' => $count,
				'context_id' => $context_id,
				'user_id' => get_current_user_id(),
			] );

			$error_message = $e->getMessage();
			if ( strpos( $error_message, 'budget' ) !== false || strpos( $error_message, 'limit' ) !== false ) {
				wp_send_json_error( [ 
					'message' => __( 'Budget limit reached. Cannot generate new ideas.', 'ai-blog-generator' ),
					'budget_exceeded' => true,
				] );
			}

			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Generation failed: %s', 'ai-blog-generator' ), 
					$error_message
				) 
			] );
		}
	}

	/**
	 * Regenerate a specific idea.
	 */
	public function regenerate_idea() {
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

		// Check budget
		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		if ( ! $budget_manager->can_generate() ) {
			wp_send_json_error( [ 
				'message' => __( 'Budget limit reached.', 'ai-blog-generator' ),
				'budget_exceeded' => true,
			] );
		}

		// Delete old idea
		$this->idea_model->delete( $idea_id );

		// Generate new idea with same context
		$generator = new Idea_Generator();
		$result = $generator->generate_single_idea( $idea->context_data );

		if ( is_wp_error( $result ) ) {
			Logger::error( 'Idea regeneration failed', [
				'idea_id' => $idea_id,
				'error' => $result->get_error_message(),
				'action' => 'regenerate_idea',
			] );
			wp_send_json_error( [ 
				'message' => sprintf( 
					__( 'Regeneration failed: %s', 'ai-blog-generator' ), 
					$result->get_error_message() 
				) 
			] );
		}

		// Log the action
		Logger::info( 'Idea regenerated', [
			'old_idea_id' => $idea_id,
			'new_idea_id' => $result['id'],
			'action' => 'regenerate_idea',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Idea regenerated successfully.', 'ai-blog-generator' ),
			'idea' => $result,
		] );
	}

	/**
	 * Update an idea.
	 */
	public function update_idea() {
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
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
		
		if ( ! $idea_id || empty( $title ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid input data.', 'ai-blog-generator' ) ] );
		}

		// Get the idea
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea ) {
			wp_send_json_error( [ 'message' => __( 'Idea not found.', 'ai-blog-generator' ) ] );
		}

		// Update idea
		$updated = $this->idea_model->update( $idea_id, [
			'title' => $title,
			'description' => $description,
			'updated_at' => current_time( 'mysql' ),
		] );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update idea.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Idea updated', [
			'idea_id' => $idea_id,
			'action' => 'update_idea',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Idea updated successfully.', 'ai-blog-generator' ),
		] );
	}

	/**
	 * Delete an idea.
	 */
	public function delete_idea() {
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

		// Check if idea has been used
					if ( $idea['status'] === 'generated' ) {
			wp_send_json_error( [ 'message' => __( 'Cannot delete ideas that have been generated.', 'ai-blog-generator' ) ] );
		}

		// Delete idea
		$deleted = $this->idea_model->delete( $idea_id );
		if ( ! $deleted ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete idea.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Idea deleted', [
			'idea_id' => $idea_id,
			'title' => $idea->title,
			'action' => 'delete_idea',
			'user_id' => get_current_user_id(),
		] );

		// Get updated statistics
		$stats = $this->idea_model->get_statistics();

		wp_send_json_success( [
			'message' => __( 'Idea deleted successfully.', 'ai-blog-generator' ),
			'statistics' => $stats,
		] );
	}

	/**
	 * Update idea category.
	 */
	public function update_idea_category() {
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
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		
		if ( ! $idea_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid idea ID.', 'ai-blog-generator' ) ] );
		}

		// Validate category
		if ( $category_id && ! term_exists( $category_id, 'category' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid category.', 'ai-blog-generator' ) ] );
		}

		// Update idea category
		$updated = $this->idea_model->update( $idea_id, [
			'category_id' => $category_id,
			'updated_at' => current_time( 'mysql' ),
		] );

		if ( ! $updated ) {
			wp_send_json_error( [ 'message' => __( 'Failed to update category.', 'ai-blog-generator' ) ] );
		}

		// Get category name
		$category_name = '';
		if ( $category_id ) {
			$category = get_category( $category_id );
			if ( $category ) {
				$category_name = $category->name;
			}
		}

		// Log the action
		Logger::info( 'Idea category updated', [
			'idea_id' => $idea_id,
			'category_id' => $category_id,
			'category_name' => $category_name,
			'action' => 'update_idea_category',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Category updated successfully.', 'ai-blog-generator' ),
			'category_name' => $category_name,
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
	 * Bulk delete ideas.
	 */
	public function bulk_delete_ideas() {
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

		$deleted_count = 0;
		$failed_ids = [];
		$protected_ids = [];

		foreach ( $idea_ids as $idea_id ) {
			// Check if idea has been used
			$idea = $this->idea_model->get( $idea_id );
			if ( $idea && $idea->status === 'generated' ) {
				$protected_ids[] = $idea_id;
				continue;
			}
			
			$deleted = $this->idea_model->delete( $idea_id );
			
			if ( $deleted ) {
				$deleted_count++;
			} else {
				$failed_ids[] = $idea_id;
			}
		}

		// Log the action
		Logger::info( 'Bulk ideas deleted', [
			'total_selected' => count( $idea_ids ),
			'deleted_count' => $deleted_count,
			'failed_ids' => $failed_ids,
			'protected_ids' => $protected_ids,
			'action' => 'bulk_delete_ideas',
			'user_id' => get_current_user_id(),
		] );

		if ( $deleted_count === 0 && empty( $protected_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete any ideas.', 'ai-blog-generator' ) ] );
		}

		$message = '';
		if ( $deleted_count > 0 ) {
			$message = sprintf(
				/* translators: %d: number of deleted ideas */
				_n( '%d idea deleted successfully.', '%d ideas deleted successfully.', $deleted_count, 'ai-blog-generator' ),
				$deleted_count
			);
		}

		if ( ! empty( $protected_ids ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of protected ideas */
				_n( '%d idea was protected from deletion.', '%d ideas were protected from deletion.', count( $protected_ids ), 'ai-blog-generator' ),
				count( $protected_ids )
			);
		}

		wp_send_json_success( [
			'message' => trim( $message ),
			'deleted_count' => $deleted_count,
			'statistics' => $this->idea_model->get_statistics(),
		] );
	}

	/**
	 * Get idea details.
	 */
	public function get_idea_details() {
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

		// Add category information
		if ( $idea->category_id ) {
			$category = get_category( $idea->category_id );
			if ( $category ) {
				$idea->category_name = $category->name;
				$idea->category_slug = $category->slug;
			}
		}

		// Add context information if available
		if ( ! empty( $idea->context_data ) ) {
			$idea->context_data = maybe_unserialize( $idea->context_data );
		}

		wp_send_json_success( [
			'idea' => $idea,
		] );
	}

	/**
	 * Get ideas by status.
	 */
	public function get_ideas_by_status() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
		$valid_statuses = [ 'pending', 'approved', 'denied', 'generated' ];
		
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid status.', 'ai-blog-generator' ) ] );
		}

		// Get pagination parameters
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		
		// Get ideas
		$ideas = $this->idea_model->get_by_status( $status, $per_page, ( $page - 1 ) * $per_page );
		
		// Get total count
		$total = $this->idea_model->count_by_status( $status );

		// Add category names
		foreach ( $ideas as $idea ) {
			if ( $idea->category_id ) {
				$category = get_category( $idea->category_id );
				if ( $category ) {
					$idea->category_name = $category->name;
				}
			}
		}

		wp_send_json_success( [
			'ideas' => $ideas,
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );
	}

	/**
	 * Save selected ideas after user confirmation.
	 */
	public function save_selected_ideas() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get selected ideas
		$selected_ideas = isset( $_POST['selected_ideas'] ) && is_array( $_POST['selected_ideas'] ) 
			? $_POST['selected_ideas'] 
			: [];

		if ( empty( $selected_ideas ) ) {
			wp_send_json_error( [ 'message' => __( 'No ideas selected for saving.', 'ai-blog-generator' ) ] );
		}

		Logger::info( 'save_selected_ideas_start', 'Saving user-selected ideas', [
			'ideas_count' => count( $selected_ideas ),
			'user_id' => get_current_user_id(),
		] );

		$saved_count = 0;
		$failed_count = 0;
		$saved_ideas = [];

		foreach ( $selected_ideas as $index => $idea_data ) {
			Logger::debug( 'processing_selected_idea', 'Processing individual selected idea', [
				'index' => $index,
				'idea_data_keys' => array_keys( $idea_data ),
				'has_title' => !empty( $idea_data['title'] ),
				'has_description' => !empty( $idea_data['description'] )
			] );

			// Validate idea data
			if ( empty( $idea_data['title'] ) || empty( $idea_data['description'] ) ) {
				Logger::warning( 'idea_validation_failed', 'Skipping idea with missing required fields', [
					'index' => $index,
					'has_title' => !empty( $idea_data['title'] ),
					'has_description' => !empty( $idea_data['description'] ),
					'idea_data' => $idea_data
				] );
				$failed_count++;
				continue;
			}

			// Sanitize input (removed keywords field as it doesn't exist in database)
			$sanitized_idea = [
				'title' => sanitize_text_field( $idea_data['title'] ),
				'description' => sanitize_textarea_field( $idea_data['description'] ),
				'category_id' => isset( $idea_data['category_id'] ) ? absint( $idea_data['category_id'] ) : null,
				'persona_id' => isset( $idea_data['persona_id'] ) ? absint( $idea_data['persona_id'] ) : null,
				'status' => 'pending',
			];

			Logger::debug( 'idea_sanitized', 'Idea data sanitized', [
				'index' => $index,
				'original_title' => $idea_data['title'],
				'sanitized_title' => $sanitized_idea['title'],
				'sanitized_data' => $sanitized_idea
			] );

			// Check for duplicate title
			if ( $this->idea_model->is_duplicate_title( $sanitized_idea['title'] ) ) {
				Logger::warning( 'duplicate_idea_title_skip', 'Skipping duplicate title during save', [
					'index' => $index,
					'title' => $sanitized_idea['title']
				] );
				$failed_count++;
				continue;
			}

			Logger::debug( 'saving_idea_to_database', 'Attempting to save idea to database', [
				'index' => $index,
				'title' => $sanitized_idea['title'],
				'sanitized_data' => $sanitized_idea
			] );

			// Save idea
			$idea_id = $this->idea_model->create( $sanitized_idea );

			Logger::debug( 'idea_create_result', 'Create method result', [
				'index' => $index,
				'idea_id' => $idea_id,
				'success' => !empty( $idea_id ),
				'title' => $sanitized_idea['title']
			] );

			if ( $idea_id ) {
				$saved_count++;
				$saved_ideas[] = array_merge( $sanitized_idea, [ 'id' => $idea_id ] );
				Logger::debug( 'idea_saved', 'Individual idea saved successfully', [
					'idea_id' => $idea_id,
					'title' => $sanitized_idea['title'],
					'index' => $index
				] );
			} else {
				$failed_count++;
				Logger::error( 'idea_save_failed', 'Failed to save individual idea', [
					'index' => $index,
					'title' => $sanitized_idea['title'],
					'sanitized_data' => $sanitized_idea,
					'model_error' => method_exists( $this->idea_model, 'get_last_error' ) ? $this->idea_model->get_last_error() : 'no_error_method'
				] );
			}
		}

		// Get updated statistics
		$statistics = $this->idea_model->get_statistics();

		Logger::info( 'save_selected_ideas_complete', 'Completed saving selected ideas', [
			'saved_count' => $saved_count,
			'failed_count' => $failed_count,
			'total_selected' => count( $selected_ideas ),
		] );

		if ( $saved_count === 0 ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save any ideas. Please try again.', 'ai-blog-generator' ) ] );
		}

		$message = sprintf(
			/* translators: %1$d: number of saved ideas, %2$d: number of failed ideas */
			_n( '%1$d idea saved successfully.', '%1$d ideas saved successfully.', $saved_count, 'ai-blog-generator' ),
			$saved_count
		);

		if ( $failed_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of failed ideas */
				_n( '%d idea failed to save.', '%d ideas failed to save.', $failed_count, 'ai-blog-generator' ),
				$failed_count
			);
		}

		wp_send_json_success( [
			'message' => $message,
			'saved_count' => $saved_count,
			'failed_count' => $failed_count,
			'saved_ideas' => $saved_ideas,
			'statistics' => $statistics,
		] );
	}

	/**
	 * Compile contexts for idea generation.
	 *
	 * @param int $context_id Optional specific context ID, or 0 for all active contexts.
	 * @return array Compiled contexts.
	 */
	private function compile_contexts( $context_id = 0 ) {
		$contexts = [];

		if ( $context_id > 0 ) {
			// Get specific context
			$context = $this->context_model->find( $context_id );
			if ( $context && $context->active ) {
				$contexts[ $context->type ] = $context->content;
			}
		} else {
			// Get all active contexts
			$active_contexts = $this->context_model->find_all( [ 'active' => 1 ], 'type ASC' );
			foreach ( $active_contexts as $context ) {
				$contexts[ $context->type ] = $context->content;
			}
		}

		Logger::debug( 'contexts_compiled', 'Contexts compiled for idea generation', [
			'context_id' => $context_id,
			'contexts_found' => array_keys( $contexts ),
			'total_length' => array_sum( array_map( 'strlen', $contexts ) )
		] );

		return $contexts;
	}

	/**
	 * Get or create category by name.
	 *
	 * @param string $category_name Category name.
	 * @return int Category ID.
	 */
	private function get_or_create_category( $category_name ) {
		if ( empty( $category_name ) ) {
			return get_option( 'default_category', 1 );
		}

		// Check if category exists
		$category = get_term_by( 'name', $category_name, 'category' );
		
		if ( $category ) {
			return $category->term_id;
		}

		// Create new category
		$result = wp_insert_term( $category_name, 'category' );
		
		if ( is_wp_error( $result ) ) {
			Logger::error( 'category_creation_failed', 'Failed to create category', [
				'category_name' => $category_name,
				'error' => $result->get_error_message()
			] );
			return get_option( 'default_category', 1 );
		}

		Logger::info( 'category_created', 'New category created', [
			'category_name' => $category_name,
			'category_id' => $result['term_id']
		] );

		return $result['term_id'];
	}
} 

 