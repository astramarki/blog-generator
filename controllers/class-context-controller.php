<?php
/**
 * Context Controller Class
 *
 * Handles context management operations.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Ajax_Handler;
use AI_Blog_Generator\Models\Database_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Controller Class
 */
class Context_Controller {

	use Ajax_Handler;

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
		$this->context_model = new Context_Model();
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Context operations
		add_action( 'wp_ajax_ai_blog_create_context', [ $this, 'create_context' ] );
		add_action( 'wp_ajax_ai_blog_update_context', [ $this, 'update_context' ] );
		add_action( 'wp_ajax_ai_blog_delete_context', [ $this, 'delete_context' ] );
		add_action( 'wp_ajax_ai_blog_toggle_context', [ $this, 'toggle_context' ] );
		add_action( 'wp_ajax_ai_blog_get_context', [ $this, 'get_context' ] );
		add_action( 'wp_ajax_ai_blog_get_contexts', [ $this, 'get_contexts' ] );
		add_action( 'wp_ajax_ai_blog_get_active_contexts', [ $this, 'get_active_contexts' ] );
		
		// Bulk operations
		add_action( 'wp_ajax_ai_blog_bulk_activate_contexts', [ $this, 'bulk_activate_contexts' ] );
		add_action( 'wp_ajax_ai_blog_bulk_deactivate_contexts', [ $this, 'bulk_deactivate_contexts' ] );
		
		// Import/Export
		add_action( 'wp_ajax_ai_blog_export_contexts', [ $this, 'export_contexts' ] );
		add_action( 'wp_ajax_ai_blog_import_contexts', [ $this, 'import_contexts' ] );
		
