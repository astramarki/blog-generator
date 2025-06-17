<?php
/**
 * Analytics Controller Class
 *
 * Handles cost tracking and usage analytics operations.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Models\Log_Model;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics Controller Class
 */
class Analytics_Controller {

	/**
	 * Cost model instance.
	 *
	 * @var Cost_Model
	 */
	private $cost_model;

	/**
	 * Log model instance.
	 *
	 * @var Log_Model
	 */
	private $log_model;

	/**
	 * Blog model instance.
	 *
	 * @var Blog_Model
	 */
	private $blog_model;

	/**
	 * Idea model instance.
	 *
	 * @var Idea_Model
	 */
	private $idea_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cost_model = new Cost_Model();
		$this->log_model = new Log_Model();
		$this->blog_model = new Blog_Model();
		$this->idea_model = new Idea_Model();
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Cost analytics
		add_action( 'wp_ajax_ai_blog_get_cost_summary', [ $this, 'get_cost_summary' ] );
		add_action( 'wp_ajax_ai_blog_get_cost_details', [ $this, 'get_cost_details' ] );
		add_action( 'wp_ajax_ai_blog_get_cost_by_date', [ $this, 'get_cost_by_date' ] );
		add_action( 'wp_ajax_ai_blog_get_cost_by_service', [ $this, 'get_cost_by_service' ] );
		
		// Usage analytics
		add_action( 'wp_ajax_ai_blog_get_usage_statistics', [ $this, 'get_usage_statistics' ] );
		add_action( 'wp_ajax_ai_blog_get_generation_metrics', [ $this, 'get_generation_metrics' ] );
		add_action( 'wp_ajax_ai_blog_get_performance_data', [ $this, 'get_performance_data' ] );
		
		// Budget tracking
		add_action( 'wp_ajax_ai_blog_get_budget_status', [ $this, 'get_budget_status' ] );
		add_action( 'wp_ajax_ai_blog_reset_monthly_budget', [ $this, 'reset_monthly_budget' ] );
		
