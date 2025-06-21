<?php
/**
 * Persona Controller
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Ajax_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persona Controller Class
 *
 * Handles persona-related AJAX requests and operations.
 *
 * @since 1.5.0
 */
class Persona_Controller {

	use Ajax_Handler;

	/**
	 * Persona model instance.
	 *
	 * @var Persona_Model
	 */
	private $persona_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->persona_model = new Persona_Model();
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Get persona
		add_action( 'wp_ajax_ai_blog_get_persona', [ $this, 'get_persona' ] );
		
		// Create persona
		add_action( 'wp_ajax_ai_blog_create_persona', [ $this, 'create_persona' ] );
		
		// Update persona
		add_action( 'wp_ajax_ai_blog_update_persona', [ $this, 'update_persona' ] );
		
		// Delete persona
		add_action( 'wp_ajax_ai_blog_delete_persona', [ $this, 'delete_persona' ] );
		
		// Toggle persona status
		add_action( 'wp_ajax_ai_blog_toggle_persona', [ $this, 'toggle_persona' ] );
		
		// Get all personas
		add_action( 'wp_ajax_ai_blog_get_personas', [ $this, 'get_personas' ] );
	}

	/**
	 * Get single persona.
	 */
	public function get_persona() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Validate persona ID
			$persona_id = isset( $_POST['persona_id'] ) ? absint( $_POST['persona_id'] ) : 0;
			
			if ( ! $persona_id ) {
				$this->send_ajax_error( __( 'Invalid persona ID.', 'ai-blog-generator' ) );
				return;
			}

			// Get persona
			$persona = $this->persona_model->get( $persona_id );
			
			if ( ! $persona ) {
				$this->send_ajax_error( __( 'Persona not found.', 'ai-blog-generator' ) );
				return;
			}

			// Format persona data
			$persona_data = $this->persona_model->format( $persona );

			Logger::info( 'persona_retrieved', 'Persona data retrieved', [
				'persona_id' => $persona_id,
			] );

			$this->send_ajax_success( [
				'persona' => $persona_data,
			], __( 'Persona loaded successfully.', 'ai-blog-generator' ), 'get_persona' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_persona' );
		}

		$this->end_timer( $start_time, 'get_persona' );
	}

	/**
	 * Create new persona.
	 */
	public function create_persona() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Sanitize input data
			$persona_data = $this->sanitize_ajax_data( $_POST, [
				'name' => 'sanitize_text_field',
				'bio' => 'sanitize_textarea_field',
				'expertise' => 'sanitize_textarea_field',
				'writing_style' => 'sanitize_textarea_field',
				'tone' => function( $value ) {
					// Handle multiple tones separated by commas
					if ( strpos( $value, ',' ) !== false ) {
						$tones = explode( ',', $value );
						$sanitized_tones = array_map( 'sanitize_key', $tones );
						return implode( ',', array_filter( $sanitized_tones ) );
					}
					return sanitize_key( $value );
				},
				'layout_style' => 'sanitize_textarea_field',
				'layout_rules' => 'sanitize_text_field',
				'wordpress_user_id' => function( $value ) { return $value ? absint( $value ) : null; },
				'active' => function( $value ) { return (int) (bool) $value; },
			] );

			// Validate required fields
			if ( empty( $persona_data['name'] ) ) {
				$this->send_ajax_error( __( 'Persona name is required.', 'ai-blog-generator' ) );
				return;
			}

			if ( empty( $persona_data['bio'] ) ) {
				$this->send_ajax_error( __( 'Persona biography is required.', 'ai-blog-generator' ) );
				return;
			}

			// Create persona
			$persona_id = $this->persona_model->create( $persona_data );
			
			if ( ! $persona_id ) {
				$this->send_ajax_error( __( 'Failed to create persona.', 'ai-blog-generator' ) );
				return;
			}

			Logger::info( 'persona_created', 'New persona created', [
				'persona_id' => $persona_id,
				'name' => $persona_data['name'],
			] );

			$this->send_ajax_success( [
				'persona_id' => $persona_id,
			], __( 'Persona created successfully.', 'ai-blog-generator' ), 'create_persona' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'create_persona' );
		}

		$this->end_timer( $start_time, 'create_persona' );
	}

	/**
	 * Update existing persona.
	 */
	public function update_persona() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Validate persona ID
			$persona_id = isset( $_POST['persona_id'] ) ? absint( $_POST['persona_id'] ) : 0;
			
			if ( ! $persona_id ) {
				$this->send_ajax_error( __( 'Invalid persona ID.', 'ai-blog-generator' ) );
				return;
			}

			// Check if persona exists
			$existing_persona = $this->persona_model->get( $persona_id );
			if ( ! $existing_persona ) {
				$this->send_ajax_error( __( 'Persona not found.', 'ai-blog-generator' ) );
				return;
			}

			// Sanitize input data
			$persona_data = $this->sanitize_ajax_data( $_POST, [
				'name' => 'sanitize_text_field',
				'bio' => 'sanitize_textarea_field',
				'expertise' => 'sanitize_textarea_field',
				'writing_style' => 'sanitize_textarea_field',
				'tone' => function( $value ) {
					// Handle multiple tones separated by commas
					if ( strpos( $value, ',' ) !== false ) {
						$tones = explode( ',', $value );
						$sanitized_tones = array_map( 'sanitize_key', $tones );
						return implode( ',', array_filter( $sanitized_tones ) );
					}
					return sanitize_key( $value );
				},
				'layout_style' => 'sanitize_textarea_field',
				'layout_rules' => 'sanitize_text_field',
				'wordpress_user_id' => function( $value ) { return $value ? absint( $value ) : null; },
				'active' => function( $value ) { return (int) (bool) $value; },
			] );

			// Update persona
			$result = $this->persona_model->update( $persona_id, $persona_data );
			
			if ( ! $result ) {
				$this->send_ajax_error( __( 'Failed to update persona.', 'ai-blog-generator' ) );
				return;
			}

			Logger::info( 'persona_updated', 'Persona updated', [
				'persona_id' => $persona_id,
				'name' => $persona_data['name'] ?? $existing_persona->name,
			] );

			$this->send_ajax_success( [
				'persona_id' => $persona_id,
			], __( 'Persona updated successfully.', 'ai-blog-generator' ), 'update_persona' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'update_persona' );
		}

		$this->end_timer( $start_time, 'update_persona' );
	}

	/**
	 * Delete persona.
	 */
	public function delete_persona() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Validate persona ID
			$persona_id = isset( $_POST['persona_id'] ) ? absint( $_POST['persona_id'] ) : 0;
			
			if ( ! $persona_id ) {
				$this->send_ajax_error( __( 'Invalid persona ID.', 'ai-blog-generator' ) );
				return;
			}

			// Check if persona exists
			$persona = $this->persona_model->get( $persona_id );
			if ( ! $persona ) {
				$this->send_ajax_error( __( 'Persona not found.', 'ai-blog-generator' ) );
				return;
			}

			// Check if persona is used in any posts
			$stats = $this->persona_model->get_statistics();
			$persona_post_count = 0;
			
			foreach ( $stats['post_counts'] as $post_data ) {
				if ( $post_data['id'] == $persona_id ) {
					$persona_post_count = $post_data['post_count'];
					break;
				}
			}

			if ( $persona_post_count > 0 ) {
				$this->send_ajax_error( 
					sprintf( 
						__( 'Cannot delete persona. It has been used in %d blog posts.', 'ai-blog-generator' ), 
						$persona_post_count 
					) 
				);
				return;
			}

			// Delete persona
			$result = $this->persona_model->delete( $persona_id );
			
			if ( ! $result ) {
				$this->send_ajax_error( __( 'Failed to delete persona.', 'ai-blog-generator' ) );
				return;
			}

			Logger::info( 'persona_deleted', 'Persona deleted', [
				'persona_id' => $persona_id,
				'name' => $persona->name,
			] );

			$this->send_ajax_success( [
				'persona_id' => $persona_id,
			], __( 'Persona deleted successfully.', 'ai-blog-generator' ), 'delete_persona' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'delete_persona' );
		}

		$this->end_timer( $start_time, 'delete_persona' );
	}

	/**
	 * Toggle persona active status.
	 */
	public function toggle_persona() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Validate persona ID
			$persona_id = isset( $_POST['persona_id'] ) ? absint( $_POST['persona_id'] ) : 0;
			
			if ( ! $persona_id ) {
				$this->send_ajax_error( __( 'Invalid persona ID.', 'ai-blog-generator' ) );
				return;
			}

			// Toggle status
			$result = $this->persona_model->toggle_active( $persona_id );
			
			if ( ! $result ) {
				$this->send_ajax_error( __( 'Failed to update persona status.', 'ai-blog-generator' ) );
				return;
			}

			// Get updated persona
			$persona = $this->persona_model->get( $persona_id );
			$new_status = $persona->active ? 'active' : 'inactive';

			Logger::info( 'persona_status_toggled', 'Persona status toggled', [
				'persona_id' => $persona_id,
				'new_status' => $new_status,
			] );

			$this->send_ajax_success( [
				'persona_id' => $persona_id,
				'active' => (bool) $persona->active,
				'status_text' => $persona->active ? __( 'Active', 'ai-blog-generator' ) : __( 'Inactive', 'ai-blog-generator' ),
			], 
			sprintf( 
				__( 'Persona %s successfully.', 'ai-blog-generator' ), 
				$persona->active ? __( 'activated', 'ai-blog-generator' ) : __( 'deactivated', 'ai-blog-generator' )
			), 
			'toggle_persona' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'toggle_persona' );
		}

		$this->end_timer( $start_time, 'toggle_persona' );
	}

	/**
	 * Get all personas.
	 */
	public function get_personas() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Get filter parameters
			$active_only = isset( $_POST['active_only'] ) ? (bool) $_POST['active_only'] : false;
			
			// Get personas
			if ( $active_only ) {
				$personas = $this->persona_model->get_active_personas();
			} else {
				$personas = $this->persona_model->get_all( [], 'name ASC' );
			}

			// Format personas
			$formatted_personas = [];
			foreach ( $personas as $persona ) {
				$formatted_personas[] = $this->persona_model->format( $persona );
			}

			Logger::info( 'personas_retrieved', 'Personas list retrieved', [
				'count' => count( $formatted_personas ),
				'active_only' => $active_only,
			] );

			$this->send_ajax_success( [
				'personas' => $formatted_personas,
				'total' => count( $formatted_personas ),
			], __( 'Personas loaded successfully.', 'ai-blog-generator' ), 'get_personas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_personas' );
		}

		$this->end_timer( $start_time, 'get_personas' );
	}
} 