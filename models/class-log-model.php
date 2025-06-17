<?php
/**
 * Log Model
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
 * Log Model Class
 *
 * Handles log data operations.
 *
 * @since 1.0.0
 */
class Log_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'logs';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'action',
		'message',
		'level',
		'context',
	];

	/**
	 * Log an info message.
	 *
	 * @param string $action  Action name.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return int|false
	 */
	public function info( $action, $message, $context = [] ) {
		return $this->log( $action, $message, 'info', $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $action  Action name.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return int|false
	 */
	public function warning( $action, $message, $context = [] ) {
		return $this->log( $action, $message, 'warning', $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $action  Action name.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return int|false
	 */
	public function error( $action, $message, $context = [] ) {
		return $this->log( $action, $message, 'error', $context );
	}

	/**
	 * Log a message.
	 *
	 * @param string $action  Action name.
	 * @param string $message Log message.
	 * @param string $level   Log level.
	 * @param array  $context Additional context data.
	 * @return int|false
	 */
	public function log( $action, $message, $level = 'info', $context = [] ) {
		$data = [
			'action'  => $action,
			'message' => $message,
			'level'   => $level,
			'context' => ! empty( $context ) ? wp_json_encode( $context ) : null,
		];

		return $this->create( $data );
	}

	/**
	 * Get logs with filtering.
	 *
	 * @param array $filters Filters (level, action, date_from, date_to, search).
	 * @param int   $limit   Number of logs to retrieve (0 for no limit).
	 * @param int   $offset  Offset for pagination.
	 * @return array
	 */
	public function get_filtered( $filters = [], $limit = 100, $offset = 0 ) {
		global $wpdb;

		$sql = "SELECT id, action, message, level, context, created_at 
				FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " WHERE 1=1";

		// Apply filters.
		if ( ! empty( $filters['level'] ) ) {
			$sql .= $wpdb->prepare( " AND level = %s", $filters['level'] );
		}

		if ( ! empty( $filters['action'] ) ) {
			$sql .= $wpdb->prepare( " AND action LIKE %s", '%' . $wpdb->esc_like( $filters['action'] ) . '%' );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			// Handle both date and datetime formats
			if ( strlen( $filters['date_from'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at >= %s", $filters['date_from'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $filters['date_from'] );
			}
		}

		if ( ! empty( $filters['date_to'] ) ) {
			// Handle both date and datetime formats
			if ( strlen( $filters['date_to'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at <= %s", $filters['date_to'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $filters['date_to'] );
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$sql .= $wpdb->prepare( 
				" AND (message LIKE %s OR action LIKE %s OR context LIKE %s)", 
				$search, $search, $search 
			);
		}

		$sql .= " ORDER BY created_at DESC";
		
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $limit, $offset );
		}

		$results = $wpdb->get_results( $sql );

		// Decode context JSON and add additional data.
		foreach ( $results as &$log ) {
			if ( ! empty( $log->context ) ) {
				$log->context_data = json_decode( $log->context, true );
			} else {
				$log->context_data = [];
			}
		}

		return $results;
	}

	/**
	 * Get log count with filtering.
	 *
	 * @param array $filters Filters (level, action, date_from, date_to, search).
	 * @return int
	 */
	public function count_filtered( $filters = [] ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " WHERE 1=1";

		// Apply filters.
		if ( ! empty( $filters['level'] ) ) {
			$sql .= $wpdb->prepare( " AND level = %s", $filters['level'] );
		}

		if ( ! empty( $filters['action'] ) ) {
			$sql .= $wpdb->prepare( " AND action LIKE %s", '%' . $wpdb->esc_like( $filters['action'] ) . '%' );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			// Handle both date and datetime formats
			if ( strlen( $filters['date_from'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at >= %s", $filters['date_from'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $filters['date_from'] );
			}
		}

		if ( ! empty( $filters['date_to'] ) ) {
			// Handle both date and datetime formats
			if ( strlen( $filters['date_to'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at <= %s", $filters['date_to'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $filters['date_to'] );
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$sql .= $wpdb->prepare( 
				" AND (message LIKE %s OR action LIKE %s OR context LIKE %s)", 
				$search, $search, $search 
			);
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Clean old logs.
	 *
	 * @param int $days Number of days to keep logs.
	 * @return int Number of logs deleted.
	 */
	public function clean_old_logs( $days = 30 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"DELETE FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " 
			WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		);

		$deleted = $wpdb->query( $sql );

		if ( $deleted > 0 ) {
			$this->info( 'log_cleanup', sprintf( 'Deleted %d old logs', $deleted ), [ 'days_kept' => $days ] );
		}

		return $deleted;
	}

	/**
	 * Get logs by level.
	 *
	 * @param string $level Log level.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_by_level( $level, $limit = 100 ) {
		return $this->find_all( [ 'level' => $level ], 'created_at DESC', $limit );
	}

	/**
	 * Get recent errors.
	 *
	 * @param int $limit Number of errors to retrieve.
	 * @return array
	 */
	public function get_recent_errors( $limit = 10 ) {
		return $this->get_by_level( 'error', $limit );
	}

	/**
	 * Get logs by action.
	 *
	 * @param string $action Action name.
	 * @param int    $limit  Limit.
	 * @return array
	 */
	public function get_by_action( $action, $limit = 100 ) {
		return $this->find_all( [ 'action' => $action ], 'created_at DESC', $limit );
	}

	/**
	 * Get unique actions.
	 *
	 * @return array
	 */
	public function get_unique_actions() {
		global $wpdb;

		$sql = "SELECT DISTINCT action 
				FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " 
				ORDER BY action ASC";

		return $wpdb->get_col( $sql );
	}

	/**
	 * Get log statistics.
	 *
	 * @param string $period Period (hour, day, week, month).
	 * @return array
	 */
	public function get_statistics( $period = 'day' ) {
		global $wpdb;

		$date_format = '%Y-%m-%d';
		switch ( $period ) {
			case 'hour':
				$date_format = '%Y-%m-%d %H:00:00';
				break;
			case 'week':
				$date_format = '%Y-%u';
				break;
			case 'month':
				$date_format = '%Y-%m';
				break;
		}

		$sql = $wpdb->prepare(
			"SELECT 
				DATE_FORMAT(created_at, %s) as period,
				level,
				COUNT(*) as count
			FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . "
			GROUP BY period, level
			ORDER BY period DESC, level",
			$date_format
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Export logs.
	 *
	 * @param array $filters Filters for logs.
	 * @return array Array of logs for export.
	 */
	public function export( $filters = [] ) {
		$logs = $this->get_filtered( $filters, 10000, 0 ); // Large limit for export.

		$export_data = [];
		foreach ( $logs as $log ) {
			$export_data[] = [
				'timestamp' => $log->created_at,
				'level'     => $log->level,
				'action'    => $log->action,
				'message'   => $log->message,
				'context'   => $log->context_data,
			];
		}

		return $export_data;
	}

	/**
	 * Validate data.
	 *
	 * @param array    $data Data to validate.
	 * @param int|null $id   Record ID for updates.
	 * @return bool
	 */
	protected function validate( $data, $id = null ) {
		// Action is required.
		if ( isset( $data['action'] ) && empty( $data['action'] ) ) {
			return false;
		}

		// Message is required.
		if ( isset( $data['message'] ) && empty( $data['message'] ) ) {
			return false;
		}

		// Validate level.
		if ( isset( $data['level'] ) ) {
			$valid_levels = [ 'info', 'warning', 'error' ];
			if ( ! in_array( $data['level'], $valid_levels, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get distinct actions for filtering.
	 *
	 * @return array
	 */
	public function get_distinct_actions() {
		global $wpdb;

		$sql = "SELECT DISTINCT action 
				FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " 
				ORDER BY action ASC";

		return $wpdb->get_col( $sql );
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
			case 'action':
				return sanitize_key( $value );
			
			case 'message':
				return sanitize_text_field( $value );
			
			case 'level':
				return sanitize_key( $value );
			
			case 'context':
				// Context is already JSON encoded, just sanitize as text.
				return sanitize_text_field( $value );
			
			default:
				return parent::sanitize_field( $field, $value );
		}
	}

	/**
	 * Get level statistics with optional filtering.
	 *
	 * @param array $filters Filters to apply to statistics.
	 * @return array Statistics array with level counts.
	 */
	public function get_level_statistics( $filters = [] ) {
		global $wpdb;

		$sql = "SELECT 
					level,
					COUNT(*) as count
				FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " 
				WHERE 1=1";

		// Apply same filters as get_filtered method
		if ( ! empty( $filters['action'] ) ) {
			$sql .= $wpdb->prepare( " AND action LIKE %s", '%' . $wpdb->esc_like( $filters['action'] ) . '%' );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			if ( strlen( $filters['date_from'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at >= %s", $filters['date_from'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $filters['date_from'] );
			}
		}

		if ( ! empty( $filters['date_to'] ) ) {
			if ( strlen( $filters['date_to'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at <= %s", $filters['date_to'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $filters['date_to'] );
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$sql .= $wpdb->prepare( 
				" AND (message LIKE %s OR action LIKE %s OR context LIKE %s)", 
				$search, $search, $search 
			);
		}

		$sql .= " GROUP BY level";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Format results for frontend
		$stats = [
			'info' => 0,
			'warning' => 0,
			'error' => 0,
			'total' => 0
		];

		foreach ( $results as $result ) {
			$level = $result['level'];
			$count = (int) $result['count'];
			
			if ( isset( $stats[ $level ] ) ) {
				$stats[ $level ] = $count;
			}
			$stats['total'] += $count;
		}

		return $stats;
	}

	/**
	 * Check if there are new logs since a given log ID.
	 *
	 * @param int   $last_log_id Last known log ID.
	 * @param array $filters     Filters to apply.
	 * @return bool True if there are new logs.
	 */
	public function has_new_logs_since( $last_log_id, $filters = [] ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " WHERE id > %d";

		// Apply same filters as get_filtered method but exclude level filter for live updates
		if ( ! empty( $filters['action'] ) ) {
			$sql .= $wpdb->prepare( " AND action LIKE %s", '%' . $wpdb->esc_like( $filters['action'] ) . '%' );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			if ( strlen( $filters['date_from'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at >= %s", $filters['date_from'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $filters['date_from'] );
			}
		}

		if ( ! empty( $filters['date_to'] ) ) {
			if ( strlen( $filters['date_to'] ) > 10 ) {
				$sql .= $wpdb->prepare( " AND created_at <= %s", $filters['date_to'] );
			} else {
				$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $filters['date_to'] );
			}
		}

		if ( ! empty( $filters['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$sql .= $wpdb->prepare( 
				" AND (message LIKE %s OR action LIKE %s OR context LIKE %s)", 
				$search, $search, $search 
			);
		}

		$prepared_sql = $wpdb->prepare( $sql, $last_log_id );
		$count = (int) $wpdb->get_var( $prepared_sql );

		return $count > 0;
	}
} 