		// Reports
		add_action( 'wp_ajax_ai_blog_export_analytics_report', [ $this, 'export_analytics_report' ] );
		add_action( 'wp_ajax_ai_blog_email_monthly_report', [ $this, 'email_monthly_report' ] );
	}

	/**
	 * Get cost summary.
	 */
	public function get_cost_summary() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get date range
		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'month';
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
		$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';

		// Calculate dates based on period
		if ( ! $start_date || ! $end_date ) {
			switch ( $period ) {
				case 'week':
					$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
					$end_date = date( 'Y-m-d' );
					break;
				case 'month':
					$start_date = date( 'Y-m-01' );
					$end_date = date( 'Y-m-d' );
					break;
				case 'year':
					$start_date = date( 'Y-01-01' );
					$end_date = date( 'Y-m-d' );
					break;
				default:
					$start_date = date( 'Y-m-01' );
					$end_date = date( 'Y-m-d' );
			}
		}

		// Get cost summary
		$total_cost = $this->cost_model->get_total_cost( $start_date, $end_date );
		$costs_by_service = $this->cost_model->get_costs_by_service( $start_date, $end_date );
		$costs_by_type = $this->cost_model->get_costs_by_type( $start_date, $end_date );
		
		// Get budget info
		$monthly_budget = get_option( 'ai_blog_monthly_budget', 100 );
		$current_month_cost = $this->cost_model->get_total_cost( date( 'Y-m-01' ), date( 'Y-m-d' ) );
		$budget_remaining = max( 0, $monthly_budget - $current_month_cost );
		$budget_percentage = $monthly_budget > 0 ? ( $current_month_cost / $monthly_budget ) * 100 : 0;

		// Get token usage
		$token_usage = $this->cost_model->get_token_usage( $start_date, $end_date );

		wp_send_json_success( [
			'summary' => [
				'total_cost' => $total_cost,
				'period' => $period,
				'start_date' => $start_date,
				'end_date' => $end_date,
			],
			'budget' => [
				'monthly_limit' => $monthly_budget,
				'current_month_cost' => $current_month_cost,
				'remaining' => $budget_remaining,
				'percentage_used' => round( $budget_percentage, 2 ),
			],
			'costs_by_service' => $costs_by_service,
			'costs_by_type' => $costs_by_type,
			'token_usage' => $token_usage,
		] );
	}

	/**
	 * Get detailed cost information.
	 */
	public function get_cost_details() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get pagination parameters
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		$service = isset( $_POST['service'] ) ? sanitize_text_field( $_POST['service'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
		$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';

		// Build conditions
		$conditions = [];
		if ( $service ) {
			$conditions['service'] = $service;
		}
		if ( $type ) {
			$conditions['type'] = $type;
		}

		// Get costs
		$costs = $this->cost_model->get_detailed_costs( 
			$conditions, 
			$start_date, 
			$end_date, 
			$per_page, 
			( $page - 1 ) * $per_page 
		);
		
		$total = $this->cost_model->count_costs( $conditions, $start_date, $end_date );

		// Add related data
		foreach ( $costs as $cost ) {
			// Add related post/idea title if available
			if ( $cost->reference_id ) {
				if ( $cost->type === 'blog_generation' ) {
					$blog = $this->blog_model->get( $cost->reference_id );
					if ( $blog ) {
						$cost->reference_title = $blog->title;
					}
				} elseif ( $cost->type === 'idea_generation' ) {
					$idea = $this->idea_model->get( $cost->reference_id );
					if ( $idea ) {
						$cost->reference_title = $idea->title;
					}
				}
			}
		}

		wp_send_json_success( [
			'costs' => $costs,
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );
	}

	/**
	 * Get costs grouped by date.
	 */
	public function get_cost_by_date() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get parameters
		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'month';
		$group_by = isset( $_POST['group_by'] ) ? sanitize_text_field( $_POST['group_by'] ) : 'day';

		// Calculate date range
		switch ( $period ) {
			case 'week':
				$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
				$end_date = date( 'Y-m-d' );
				break;
			case 'month':
				$start_date = date( 'Y-m-01' );
				$end_date = date( 'Y-m-d' );
				break;
			case 'year':
				$start_date = date( 'Y-01-01' );
				$end_date = date( 'Y-m-d' );
				$group_by = 'month'; // Force monthly grouping for year view
				break;
			default:
				$start_date = date( 'Y-m-01' );
				$end_date = date( 'Y-m-d' );
		}

		// Get costs by date
		$costs_by_date = $this->cost_model->get_costs_by_date( $start_date, $end_date, $group_by );

		// Fill in missing dates with zero values
		$filled_data = [];
		$current_date = $start_date;
		
		while ( $current_date <= $end_date ) {
			$key = $group_by === 'month' ? date( 'Y-m', strtotime( $current_date ) ) : $current_date;
			$filled_data[ $key ] = 0;
			
			if ( $group_by === 'month' ) {
				$current_date = date( 'Y-m-d', strtotime( '+1 month', strtotime( $current_date ) ) );
			} else {
				$current_date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $current_date ) ) );
			}
		}

		// Merge actual data
		foreach ( $costs_by_date as $row ) {
			$filled_data[ $row->date ] = (float) $row->total_cost;
		}

		// Convert to array format for charts
		$chart_data = [
			'labels' => array_keys( $filled_data ),
			'data' => array_values( $filled_data ),
		];

		wp_send_json_success( [
			'chart_data' => $chart_data,
			'total' => array_sum( $filled_data ),
			'period' => $period,
			'group_by' => $group_by,
		] );
	}

	/**
	 * Get costs grouped by service.
	 */
	public function get_cost_by_service() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get date range
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : date( 'Y-m-01' );
		$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : date( 'Y-m-d' );

		// Get costs by service
		$costs_by_service = $this->cost_model->get_costs_by_service( $start_date, $end_date );

		// Format for pie chart
		$chart_data = [
			'labels' => [],
			'data' => [],
			'backgroundColor' => [],
		];

		$colors = [
			'openai' => '#10a37f',
			'claude' => '#734CF2',
			'dalle' => '#ff6b6b',
			'other' => '#95a5a6',
		];

		foreach ( $costs_by_service as $service => $cost ) {
			$chart_data['labels'][] = ucfirst( $service );
			$chart_data['data'][] = (float) $cost;
			$chart_data['backgroundColor'][] = $colors[ $service ] ?? $colors['other'];
		}

		wp_send_json_success( [
			'chart_data' => $chart_data,
			'costs_by_service' => $costs_by_service,
			'total' => array_sum( $costs_by_service ),
		] );
	}

	/**
	 * Get usage statistics.
	 */
	public function get_usage_statistics() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get date range
		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'all';
		
		// Calculate dates
		switch ( $period ) {
			case 'today':
				$start_date = date( 'Y-m-d' );
				$end_date = date( 'Y-m-d' );
				break;
			case 'week':
				$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
				$end_date = date( 'Y-m-d' );
				break;
			case 'month':
				$start_date = date( 'Y-m-01' );
				$end_date = date( 'Y-m-d' );
				break;
			default:
				$start_date = '';
				$end_date = '';
		}

		// Get statistics
		$stats = [
			'ideas' => [
				'total' => $this->idea_model->count_by_date_range( $start_date, $end_date ),
				'pending' => $this->idea_model->count_by_status( 'pending', $start_date, $end_date ),
				'approved' => $this->idea_model->count_by_status( 'approved', $start_date, $end_date ),
				'denied' => $this->idea_model->count_by_status( 'denied', $start_date, $end_date ),
				'generated' => $this->idea_model->count_by_status( 'generated', $start_date, $end_date ),
			],
			'blogs' => [
				'total' => $this->blog_model->count_by_date_range( $start_date, $end_date ),
				'queued' => $this->blog_model->count_by_status( 'queued', $start_date, $end_date ),
				'generating' => $this->blog_model->count_by_status( 'generating', $start_date, $end_date ),
				'ready' => $this->blog_model->count_by_status( 'ready', $start_date, $end_date ),
				'scheduled' => $this->blog_model->count_by_status( 'scheduled', $start_date, $end_date ),
				'published' => $this->blog_model->count_by_status( 'published', $start_date, $end_date ),
				'failed' => $this->blog_model->count_by_status( 'failed', $start_date, $end_date ),
			],
			'performance' => [
				'average_generation_time' => $this->blog_model->get_average_generation_time( $start_date, $end_date ),
				'success_rate' => $this->blog_model->get_success_rate( $start_date, $end_date ),
				'daily_average' => $this->blog_model->get_daily_average( $start_date, $end_date ),
			],
			'costs' => [
				'total' => $this->cost_model->get_total_cost( $start_date, $end_date ),
				'average_per_blog' => $this->cost_model->get_average_cost_per_blog( $start_date, $end_date ),
				'average_per_idea' => $this->cost_model->get_average_cost_per_idea( $start_date, $end_date ),
			],
		];

		// Add conversion rates
		if ( $stats['ideas']['total'] > 0 ) {
			$stats['ideas']['approval_rate'] = round( ( $stats['ideas']['approved'] / $stats['ideas']['total'] ) * 100, 2 );
			$stats['ideas']['generation_rate'] = round( ( $stats['ideas']['generated'] / $stats['ideas']['total'] ) * 100, 2 );
		} else {
			$stats['ideas']['approval_rate'] = 0;
			$stats['ideas']['generation_rate'] = 0;
		}

		wp_send_json_success( [
			'statistics' => $stats,
			'period' => $period,
			'date_range' => [
				'start' => $start_date,
				'end' => $end_date,
			],
		] );
	}

	/**
	 * Get generation metrics.
	 */
	public function get_generation_metrics() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get parameters
		$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
		$start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
		$end_date = date( 'Y-m-d' );

		// Get generation data by day
		$generation_data = $this->blog_model->get_generation_by_day( $start_date, $end_date );
		
		// Get category breakdown
		$category_breakdown = $this->blog_model->get_category_breakdown( $start_date, $end_date );
		
		// Get time distribution (hour of day)
		$time_distribution = $this->blog_model->get_generation_time_distribution( $start_date, $end_date );

		// Format data for charts
		$daily_chart = [
			'labels' => [],
			'datasets' => [
				[
					'label' => __( 'Ideas Generated', 'ai-blog-generator' ),
					'data' => [],
					'borderColor' => '#3498db',
					'backgroundColor' => 'rgba(52, 152, 219, 0.1)',
				],
				[
					'label' => __( 'Blogs Generated', 'ai-blog-generator' ),
					'data' => [],
					'borderColor' => '#2ecc71',
					'backgroundColor' => 'rgba(46, 204, 113, 0.1)',
				],
				[
					'label' => __( 'Blogs Published', 'ai-blog-generator' ),
					'data' => [],
					'borderColor' => '#9b59b6',
					'backgroundColor' => 'rgba(155, 89, 182, 0.1)',
				],
			],
		];

		// Fill in data
		$current_date = $start_date;
		while ( $current_date <= $end_date ) {
			$daily_chart['labels'][] = $current_date;
			
			// Find data for this date
			$ideas = 0;
			$generated = 0;
			$published = 0;
			
			foreach ( $generation_data as $row ) {
				if ( $row->date === $current_date ) {
					if ( $row->type === 'ideas' ) {
						$ideas = (int) $row->count;
					} elseif ( $row->type === 'generated' ) {
						$generated = (int) $row->count;
					} elseif ( $row->type === 'published' ) {
						$published = (int) $row->count;
					}
				}
			}
			
			$daily_chart['datasets'][0]['data'][] = $ideas;
			$daily_chart['datasets'][1]['data'][] = $generated;
			$daily_chart['datasets'][2]['data'][] = $published;
			
			$current_date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $current_date ) ) );
		}

		wp_send_json_success( [
			'daily_chart' => $daily_chart,
			'category_breakdown' => $category_breakdown,
			'time_distribution' => $time_distribution,
			'summary' => [
				'total_ideas' => array_sum( $daily_chart['datasets'][0]['data'] ),
				'total_generated' => array_sum( $daily_chart['datasets'][1]['data'] ),
				'total_published' => array_sum( $daily_chart['datasets'][2]['data'] ),
			],
		] );
	}

	/**
	 * Get performance data.
	 */
	public function get_performance_data() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get recent logs for performance analysis
		$recent_logs = $this->log_model->get_performance_logs( 100 );
		
		// Analyze response times
		$response_times = [
			'openai' => [],
			'claude' => [],
			'dalle' => [],
		];
		
		$error_rates = [
			'openai' => [ 'success' => 0, 'error' => 0 ],
			'claude' => [ 'success' => 0, 'error' => 0 ],
			'dalle' => [ 'success' => 0, 'error' => 0 ],
		];

		foreach ( $recent_logs as $log ) {
			if ( isset( $log->context['service'] ) && isset( $log->context['response_time'] ) ) {
				$service = $log->context['service'];
				$response_times[ $service ][] = $log->context['response_time'];
				
				if ( $log->level === 'error' ) {
					$error_rates[ $service ]['error']++;
				} else {
					$error_rates[ $service ]['success']++;
				}
			}
		}

		// Calculate averages and percentiles
		$performance_metrics = [];
		foreach ( $response_times as $service => $times ) {
			if ( ! empty( $times ) ) {
				sort( $times );
				$count = count( $times );
				
				$performance_metrics[ $service ] = [
					'average' => round( array_sum( $times ) / $count, 2 ),
					'min' => min( $times ),
					'max' => max( $times ),
					'p50' => $times[ (int) ( $count * 0.5 ) ],
					'p95' => $times[ (int) ( $count * 0.95 ) ],
					'p99' => $times[ (int) ( $count * 0.99 ) ],
					'error_rate' => $error_rates[ $service ]['error'] > 0 
						? round( ( $error_rates[ $service ]['error'] / ( $error_rates[ $service ]['error'] + $error_rates[ $service ]['success'] ) ) * 100, 2 )
						: 0,
				];
			}
		}

		// Get system health metrics
		$health_metrics = [
			'database_size' => $this->get_database_size(),
			'log_entries' => $this->log_model->count(),
			'oldest_log' => $this->log_model->get_oldest_date(),
			'api_status' => $this->check_api_status(),
		];

		wp_send_json_success( [
			'performance_metrics' => $performance_metrics,
			'health_metrics' => $health_metrics,
			'recent_errors' => $this->log_model->get_recent_errors( 10 ),
		] );
	}

	/**
	 * Get budget status.
	 */
	public function get_budget_status() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		$status = $budget_manager->get_budget_status();

		// Add historical data
		$daily_costs = $this->cost_model->get_costs_by_date( 
			date( 'Y-m-01' ), 
			date( 'Y-m-d' ), 
			'day' 
		);

		// Calculate projection
		$days_in_month = date( 't' );
		$days_passed = date( 'j' );
		$projected_cost = $days_passed > 0 
			? ( $status['used'] / $days_passed ) * $days_in_month 
			: 0;

		$status['projection'] = [
			'estimated_monthly' => round( $projected_cost, 2 ),
			'days_remaining' => $days_in_month - $days_passed,
			'daily_average' => $days_passed > 0 ? round( $status['used'] / $days_passed, 2 ) : 0,
			'will_exceed' => $projected_cost > $status['limit'],
		];

		$status['daily_breakdown'] = $daily_costs;

		wp_send_json_success( $status );
	}

	/**
	 * Reset monthly budget tracking.
	 */
	public function reset_monthly_budget() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Archive current month's costs
		$archive_date = date( 'Y-m' );
		$total_cost = $this->cost_model->get_total_cost( date( 'Y-m-01' ), date( 'Y-m-t' ) );
		
		// Store in options for historical tracking
		$historical_costs = get_option( 'ai_blog_historical_costs', [] );
		$historical_costs[ $archive_date ] = $total_cost;
		update_option( 'ai_blog_historical_costs', $historical_costs );

		// Reset budget manager
		$budget_manager = new \AI_Blog_Generator\Services\Budget_Manager();
		$budget_manager->reset_monthly_tracking();

		// Log the action
		Logger::info( 'Monthly budget reset', [
			'month' => $archive_date,
			'total_cost' => $total_cost,
			'action' => 'reset_monthly_budget',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => __( 'Monthly budget tracking reset successfully.', 'ai-blog-generator' ),
			'archived_month' => $archive_date,
			'archived_cost' => $total_cost,
		] );
	}

	/**
	 * Export analytics report.
	 */
	public function export_analytics_report() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get parameters
		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'csv';
		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'month';
		$include_details = isset( $_POST['include_details'] ) ? (bool) $_POST['include_details'] : false;

		// Calculate date range
		switch ( $period ) {
			case 'week':
				$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
				$end_date = date( 'Y-m-d' );
				break;
			case 'month':
				$start_date = date( 'Y-m-01' );
				$end_date = date( 'Y-m-d' );
				break;
			case 'year':
				$start_date = date( 'Y-01-01' );
				$end_date = date( 'Y-m-d' );
				break;
			default:
				$start_date = date( 'Y-m-01' );
				$end_date = date( 'Y-m-d' );
		}

		// Gather report data
		$report_data = [
			'summary' => [
				'period' => $period,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'generated_at' => current_time( 'mysql' ),
			],
			'costs' => [
				'total' => $this->cost_model->get_total_cost( $start_date, $end_date ),
				'by_service' => $this->cost_model->get_costs_by_service( $start_date, $end_date ),
				'by_type' => $this->cost_model->get_costs_by_type( $start_date, $end_date ),
			],
			'usage' => [
				'ideas_generated' => $this->idea_model->count_by_date_range( $start_date, $end_date ),
				'blogs_generated' => $this->blog_model->count_by_status( 'ready', $start_date, $end_date ) +
					$this->blog_model->count_by_status( 'scheduled', $start_date, $end_date ) +
					$this->blog_model->count_by_status( 'published', $start_date, $end_date ),
				'blogs_published' => $this->blog_model->count_by_status( 'published', $start_date, $end_date ),
			],
		];

		if ( $include_details ) {
			$report_data['detailed_costs'] = $this->cost_model->get_detailed_costs( [], $start_date, $end_date );
			$report_data['daily_breakdown'] = $this->cost_model->get_costs_by_date( $start_date, $end_date, 'day' );
		}

		// Generate filename
		$filename = 'ai-blog-analytics-' . $period . '-' . date( 'Y-m-d' );

		// Log the export
		Logger::info( 'Analytics report exported', [
			'format' => $format,
			'period' => $period,
			'filename' => $filename,
			'action' => 'export_analytics_report',
			'user_id' => get_current_user_id(),
		] );

		if ( $format === 'csv' ) {
			$this->export_as_csv( $report_data, $filename );
		} else {
			$this->export_as_json( $report_data, $filename );
		}
	}

	/**
	 * Email monthly report.
	 */
	public function email_monthly_report() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ai_blog_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
		}

		// Get email address
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : get_option( 'admin_email' );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid email address.', 'ai-blog-generator' ) ] );
		}

		// Generate report for previous month
		$month = isset( $_POST['month'] ) ? sanitize_text_field( $_POST['month'] ) : date( 'Y-m', strtotime( '-1 month' ) );
		$start_date = $month . '-01';
		$end_date = date( 'Y-m-t', strtotime( $start_date ) );

		// Gather report data
		$report_data = [
			'month' => date( 'F Y', strtotime( $start_date ) ),
			'costs' => [
				'total' => $this->cost_model->get_total_cost( $start_date, $end_date ),
				'by_service' => $this->cost_model->get_costs_by_service( $start_date, $end_date ),
			],
			'usage' => [
				'ideas_generated' => $this->idea_model->count_by_date_range( $start_date, $end_date ),
				'blogs_generated' => $this->blog_model->count_by_date_range( $start_date, $end_date ),
				'blogs_published' => $this->blog_model->count_by_status( 'published', $start_date, $end_date ),
			],
			'performance' => [
				'success_rate' => $this->blog_model->get_success_rate( $start_date, $end_date ),
				'average_generation_time' => $this->blog_model->get_average_generation_time( $start_date, $end_date ),
			],
		];

		// Generate email content
		$subject = sprintf( 
			__( 'AI Blog Generator Monthly Report - %s', 'ai-blog-generator' ), 
			$report_data['month'] 
		);
		
		$message = $this->generate_email_report( $report_data );

		// Send email
		$sent = wp_mail( $email, $subject, $message, [
			'Content-Type: text/html; charset=UTF-8',
		] );

		if ( ! $sent ) {
			wp_send_json_error( [ 'message' => __( 'Failed to send email.', 'ai-blog-generator' ) ] );
		}

		// Log the action
		Logger::info( 'Monthly report emailed', [
			'email' => $email,
			'month' => $month,
			'action' => 'email_monthly_report',
			'user_id' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'message' => sprintf( 
				__( 'Monthly report sent to %s', 'ai-blog-generator' ), 
				$email 
			),
		] );
	}

	/**
	 * Get database size.
	 *
	 * @return array Database size information.
	 */
	private function get_database_size() {
		global $wpdb;
		
		$tables = [
			AI_BLOG_GENERATOR_TABLE_IDEAS,
			AI_BLOG_GENERATOR_TABLE_POSTS,
			AI_BLOG_GENERATOR_TABLE_CONTEXTS,
			AI_BLOG_GENERATOR_TABLE_LOGS,
			AI_BLOG_GENERATOR_TABLE_COSTS,
		];
		
		$total_size = 0;
		$table_sizes = [];
		
		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$result = $wpdb->get_row( "SHOW TABLE STATUS WHERE Name = '{$table_name}'" );
			
			if ( $result ) {
				$size = $result->Data_length + $result->Index_length;
				$total_size += $size;
				$table_sizes[ $table ] = $this->format_bytes( $size );
			}
		}
		
		return [
			'total' => $this->format_bytes( $total_size ),
			'tables' => $table_sizes,
		];
	}

	/**
	 * Format bytes to human readable.
	 *
	 * @param int $bytes Bytes to format.
	 * @return string Formatted string.
	 */
	private function format_bytes( $bytes ) {
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$i = 0;
		
		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
			$bytes /= 1024;
			$i++;
		}
		
		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Check API status.
	 *
	 * @return array API status information.
	 */
	private function check_api_status() {
		$status = [];
		
		// Check OpenAI
		if ( get_option( 'ai_blog_openai_api_key' ) ) {
			$status['openai'] = __( 'Configured', 'ai-blog-generator' );
		} else {
			$status['openai'] = __( 'Not configured', 'ai-blog-generator' );
		}
		
		// Check Claude
		if ( get_option( 'ai_blog_claude_api_key' ) ) {
			$status['claude'] = __( 'Configured', 'ai-blog-generator' );
		} else {
			$status['claude'] = __( 'Not configured', 'ai-blog-generator' );
		}
		
		return $status;
	}

	/**
	 * Export data as CSV.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filename Base filename.
	 */
	private function export_as_csv( $data, $filename ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel UTF-8 compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Summary section
		fputcsv( $output, [ 'AI Blog Generator Analytics Report' ] );
		fputcsv( $output, [ 'Period:', $data['summary']['period'] ] );
		fputcsv( $output, [ 'Date Range:', $data['summary']['start_date'] . ' to ' . $data['summary']['end_date'] ] );
		fputcsv( $output, [] );
		
		// Cost summary
		fputcsv( $output, [ 'Cost Summary' ] );
		fputcsv( $output, [ 'Total Cost:', '$' . number_format( $data['costs']['total'], 2 ) ] );
		fputcsv( $output, [] );
		
		// Cost by service
		fputcsv( $output, [ 'Service', 'Cost' ] );
		foreach ( $data['costs']['by_service'] as $service => $cost ) {
			fputcsv( $output, [ ucfirst( $service ), '$' . number_format( $cost, 2 ) ] );
		}
		fputcsv( $output, [] );
		
		// Usage summary
		fputcsv( $output, [ 'Usage Summary' ] );
		fputcsv( $output, [ 'Ideas Generated:', $data['usage']['ideas_generated'] ] );
		fputcsv( $output, [ 'Blogs Generated:', $data['usage']['blogs_generated'] ] );
		fputcsv( $output, [ 'Blogs Published:', $data['usage']['blogs_published'] ] );
		
		// Detailed costs if included
		if ( isset( $data['detailed_costs'] ) ) {
			fputcsv( $output, [] );
			fputcsv( $output, [ 'Detailed Costs' ] );
			fputcsv( $output, [ 'Date', 'Service', 'Type', 'Tokens', 'Cost' ] );
			
			foreach ( $data['detailed_costs'] as $cost ) {
				fputcsv( $output, [
					$cost->created_at,
					$cost->service,
					$cost->type,
					$cost->tokens_used,
					'$' . number_format( $cost->cost, 2 ),
				] );
			}
		}
		
		fclose( $output );
		exit;
	}

	/**
	 * Export data as JSON.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filename Base filename.
	 */
	private function export_as_json( $data, $filename ) {
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Generate email report content.
	 *
	 * @param array $data Report data.
	 * @return string HTML email content.
	 */
	private function generate_email_report( $data ) {
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
		.container { max-width: 600px; margin: 0 auto; padding: 20px; }
		h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
		h2 { color: #34495e; margin-top: 30px; }
		.metric { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #3498db; }
		.metric-label { font-weight: bold; color: #7f8c8d; }
		.metric-value { font-size: 24px; color: #2c3e50; }
		.cost-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
		.cost-table th, .cost-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
		.cost-table th { background: #3498db; color: white; }
		.footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #7f8c8d; font-size: 14px; }
	</style>
</head>
<body>
	<div class="container">
		<h1><?php esc_html_e( 'AI Blog Generator Monthly Report', 'ai-blog-generator' ); ?></h1>
		<p><?php echo esc_html( $data['month'] ); ?></p>
		
		<h2><?php esc_html_e( 'Usage Summary', 'ai-blog-generator' ); ?></h2>
		<div class="metric">
			<div class="metric-label"><?php esc_html_e( 'Ideas Generated', 'ai-blog-generator' ); ?></div>
			<div class="metric-value"><?php echo esc_html( $data['usage']['ideas_generated'] ); ?></div>
		</div>
		<div class="metric">
			<div class="metric-label"><?php esc_html_e( 'Blogs Generated', 'ai-blog-generator' ); ?></div>
			<div class="metric-value"><?php echo esc_html( $data['usage']['blogs_generated'] ); ?></div>
		</div>
		<div class="metric">
			<div class="metric-label"><?php esc_html_e( 'Blogs Published', 'ai-blog-generator' ); ?></div>
			<div class="metric-value"><?php echo esc_html( $data['usage']['blogs_published'] ); ?></div>
		</div>
		
		<h2><?php esc_html_e( 'Cost Analysis', 'ai-blog-generator' ); ?></h2>
		<div class="metric">
			<div class="metric-label"><?php esc_html_e( 'Total Cost', 'ai-blog-generator' ); ?></div>
			<div class="metric-value">$<?php echo esc_html( number_format( $data['costs']['total'], 2 ) ); ?></div>
		</div>
		
		<?php if ( ! empty( $data['costs']['by_service'] ) ) : ?>
		<table class="cost-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Service', 'ai-blog-generator' ); ?></th>
					<th><?php esc_html_e( 'Cost', 'ai-blog-generator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['costs']['by_service'] as $service => $cost ) : ?>
				<tr>
					<td><?php echo esc_html( ucfirst( $service ) ); ?></td>
					<td>$<?php echo esc_html( number_format( $cost, 2 ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		
		<h2><?php esc_html_e( 'Performance Metrics', 'ai-blog-generator' ); ?></h2>
		<div class="metric">
			<div class="metric-label"><?php esc_html_e( 'Success Rate', 'ai-blog-generator' ); ?></div>
			<div class="metric-value"><?php echo esc_html( $data['performance']['success_rate'] ); ?>%</div>
		</div>
		<div class="metric">
			<div class="metric-label"><?php esc_html_e( 'Average Generation Time', 'ai-blog-generator' ); ?></div>
			<div class="metric-value"><?php echo esc_html( round( $data['performance']['average_generation_time'], 1 ) ); ?> <?php esc_html_e( 'seconds', 'ai-blog-generator' ); ?></div>
		</div>
		
		<div class="footer">
			<p><?php esc_html_e( 'This report was automatically generated by AI Blog Generator.', 'ai-blog-generator' ); ?></p>
			<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?> | <?php echo esc_html( get_bloginfo( 'url' ) ); ?></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
} 
 
 