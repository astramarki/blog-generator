<?php
/**
 * Brand Feature Model
 *
 * @package AI_Blog_Generator
 * @subpackage Models
 */

namespace AI_Blog_Generator\Models;

use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand Feature Model Class
 *
 * Handles database operations for brand features.
 *
 * @since 1.8.0
 */
class Brand_Feature_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table_name = 'ai_blog_brand_features';

	/**
	 * Get all brand features.
	 *
	 * @param array  $where    Where conditions.
	 * @param string $order_by Order by clause.
	 * @param string $limit    Limit clause.
	 * @return array
	 */
	public function get_all( $where = [], $order_by = 'name ASC', $limit = '' ) {
		$db = Database_Manager::get_instance();
		return $db->get_all( $this->table_name, $where, $order_by, $limit );
	}

	/**
	 * Get a single brand feature.
	 *
	 * @param array  $where  Where conditions.
	 * @param string $fields Fields to select.
	 * @return array|null
	 */
	public function get( $where = [], $fields = '*' ) {
		$db = Database_Manager::get_instance();
		return $db->get( $this->table_name, $where, $fields );
	}

	/**
	 * Get brand feature by ID.
	 *
	 * @param int $id Brand feature ID.
	 * @return array|null
	 */
	public function get_by_id( $id ) {
		return $this->get( [ 'id' => $id ] );
	}

	/**
	 * Get active brand features.
	 *
	 * @param string $category Optional category filter.
	 * @return array
	 */
	public function get_active_features( $category = null ) {
		$where = [ 'active' => 1 ];
		
		if ( $category ) {
			$where['category'] = $category;
		}
		
		return $this->get_all( $where, 'name ASC' );
	}

	/**
	 * Get brand features by category.
	 *
	 * @param string $category Category name.
	 * @return array
	 */
	public function get_by_category( $category ) {
		return $this->get_all( [ 'category' => $category ], 'name ASC' );
	}

	/**
	 * Create a new brand feature.
	 *
	 * @param array $data Brand feature data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		// Validate required fields
		if ( empty( $data['name'] ) || empty( $data['url'] ) ) {
			Logger::error( 'brand_feature_create', 'Missing required fields', $data );
			return false;
		}

		// Sanitize data
		$data = $this->sanitize_data( $data );

		// Set defaults
		$data['active'] = isset( $data['active'] ) ? (int) $data['active'] : 1;
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$db = Database_Manager::get_instance();
		$result = $db->insert( $this->table_name, $data );

		if ( $result ) {
			Logger::info( 'brand_feature_created', 'Brand feature created successfully', [ 'id' => $result ] );
		}

		return $result;
	}

	/**
	 * Update a brand feature.
	 *
	 * @param array $data  Brand feature data.
	 * @param array $where Where conditions.
	 * @return bool True on success, false on failure.
	 */
	public function update( $data, $where ) {
		// Sanitize data
		$data = $this->sanitize_data( $data );

		// Update timestamp
		$data['updated_at'] = current_time( 'mysql' );

		$db = Database_Manager::get_instance();
		$result = $db->update( $this->table_name, $data, $where );

		if ( $result !== false ) {
			Logger::info( 'brand_feature_updated', 'Brand feature updated successfully', $where );
		}

		return $result !== false;
	}

	/**
	 * Delete a brand feature.
	 *
	 * @param array $where Where conditions.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $where ) {
		$db = Database_Manager::get_instance();
		$result = $db->delete( $this->table_name, $where );

		if ( $result ) {
			Logger::info( 'brand_feature_deleted', 'Brand feature deleted successfully', $where );
		}

		return $result;
	}

	/**
	 * Toggle brand feature active status.
	 *
	 * @param int $id Brand feature ID.
	 * @return bool True on success, false on failure.
	 */
	public function toggle_active( $id ) {
		$feature = $this->get_by_id( $id );
		
		if ( ! $feature ) {
			return false;
		}

		$new_status = $feature['active'] ? 0 : 1;
		
		return $this->update( [ 'active' => $new_status ], [ 'id' => $id ] );
	}

	/**
	 * Validate brand feature data.
	 *
	 * @param array $data Brand feature data.
	 * @param int|null $id Optional ID for updates.
	 * @return bool|array True if valid, array of errors if not.
	 */
	public function validate( $data, $id = null ) {
		$errors = [];

		// Check required fields
		if ( empty( $data['name'] ) ) {
			$errors[] = __( 'Brand feature name is required.', 'ai-blog-generator' );
		}

		if ( empty( $data['url'] ) ) {
			$errors[] = __( 'Brand feature URL is required.', 'ai-blog-generator' );
		}

		// Validate URL
		if ( ! empty( $data['url'] ) && ! filter_var( $data['url'], FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'Invalid URL format.', 'ai-blog-generator' );
		}

		// Validate category
		$valid_categories = [ 'informational_page', 'document', 'image', 'video' ];
		if ( ! empty( $data['category'] ) && ! in_array( $data['category'], $valid_categories, true ) ) {
			$errors[] = __( 'Invalid category selected.', 'ai-blog-generator' );
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Sanitize brand feature data.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize_data( $data ) {
		$sanitized = [];

		if ( isset( $data['name'] ) ) {
			$sanitized['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['category'] ) ) {
			$sanitized['category'] = sanitize_text_field( $data['category'] );
		}

		if ( isset( $data['url'] ) ) {
			$sanitized['url'] = esc_url_raw( $data['url'] );
		}

		if ( isset( $data['active'] ) ) {
			$sanitized['active'] = (int) $data['active'];
		}

		return $sanitized;
	}

	/**
	 * Get brand features for content generation.
	 *
	 * @return array Formatted brand features for AI context.
	 */
	public function get_for_content_generation() {
		$features = $this->get_active_features();
		$formatted = [];

		foreach ( $features as $feature ) {
			$formatted[] = [
				'name' => $feature['name'],
				'description' => $feature['description'],
				'category' => $feature['category'],
				'url' => $feature['url'],
			];
		}

		return $formatted;
	}

	/**
	 * Search brand features.
	 *
	 * @param string $search Search term.
	 * @return array
	 */
	public function search( $search ) {
		global $wpdb;
		
		$table = AI_BLOG_GENERATOR_TABLE_BRAND_FEATURES;
		$search = '%' . $wpdb->esc_like( $search ) . '%';
		
		$sql = $wpdb->prepare(
			"SELECT * FROM $table 
			WHERE name LIKE %s 
			OR description LIKE %s 
			ORDER BY name ASC",
			$search,
			$search
		);
		
		return $wpdb->get_results( $sql, ARRAY_A );
	}
} 