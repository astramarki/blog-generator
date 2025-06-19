<?php
/**
 * Idea Model
 *
 * @package AI_Blog_Generator
 * @subpackage Models
 */

namespace AI_Blog_Generator\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idea Model Class
 *
 * Handles blog idea data operations.
 *
 * @since 1.0.0
 */
class Idea_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'ideas';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'title',
		'description',
		'category_id',
		'persona_id',
		'status',
	];

	/**
	 * Log to debug file for comprehensive debugging
	 */
	private function log_debug( $method, $message, $data = [] ) {
		$debug_log = __DIR__ . '/../debug-transaction.log';
		$timestamp = date( 'Y-m-d H:i:s' );
		$log_entry = "$timestamp - IDEA_MODEL::$method - $message\n";
		if ( ! empty( $data ) ) {
			// Create safe representation of data to avoid binary corruption
			$safe_data = $this->make_data_safe_for_logging( $data );
			$log_entry .= "$timestamp - IDEA_MODEL::$method - DATA: " . json_encode( $safe_data, JSON_UNESCAPED_SLASHES ) . "\n";
		}
		file_put_contents( $debug_log, $log_entry, FILE_APPEND );
	}
	
	/**
	 * Create a safe representation of data for logging (avoid binary corruption)
	 */
	private function make_data_safe_for_logging( $data ) {
		if ( is_object( $data ) ) {
			if ( isset( $data->id ) && isset( $data->title ) ) {
				return "Object[id={$data->id}, title=" . substr( $data->title ?? 'null', 0, 50 ) . "]";
			}
			return "Object[" . get_class( $data ) . "]";
		}
		
		if ( is_array( $data ) ) {
			$safe_array = [];
			foreach ( $data as $key => $value ) {
				if ( is_string( $value ) && strlen( $value ) > 100 ) {
					$safe_array[$key] = substr( $value, 0, 100 ) . '...[truncated]';
				} elseif ( is_object( $value ) ) {
					$safe_array[$key] = $this->make_data_safe_for_logging( $value );
				} else {
					$safe_array[$key] = $value;
				}
			}
			return $safe_array;
		}
		
		return $data;
	}

	/**
	 * Get idea by ID with comprehensive debugging
	 */
	public function get( $id ) {
		$this->log_debug( 'get', 'Method called', [
			'id' => $id,
			'id_type' => gettype( $id ),
			'memory_usage' => memory_get_usage()
		] );

		try {
			// Validate input
			if ( empty( $id ) ) {
				$this->log_debug( 'get', 'Invalid ID provided', [ 'id' => $id ] );
				return null;
			}

			$this->log_debug( 'get', 'Calling parent::get method' );
			$result = parent::get( $id );
			
			$this->log_debug( 'get', 'Parent get method completed', [
				'result_type' => gettype( $result ),
				'result_is_null' => is_null( $result ),
				'result_data' => is_array( $result ) ? 'ARRAY[' . implode(', ', array_keys($result)) . ']' : (is_null( $result ) ? 'NULL' : 'SCALAR')
			] );

			return $result;

		} catch ( \Exception $e ) {
			$this->log_debug( 'get', 'Exception in get method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Update idea with comprehensive debugging
	 */
	public function update( $id, $data ) {
		$this->log_debug( 'update', 'Method called', [
			'id' => $id,
			'data' => $data,
			'data_keys' => array_keys( $data ),
			'memory_usage' => memory_get_usage()
		] );

		try {
			// Validate input
			if ( empty( $id ) ) {
				$this->log_debug( 'update', 'Invalid ID provided', [ 'id' => $id ] );
				return false;
			}

			if ( empty( $data ) || ! is_array( $data ) ) {
				$this->log_debug( 'update', 'Invalid data provided', [ 'data' => $data ] );
				return false;
			}

			$this->log_debug( 'update', 'Calling parent::update method' );
			$result = parent::update( $id, $data );
			
			$this->log_debug( 'update', 'Parent update method completed', [
				'result' => $result,
				'result_type' => gettype( $result )
			] );

			return $result;

		} catch ( \Exception $e ) {
			$this->log_debug( 'update', 'Exception in update method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Get ideas by status.
	 *
	 * @param string $status Post status.
	 * @return array
	 */
	public function get_by_status( $status ) {
		$this->log_debug( 'get_by_status', 'Method called', [
			'status' => $status,
			'memory_usage' => memory_get_usage()
		] );

		try {
			global $wpdb;

			$table_name = AI_BLOG_GENERATOR_TABLE_IDEAS;
			$this->log_debug( 'get_by_status', 'Table name resolved', [ 'table' => $table_name ] );

			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE status = %s ORDER BY created_at DESC",
				$status
			);

			$this->log_debug( 'get_by_status', 'SQL query prepared', [
				'sql' => $sql,
				'status_param' => $status
			] );

			$results = $wpdb->get_results( $sql );

			$this->log_debug( 'get_by_status', 'Query executed', [
				'results_count' => is_array( $results ) ? count( $results ) : 0,
				'results_type' => gettype( $results ),
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $results;

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_by_status', 'Exception in get_by_status method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Check if a title already exists.
	 *
	 * @param string $title Title to check.
	 * @return bool
	 */
	public function is_duplicate_title( $title ) {
		$this->log_debug( 'is_duplicate_title', 'Method called', [
			'title' => $title,
			'title_length' => strlen( $title ),
			'memory_usage' => memory_get_usage()
		] );

		try {
		global $wpdb;

			$table_name = AI_BLOG_GENERATOR_TABLE_IDEAS;
			$this->log_debug( 'is_duplicate_title', 'Table name resolved', [ 'table' => $table_name ] );

		$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE title = %s",
			$title
		);

			$this->log_debug( 'is_duplicate_title', 'SQL query prepared', [
				'sql' => $sql,
				'title_param' => $title
			] );

			$count = $wpdb->get_var( $sql );

			$this->log_debug( 'is_duplicate_title', 'Query executed', [
				'count' => $count,
				'count_type' => gettype( $count ),
				'is_duplicate' => $count > 0,
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $count > 0;

		} catch ( \Exception $e ) {
			$this->log_debug( 'is_duplicate_title', 'Exception in is_duplicate_title method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Get ideas with persona information.
	 *
	 * @param array $filters Optional filters.
	 * @return array
	 */
	public function get_with_persona( $filters = [] ) {
		$this->log_debug( 'get_with_persona', 'Method called', [
			'filters' => $filters,
			'memory_usage' => memory_get_usage()
		] );

		try {
			global $wpdb;

			$ideas_table = AI_BLOG_GENERATOR_TABLE_IDEAS;
			$personas_table = AI_BLOG_GENERATOR_TABLE_PERSONAS;

			$this->log_debug( 'get_with_persona', 'Tables resolved', [
				'ideas_table' => $ideas_table,
				'personas_table' => $personas_table
			] );

			$sql = "SELECT i.*, p.name as persona_name, p.tone as persona_tone
					FROM {$ideas_table} i
					LEFT JOIN {$personas_table} p ON i.persona_id = p.id
					WHERE 1=1";

			// Apply filters
			if ( ! empty( $filters['status'] ) ) {
				$sql .= $wpdb->prepare( " AND i.status = %s", $filters['status'] );
			}

			$sql .= " ORDER BY i.created_at DESC";

			$this->log_debug( 'get_with_persona', 'SQL query built', [
				'sql' => $sql,
				'filters_applied' => $filters
			] );

			$results = $wpdb->get_results( $sql );

			$this->log_debug( 'get_with_persona', 'Query executed', [
				'results_count' => is_array( $results ) ? count( $results ) : 0,
				'results_type' => gettype( $results ),
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $results;

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_with_persona', 'Exception in get_with_persona method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Get ideas with WordPress category information.
	 *
	 * @param array  $filters  Filter conditions.
	 * @param string $order_by Order by clause.
	 * @return array Ideas with category information.
	 */
	public function get_with_category( $filters = [], $order_by = 'created_at DESC' ) {
		$this->log_debug( 'get_with_category', 'Method called', [
			'filters' => $filters,
			'order_by' => $order_by,
			'memory_usage' => memory_get_usage()
		] );

		try {
			global $wpdb;
			
			$this->log_debug( 'get_with_category', 'Starting method execution' );
			
			// Get table names
			$ideas_table = AI_BLOG_GENERATOR_TABLE_IDEAS;
			$categories_table = $wpdb->terms;
			$term_taxonomy_table = $wpdb->term_taxonomy;
			$personas_table = AI_BLOG_GENERATOR_TABLE_PERSONAS;
			
			$this->log_debug( 'get_with_category', 'Tables resolved', [
				'ideas_table' => $ideas_table,
				'categories_table' => $categories_table,
				'term_taxonomy_table' => $term_taxonomy_table,
				'personas_table' => $personas_table
			] );

			// Build the SQL query with JOIN (include both category and persona information)
			$sql = "SELECT i.*, t.name as category_name, t.slug as category_slug, p.name as persona_name, p.tone as persona_tone
					FROM {$ideas_table} i 
					LEFT JOIN {$term_taxonomy_table} tt ON i.category_id = tt.term_id AND tt.taxonomy = 'category'
					LEFT JOIN {$categories_table} t ON tt.term_id = t.term_id
					LEFT JOIN {$personas_table} p ON i.persona_id = p.id";

			// Add WHERE conditions
			$where_conditions = [];
			$where_values = [];

			foreach ( $filters as $field => $value ) {
				if ( ! empty( $value ) ) {
					$where_conditions[] = "i.{$field} = %s";
					$where_values[] = $value;
				}
			}

			if ( ! empty( $where_conditions ) ) {
				$sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
			}

			// Add ORDER BY
			$sql .= " ORDER BY i.{$order_by}";

			$this->log_debug( 'get_with_category', 'SQL query built', [
				'sql' => $sql,
				'where_values' => $where_values
			] );

			// Execute query
			if ( ! empty( $where_values ) ) {
				$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
			} else {
				$results = $wpdb->get_results( $sql );
			}

			$this->log_debug( 'get_with_category', 'Query executed', [
				'results_count' => count( $results ?? [] ),
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $results ?? [];

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_with_category', 'Exception in get_with_category method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			
			\AI_Blog_Generator\Utilities\Logger::error( 'idea_get_with_category_failed', 'Failed to get ideas with category', [
				'filters' => $filters,
				'order_by' => $order_by,
				'error' => $e->getMessage()
			]);
			
			// Return empty result instead of throwing exception
			return [];
		}
	}

	/**
	 * Get ideas statistics.
	 *
	 * @return array
	 */
	public function get_statistics() {
		$this->log_debug( 'get_statistics', 'Method called', [
			'memory_usage' => memory_get_usage()
		] );

		try {
		global $wpdb;

			$table_name = AI_BLOG_GENERATOR_TABLE_IDEAS;
			$this->log_debug( 'get_statistics', 'Table name resolved', [ 'table' => $table_name ] );

		$sql = "SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
				SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied,
				SUM(CASE WHEN status = 'generating' THEN 1 ELSE 0 END) as generating,
				SUM(CASE WHEN status = 'generated' THEN 1 ELSE 0 END) as generated
			FROM {$table_name}";

			$this->log_debug( 'get_statistics', 'SQL query prepared', [ 'sql' => $sql ] );

			$result = $wpdb->get_row( $sql, ARRAY_A );

			$this->log_debug( 'get_statistics', 'Query executed', [
				'result' => is_array( $result ) ? 'ARRAY[' . implode(', ', array_keys($result)) . ']' : (is_null( $result ) ? 'NULL' : 'SCALAR'),
				'result_type' => gettype( $result ),
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $result ?: [
			'total' => 0,
			'pending' => 0,
			'approved' => 0,
			'denied' => 0,
				'generating' => 0,
			'generated' => 0,
		];

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_statistics', 'Exception in get_statistics method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Validate data.
	 *
	 * @param array    $data Data to validate.
	 * @param int|null $id   Record ID for updates.
	 * @return bool
	 */
	protected function validate( $data, $id = null ) {
		$this->log_debug( 'validate', 'Method called', [
			'data' => $data,
			'id' => $id,
			'memory_usage' => memory_get_usage()
		] );

		try {
			// Title is required and must be unique.
			if ( isset( $data['title'] ) ) {
				if ( empty( trim( $data['title'] ) ) ) {
					$this->log_debug( 'validate', 'Title validation failed - empty', [ 'title' => $data['title'] ] );
			return false;
		}

				// Check for duplicates (skip current record for updates).
				$duplicate_check = $this->is_duplicate_title( $data['title'] );
				if ( $duplicate_check && ! $id ) {
					$this->log_debug( 'validate', 'Title validation failed - duplicate', [ 'title' => $data['title'] ] );
				return false;
			}
		}

		// Validate status.
		if ( isset( $data['status'] ) ) {
			$valid_statuses = [ 'pending', 'approved', 'denied', 'generating', 'generated', 'failed' ];
			if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
				$this->log_debug( 'validate', 'Status validation failed', [
					'status' => $data['status'],
					'valid_statuses' => $valid_statuses
				] );
				return false;
			}
		}

			// Validate category_id exists.
			if ( isset( $data['category_id'] ) && $data['category_id'] ) {
				if ( ! get_term( $data['category_id'], 'category' ) ) {
					$this->log_debug( 'validate', 'Category validation failed', [ 'category_id' => $data['category_id'] ] );
					return false;
				}
			}

			$this->log_debug( 'validate', 'Validation passed', [ 'data' => $data ] );
			return true;

		} catch ( \Exception $e ) {
			$this->log_debug( 'validate', 'Exception in validate method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			return false;
		}
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return mixed
	 */
	protected function sanitize_field( $field, $value ) {
		$this->log_debug( 'sanitize_field', 'Method called', [
			'field' => $field,
			'value' => $value,
			'value_type' => gettype( $value )
		] );

		try {
			$sanitized_value = null;

		switch ( $field ) {
			case 'title':
			case 'description':
					$sanitized_value = sanitize_text_field( $value );
					break;
			
			case 'category_id':
				case 'persona_id':
					$sanitized_value = absint( $value );
					break;
			
			case 'status':
					$sanitized_value = sanitize_key( $value );
					break;
			
			default:
					$sanitized_value = parent::sanitize_field( $field, $value );
					break;
			}

			$this->log_debug( 'sanitize_field', 'Field sanitized', [
				'field' => $field,
				'original_value' => $value,
				'sanitized_value' => $sanitized_value
			] );

			return $sanitized_value;

		} catch ( \Exception $e ) {
			$this->log_debug( 'sanitize_field', 'Exception in sanitize_field method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
				return parent::sanitize_field( $field, $value );
		}
	}
} 