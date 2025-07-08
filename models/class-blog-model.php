<?php
/**
 * Blog Model
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
 * Blog Model Class
 *
 * Handles generated blog post data operations.
 *
 * @since 1.0.0
 */
class Blog_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'generated_posts';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'idea_id',
		'post_id',
		'scheduled_time',
		'status',
		'cost',
	];

	/**
	 * Log to debug file for comprehensive debugging
	 */
	private function log_debug( $method, $message, $data = [] ) {
		$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
		$timestamp = date( 'Y-m-d H:i:s' );
		$log_entry = "$timestamp - BLOG_MODEL::$method - $message\n";
		if ( ! empty( $data ) ) {
			// Create safe representation of data to avoid binary corruption
			$safe_data = $this->make_data_safe_for_logging( $data );
			$log_entry .= "$timestamp - BLOG_MODEL::$method - DATA: " . json_encode( $safe_data, JSON_UNESCAPED_SLASHES ) . "\n";
		}
		file_put_contents( $debug_log, $log_entry, FILE_APPEND );
	}
	
	/**
	 * Create a safe representation of data for logging (avoid binary corruption)
	 */
	private function make_data_safe_for_logging( $data ) {
		if ( is_object( $data ) ) {
			if ( isset( $data->id ) && isset( $data->post_title ) ) {
				return "Object[id={$data->id}, title=" . substr( $data->post_title ?? 'null', 0, 50 ) . "]";
			} elseif ( isset( $data->id ) ) {
				return "Object[id={$data->id}]";
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
	 * Create blog record with comprehensive debugging
	 */
	public function create( $data ) {
		$this->log_debug( 'create', 'Method called', [
			'data' => $data,
			'data_keys' => array_keys( $data ),
			'memory_usage' => memory_get_usage()
		] );

		try {
			// Validate input
			if ( empty( $data ) || ! is_array( $data ) ) {
				$this->log_debug( 'create', 'Invalid data provided', [ 'data' => $data ] );
				return false;
			}

			$this->log_debug( 'create', 'Calling parent::create method' );
			$result = parent::create( $data );
			
			$this->log_debug( 'create', 'Parent create method completed', [
				'result' => $result,
				'result_type' => gettype( $result )
			] );

			return $result;

		} catch ( \Exception $e ) {
			$this->log_debug( 'create', 'Exception in create method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Update blog record with comprehensive debugging
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
	 * Get blog post with related data.
	 *
	 * @param int $id Blog ID.
	 * @return object|null
	 */
	public function get_with_details( $id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT gp.*, 
					p.post_title, 
					p.post_status as wp_status,
					p.post_date,
					p.post_modified,
					i.title as idea_title,
					i.description as idea_description,
					i.category_id
			FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " gp
			LEFT JOIN {$wpdb->posts} p ON gp.post_id = p.ID
			LEFT JOIN " . AI_BLOG_GENERATOR_TABLE_IDEAS . " i ON gp.idea_id = i.id
			WHERE gp.id = %d",
			$id
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * Get all blogs with related data.
	 *
	 * @param array  $filters Filters (status, date_from, date_to).
	 * @param string $order_by Order by clause.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_all_with_details( $filters = [], $order_by = 'gp.created_at DESC', $limit = null ) {
		global $wpdb;

		$sql = "SELECT gp.*, 
				p.post_title, 
				p.post_status as wp_status,
				p.post_date,
				p.post_modified,
				p.guid as post_url,
				i.title as idea_title,
				i.description as idea_description,
				i.category_id
		FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " gp
		LEFT JOIN {$wpdb->posts} p ON gp.post_id = p.ID
		LEFT JOIN " . AI_BLOG_GENERATOR_TABLE_IDEAS . " i ON gp.idea_id = i.id
		WHERE 1=1";

		// Apply filters.
		if ( ! empty( $filters['status'] ) ) {
			$sql .= $wpdb->prepare( " AND gp.status = %s", $filters['status'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$sql .= $wpdb->prepare( " AND DATE(gp.created_at) >= %s", $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$sql .= $wpdb->prepare( " AND DATE(gp.created_at) <= %s", $filters['date_to'] );
		}

		if ( $order_by ) {
			$sql .= " ORDER BY $order_by";
		}

		if ( $limit ) {
			$sql .= $wpdb->prepare( " LIMIT %d", $limit );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get posts by status.
	 *
	 * @param string $status Post status.
	 * @return array
	 */
	public function get_by_status( $status ) {
		return $this->db->get_posts_by_status( $status );
	}

	/**
	 * Get scheduled posts that are due.
	 *
	 * @return array
	 */
	public function get_due_scheduled_posts() {
		global $wpdb;

		$sql = "SELECT gp.*, p.post_title 
				FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " gp
				LEFT JOIN {$wpdb->posts} p ON gp.post_id = p.ID
				WHERE gp.status = 'scheduled' 
				AND gp.scheduled_time <= NOW()
				ORDER BY gp.scheduled_time ASC";

		return $wpdb->get_results( $sql );
	}

	/**
	 * Update post status.
	 *
	 * @param int    $id     Blog ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function update_status( $id, $status ) {
		$valid_statuses = [ 'draft', 'scheduled', 'published' ];
		
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		return $this->update( $id, [ 'status' => $status ] );
	}

	/**
	 * Schedule a post.
	 *
	 * @param int    $id             Blog ID.
	 * @param string $scheduled_time Scheduled time (Y-m-d H:i:s).
	 * @return bool
	 */
	public function schedule( $id, $scheduled_time ) {
		// Validate scheduled time is in the future.
		$scheduled_timestamp = strtotime( $scheduled_time );
		if ( $scheduled_timestamp <= time() ) {
			return false;
		}

		return $this->update( $id, [
			'scheduled_time' => $scheduled_time,
			'status' => 'scheduled',
		] );
	}

	/**
	 * Mark post as published.
	 *
	 * @param int $id Blog ID.
	 * @return bool
	 */
	public function mark_published( $id ) {
		return $this->update_status( $id, 'published' );
	}

	/**
	 * Get total cost for a date range.
	 *
	 * @param string|array $date_from Start date or filters array.
	 * @param string       $date_to   End date.
	 * @return float
	 */
	public function get_total_cost( $date_from = null, $date_to = null ) {
		global $wpdb;
		
		// Handle array parameter for filters
		$filters = null;
		if ( is_array( $date_from ) ) {
			$filters = $date_from;
			$date_from = $filters['date_from'] ?? null;
			$date_to = $filters['date_to'] ?? null;
		}

		$sql = "SELECT SUM(cost) as total FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " WHERE 1=1";
		
		// Handle status filter
		if ( is_array( $filters ) && ! empty( $filters['status'] ) ) {
			if ( is_array( $filters['status'] ) ) {
				// Handle array of statuses
				$placeholders = array_fill( 0, count( $filters['status'] ), '%s' );
				$sql .= $wpdb->prepare( " AND status IN (" . implode( ',', $placeholders ) . ")", $filters['status'] );
			} else {
				// Handle single status
				$sql .= $wpdb->prepare( " AND status = %s", $filters['status'] );
			}
		}

		if ( $date_from ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $date_from );
		}

		if ( $date_to ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $date_to );
		}

		$result = $wpdb->get_var( $sql );
		return floatval( $result ?: 0 );
	}

	/**
	 * Get statistics.
	 *
	 * @param string $period Period (day, week, month, year).
	 * @return array
	 */
	public function get_statistics( $period = 'month' ) {
		global $wpdb;

		$date_format = '%Y-%m';
		switch ( $period ) {
			case 'day':
				$date_format = '%Y-%m-%d';
				break;
			case 'week':
				$date_format = '%Y-%u';
				break;
			case 'year':
				$date_format = '%Y';
				break;
		}

		$sql = $wpdb->prepare(
			"SELECT 
				DATE_FORMAT(created_at, %s) as period,
				COUNT(*) as total_posts,
				SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
				SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
				SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_count,
				SUM(cost) as total_cost
			FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . "
			GROUP BY period
			ORDER BY period DESC",
			$date_format
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Validate data.
	 *
	 * @param array    $data Data to validate.
	 * @param int|null $id   Record ID for updates.
	 * @return bool
	 */
	protected function validate( $data, $id = null ) {
		// Validate idea_id exists.
		if ( isset( $data['idea_id'] ) ) {
			$idea_model = new Idea_Model();
			if ( ! $idea_model->exists( [ 'id' => $data['idea_id'] ] ) ) {
				return false;
			}
		}

		// Validate post_id exists in WordPress.
		if ( isset( $data['post_id'] ) && $data['post_id'] ) {
			if ( ! get_post( $data['post_id'] ) ) {
				return false;
			}
		}

		// Validate status.
		if ( isset( $data['status'] ) ) {
			$valid_statuses = [ 'draft', 'scheduled', 'published' ];
			if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
				return false;
			}
		}

		// Validate cost.
		if ( isset( $data['cost'] ) && $data['cost'] < 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Count posts generated today.
	 *
	 * @return int
	 */
	public function count_generated_today() {
		$this->log_debug( 'count_generated_today', 'Method called', [
			'memory_usage' => memory_get_usage()
		] );

		try {
			global $wpdb;

			$today = date( 'Y-m-d' );
			$this->log_debug( 'count_generated_today', 'Date calculated', [ 'today' => $today ] );

			$table_name = AI_BLOG_GENERATOR_TABLE_POSTS;
			$this->log_debug( 'count_generated_today', 'Table name resolved', [ 'table' => $table_name ] );

			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
				$today
			);

			$this->log_debug( 'count_generated_today', 'SQL query prepared', [
				'sql' => $sql,
				'today_param' => $today
			] );

			$count = (int) $wpdb->get_var( $sql );

			$this->log_debug( 'count_generated_today', 'Query executed', [
				'count' => $count,
				'count_type' => gettype( $count ),
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $count;

		} catch ( \Exception $e ) {
			$this->log_debug( 'count_generated_today', 'Exception in count_generated_today method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Count published posts today.
	 *
	 * @return int
	 */
	public function count_published_today() {
		global $wpdb;

		$today = date( 'Y-m-d' );
		$table_name = AI_BLOG_GENERATOR_TABLE_POSTS;

		// Query posts that are published and have WordPress posts published today
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) 
			FROM {$table_name} gp
			INNER JOIN {$wpdb->posts} p ON gp.post_id = p.ID
			WHERE gp.status = 'published' 
			AND p.post_status = 'publish'
			AND DATE(p.post_date) = %s",
			$today
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get posts scheduled for publishing.
	 *
	 * @return array
	 */
	public function get_scheduled_for_publishing() {
		global $wpdb;

		$sql = "SELECT gp.*, p.post_title 
				FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " gp
				LEFT JOIN {$wpdb->posts} p ON gp.post_id = p.ID
				WHERE gp.status = 'scheduled' 
				AND gp.scheduled_time <= NOW()
				ORDER BY gp.scheduled_time ASC";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get blog record by WordPress post ID.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array|null
	 */
	public function get_by_post_id( $post_id ) {
		return $this->find( [ 'post_id' => $post_id ] );
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return mixed
	 */
	protected function sanitize_field( $field, $value ) {
		switch ( $field ) {
			case 'idea_id':
			case 'post_id':
				return absint( $value );
			
			case 'scheduled_time':
				return sanitize_text_field( $value );
			
			case 'status':
				return sanitize_key( $value );
			
			case 'cost':
				return floatval( $value );
			
			default:
				return parent::sanitize_field( $field, $value );
		}
	}
}