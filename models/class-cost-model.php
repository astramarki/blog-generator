<?php
/**
 * Cost Model
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
 * Cost Model Class
 *
 * Handles cost analytics data operations.
 *
 * @since 1.0.0
 */
class Cost_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'cost_analytics';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'service',
		'action',
		'tokens_used',
		'cost',
	];

	/**
	 * Record Anthropic API cost.
	 *
	 * @param string $action      Action name.
	 * @param float  $cost        Cost amount.
	 * @param int    $tokens_used Tokens used.
	 * @return int|false
	 */
	public function record_anthropic_cost( $action, $cost, $tokens_used = 0 ) {
		return $this->record_cost( 'anthropic', $action, $cost, $tokens_used );
	}

	/**
	 * Record OpenAI API cost.
	 *
	 * @param string $action Action name.
	 * @param float  $cost   Cost amount.
	 * @return int|false
	 */
	public function record_openai_cost( $action, $cost ) {
		return $this->record_cost( 'openai', $action, $cost, 0 );
	}

	/**
	 * Record API cost.
	 *
	 * @param string $service     Service name.
	 * @param string $action      Action name.
	 * @param float  $cost        Cost amount.
	 * @param int    $tokens_used Tokens used.
	 * @return int|false
	 */
	public function record_cost( $service, $action, $cost, $tokens_used = 0 ) {
		$data = [
			'service'     => $service,
			'action'      => $action,
			'cost'        => $cost,
			'tokens_used' => $tokens_used,
		];

		return $this->create( $data );
	}

	/**
	 * Get costs by date range.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array
	 */
	public function get_by_date_range( $start_date, $end_date ) {
		return $this->db->get_costs_by_date_range( $start_date, $end_date );
	}

	/**
	 * Get total cost.
	 *
	 * @param string|null $service    Service filter.
	 * @param string|null $start_date Start date.
	 * @param string|null $end_date   End date.
	 * @return float
	 */
	public function get_total_cost( $service = null, $start_date = null, $end_date = null ) {
		global $wpdb;

		$sql = "SELECT SUM(cost) FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . " WHERE 1=1";

		if ( $service ) {
			$sql .= $wpdb->prepare( " AND service = %s", $service );
		}

		if ( $start_date ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $start_date );
		}

		if ( $end_date ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $end_date );
		}

		$total = $wpdb->get_var( $sql );
		return floatval( $total ?: 0 );
	}

	/**
	 * Get cost breakdown by service.
	 *
	 * @param string|null $start_date Start date.
	 * @param string|null $end_date   End date.
	 * @return array
	 */
	public function get_breakdown_by_service( $start_date = null, $end_date = null ) {
		global $wpdb;

		$sql = "SELECT 
				service,
				COUNT(*) as request_count,
				SUM(tokens_used) as total_tokens,
				SUM(cost) as total_cost,
				AVG(cost) as avg_cost
			FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . " 
			WHERE 1=1";

		if ( $start_date ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $start_date );
		}

		if ( $end_date ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $end_date );
		}

		$sql .= " GROUP BY service";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get cost breakdown by action.
	 *
	 * @param string      $service    Service filter.
	 * @param string|null $start_date Start date.
	 * @param string|null $end_date   End date.
	 * @return array
	 */
	public function get_breakdown_by_action( $service = null, $start_date = null, $end_date = null ) {
		global $wpdb;

		$sql = "SELECT 
				action,
				service,
				COUNT(*) as request_count,
				SUM(tokens_used) as total_tokens,
				SUM(cost) as total_cost,
				AVG(cost) as avg_cost
			FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . " 
			WHERE 1=1";

		if ( $service ) {
			$sql .= $wpdb->prepare( " AND service = %s", $service );
		}

		if ( $start_date ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) >= %s", $start_date );
		}

		if ( $end_date ) {
			$sql .= $wpdb->prepare( " AND DATE(created_at) <= %s", $end_date );
		}

		$sql .= " GROUP BY action, service ORDER BY total_cost DESC";

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get daily cost statistics.
	 *
	 * @param int $days Number of days to retrieve.
	 * @return array
	 */
	public function get_daily_stats( $days = 30 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT 
				DATE(created_at) as date,
				service,
				COUNT(*) as request_count,
				SUM(tokens_used) as total_tokens,
				SUM(cost) as total_cost
			FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . "
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			GROUP BY date, service
			ORDER BY date DESC",
			$days
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get monthly cost statistics.
	 *
	 * @param int $months Number of months to retrieve.
	 * @return array
	 */
	public function get_monthly_stats( $months = 12 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT 
				DATE_FORMAT(created_at, '%%Y-%%m') as month,
				service,
				COUNT(*) as request_count,
				SUM(tokens_used) as total_tokens,
				SUM(cost) as total_cost
			FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . "
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
			GROUP BY month, service
			ORDER BY month DESC",
			$months
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get cost trends.
	 *
	 * @param string $period Period (day, week, month).
	 * @param int    $limit  Number of periods to retrieve.
	 * @return array
	 */
	public function get_trends( $period = 'day', $limit = 30 ) {
		global $wpdb;

		$date_format = '%Y-%m-%d';
		$interval = 'DAY';

		switch ( $period ) {
			case 'week':
				$date_format = '%Y-%u';
				$interval = 'WEEK';
				break;
			case 'month':
				$date_format = '%Y-%m';
				$interval = 'MONTH';
				break;
		}

		$sql = $wpdb->prepare(
			"SELECT 
				DATE_FORMAT(created_at, %s) as period,
				SUM(CASE WHEN service = 'anthropic' THEN cost ELSE 0 END) as anthropic_cost,
				SUM(CASE WHEN service = 'openai' THEN cost ELSE 0 END) as openai_cost,
				SUM(cost) as total_cost
			FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . "
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d $interval)
			GROUP BY period
			ORDER BY period ASC",
			$date_format,
			$limit
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get budget usage.
	 *
	 * @param float  $budget      Monthly budget.
	 * @param string $month       Month (Y-m format).
	 * @return array Budget usage information.
	 */
	public function get_budget_usage( $budget, $month = null ) {
		if ( ! $month ) {
			$month = date( 'Y-m' );
		}

		$start_date = $month . '-01';
		$end_date = date( 'Y-m-t', strtotime( $start_date ) );

		$total_cost = $this->get_total_cost( null, $start_date, $end_date );
		$percentage = $budget > 0 ? ( $total_cost / $budget ) * 100 : 0;

		// Calculate daily average and projection.
		$days_in_month = date( 't', strtotime( $start_date ) );
		$days_passed = min( date( 'j' ), $days_in_month );
		$daily_average = $days_passed > 0 ? $total_cost / $days_passed : 0;
		$projected_total = $daily_average * $days_in_month;

		return [
			'budget'          => $budget,
			'spent'           => $total_cost,
			'remaining'       => max( 0, $budget - $total_cost ),
			'percentage'      => round( $percentage, 2 ),
			'daily_average'   => round( $daily_average, 4 ),
			'projected_total' => round( $projected_total, 2 ),
			'days_remaining'  => $days_in_month - $days_passed,
		];
	}

	/**
	 * Clean old cost records.
	 *
	 * @param int $days Number of days to keep records.
	 * @return int Number of records deleted.
	 */
	public function clean_old_records( $days = 365 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"DELETE FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . " 
			WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		);

		return $wpdb->query( $sql );
	}

	/**
	 * Validate data.
	 *
	 * @param array    $data Data to validate.
	 * @param int|null $id   Record ID for updates.
	 * @return bool
	 */
	protected function validate( $data, $id = null ) {
		// Validate service.
		if ( isset( $data['service'] ) ) {
			$valid_services = [ 'anthropic', 'openai' ];
			if ( ! in_array( $data['service'], $valid_services, true ) ) {
				return false;
			}
		}

		// Action is required.
		if ( isset( $data['action'] ) && empty( $data['action'] ) ) {
			return false;
		}

		// Cost must be non-negative.
		if ( isset( $data['cost'] ) && $data['cost'] < 0 ) {
			return false;
		}

		// Tokens must be non-negative.
		if ( isset( $data['tokens_used'] ) && $data['tokens_used'] < 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get cost breakdown for current month.
	 *
	 * @param string $period Period type.
	 * @return array
	 */
	public function get_cost_breakdown( $period = 'current_month' ) {
		$start_date = date( 'Y-m-01' );
		$end_date = date( 'Y-m-t' );
		
		if ( 'last_month' === $period ) {
			$start_date = date( 'Y-m-01', strtotime( '-1 month' ) );
			$end_date = date( 'Y-m-t', strtotime( '-1 month' ) );
		}
		
		return $this->get_breakdown_by_action( null, $start_date, $end_date );
	}

	/**
	 * Get daily costs for chart.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public function get_daily_costs( $days = 30 ) {
		return $this->get_daily_stats( $days );
	}

	/**
	 * Get cost by service for date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_cost_by_service( $start_date, $end_date ) {
		return $this->get_breakdown_by_service( $start_date, $end_date );
	}

	/**
	 * Project monthly cost based on current usage.
	 *
	 * @return array
	 */
	public function project_monthly_cost() {
		$current_month = date( 'Y-m' );
		$budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
		
		return $this->get_budget_usage( $budget, $current_month );
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
			case 'service':
			case 'action':
				return sanitize_key( $value );
			
			case 'tokens_used':
				return absint( $value );
			
			case 'cost':
				return floatval( $value );
			
			default:
				return parent::sanitize_field( $field, $value );
		}
	}
} 