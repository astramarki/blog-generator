<?php
/**
 * Budget Manager Service
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Budget Manager Class
 *
 * Handles budget tracking and validation for AI services.
 *
 * @since 1.5.10
 */
class Budget_Manager {

	/**
	 * Cost model instance.
	 *
	 * @var Cost_Model
	 */
	private $cost_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cost_model = new Cost_Model();
	}

	/**
	 * Check if generation can proceed based on budget constraints.
	 *
	 * @return bool True if generation can proceed, false otherwise.
	 */
	public function can_generate() {
		try {
			// Check if generation is manually paused
			if ( get_option( 'ai_blog_generator_generation_paused', false ) ) {
				Logger::info( 'generation_paused_manual', 'Generation manually paused' );
				return false;
			}

			// Get budget settings
			$monthly_budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
			$alert_threshold = intval( get_option( 'ai_blog_generator_budget_alert_threshold', 80 ) );

			// If budget is 0 or negative, allow unlimited generation
			if ( $monthly_budget <= 0 ) {
				Logger::debug( 'budget_unlimited', 'Budget set to unlimited' );
				return true;
			}

			// Get current month usage
			$current_usage = $this->get_monthly_usage();
			$usage_percentage = ( $current_usage / $monthly_budget ) * 100;

			Logger::debug( 'budget_check', 'Checking budget constraints', [
				'monthly_budget' => $monthly_budget,
				'current_usage' => $current_usage,
				'usage_percentage' => $usage_percentage,
				'alert_threshold' => $alert_threshold
			] );

			// If we've exceeded the budget, don't allow generation
			if ( $current_usage >= $monthly_budget ) {
				Logger::warning( 'budget_exceeded', 'Monthly budget exceeded', [
					'monthly_budget' => $monthly_budget,
					'current_usage' => $current_usage,
					'overage' => $current_usage - $monthly_budget
				] );
				return false;
			}

			// Log warning if approaching budget limit
			if ( $usage_percentage >= $alert_threshold ) {
				Logger::warning( 'budget_threshold_reached', 'Budget threshold reached', [
					'monthly_budget' => $monthly_budget,
					'current_usage' => $current_usage,
					'usage_percentage' => $usage_percentage,
					'alert_threshold' => $alert_threshold
				] );
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'budget_check_error', 'Error checking budget constraints', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			
			// On error, allow generation to proceed to avoid blocking
			return true;
		}
	}

	/**
	 * Get current month usage.
	 *
	 * @return float Current month usage amount.
	 */
	public function get_monthly_usage() {
		try {
			$current_month_start = date( 'Y-m-01' );
			$current_month_end = date( 'Y-m-t' );
			
			return $this->cost_model->get_total_cost( $current_month_start, $current_month_end );

		} catch ( \Exception $e ) {
			Logger::error( 'monthly_usage_error', 'Error getting monthly usage', [
				'error' => $e->getMessage()
			] );
			return 0.0;
		}
	}

	/**
	 * Get budget status information.
	 *
	 * @return array Budget status with detailed information.
	 */
	public function get_budget_status() {
		try {
			$monthly_budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
			$alert_threshold = intval( get_option( 'ai_blog_generator_budget_alert_threshold', 80 ) );
			$current_usage = $this->get_monthly_usage();
			
			$usage_percentage = $monthly_budget > 0 ? ( $current_usage / $monthly_budget ) * 100 : 0;
			$remaining = max( 0, $monthly_budget - $current_usage );
			
			$status = 'ok';
			if ( $monthly_budget > 0 ) {
				if ( $current_usage >= $monthly_budget ) {
					$status = 'exceeded';
				} elseif ( $usage_percentage >= $alert_threshold ) {
					$status = 'warning';
				}
			}

			return [
				'budget' => $monthly_budget,
				'usage' => $current_usage,
				'remaining' => $remaining,
				'percentage' => round( $usage_percentage, 2 ),
				'status' => $status,
				'alert_threshold' => $alert_threshold,
				'can_generate' => $this->can_generate(),
				'generation_paused' => get_option( 'ai_blog_generator_generation_paused', false ),
				'month' => date( 'F Y' )
			];

		} catch ( \Exception $e ) {
			Logger::error( 'budget_status_error', 'Error getting budget status', [
				'error' => $e->getMessage()
			] );
			
			return [
				'budget' => 0,
				'usage' => 0,
				'remaining' => 0,
				'percentage' => 0,
				'status' => 'error',
				'alert_threshold' => 80,
				'can_generate' => true,
				'generation_paused' => false,
				'month' => date( 'F Y' ),
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Reset monthly budget tracking.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset_monthly_tracking() {
		try {
			Logger::info( 'budget_reset_start', 'Starting monthly budget reset', [
				'user_id' => get_current_user_id(),
				'current_month' => date( 'Y-m' )
			] );

			// Clear generation pause if it was due to budget
			delete_option( 'ai_blog_generator_generation_paused' );

			// Log the reset action
			Logger::info( 'budget_reset_complete', 'Monthly budget tracking reset', [
				'reset_by' => get_current_user_id(),
				'reset_date' => current_time( 'mysql' )
			] );

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'budget_reset_error', 'Error resetting monthly budget tracking', [
				'error' => $e->getMessage(),
				'user_id' => get_current_user_id()
			] );
			return false;
		}
	}

	/**
	 * Estimate cost for a given operation.
	 *
	 * @param string $operation Type of operation (generate_idea, generate_blog, generate_image).
	 * @param array  $params    Operation parameters.
	 * @return float Estimated cost.
	 */
	public function estimate_cost( $operation, $params = [] ) {
		try {
			// Base estimates - these should be refined based on actual usage patterns
			$cost_estimates = [
				'generate_idea' => 0.05,      // Rough estimate for idea generation
				'generate_blog' => 2.00,      // Rough estimate for full blog post
				'generate_image' => 0.50,     // Rough estimate for image generation
				'process_queue' => 1.00,      // Rough estimate for queue processing
			];

			$base_cost = isset( $cost_estimates[ $operation ] ) ? $cost_estimates[ $operation ] : 1.00;

			// Adjust based on parameters
			if ( isset( $params['word_count'] ) ) {
				// Adjust for longer content
				$word_multiplier = max( 1, $params['word_count'] / 1000 );
				$base_cost *= $word_multiplier;
			}

			if ( isset( $params['complexity'] ) ) {
				// Adjust for complexity
				$complexity_multipliers = [
					'simple' => 0.8,
					'standard' => 1.0,
					'complex' => 1.5,
					'advanced' => 2.0
				];
				$multiplier = isset( $complexity_multipliers[ $params['complexity'] ] ) 
					? $complexity_multipliers[ $params['complexity'] ] 
					: 1.0;
				$base_cost *= $multiplier;
			}

			Logger::debug( 'cost_estimated', 'Operation cost estimated', [
				'operation' => $operation,
				'base_cost' => $cost_estimates[ $operation ] ?? 1.00,
				'estimated_cost' => $base_cost,
				'params' => $params
			] );

			return round( $base_cost, 4 );

		} catch ( \Exception $e ) {
			Logger::error( 'cost_estimation_error', 'Error estimating operation cost', [
				'operation' => $operation,
				'params' => $params,
				'error' => $e->getMessage()
			] );
			return 1.00; // Default fallback cost
		}
	}

	/**
	 * Check if a specific operation can proceed based on cost estimate.
	 *
	 * @param string $operation Type of operation.
	 * @param array  $params    Operation parameters.
	 * @return bool True if operation can proceed, false otherwise.
	 */
	public function can_afford_operation( $operation, $params = [] ) {
		try {
			if ( ! $this->can_generate() ) {
				return false;
			}

			$monthly_budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
			
			// If budget is unlimited, allow operation
			if ( $monthly_budget <= 0 ) {
				return true;
			}

			$estimated_cost = $this->estimate_cost( $operation, $params );
			$current_usage = $this->get_monthly_usage();

			$would_exceed = ( $current_usage + $estimated_cost ) > $monthly_budget;

			Logger::debug( 'affordability_check', 'Checking if operation is affordable', [
				'operation' => $operation,
				'estimated_cost' => $estimated_cost,
				'current_usage' => $current_usage,
				'monthly_budget' => $monthly_budget,
				'would_exceed' => $would_exceed
			] );

			return ! $would_exceed;

		} catch ( \Exception $e ) {
			Logger::error( 'affordability_check_error', 'Error checking operation affordability', [
				'operation' => $operation,
				'params' => $params,
				'error' => $e->getMessage()
			] );
			return true; // Default to allowing on error
		}
	}

	/**
	 * Get projected monthly cost based on current usage.
	 *
	 * @return array Projection data.
	 */
	public function get_monthly_projection() {
		try {
			$current_day = date( 'j' );
			$days_in_month = date( 't' );
			$current_usage = $this->get_monthly_usage();
			
			// Calculate daily average
			$daily_average = $current_day > 0 ? $current_usage / $current_day : 0;
			
			// Project for full month
			$projected_total = $daily_average * $days_in_month;
			
			$monthly_budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
			$projected_percentage = $monthly_budget > 0 ? ( $projected_total / $monthly_budget ) * 100 : 0;

			return [
				'current_usage' => $current_usage,
				'daily_average' => round( $daily_average, 4 ),
				'projected_total' => round( $projected_total, 2 ),
				'projected_percentage' => round( $projected_percentage, 2 ),
				'days_remaining' => $days_in_month - $current_day,
				'budget' => $monthly_budget,
				'will_exceed' => $projected_total > $monthly_budget
			];

		} catch ( \Exception $e ) {
			Logger::error( 'projection_error', 'Error calculating monthly projection', [
				'error' => $e->getMessage()
			] );
			return [];
		}
	}
} 