		// Seed image operations
		add_action( 'wp_ajax_ai_blog_upload_seed_image', [ $this, 'upload_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_update_seed_image', [ $this, 'update_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_delete_seed_image', [ $this, 'delete_seed_image' ] );
		add_action( 'wp_ajax_ai_blog_get_seed_image', [ $this, 'get_seed_image' ] );
	}

	/**
	 * Create a new context.
	 */
	public function create_context() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$usage_flags = isset( $_POST['usage_flags'] ) ? sanitize_text_field( $_POST['usage_flags'] ) : 'content';
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( $_POST['content'] ) : '';
		$active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : true;
		$always_include_content = isset( $_POST['always_include_content'] ) ? (bool) $_POST['always_include_content'] : false;
		$always_include_images = isset( $_POST['always_include_images'] ) ? (bool) $_POST['always_include_images'] : false;
		$always_include_avada = isset( $_POST['always_include_avada'] ) ? (bool) $_POST['always_include_avada'] : false;
		$always_include_html = isset( $_POST['always_include_html'] ) ? (bool) $_POST['always_include_html'] : false;

		if ( empty( $name ) || empty( $type ) || empty( $content ) ) {
			wp_send_json_error( [ 'message' => __( 'Name, type, and content are required.', 'ai-blog-generator' ) ] );
		}

		// Validate type
		$valid_types = [ 'general', 'products', 'seo', 'keywords', 'image', 'layout' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid context type.', 'ai-blog-generator' ) ] );
		}

		// Validate usage flags
		$valid_usage = [ 'ideas', 'content', 'images' ];
		if ( ! in_array( $usage_flags, $valid_usage, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid usage category.', 'ai-blog-generator' ) ] );
		}



		// Create context
		$context_id = $this->context_model->create( [
			'name' => $name,
			'type' => $type,
			'usage_flags' => $usage_flags,
			'content' => $content,
			'active' => $active,
			'always_include_content' => $always_include_content,
			'always_include_images' => $always_include_images,
			'always_include_avada' => $always_include_avada,
			'always_include_html' => $always_include_html,
		] );

		if ( ! $context_id ) {
			Logger::error( 'Failed to create context', [
				'name' => $name,
				'type' => $type,
				'action' => 'create_context',
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to create context.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Context created', [
			'context_id' => $context_id,
			'name' => $name,
			'type' => $type,
			'action' => 'create_context',
			'user_id' => get_current_user_id(),
		] );

		// Get the created context
		$context = $this->context_model->get( $context_id );

		wp_send_json_success( [
			'message' => __( 'Context created successfully.', 'ai-blog-generator' ),
			'context' => $context,
		] );
	}

	/**
	 * Update an existing context.
	 */
	public function update_context() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return; // verify_ajax_security already sends the error response
			}

			// Validate required parameters
			$params = $this->validate_ajax_params( [ 'context_id' ] );
			if ( false === $params ) {
				return; // validate_ajax_params already sends the error response
			}

			// Sanitize input data
			$sanitized_data = $this->sanitize_ajax_data( $_POST, [
				'context_id' => 'absint',
				'name' => 'sanitize_text_field',
				'type' => 'sanitize_text_field',
				'usage_flags' => 'sanitize_text_field',
				'content' => 'sanitize_textarea_field',
				'always_include_content' => function( $value ) { return (int) (bool) $value; },
				'always_include_images' => function( $value ) { return (int) (bool) $value; },
				'always_include_avada' => function( $value ) { return (int) (bool) $value; },
				'always_include_html' => function( $value ) { return (int) (bool) $value; },
			] );

			$context_id = $sanitized_data['context_id'];
			if ( ! $context_id ) {
				$this->send_ajax_error( 
					__( 'Invalid context ID.', 'ai-blog-generator' ),
					[ 'provided_id' => $params['context_id'] ],
					'update_context',
					'invalid_id'
				);
				return;
			}

			$this->log_info( 'update_context_start', 'Starting context update process', [
				'context_id' => $context_id,
				'fields_to_update' => array_keys( array_filter( $sanitized_data ) ),
				'user_id' => get_current_user_id()
			] );

			// Get existing context
			$context = $this->context_model->get( $context_id );
			if ( ! $context ) {
				$this->send_ajax_error( 
					__( 'Context not found.', 'ai-blog-generator' ),
					[ 'context_id' => $context_id ],
					'update_context',
					'context_not_found'
				);
				return;
			}

			$this->log_debug( 'context_found', 'Context retrieved for update', [
				'context_id' => $context_id,
				'context_name' => $context->name,
				'current_type' => $context->type,
				'current_active' => $context->active
			] );

			// Build update data
			$update_data = [];
			
			if ( ! empty( $sanitized_data['name'] ) ) {
				$update_data['name'] = $sanitized_data['name'];
			}
			
			if ( ! empty( $sanitized_data['type'] ) ) {
				$valid_types = [ 'general', 'products', 'seo', 'keywords', 'image', 'layout' ];
				if ( ! in_array( $sanitized_data['type'], $valid_types, true ) ) {
					$this->send_ajax_error( 
						__( 'Invalid context type.', 'ai-blog-generator' ),
						[ 'provided_type' => $sanitized_data['type'], 'valid_types' => $valid_types ],
						'update_context',
						'invalid_type'
					);
					return;
				}
				$update_data['type'] = $sanitized_data['type'];
			}
			
			if ( ! empty( $sanitized_data['content'] ) ) {
				$update_data['content'] = $sanitized_data['content'];
			}
			
			if ( ! empty( $sanitized_data['usage_flags'] ) ) {
				$valid_usage = [ 'ideas', 'content', 'images' ];
				if ( ! in_array( $sanitized_data['usage_flags'], $valid_usage, true ) ) {
					$this->send_ajax_error( 
						__( 'Invalid usage category.', 'ai-blog-generator' ),
						[ 'provided_usage' => $sanitized_data['usage_flags'], 'valid_usage' => $valid_usage ],
						'update_context',
						'invalid_usage'
					);
					return;
				}
				$update_data['usage_flags'] = $sanitized_data['usage_flags'];
			}

			// Always include checkbox fields in update data since they're always sent from JS (as 0 or 1)
			$update_data['always_include_content'] = isset( $_POST['always_include_content'] ) ? (int) $_POST['always_include_content'] : 0;
			$update_data['always_include_images'] = isset( $_POST['always_include_images'] ) ? (int) $_POST['always_include_images'] : 0;
			$update_data['always_include_avada'] = isset( $_POST['always_include_avada'] ) ? (int) $_POST['always_include_avada'] : 0;
			$update_data['always_include_html'] = isset( $_POST['always_include_html'] ) ? (int) $_POST['always_include_html'] : 0;

			// Add updated timestamp
			$update_data['updated_at'] = current_time( 'mysql' );

			if ( empty( $update_data ) || count( $update_data ) === 1 ) { // Only timestamp
				$this->send_ajax_error( 
					__( 'No data to update.', 'ai-blog-generator' ),
					[ 'context_id' => $context_id ],
					'update_context',
					'no_update_data'
				);
				return;
			}

			$this->log_debug( 'update_data_prepared', 'Update data prepared for context', [
				'context_id' => $context_id,
				'update_fields' => array_keys( $update_data ),
				'data_size' => count( $update_data )
			] );

			// Update context
			$updated = $this->context_model->update( $context_id, $update_data );

			if ( ! $updated ) {
				$this->log_error( 'context_update_failed', 'Failed to update context in database', [
					'context_id' => $context_id,
					'update_data' => $update_data
				] );

				$this->send_ajax_error( 
					__( 'Failed to update context.', 'ai-blog-generator' ),
					[ 'context_id' => $context_id ],
					'update_context',
					'database_update_failed'
				);
				return;
			}

			$this->log_info( 'context_updated_success', 'Context updated successfully', [
				'context_id' => $context_id,
				'updated_fields' => array_keys( $update_data ),
				'updated_by' => get_current_user_id(),
				'old_name' => $context->name,
				'new_name' => $update_data['name'] ?? $context->name
			] );

			// Get updated context for response
			$updated_context = $this->context_model->get( $context_id );

			$this->send_ajax_success( [
				'context' => $updated_context,
				'context_id' => $context_id,
				'updated_fields' => array_keys( $update_data )
			], __( 'Context updated successfully.', 'ai-blog-generator' ), 'update_context' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'update_context', [
				'context_id' => $context_id ?? 'unknown',
				'request_data_keys' => array_keys( $_POST )
			] );
		}

