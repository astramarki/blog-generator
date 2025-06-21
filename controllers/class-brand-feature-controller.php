<?php
/**
 * Brand Feature Controller
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Brand_Feature_Model;
use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand Feature Controller Class
 *
 * Handles brand feature operations and AJAX requests.
 *
 * @since 1.8.0
 */
class Brand_Feature_Controller {

	/**
	 * Brand Feature model instance.
	 *
	 * @var Brand_Feature_Model
	 */
	private $brand_feature_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->brand_feature_model = new Brand_Feature_Model();
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Brand feature operations
		add_action( 'wp_ajax_ai_blog_get_brand_features', [ $this, 'ajax_get_brand_features' ] );
		add_action( 'wp_ajax_ai_blog_get_brand_feature', [ $this, 'ajax_get_brand_feature' ] );
		add_action( 'wp_ajax_ai_blog_create_brand_feature', [ $this, 'ajax_create_brand_feature' ] );
		add_action( 'wp_ajax_ai_blog_update_brand_feature', [ $this, 'ajax_update_brand_feature' ] );
		add_action( 'wp_ajax_ai_blog_delete_brand_feature', [ $this, 'ajax_delete_brand_feature' ] );
		add_action( 'wp_ajax_ai_blog_toggle_brand_feature', [ $this, 'ajax_toggle_brand_feature' ] );
		add_action( 'wp_ajax_ai_blog_search_brand_features', [ $this, 'ajax_search_brand_features' ] );
	}

	/**
	 * Get all brand features via AJAX.
	 */
	public function ajax_get_brand_features() {
		$this->verify_ajax_security();

		try {
			$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
			$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
			$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
			$category = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';

			// Get features based on search or category
			if ( ! empty( $search ) ) {
				$features = $this->brand_feature_model->search( $search );
			} elseif ( ! empty( $category ) ) {
				$features = $this->brand_feature_model->get_by_category( $category );
			} else {
				$features = $this->brand_feature_model->get_all();
			}

			// Calculate pagination
			$total = count( $features );
			$total_pages = ceil( $total / $per_page );
			$offset = ( $page - 1 ) * $per_page;
			
			// Slice array for pagination
			$features = array_slice( $features, $offset, $per_page );

			wp_send_json_success( [
				'features' => $features,
				'pagination' => [
					'total' => $total,
					'per_page' => $per_page,
					'current_page' => $page,
					'total_pages' => $total_pages,
				],
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_brand_features', 'Error getting brand features', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( __( 'Failed to get brand features.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Get single brand feature via AJAX.
	 */
	public function ajax_get_brand_feature() {
		$this->verify_ajax_security();

		$feature_id = isset( $_GET['feature_id'] ) ? absint( $_GET['feature_id'] ) : 0;

		if ( ! $feature_id ) {
			wp_send_json_error( __( 'Invalid feature ID.', 'ai-blog-generator' ) );
		}

		try {
			$feature = $this->brand_feature_model->get_by_id( $feature_id );

			if ( ! $feature ) {
				wp_send_json_error( __( 'Brand feature not found.', 'ai-blog-generator' ) );
			}

			wp_send_json_success( [ 'feature' => $feature ] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_get_brand_feature', 'Error getting brand feature', [ 
				'feature_id' => $feature_id,
				'error' => $e->getMessage() 
			] );
			wp_send_json_error( __( 'Failed to get brand feature.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Create brand feature via AJAX.
	 */
	public function ajax_create_brand_feature() {
		$this->verify_ajax_security();

		$data = [
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : 'informational_page',
			'url' => isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '',
			'active' => isset( $_POST['active'] ) ? absint( $_POST['active'] ) : 1,
		];

		// Validate data
		$validation = $this->brand_feature_model->validate( $data );
		if ( $validation !== true ) {
			wp_send_json_error( implode( ' ', $validation ) );
		}

		try {
			$feature_id = $this->brand_feature_model->create( $data );

			if ( ! $feature_id ) {
				wp_send_json_error( __( 'Failed to create brand feature.', 'ai-blog-generator' ) );
			}

			$feature = $this->brand_feature_model->get_by_id( $feature_id );

			wp_send_json_success( [
				'message' => __( 'Brand feature created successfully.', 'ai-blog-generator' ),
				'feature' => $feature,
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_create_brand_feature', 'Error creating brand feature', [ 
				'data' => $data,
				'error' => $e->getMessage() 
			] );
			wp_send_json_error( __( 'Failed to create brand feature.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Update brand feature via AJAX.
	 */
	public function ajax_update_brand_feature() {
		$this->verify_ajax_security();

		$feature_id = isset( $_POST['feature_id'] ) ? absint( $_POST['feature_id'] ) : 0;

		if ( ! $feature_id ) {
			wp_send_json_error( __( 'Invalid feature ID.', 'ai-blog-generator' ) );
		}

		$data = [
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : 'informational_page',
			'url' => isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '',
			'active' => isset( $_POST['active'] ) ? absint( $_POST['active'] ) : 1,
		];

		// Validate data
		$validation = $this->brand_feature_model->validate( $data );
		if ( $validation !== true ) {
			wp_send_json_error( implode( ' ', $validation ) );
		}

		try {
			$success = $this->brand_feature_model->update( $data, [ 'id' => $feature_id ] );

			if ( ! $success ) {
				wp_send_json_error( __( 'Failed to update brand feature.', 'ai-blog-generator' ) );
			}

			$feature = $this->brand_feature_model->get_by_id( $feature_id );

			wp_send_json_success( [
				'message' => __( 'Brand feature updated successfully.', 'ai-blog-generator' ),
				'feature' => $feature,
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_update_brand_feature', 'Error updating brand feature', [ 
				'feature_id' => $feature_id,
				'data' => $data,
				'error' => $e->getMessage() 
			] );
			wp_send_json_error( __( 'Failed to update brand feature.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Delete brand feature via AJAX.
	 */
	public function ajax_delete_brand_feature() {
		$this->verify_ajax_security();

		$feature_id = isset( $_POST['feature_id'] ) ? absint( $_POST['feature_id'] ) : 0;

		if ( ! $feature_id ) {
			wp_send_json_error( __( 'Invalid feature ID.', 'ai-blog-generator' ) );
		}

		try {
			$success = $this->brand_feature_model->delete( [ 'id' => $feature_id ] );

			if ( ! $success ) {
				wp_send_json_error( __( 'Failed to delete brand feature.', 'ai-blog-generator' ) );
			}

			wp_send_json_success( [
				'message' => __( 'Brand feature deleted successfully.', 'ai-blog-generator' ),
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_delete_brand_feature', 'Error deleting brand feature', [ 
				'feature_id' => $feature_id,
				'error' => $e->getMessage() 
			] );
			wp_send_json_error( __( 'Failed to delete brand feature.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Toggle brand feature active status via AJAX.
	 */
	public function ajax_toggle_brand_feature() {
		$this->verify_ajax_security();

		$feature_id = isset( $_POST['feature_id'] ) ? absint( $_POST['feature_id'] ) : 0;

		if ( ! $feature_id ) {
			wp_send_json_error( __( 'Invalid feature ID.', 'ai-blog-generator' ) );
		}

		try {
			$success = $this->brand_feature_model->toggle_active( $feature_id );

			if ( ! $success ) {
				wp_send_json_error( __( 'Failed to toggle brand feature status.', 'ai-blog-generator' ) );
			}

			$feature = $this->brand_feature_model->get_by_id( $feature_id );

			wp_send_json_success( [
				'message' => $feature['active'] ? 
					__( 'Brand feature activated.', 'ai-blog-generator' ) : 
					__( 'Brand feature deactivated.', 'ai-blog-generator' ),
				'feature' => $feature,
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_toggle_brand_feature', 'Error toggling brand feature', [ 
				'feature_id' => $feature_id,
				'error' => $e->getMessage() 
			] );
			wp_send_json_error( __( 'Failed to toggle brand feature status.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Search brand features via AJAX.
	 */
	public function ajax_search_brand_features() {
		$this->verify_ajax_security();

		$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

		try {
			$features = $this->brand_feature_model->search( $search );

			wp_send_json_success( [
				'features' => $features,
				'total' => count( $features ),
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'ajax_search_brand_features', 'Error searching brand features', [ 
				'search' => $search,
				'error' => $e->getMessage() 
			] );
			wp_send_json_error( __( 'Failed to search brand features.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Verify AJAX request security.
	 */
	private function verify_ajax_security() {
		// Check nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'ai-blog-generator' ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-blog-generator' ) );
		}
	}

	/**
	 * Get brand features for content generation.
	 *
	 * @return array Formatted brand features.
	 */
	public function get_features_for_generation() {
		return $this->brand_feature_model->get_for_content_generation();
	}

	/**
	 * Get category options.
	 *
	 * @return array Category options for select dropdown.
	 */
	public static function get_category_options() {
		return [
			'informational_page' => __( 'Informational Page', 'ai-blog-generator' ),
			'document' => __( 'Document', 'ai-blog-generator' ),
			'image' => __( 'Image', 'ai-blog-generator' ),
			'video' => __( 'Video', 'ai-blog-generator' ),
		];
	}
} 