<?php
/**
 * Blog Ideas Model V2
 *
 * Handles all database operations for blog ideas and their categories.
 *
 * @package AI_Blog_Generator
 * @subpackage Models
 */

namespace AI_Blog_Generator\Models;

use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Loggable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blog Ideas Model V2 Class
 */
class Blog_Ideas_Model_V2 extends Model {

	use Loggable;

	/**
	 * Table name for blog ideas.
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Categories connector table name.
	 *
	 * @var string
	 */
	protected $categories_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		
		global $wpdb;
		$this->table_name = AI_BLOG_GENERATOR_TABLE_IDEAS;
		$this->categories_table_name = AI_BLOG_GENERATOR_TABLE_IDEA_CATEGORIES;
		
		$this->log_info( 'blog_ideas_model_v2_init', 'Blog Ideas Model V2 initialized', [
			'ideas_table' => $this->table_name,
			'categories_table' => $this->categories_table_name
		] );
	}

	/**
	 * Get all pending blog ideas with their categories and personas.
	 *
	 * @param array $filters Optional filters.
	 * @return array Array of blog ideas.
	 */
	public function get_pending_ideas( $filters = [] ) {
		$this->log_function_entry( [ 'filters' => $filters ] );
		
		global $wpdb;
		
		$sql = "
			SELECT 
				i.id,
				i.title,
				i.description,
				i.persona_id,
				i.status,
				i.created_at,
				i.updated_at,
				p.name as persona_name,
				GROUP_CONCAT(DISTINCT c.category_id) as category_ids,
				GROUP_CONCAT(DISTINCT t.name) as category_names
			FROM {$this->table_name} i
			LEFT JOIN {$wpdb->prefix}ai_blog_personas p ON i.persona_id = p.id
			LEFT JOIN {$this->categories_table_name} c ON i.id = c.blog_idea_id
			LEFT JOIN {$wpdb->terms} t ON c.category_id = t.term_id
			WHERE i.status = 'pending'
		";
		
		// Add date filters if provided
		if ( ! empty( $filters['date_from'] ) ) {
			$sql .= $wpdb->prepare( " AND DATE(i.created_at) >= %s", $filters['date_from'] );
		}
		
		if ( ! empty( $filters['date_to'] ) ) {
			$sql .= $wpdb->prepare( " AND DATE(i.created_at) <= %s", $filters['date_to'] );
		}
		
		// Add persona filter if provided
		if ( ! empty( $filters['persona_id'] ) ) {
			$sql .= $wpdb->prepare( " AND i.persona_id = %d", $filters['persona_id'] );
		}
		
		$sql .= "
			GROUP BY i.id
			ORDER BY i.created_at DESC
		";
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		if ( $wpdb->last_error ) {
			$this->log_error( 'get_pending_ideas_error', 'Database error getting pending ideas', [
				'error' => $wpdb->last_error,
				'query' => $sql
			] );
			return [];
		}
		
		// Process results to convert category data
		foreach ( $results as &$result ) {
			$result['category_ids'] = ! empty( $result['category_ids'] ) ? explode( ',', $result['category_ids'] ) : [];
			$result['category_names'] = ! empty( $result['category_names'] ) ? explode( ',', $result['category_names'] ) : [];
			$result['created_at_ny'] = $this->convert_to_ny_time( $result['created_at'] );
			
			// CRITICAL: If the idea is generating, check for real-time status from transient
			if ( $result['status'] === 'generating' ) {
				$transient_status = get_transient( "ai_blog_generation_status_{$result['id']}" );
				if ( $transient_status && isset( $transient_status['message'] ) ) {
					// Override the database generation_status with the real-time status
					$result['generation_status'] = $transient_status['message'];
				}
			}
		}
		
		$this->log_info( 'pending_ideas_retrieved', 'Retrieved pending ideas', [
			'count' => count( $results )
		] );
		
		$this->log_function_exit( count( $results ) );
		return $results;
	}

	/**
	 * Get idea statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		$this->log_function_entry();
		
		global $wpdb;
		
		$sql = "
			SELECT 
				COUNT(*) as total_generated,
				SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
				SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied,
				SUM(CASE WHEN status = 'generated' THEN 1 ELSE 0 END) as generated,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
			FROM {$this->table_name}
		";
		
		$stats = $wpdb->get_row( $sql, ARRAY_A );
		
		if ( $wpdb->last_error ) {
			$this->log_error( 'get_statistics_error', 'Database error getting statistics', [
				'error' => $wpdb->last_error
			] );
			return [
				'total_generated' => 0,
				'approved' => 0,
				'denied' => 0,
				'generated' => 0,
				'pending' => 0
			];
		}
		
		$this->log_info( 'statistics_retrieved', 'Retrieved idea statistics', $stats );
		$this->log_function_exit( $stats );
		
		return $stats;
	}

	/**
	 * Create new blog ideas.
	 *
	 * @param array $ideas Array of idea data.
	 * @return array Result with success/failure info.
	 */
	public function create_ideas( $ideas ) {
		$this->log_function_entry( [ 'ideas_count' => count( $ideas ) ] );
		
		global $wpdb;
		
		$created_count = 0;
		$errors = [];
		
		foreach ( $ideas as $idea ) {
			$wpdb->query( 'START TRANSACTION' );
			
			try {
				// Insert the main idea
				$result = $wpdb->insert(
					$this->table_name,
					[
						'title' => sanitize_text_field( $idea['title'] ),
						'description' => sanitize_textarea_field( $idea['description'] ),
						'persona_id' => ! empty( $idea['persona_id'] ) ? absint( $idea['persona_id'] ) : null,
						'status' => 'pending',
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' )
					],
					[ '%s', '%s', '%d', '%s', '%s', '%s' ]
				);
				
				if ( false === $result ) {
					throw new \Exception( 'Failed to insert idea: ' . $wpdb->last_error );
				}
				
				$idea_id = $wpdb->insert_id;
				
				// Insert categories if provided
				if ( ! empty( $idea['categories'] ) ) {
					foreach ( $idea['categories'] as $category ) {
						$category_id = $this->resolve_category_id( $category );
						
						if ( $category_id ) {
							$cat_result = $wpdb->insert(
								$this->categories_table_name,
								[
									'blog_idea_id' => $idea_id,
									'category_id' => $category_id
								],
								[ '%d', '%d' ]
							);
							
							if ( false === $cat_result ) {
								throw new \Exception( 'Failed to insert category: ' . $wpdb->last_error );
							}
						}
					}
				}
				
				$wpdb->query( 'COMMIT' );
				$created_count++;
				
				$this->log_info( 'idea_created', 'Blog idea created successfully', [
					'idea_id' => $idea_id,
					'title' => $idea['title'],
					'categories_count' => count( $idea['categories'] ?? [] )
				] );
				
			} catch ( \Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				$errors[] = $e->getMessage();
				
				$this->log_error( 'idea_creation_failed', 'Failed to create blog idea', [
					'title' => $idea['title'] ?? 'unknown',
					'error' => $e->getMessage()
				] );
			}
		}
		
		$result = [
			'success' => $created_count > 0,
			'created_count' => $created_count,
			'total_count' => count( $ideas ),
			'errors' => $errors
		];
		
		$this->log_function_exit( $result );
		return $result;
	}

	/**
	 * Update idea details.
	 *
	 * @param int   $idea_id The idea ID.
	 * @param array $data    The data to update.
	 * @return bool Success status.
	 */
	public function update_idea( $idea_id, $data ) {
		$this->log_function_entry( [
			'idea_id' => $idea_id,
			'data_keys' => array_keys( $data )
		] );
		
		global $wpdb;
		
		// Allowed fields to update
		$allowed_fields = [ 'title', 'description', 'persona_id', 'status', 'generation_status', 'generation_error', 'generation_started_at', 'generation_completed_at', 'updated_at' ];
		$update_data = [];
		$format = [];
		
		foreach ( $data as $field => $value ) {
			if ( in_array( $field, $allowed_fields, true ) ) {
				$update_data[ $field ] = $value;
				
				// Determine format based on field
				if ( in_array( $field, [ 'persona_id' ] ) ) {
					$format[] = '%d';
				} else {
					$format[] = '%s';
				}
			}
		}
		
		if ( empty( $update_data ) ) {
			$this->log_error( 'no_valid_fields', 'No valid fields provided for update', [
				'idea_id' => $idea_id,
				'provided_fields' => array_keys( $data )
			] );
			return false;
		}
		
		// Always update the updated_at timestamp
		if ( ! isset( $update_data['updated_at'] ) ) {
			$update_data['updated_at'] = current_time( 'mysql' );
			$format[] = '%s';
		}
		
		// Log the update attempt with detailed information
		$debug_log = __DIR__ . '/../debug-transaction.log';
		$log_entry = date( 'Y-m-d H:i:s' ) . " - BLOG_IDEAS_MODEL_V2 UPDATE_IDEA:\n";
		$log_entry .= "  Idea ID: $idea_id\n";
		$log_entry .= "  Update Data: " . json_encode( $update_data, JSON_UNESCAPED_SLASHES ) . "\n";
		$log_entry .= "  Format: " . json_encode( $format ) . "\n";
		$log_entry .= "  Table: {$this->table_name}\n";
		
		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			[ 'id' => $idea_id ],
			$format,
			[ '%d' ]
		);
		
		// Log the SQL query and result with safe data
		$last_query = $wpdb->last_query;
		$last_error = $wpdb->last_error;
		$safe_query = $last_query ? substr( preg_replace( '/[^\x20-\x7E]/', '?', $last_query ), 0, 200 ) . '...' : 'None';
		$safe_error = $last_error ? preg_replace( '/[^\x20-\x7E]/', '?', $last_error ) : 'None';
		
		$log_entry .= "  SQL Query: $safe_query\n";
		$log_entry .= "  SQL Error: $safe_error\n";
		$log_entry .= "  Rows Affected: " . intval( $wpdb->rows_affected ) . "\n";
		$log_entry .= "  Update Result: " . ($result !== false ? 'SUCCESS' : 'FAILED') . "\n";
		$log_entry .= "  Result Value: " . intval( $result ) . "\n\n";
		file_put_contents( $debug_log, $log_entry, FILE_APPEND );
		
		if ( false === $result ) {
			$this->log_error( 'idea_update_failed', 'Failed to update idea', [
				'idea_id' => $idea_id,
				'data' => $update_data,
				'error' => $wpdb->last_error,
				'sql_query' => $last_query,
				'rows_affected' => $wpdb->rows_affected
			] );
			return false;
		}
		
		$this->log_info( 'idea_updated', 'Idea updated successfully', [
			'idea_id' => $idea_id,
			'updated_fields' => array_keys( $update_data ),
			'rows_affected' => $result,
			'sql_query' => $last_query
		] );
		
		$this->log_function_exit( true );
		return true;
	}

	/**
	 * Update idea status.
	 *
	 * @param int    $idea_id The idea ID.
	 * @param string $status  The new status.
	 * @param array  $additional_data Additional data to update.
	 * @return bool Success status.
	 */
	public function update_status( $idea_id, $status, $additional_data = [] ) {
		$this->log_function_entry( [
			'idea_id' => $idea_id,
			'status' => $status,
			'additional_data' => $additional_data
		] );
		
		global $wpdb;
		
		$valid_statuses = [ 'pending', 'approved', 'denied', 'generated', 'generating' ];
		
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$this->log_error( 'invalid_status', 'Invalid status provided', [
				'status' => $status,
				'valid_statuses' => $valid_statuses
			] );
			return false;
		}
		
		$update_data = array_merge( $additional_data, [
			'status' => $status,
			'updated_at' => current_time( 'mysql' )
		] );
		
		$result = $this->update_idea( $idea_id, $update_data );
		
		$this->log_function_exit( $result );
		return $result;
	}

	/**
	 * Bulk update idea statuses.
	 *
	 * @param array  $idea_ids Array of idea IDs.
	 * @param string $status   The new status.
	 * @return array Result with success/failure info.
	 */
	public function bulk_update_status( $idea_ids, $status ) {
		$this->log_function_entry( [
			'idea_ids' => $idea_ids,
			'status' => $status,
			'count' => count( $idea_ids )
		] );
		
		$updated_count = 0;
		$errors = [];
		
		foreach ( $idea_ids as $idea_id ) {
			$result = $this->update_status( absint( $idea_id ), $status );
			
			if ( $result ) {
				$updated_count++;
			} else {
				$errors[] = "Failed to update idea ID: $idea_id";
			}
		}
		
		$result = [
			'success' => $updated_count > 0,
			'updated_count' => $updated_count,
			'total_count' => count( $idea_ids ),
			'errors' => $errors
		];
		
		$this->log_function_exit( $result );
		return $result;
	}

	/**
	 * Get all existing blog titles and categories.
	 *
	 * @return array Array of existing content.
	 */
	public function get_existing_content() {
		$this->log_function_entry();
		
		global $wpdb;
		
		// Get all ideas from database
		$ideas_sql = "
			SELECT 
				i.title,
				GROUP_CONCAT(DISTINCT c.category_id) as category_ids
			FROM {$this->table_name} i
			LEFT JOIN {$this->categories_table_name} c ON i.id = c.blog_idea_id
			GROUP BY i.id, i.title
		";
		
		$existing_ideas = $wpdb->get_results( $ideas_sql, ARRAY_A );
		
		// Get all WordPress posts
		$posts_sql = "
			SELECT 
				p.post_title as title,
				GROUP_CONCAT(DISTINCT tr.term_taxonomy_id) as category_ids
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE p.post_status IN ('publish', 'draft')
			AND p.post_type = 'post'
			AND (tt.taxonomy = 'category' OR tt.taxonomy IS NULL)
			GROUP BY p.ID, p.post_title
		";
		
		$existing_posts = $wpdb->get_results( $posts_sql, ARRAY_A );
		
		$result = [
			'ideas' => $existing_ideas ?: [],
			'posts' => $existing_posts ?: []
		];
		
		$this->log_info( 'existing_content_retrieved', 'Retrieved existing content', [
			'ideas_count' => count( $result['ideas'] ),
			'posts_count' => count( $result['posts'] )
		] );
		
		$this->log_function_exit( $result );
		return $result;
	}

	/**
	 * Get all WordPress categories.
	 *
	 * @return array Array of categories.
	 */
	public function get_wordpress_categories() {
		$this->log_function_entry();
		
		$categories = get_categories( [
			'taxonomy' => 'category',
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		] );
		
		$result = [];
		foreach ( $categories as $category ) {
			$result[] = [
				'id' => $category->term_id,
				'name' => $category->name,
				'slug' => $category->slug,
				'count' => $category->count
			];
		}
		
		$this->log_info( 'wordpress_categories_retrieved', 'Retrieved WordPress categories', [
			'count' => count( $result )
		] );
		
		$this->log_function_exit( count( $result ) );
		return $result;
	}

	/**
	 * Get single idea by ID.
	 *
	 * @param int $idea_id The idea ID.
	 * @return array|null Idea data or null if not found.
	 */
	public function get_idea( $idea_id ) {
		$this->log_function_entry( [ 'idea_id' => $idea_id ] );
		
		global $wpdb;
		
		$sql = "
			SELECT 
				i.*,
				p.name as persona_name,
				GROUP_CONCAT(DISTINCT c.category_id) as category_ids,
				GROUP_CONCAT(DISTINCT t.name) as category_names
			FROM {$this->table_name} i
			LEFT JOIN {$wpdb->prefix}ai_blog_personas p ON i.persona_id = p.id
			LEFT JOIN {$this->categories_table_name} c ON i.id = c.blog_idea_id
			LEFT JOIN {$wpdb->terms} t ON c.category_id = t.term_id
			WHERE i.id = %d
			GROUP BY i.id
		";
		
		$result = $wpdb->get_row( $wpdb->prepare( $sql, $idea_id ), ARRAY_A );
		
		if ( $result ) {
			$result['category_ids'] = ! empty( $result['category_ids'] ) ? explode( ',', $result['category_ids'] ) : [];
			$result['category_names'] = ! empty( $result['category_names'] ) ? explode( ',', $result['category_names'] ) : [];
			$result['created_at_ny'] = $this->convert_to_ny_time( $result['created_at'] );
			
			// CRITICAL: If the idea is generating, check for real-time status from transient
			if ( $result['status'] === 'generating' ) {
				$transient_status = get_transient( "ai_blog_generation_status_{$result['id']}" );
				if ( $transient_status && isset( $transient_status['message'] ) ) {
					// Override the database generation_status with the real-time status
					$result['generation_status'] = $transient_status['message'];
					$this->log_debug( 'real_time_status_applied', 'Applied real-time generation status from transient', [
						'idea_id' => $result['id'],
						'db_status' => $result['generation_status'] ?? 'null',
						'transient_status' => $transient_status['message']
					] );
				}
			}
		}
		
		$this->log_function_exit( $result ? 'found' : 'not_found' );
		return $result;
	}

	/**
	 * Resolve category ID from name or ID.
	 *
	 * @param string|int $category Category name or ID.
	 * @return int|null Category ID or null if not found.
	 */
	private function resolve_category_id( $category ) {
		if ( is_numeric( $category ) ) {
			return absint( $category );
		}
		
		$term = get_term_by( 'name', $category, 'category' );
		
		if ( $term ) {
			return $term->term_id;
		}
		
		// Try by slug as fallback
		$term = get_term_by( 'slug', sanitize_title( $category ), 'category' );
		
		return $term ? $term->term_id : null;
	}

	/**
	 * Get ideas by status.
	 *
	 * @param string $status The status to filter by.
	 * @return array Array of ideas with the specified status.
	 */
	public function get_by_status( $status ) {
		$this->log_function_entry( [ 'status' => $status ] );
		
		global $wpdb;
		
		$sql = "
			SELECT 
				i.*,
				p.name as persona_name,
				GROUP_CONCAT(DISTINCT c.category_id) as category_ids,
				GROUP_CONCAT(DISTINCT t.name) as category_names
			FROM {$this->table_name} i
			LEFT JOIN {$wpdb->prefix}ai_blog_personas p ON i.persona_id = p.id
			LEFT JOIN {$this->categories_table_name} c ON i.id = c.blog_idea_id
			LEFT JOIN {$wpdb->terms} t ON c.category_id = t.term_id
			WHERE i.status = %s
			GROUP BY i.id
			ORDER BY i.updated_at DESC
		";
		
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $status ), ARRAY_A );
		
		if ( $wpdb->last_error ) {
			$this->log_error( 'get_by_status_error', 'Database error getting ideas by status', [
				'error' => $wpdb->last_error,
				'status' => $status
			] );
			return [];
		}
		
		// Process results to convert category data
		foreach ( $results as &$result ) {
			$result['category_ids'] = ! empty( $result['category_ids'] ) ? explode( ',', $result['category_ids'] ) : [];
			$result['category_names'] = ! empty( $result['category_names'] ) ? explode( ',', $result['category_names'] ) : [];
			$result['created_at_ny'] = $this->convert_to_ny_time( $result['created_at'] );
			
			// CRITICAL: If the idea is generating, check for real-time status from transient
			if ( $result['status'] === 'generating' ) {
				$transient_status = get_transient( "ai_blog_generation_status_{$result['id']}" );
				if ( $transient_status && isset( $transient_status['message'] ) ) {
					// Override the database generation_status with the real-time status
					$result['generation_status'] = $transient_status['message'];
				}
			}
		}
		
		$this->log_info( 'ideas_by_status_retrieved', 'Retrieved ideas by status', [
			'status' => $status,
			'count' => count( $results )
		] );
		
		$this->log_function_exit( count( $results ) );
		return $results;
	}

	/**
	 * Count ideas by status.
	 *
	 * @param string $status The status to count.
	 * @return int Number of ideas with the specified status.
	 */
	public function count_by_status( $status ) {
		$this->log_function_entry( [ 'status' => $status ] );
		
		global $wpdb;
		
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s";
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $status ) );
		
		if ( $wpdb->last_error ) {
			$this->log_error( 'count_by_status_error', 'Database error counting ideas by status', [
				'error' => $wpdb->last_error,
				'status' => $status
			] );
			return 0;
		}
		
		$count = intval( $count );
		
		$this->log_info( 'ideas_counted_by_status', 'Counted ideas by status', [
			'status' => $status,
			'count' => $count
		] );
		
		$this->log_function_exit( $count );
		return $count;
	}

	/**
	 * Convert UTC time to New York time.
	 *
	 * @param string $utc_time UTC timestamp.
	 * @return string New York formatted time.
	 */
	private function convert_to_ny_time( $utc_time ) {
		$date = new \DateTime( $utc_time, new \DateTimeZone( 'UTC' ) );
		$date->setTimezone( new \DateTimeZone( 'America/New_York' ) );
		return $date->format( 'M j, Y g:i A T' );
	}

	/**
	 * Compatibility method: Get idea by ID (alias for get_idea)
	 *
	 * @param int $idea_id The idea ID.
	 * @return array|null Idea data or null if not found.
	 */
	public function get( $idea_id ) {
		return $this->get_idea( $idea_id );
	}

	/**
	 * Compatibility method: Update idea (alias for update_idea)
	 *
	 * @param int   $idea_id The idea ID.
	 * @param array $data    The data to update.
	 * @return bool Success status.
	 */
	public function update( $idea_id, $data ) {
		return $this->update_idea( $idea_id, $data );
	}

	/**
	 * Compatibility method: Create single idea
	 *
	 * @param array $idea_data The idea data.
	 * @return int|false Idea ID on success, false on failure.
	 */
	public function create( $idea_data ) {
		$this->log_function_entry( [ 'idea_data' => array_keys( $idea_data ) ] );
		
		global $wpdb;
		
		try {
			// Insert the idea
			$result = $wpdb->insert(
				$this->table_name,
				[
					'title' => sanitize_text_field( $idea_data['title'] ),
					'description' => sanitize_textarea_field( $idea_data['description'] ?? '' ),
					'category_id' => isset( $idea_data['category_id'] ) ? absint( $idea_data['category_id'] ) : null,
					'persona_id' => isset( $idea_data['persona_id'] ) ? absint( $idea_data['persona_id'] ) : null,
					'status' => sanitize_text_field( $idea_data['status'] ?? 'pending' ),
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' )
				],
				[ '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
			);
			
			if ( false === $result ) {
				$this->log_error( 'idea_create_failed', 'Failed to create idea', [
					'title' => $idea_data['title'],
					'error' => $wpdb->last_error
				] );
				return false;
			}
			
			$idea_id = $wpdb->insert_id;
			
			$this->log_info( 'idea_created', 'Idea created successfully', [
				'idea_id' => $idea_id,
				'title' => $idea_data['title']
			] );
			
			$this->log_function_exit( $idea_id );
			return $idea_id;
			
		} catch ( \Exception $e ) {
			$this->log_error( 'idea_create_exception', 'Exception creating idea', [
				'error' => $e->getMessage(),
				'title' => $idea_data['title'] ?? 'unknown'
			] );
			return false;
		}
	}

	/**
	 * Check if a title already exists.
	 *
	 * @param string $title Title to check.
	 * @return bool
	 */
	public function is_duplicate_title( $title ) {
		$this->log_function_entry( [ 'title' => $title ] );
		
		global $wpdb;
		
		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s";
		$count = $wpdb->get_var( $wpdb->prepare( $sql, $title ) );
		
		if ( $wpdb->last_error ) {
			$this->log_error( 'is_duplicate_title_error', 'Database error checking duplicate title', [
				'error' => $wpdb->last_error,
				'title' => $title
			] );
			return false;
		}
		
		$is_duplicate = intval( $count ) > 0;
		
		$this->log_info( 'duplicate_title_checked', 'Duplicate title check completed', [
			'title' => $title,
			'is_duplicate' => $is_duplicate,
			'count' => $count
		] );
		
		$this->log_function_exit( $is_duplicate );
		return $is_duplicate;
	}
} 