		$this->end_timer( $start_time, 'update_context' );
	}

	/**
	 * Delete a context.
	 */
	public function delete_context() {
		// Start output buffering to prevent any warnings from contaminating JSON response
		ob_start();
		
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$context_id = isset( $_POST['context_id'] ) ? absint( $_POST['context_id'] ) : 0;
		if ( ! $context_id ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Invalid context ID.', 'ai-blog-generator' ) ] );
		}

		try {
			// Get context
			$context = $this->context_model->get( $context_id );
			if ( ! $context ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Context not found.', 'ai-blog-generator' ) ] );
			}

			// Delete context
			$deleted = $this->context_model->delete( $context_id );

			if ( ! $deleted ) {
				Logger::error( 'Failed to delete context', [
					'context_id' => $context_id,
					'action' => 'delete_context',
				] );
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Failed to delete context.', 'ai-blog-generator' ) ] );
			}

			// Log the action
			Logger::info( 'Context deleted', [
				'context_id' => $context_id,
				'name' => $context->name,
				'type' => $context->type,
				'action' => 'delete_context',
				'user_id' => get_current_user_id(),
			] );

			// Clean any output buffer content and send success
			ob_end_clean();
			wp_send_json_success( [
				'message' => __( 'Context deleted successfully.', 'ai-blog-generator' ),
			] );
			
		} catch ( Exception $e ) {
			ob_end_clean();
			Logger::error( 'Failed to delete context', [
				'context_id' => $context_id,
				'error' => $e->getMessage(),
				'action' => 'delete_context',
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to delete context.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * Toggle context active status.
	 */
	public function toggle_context() {
		// Start output buffering to prevent any warnings from contaminating JSON response
		ob_start();
		
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$context_id = isset( $_POST['context_id'] ) ? absint( $_POST['context_id'] ) : 0;
		if ( ! $context_id ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Invalid context ID.', 'ai-blog-generator' ) ] );
		}

		try {
			// Get context
			$context = $this->context_model->get( $context_id );
			if ( ! $context ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Context not found.', 'ai-blog-generator' ) ] );
			}

			// Toggle active status
			$new_status = ! $context->active;
			$updated = $this->context_model->update( $context_id, [
				'active' => $new_status,
				'updated_at' => current_time( 'mysql' ),
			] );

			if ( ! $updated ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Failed to toggle context status.', 'ai-blog-generator' ) ] );
			}

			// Log the action
			Logger::info( 'Context status toggled', [
				'context_id' => $context_id,
				'name' => $context->name,
				'new_status' => $new_status ? 'active' : 'inactive',
				'action' => 'toggle_context',
				'user_id' => get_current_user_id(),
			] );

			// Clean any output buffer content and send success
			ob_end_clean();
			wp_send_json_success( [
				'message' => $new_status 
					? __( 'Context activated successfully.', 'ai-blog-generator' )
					: __( 'Context deactivated successfully.', 'ai-blog-generator' ),
				'active' => $new_status,
			] );
			
		} catch ( Exception $e ) {
			ob_end_clean();
			Logger::error( 'Failed to toggle context', [
				'context_id' => $context_id,
				'error' => $e->getMessage(),
				'action' => 'toggle_context',
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to toggle context status.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * Get a single context.
	 */
	public function get_context() {
		$start_time = $this->start_timer();

		try {
			// Verify security using the trait method
			if ( ! $this->verify_ajax_security() ) {
				return; // verify_ajax_security already sends the error response
			}

			// Validate required parameters
			$params = $this->validate_ajax_params( [ 'context_id' ] );
			if ( false === $params ) {
				return; // validate_ajax_params already sends the error response
			}

			// Sanitize input data
			$context_id = absint( $params['context_id'] );
			if ( ! $context_id ) {
				$this->send_ajax_error( 
					__( 'Invalid context ID.', 'ai-blog-generator' ),
					[ 'provided_id' => $params['context_id'] ],
					'get_context',
					'invalid_id'
				);
				return;
			}

			$this->log_info( 'get_context_start', 'Starting get context process', [
				'context_id' => $context_id,
				'user_id' => get_current_user_id()
			] );

			// Get context
			$context = $this->context_model->get( $context_id );
			if ( ! $context ) {
				$this->send_ajax_error( 
					__( 'Context not found.', 'ai-blog-generator' ),
					[ 'context_id' => $context_id ],
					'get_context',
					'context_not_found'
				);
				return;
			}

			$this->log_info( 'context_retrieved', 'Context retrieved successfully', [
				'context_id' => $context_id,
				'context_name' => $context->name,
				'context_type' => $context->type
			] );

			$this->send_ajax_success( [
				'context' => $context,
			], __( 'Context loaded successfully.', 'ai-blog-generator' ), 'get_context' );
			
		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_context', [
				'context_id' => $context_id ?? 'unknown'
			] );
		}

		$this->end_timer( $start_time, 'get_context' );
	}

	/**
	 * Get all contexts.
	 */
	public function get_contexts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get filter parameters
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$active_only = isset( $_POST['active_only'] ) ? (bool) $_POST['active_only'] : false;
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		// Build conditions
		$conditions = [];
		if ( $type ) {
			$conditions['type'] = $type;
		}
		if ( $active_only ) {
			$conditions['active'] = 1;
		}

		// Get contexts
		$contexts = $this->context_model->get_all( $conditions, $per_page, ( $page - 1 ) * $per_page );
		$total = $this->context_model->count( $conditions );



		// Get type statistics
		$type_stats = $this->context_model->get_type_statistics();

		wp_send_json_success( [
			'contexts' => $contexts,
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => ceil( $total / $per_page ),
			'type_statistics' => $type_stats,
		] );
	}

	/**
	 * Get active contexts.
	 */
	public function get_active_contexts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get type filter if provided
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';

		// Get active contexts
		$contexts = $this->context_model->get_active( $type );

		// Organize by type
		$organized = [
			'general' => [],
			'products' => [],
			'seo' => [],
			'keywords' => [],
			'image' => [],
			'layout' => [],
		];

		foreach ( $contexts as $context ) {
			$organized[ $context->type ][] = $context;
		}

		wp_send_json_success( [
			'contexts' => $contexts,
			'organized' => $organized,
			'total' => count( $contexts ),
		] );
	}

	/**
	 * Bulk activate contexts.
	 */
	public function bulk_activate_contexts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$context_ids = isset( $_POST['context_ids'] ) && is_array( $_POST['context_ids'] ) 
			? array_map( 'absint', $_POST['context_ids'] ) 
			: [];
			
		if ( empty( $context_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No contexts selected.', 'ai-blog-generator' ) ] );
		}

		$activated_count = 0;
		foreach ( $context_ids as $context_id ) {
			$updated = $this->context_model->update( $context_id, [
				'active' => 1,
				'updated_at' => current_time( 'mysql' ),
			] );
			
			if ( $updated ) {
				$activated_count++;
			}
		}

		// Log the action
		Logger::info( 'Bulk contexts activated', [
			'total_selected' => count( $context_ids ),
			'activated_count' => $activated_count,
			'action' => 'bulk_activate_contexts',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of activated contexts */
				_n( '%d context activated successfully.', '%d contexts activated successfully.', $activated_count, 'ai-blog-generator' ),
				$activated_count
			),
			'activated_count' => $activated_count,
		] );
	}

	/**
	 * Bulk deactivate contexts.
	 */
	public function bulk_deactivate_contexts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Validate input
		$context_ids = isset( $_POST['context_ids'] ) && is_array( $_POST['context_ids'] ) 
			? array_map( 'absint', $_POST['context_ids'] ) 
			: [];
			
		if ( empty( $context_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No contexts selected.', 'ai-blog-generator' ) ] );
		}

		$deactivated_count = 0;
		foreach ( $context_ids as $context_id ) {
			$updated = $this->context_model->update( $context_id, [
				'active' => 0,
				'updated_at' => current_time( 'mysql' ),
			] );
			
			if ( $updated ) {
				$deactivated_count++;
			}
		}

		// Log the action
		Logger::info( 'Bulk contexts deactivated', [
			'total_selected' => count( $context_ids ),
			'deactivated_count' => $deactivated_count,
			'action' => 'bulk_deactivate_contexts',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of deactivated contexts */
				_n( '%d context deactivated successfully.', '%d contexts deactivated successfully.', $deactivated_count, 'ai-blog-generator' ),
				$deactivated_count
			),
			'deactivated_count' => $deactivated_count,
		] );
	}

	/**
	 * Export contexts.
	 */
	public function export_contexts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get all contexts
		$contexts = $this->context_model->get_all();

		// Prepare export data
		$export_data = [
			'version' => AI_BLOG_GENERATOR_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'contexts' => [],
		];

		foreach ( $contexts as $context ) {
			$export_data['contexts'][] = [
				'name' => $context->name,
				'type' => $context->type,
				'content' => $context->content,
				'active' => (bool) $context->active,
			];
		}

		// Generate filename
		$filename = 'ai-blog-contexts-' . date( 'Y-m-d-His' ) . '.json';

		// Log the action
		Logger::info( 'Contexts exported', [
			'context_count' => count( $contexts ),
			'filename' => $filename,
			'action' => 'export_contexts',
			'user_id' => get_current_user_id(),
		] );

		// Send JSON file
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import contexts.
	 */
	public function import_contexts() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Check if file was uploaded
		if ( empty( $_FILES['import_file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'ai-blog-generator' ) ] );
		}

		// Validate file type
		$file_type = $_FILES['import_file']['type'];
		if ( $file_type !== 'application/json' ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file type. Please upload a JSON file.', 'ai-blog-generator' ) ] );
		}

		// Read file content
		$file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$import_data = json_decode( $file_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( [ 'message' => __( 'Invalid JSON file.', 'ai-blog-generator' ) ] );
		}

		// Validate structure
		if ( ! isset( $import_data['contexts'] ) || ! is_array( $import_data['contexts'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file structure.', 'ai-blog-generator' ) ] );
		}

		// Import contexts
		$imported_count = 0;
		$failed_count = 0;
		$valid_types = [ 'general', 'products', 'seo', 'keywords', 'image', 'layout' ];

		foreach ( $import_data['contexts'] as $context_data ) {
			// Validate required fields
			if ( empty( $context_data['name'] ) || empty( $context_data['type'] ) || empty( $context_data['content'] ) ) {
				$failed_count++;
				continue;
			}

			// Validate type
			if ( ! in_array( $context_data['type'], $valid_types, true ) ) {
				$failed_count++;
				continue;
			}

			// Create context
			$context_id = $this->context_model->create( [
				'name' => sanitize_text_field( $context_data['name'] ),
				'type' => sanitize_text_field( $context_data['type'] ),
				'content' => sanitize_textarea_field( $context_data['content'] ),
				'active' => isset( $context_data['active'] ) ? (bool) $context_data['active'] : true,
			] );

			if ( $context_id ) {
				$imported_count++;
			} else {
				$failed_count++;
			}
		}

		// Log the action
		Logger::info( 'Contexts imported', [
			'total_contexts' => count( $import_data['contexts'] ),
			'imported_count' => $imported_count,
			'failed_count' => $failed_count,
			'action' => 'import_contexts',
			'user_id' => get_current_user_id(),
		] );

		$message = sprintf(
			/* translators: %d: number of imported contexts */
			_n( '%d context imported successfully.', '%d contexts imported successfully.', $imported_count, 'ai-blog-generator' ),
			$imported_count
		);

		if ( $failed_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of failed contexts */
				_n( '%d context failed to import.', '%d contexts failed to import.', $failed_count, 'ai-blog-generator' ),
				$failed_count
			);
		}

		wp_send_json_success( [
			'message' => $message,
			'imported_count' => $imported_count,
			'failed_count' => $failed_count,
		] );
	}

	// === SEED IMAGE METHODS ===

	/**
	 * Upload a new seed image.
	 */
	public function upload_seed_image() {
		// AGGRESSIVE output buffering to prevent any output corruption
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();
		
		// IMMEDIATE DEBUG: Log that the AJAX handler was called
		error_log( 'AI Blog Generator: upload_seed_image AJAX handler called at ' . current_time( 'mysql' ) );
		Logger::info( 'AJAX handler called', [ 'action' => 'upload_seed_image', 'timestamp' => current_time( 'mysql' ) ] );
		
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return; // verify_ajax_security already sends the error response
			}

			$this->log_info( 'seed_image_upload_start', 'Starting seed image upload process', [
				'post_data_keys' => array_keys( $_POST ),
				'files_data_keys' => array_keys( $_FILES ),
				'user_id' => get_current_user_id()
			] );

			// Validate required fields
			$params = $this->validate_ajax_params( [ 'product_name' ] );
			if ( false === $params ) {
				return; // validate_ajax_params already sends the error response
			}

			// Sanitize input data
			$sanitized_data = $this->sanitize_ajax_data( $_POST, [
				'product_name' => 'sanitize_text_field',
				'context_id' => 'absint'
			] );

			$product_name = $sanitized_data['product_name'];
			$context_id = $sanitized_data['context_id'] ?? null;

			$this->log_debug( 'seed_image_fields_validated', 'Seed image upload fields validated', [
				'product_name' => $product_name,
				'context_id' => $context_id,
				'has_file' => ! empty( $_FILES['image_file'] )
			] );

			// Check if file was uploaded
			if ( empty( $_FILES['image_file'] ) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK ) {
				$error_details = empty( $_FILES['image_file'] ) ? 'No file uploaded' : 'Upload error: ' . $_FILES['image_file']['error'];
				
				$this->log_error( 'seed_image_file_upload_issue', 'File upload issue detected', [
					'error_details' => $error_details,
					'files_data' => $_FILES,
					'upload_error_code' => $_FILES['image_file']['error'] ?? 'no_file'
				] );

				$this->send_ajax_error(
					__( 'Please select a valid PNG image file.', 'ai-blog-generator' ),
					[ 'upload_error' => $error_details ],
					'upload_seed_image',
					'file_upload_error'
				);
				return;
			}

			$file = $_FILES['image_file'];
			
			$this->log_debug( 'seed_image_file_details', 'Processing uploaded file', [
				'filename' => $file['name'],
				'mime_type' => $file['type'],
				'file_size' => $file['size'],
				'temp_file' => $file['tmp_name']
			] );

			// Validate file type (PNG only)
			$file_type = wp_check_filetype( $file['name'] );
			if ( $file_type['type'] !== 'image/png' ) {
				$this->log_error( 'seed_image_invalid_file_type', 'Invalid file type uploaded', [
					'detected_type' => $file_type['type'],
					'filename' => $file['name'],
					'expected_type' => 'image/png'
				] );

				$this->send_ajax_error(
					__( 'Only PNG files are allowed.', 'ai-blog-generator' ),
					[ 'detected_type' => $file_type['type'] ],
					'upload_seed_image',
					'invalid_file_type'
				);
				return;
			}

			// Validate file size (10MB max)
			$max_size = 10 * 1024 * 1024; // 10MB
			if ( $file['size'] > $max_size ) {
				$this->log_error( 'seed_image_file_too_large', 'Uploaded file exceeds size limit', [
					'file_size' => $file['size'],
					'max_size' => $max_size,
					'filename' => $file['name']
				] );

				$this->send_ajax_error(
					__( 'File size must be less than 10MB.', 'ai-blog-generator' ),
					[ 'file_size' => $file['size'], 'max_size' => $max_size ],
					'upload_seed_image',
					'file_too_large'
				);
				return;
			}

			// Check WordPress upload directory permissions
			$upload_dir = wp_upload_dir();
			if ( $upload_dir['error'] ) {
				$this->log_error( 'seed_image_upload_dir_error', 'WordPress upload directory error', [
					'upload_dir_error' => $upload_dir['error']
				] );

				$this->send_ajax_error(
					__( 'Upload directory is not writable.', 'ai-blog-generator' ),
					[ 'upload_error' => $upload_dir['error'] ],
					'upload_seed_image',
					'upload_dir_error'
				);
				return;
			}

			$this->log_debug( 'seed_image_upload_dir_ok', 'Upload directory accessible', [
				'upload_path' => $upload_dir['path'],
				'upload_url' => $upload_dir['url']
			] );

			// Upload file to WordPress media library
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			$upload_overrides = [
				'test_form' => false,
				'mimes' => [ 'png' => 'image/png' ]
			];

			$this->log_debug( 'seed_image_starting_wp_upload', 'Starting WordPress file upload', [
				'upload_overrides' => $upload_overrides
			] );

			// Handle file upload with error suppression to prevent JSON contamination
			$uploaded_file = wp_handle_upload( $file, $upload_overrides );

			$this->log_debug( 'seed_image_wp_upload_result', 'WordPress upload completed', [
				'upload_result' => $uploaded_file,
				'has_error' => isset( $uploaded_file['error'] )
			] );

			if ( isset( $uploaded_file['error'] ) ) {
				$this->log_error( 'seed_image_wp_upload_failed', 'WordPress file upload failed', [
					'upload_error' => $uploaded_file['error'],
					'filename' => $file['name']
				] );

				$this->send_ajax_error(
					__( 'File upload failed: ', 'ai-blog-generator' ) . $uploaded_file['error'],
					[ 'upload_error' => $uploaded_file['error'] ],
					'upload_seed_image',
					'wp_upload_failed'
				);
				return;
			}

			// Create attachment
			$attachment_data = [
				'post_title' => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
				'post_content' => '',
				'post_status' => 'inherit',
				'post_mime_type' => $uploaded_file['type']
			];

			$this->log_debug( 'seed_image_creating_attachment', 'Creating WordPress attachment', [
				'attachment_data' => $attachment_data,
				'file_path' => $uploaded_file['file']
			] );

			$attachment_id = wp_insert_attachment( $attachment_data, $uploaded_file['file'] );

			if ( is_wp_error( $attachment_id ) ) {
				$this->log_error( 'seed_image_attachment_creation_failed', 'Failed to create WordPress attachment', [
					'wp_error' => $attachment_id->get_error_message()
				] );

				$this->send_ajax_error(
					__( 'Failed to create media attachment.', 'ai-blog-generator' ),
					[ 'wp_error' => $attachment_id->get_error_message() ],
					'upload_seed_image',
					'attachment_creation_failed'
				);
				return;
			}

			// Generate attachment metadata
			$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded_file['file'] );
			wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

			$this->log_debug( 'seed_image_attachment_created', 'WordPress attachment created successfully', [
				'attachment_id' => $attachment_id,
				'metadata_generated' => ! empty( $attachment_metadata )
			] );

			// Save to seed images table using Database Manager
			$db_manager = Database_Manager::get_instance();
			
			// Check if table exists
			$table_name = AI_BLOG_GENERATOR_TABLE_SEED_IMAGES;
			global $wpdb;
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			
			if ( ! $table_exists ) {
				$this->log_error( 'seed_image_table_missing', 'Seed images table does not exist', [
					'table_name' => $table_name,
					'wpdb_prefix' => $wpdb->prefix
				] );

				$this->send_ajax_error(
					__( 'Database table not found. Please check plugin installation.', 'ai-blog-generator' ),
					[ 'table_name' => $table_name ],
					'upload_seed_image',
					'table_missing'
				);
				return;
			}

			$this->log_debug( 'seed_image_saving_to_db', 'Saving seed image data to database', [
				'table_name' => $table_name,
				'product_name' => $product_name,
				'context_id' => $context_id
			] );

			$seed_image_id = $db_manager->insert( 'seed_images', [
				'product_name' => $product_name,
				'image_url' => $uploaded_file['url'],
				'context_id' => $context_id,
				'created_at' => current_time( 'mysql' )
			] );

			if ( ! $seed_image_id ) {
				$this->log_error( 'seed_image_db_insert_failed', 'Failed to save seed image to database', [
					'table_name' => $table_name,
					'data' => [
						'product_name' => $product_name,
						'image_url' => $uploaded_file['url'],
						'context_id' => $context_id
					],
					'db_error' => $wpdb->last_error
				] );

				$this->send_ajax_error(
					__( 'Failed to save seed image data.', 'ai-blog-generator' ),
					[ 'db_error' => $wpdb->last_error ],
					'upload_seed_image',
					'db_insert_failed'
				);
				return;
			}

			$this->log_info( 'seed_image_upload_success', 'Seed image uploaded and saved successfully', [
				'seed_image_id' => $seed_image_id,
				'product_name' => $product_name,
				'attachment_id' => $attachment_id,
				'image_url' => $uploaded_file['url'],
				'context_id' => $context_id,
				'file_size' => $file['size'],
				'uploaded_by' => get_current_user_id()
			] );

			// Clean all output buffers and send clean JSON response
			while ( ob_get_level() ) {
				ob_end_clean();
			}

			$response_data = [
				'seed_image' => [
					'id' => $seed_image_id,
					'product_name' => $product_name,
					'image_url' => $uploaded_file['url'],
					'context_id' => $context_id,
					'attachment_id' => $attachment_id
				],
				'message' => __( 'Seed image uploaded successfully.', 'ai-blog-generator' )
			];

			// Send success response and exit immediately
			wp_send_json_success( $response_data );
			exit; // Force exit to prevent any further output

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'upload_seed_image', [
				'product_name' => $product_name ?? 'unknown',
				'context_id' => $context_id ?? 'unknown',
				'file_details' => $_FILES['image_file'] ?? 'no_file'
			] );
		}

		$this->end_timer( $start_time, 'upload_seed_image' );
	}

	/**
	 * Update an existing seed image.
	 */
	public function update_seed_image() {
		// Start output buffering to prevent any warnings from contaminating JSON response
		ob_start();
		
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		try {
			// Validate input
			$seed_id = isset( $_POST['seed_id'] ) ? absint( $_POST['seed_id'] ) : 0;
			$product_name = isset( $_POST['product_name'] ) ? sanitize_text_field( $_POST['product_name'] ) : '';
			$context_id = isset( $_POST['context_id'] ) ? absint( $_POST['context_id'] ) : null;

			if ( ! $seed_id ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Invalid seed image ID.', 'ai-blog-generator' ) ] );
			}

			if ( empty( $product_name ) ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Product name is required.', 'ai-blog-generator' ) ] );
			}

			// Update seed image record
			global $wpdb;
			$table_name = AI_BLOG_GENERATOR_TABLE_SEED_IMAGES;

			$result = $wpdb->update(
				$table_name,
				[
					'product_name' => $product_name,
					'context_id' => $context_id
				],
				[ 'id' => $seed_id ],
				[ '%s', '%d' ],
				[ '%d' ]
			);

			if ( $result === false ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Failed to update seed image.', 'ai-blog-generator' ) ] );
			}

			// Log the action
			Logger::info( 'Seed image updated', [
				'seed_image_id' => $seed_id,
				'product_name' => $product_name,
				'context_id' => $context_id,
				'action' => 'update_seed_image',
				'user_id' => get_current_user_id(),
			] );

			ob_end_clean();
			wp_send_json_success( [
				'message' => __( 'Seed image updated successfully.', 'ai-blog-generator' )
			] );

		} catch ( Exception $e ) {
			ob_end_clean();
			Logger::error( 'Failed to update seed image', [
				'seed_id' => $seed_id ?? 0,
				'error' => $e->getMessage(),
				'action' => 'update_seed_image',
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to update seed image.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * Delete a seed image.
	 */
	public function delete_seed_image() {
		// Start output buffering to prevent any warnings from contaminating JSON response
		ob_start();
		
		try {
			// Verify nonce
			if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			}

			// Check capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			}

			// Validate input
			$seed_id = isset( $_POST['seed_id'] ) ? absint( $_POST['seed_id'] ) : 0;
			
			if ( ! $seed_id ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Invalid seed ID.', 'ai-blog-generator' ) ] );
			}

			// Get database manager
			$db_manager = Database_Manager::get_instance();
			
			// Check if seed image exists
			$seed_image = $db_manager->get( 'seed_images', ['id' => $seed_id] );
			
			if ( ! $seed_image ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Seed image not found.', 'ai-blog-generator' ) ] );
			}

			// Delete from database
			$deleted = $db_manager->delete( 'seed_images', ['id' => $seed_id] );
			
			if ( ! $deleted ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Failed to delete seed image.', 'ai-blog-generator' ) ] );
			}

			// Also delete associated WordPress attachment if it exists
			if ( ! empty( $seed_image->image_url ) ) {
				$attachment_id = attachment_url_to_postid( $seed_image->image_url );
				if ( $attachment_id ) {
					wp_delete_attachment( $attachment_id, true );
				}
			}

			// Log the action
			Logger::info( 'Seed image deleted', [
				'seed_id' => $seed_id,
				'product_name' => $seed_image->product_name ?? 'unknown',
				'image_url' => $seed_image->image_url ?? 'unknown',
				'action' => 'delete_seed_image',
				'user_id' => get_current_user_id(),
			] );

			// Clean any output buffer content and send success
			ob_end_clean();
			wp_send_json_success( [
				'message' => __( 'Seed image deleted successfully.', 'ai-blog-generator' ),
			] );
			
		} catch ( \Exception $e ) {
			ob_end_clean();
			
			Logger::error( 'Failed to delete seed image', [
				'seed_id' => $seed_id ?? 'unknown',
				'error' => $e->getMessage(),
				'action' => 'delete_seed_image',
			] );
			
			wp_send_json_error( [ 'message' => __( 'Failed to delete seed image.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * Get a single seed image.
	 */
	public function get_seed_image() {
		// Start output buffering to prevent any warnings from contaminating JSON response
		ob_start();
		
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			ob_end_clean();
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		try {
			// Validate input
			$seed_id = isset( $_POST['seed_id'] ) ? absint( $_POST['seed_id'] ) : 0;

			if ( ! $seed_id ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Invalid seed image ID.', 'ai-blog-generator' ) ] );
			}

			// Get seed image data
			global $wpdb;
			$table_name = AI_BLOG_GENERATOR_TABLE_SEED_IMAGES;
			
			$seed_image = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$seed_id
			) );

			if ( ! $seed_image ) {
				ob_end_clean();
				wp_send_json_error( [ 'message' => __( 'Seed image not found.', 'ai-blog-generator' ) ] );
			}

			ob_end_clean();
			wp_send_json_success( $seed_image );

		} catch ( Exception $e ) {
			ob_end_clean();
			Logger::error( 'Failed to get seed image', [
				'seed_id' => $seed_id ?? 0,
				'error' => $e->getMessage(),
				'action' => 'get_seed_image',
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to retrieve seed image.', 'ai-blog-generator' ) ] );
		}
	}

} 
 