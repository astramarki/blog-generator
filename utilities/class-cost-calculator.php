<?php
/**
 * Cost Calculator Utility
 *
 * @package AI_Blog_Generator
 * @subpackage Utilities
 */

namespace AI_Blog_Generator\Utilities;

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Models\Cost_Model;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cost Calculator Class
 *
 * Track and calculate API usage costs.
 *
 * @since 1.0.0
 */
class Cost_Calculator {

	/**
	 * OpenAI Pricing Constants (per 1K tokens) - As of 2025
	 */
	const OPENAI_GPT4_INPUT_COST = 0.03;       // GPT-4 input
	const OPENAI_GPT4_OUTPUT_COST = 0.06;      // GPT-4 output
	const OPENAI_GPT4_TURBO_INPUT_COST = 0.01; // GPT-4 Turbo input
	const OPENAI_GPT4_TURBO_OUTPUT_COST = 0.03; // GPT-4 Turbo output
	const OPENAI_GPT35_TURBO_INPUT_COST = 0.0005; // GPT-3.5 Turbo input
	const OPENAI_GPT35_TURBO_OUTPUT_COST = 0.0015; // GPT-3.5 Turbo output
	
	/**
	 * OpenAI Image Generation Pricing - As of 2025
	 */
	const OPENAI_DALLE3_STANDARD_COST = 0.040; // DALL-E 3 standard quality 1024x1024
	const OPENAI_DALLE3_HD_COST = 0.080;       // DALL-E 3 HD quality 1024x1024
	const OPENAI_DALLE2_COST = 0.020;          // DALL-E 2 1024x1024
	
	/**
	 * Anthropic Claude Pricing Constants (per 1K tokens) - As of 2025
	 */
	const ANTHROPIC_SONNET_4_INPUT_COST = 0.003;  // Claude Sonnet 4 input
	const ANTHROPIC_SONNET_4_OUTPUT_COST = 0.015; // Claude Sonnet 4 output
	const ANTHROPIC_OPUS_4_INPUT_COST = 0.015;    // Claude Opus 4 input
	const ANTHROPIC_OPUS_4_OUTPUT_COST = 0.075;   // Claude Opus 4 output
	
	/**
	 * Default models for calculation
	 */
	const DEFAULT_OPENAI_MODEL = 'gpt-4-turbo';
	const DEFAULT_ANTHROPIC_MODEL = 'claude-sonnet-4';
	const DEFAULT_IMAGE_MODEL = 'dall-e-3';

	/**
	 * Calculate Anthropic (Claude) API cost.
	 *
	 * @param int    $input_tokens  Number of input tokens.
	 * @param int    $output_tokens Number of output tokens.
	 * @param string $model         Model used (sonnet-4, opus-4, etc.).
	 * @return float Cost in USD.
	 */
	public static function calculate_anthropic_cost( $input_tokens, $output_tokens, $model = 'sonnet-4' ) {
		$input_cost = 0;
		$output_cost = 0;
		
		switch ( strtolower( $model ) ) {
			case 'sonnet-4':
			case 'claude-sonnet-4':
			case 'claude-4-sonnet':
				$input_cost = ( $input_tokens / 1000 ) * self::ANTHROPIC_SONNET_4_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::ANTHROPIC_SONNET_4_OUTPUT_COST;
				break;
				
			case 'opus-4':
			case 'claude-opus-4':
			case 'claude-4-opus':
				$input_cost = ( $input_tokens / 1000 ) * self::ANTHROPIC_OPUS_4_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::ANTHROPIC_OPUS_4_OUTPUT_COST;
				break;
				
			default:
				// Default to Sonnet 4 pricing (most commonly used)
				$input_cost = ( $input_tokens / 1000 ) * self::ANTHROPIC_SONNET_4_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::ANTHROPIC_SONNET_4_OUTPUT_COST;
		}
		
		return round( $input_cost + $output_cost, 4 );
	}

	/**
	 * Calculate OpenAI text generation cost.
	 *
	 * @param int    $input_tokens  Number of input tokens.
	 * @param int    $output_tokens Number of output tokens.
	 * @param string $model         Model used.
	 * @return float Cost in USD.
	 */
	public static function calculate_openai_text_cost( $input_tokens, $output_tokens, $model = 'gpt-4-turbo' ) {
		$input_cost = 0;
		$output_cost = 0;
		
		switch ( strtolower( $model ) ) {
			case 'gpt-4':
				$input_cost = ( $input_tokens / 1000 ) * self::OPENAI_GPT4_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::OPENAI_GPT4_OUTPUT_COST;
				break;
				
			case 'gpt-4-turbo':
			case 'gpt-4-turbo-preview':
				$input_cost = ( $input_tokens / 1000 ) * self::OPENAI_GPT4_TURBO_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::OPENAI_GPT4_TURBO_OUTPUT_COST;
				break;
				
			case 'gpt-3.5-turbo':
			case 'gpt-35-turbo':
				$input_cost = ( $input_tokens / 1000 ) * self::OPENAI_GPT35_TURBO_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::OPENAI_GPT35_TURBO_OUTPUT_COST;
				break;
				
			default:
				// Default to GPT-4 Turbo pricing
				$input_cost = ( $input_tokens / 1000 ) * self::OPENAI_GPT4_TURBO_INPUT_COST;
				$output_cost = ( $output_tokens / 1000 ) * self::OPENAI_GPT4_TURBO_OUTPUT_COST;
		}
		
		return round( $input_cost + $output_cost, 4 );
	}

	/**
	 * Calculate OpenAI image generation cost.
	 *
	 * @param int    $image_count Number of images.
	 * @param string $model       Model used (dall-e-3, dall-e-2).
	 * @param string $quality     Quality setting (standard, hd).
	 * @param string $size        Image size.
	 * @return float Cost in USD.
	 */
	public static function calculate_openai_image_cost( $image_count, $model = 'dall-e-3', $quality = 'standard', $size = '1024x1024' ) {
		$cost_per_image = 0;
		
		switch ( strtolower( $model ) ) {
			case 'dall-e-3':
			case 'dalle3':
				if ( $quality === 'hd' ) {
					$cost_per_image = self::OPENAI_DALLE3_HD_COST;
				} else {
					$cost_per_image = self::OPENAI_DALLE3_STANDARD_COST;
				}
				// Add 25% for larger sizes
				if ( in_array( $size, [ '1792x1024', '1024x1792' ], true ) ) {
					$cost_per_image *= 1.25;
				}
				break;
				
			case 'dall-e-2':
			case 'dalle2':
				$cost_per_image = self::OPENAI_DALLE2_COST;
				// Smaller sizes cost less
				if ( $size === '512x512' ) {
					$cost_per_image *= 0.9;
				} elseif ( $size === '256x256' ) {
					$cost_per_image *= 0.8;
				}
				break;
				
			default:
				$cost_per_image = self::OPENAI_DALLE3_STANDARD_COST;
		}
		
		return round( $cost_per_image * $image_count, 4 );
	}

	/**
	 * Record cost to database.
	 *
	 * @param string $service      Service name (openai, anthropic).
	 * @param string $type         Action type (idea_generation, blog_generation, image_generation).
	 * @param float  $cost         Cost in USD.
	 * @param int    $tokens       Token count (optional).
	 * @param int    $reference_id Reference ID (optional).
	 * @param array  $metadata     Additional metadata (optional).
	 * @return int|false Inserted cost record ID or false on failure.
	 */
	public static function record_cost( $service, $type, $cost, $tokens = 0, $reference_id = 0, $metadata = [] ) {
		$cost_model = new Cost_Model();
		
		// Prepare cost data
		$cost_data = [
			'service' => sanitize_text_field( $service ),
			'type' => sanitize_text_field( $type ),
			'cost' => floatval( $cost ),
			'tokens_used' => intval( $tokens ),
		];
		
		// Add reference ID if provided
		if ( $reference_id > 0 ) {
			$cost_data['reference_id'] = intval( $reference_id );
		}
		
		// Add metadata if provided
		if ( ! empty( $metadata ) ) {
			$cost_data['metadata'] = maybe_serialize( $metadata );
		}
		
		// Record the cost
		$result = $cost_model->create( $cost_data );
		
		if ( $result ) {
			// Log the cost recording
			Logger::info( 'Cost recorded', [
				'service' => $service,
				'type' => $type,
				'cost' => $cost,
				'tokens' => $tokens,
				'reference_id' => $reference_id,
			] );
			
			// Update budget tracking
			self::update_budget_tracking( $cost );
		} else {
			Logger::error( 'Failed to record cost', [
				'service' => $service,
				'type' => $type,
				'cost' => $cost,
			] );
		}
		
		return $result;
	}

	/**
	 * Get total cost for a date range.
	 *
	 * @param string $date_from Start date (Y-m-d format).
	 * @param string $date_to   End date (Y-m-d format).
	 * @return float Total cost in USD.
	 */
	public static function get_total_cost( $date_from = null, $date_to = null ) {
		$cost_model = new Cost_Model();
		
		// Default to current month if no dates provided
		if ( ! $date_from ) {
			$date_from = date( 'Y-m-01' );
		}
		if ( ! $date_to ) {
			$date_to = date( 'Y-m-d' );
		}
		
		return $cost_model->get_total_cost( $date_from, $date_to );
	}

	/**
	 * Get cost breakdown by service, type, or period.
	 *
	 * @param string $period    Period (day, week, month, year, all).
	 * @param string $group_by  Group by (service, type, date).
	 * @return array Cost breakdown data.
	 */
	public static function get_cost_breakdown( $period = 'month', $group_by = 'service' ) {
		$cost_model = new Cost_Model();
		
		// Calculate date range based on period
		switch ( $period ) {
			case 'day':
				$date_from = date( 'Y-m-d' );
				$date_to = date( 'Y-m-d' );
				break;
				
			case 'week':
				$date_from = date( 'Y-m-d', strtotime( '-7 days' ) );
				$date_to = date( 'Y-m-d' );
				break;
				
			case 'month':
				$date_from = date( 'Y-m-01' );
				$date_to = date( 'Y-m-d' );
				break;
				
			case 'year':
				$date_from = date( 'Y-01-01' );
				$date_to = date( 'Y-m-d' );
				break;
				
			case 'all':
			default:
				$date_from = '2000-01-01'; // Effectively all time
				$date_to = date( 'Y-m-d' );
				break;
		}
		
		// Get breakdown based on grouping
		switch ( $group_by ) {
			case 'service':
				return $cost_model->get_costs_by_service( $date_from, $date_to );
				
			case 'type':
				return $cost_model->get_costs_by_type( $date_from, $date_to );
				
			case 'date':
				$group_interval = $period === 'year' ? 'month' : 'day';
				return $cost_model->get_costs_by_date( $date_from, $date_to, $group_interval );
				
			default:
				return [];
		}
	}

	/**
	 * Calculate estimated cost for an operation.
	 *
	 * @param string $operation Operation type (idea_generation, blog_generation, image_generation).
	 * @param array  $params    Parameters for calculation.
	 * @return float Estimated cost in USD.
	 */
	public static function estimate_cost( $operation, $params = [] ) {
		switch ( $operation ) {
			case 'idea_generation':
				// Estimate ~500 input tokens for context, ~1000 output tokens for 5 ideas
				$model = $params['model'] ?? self::DEFAULT_ANTHROPIC_MODEL;
				return self::calculate_anthropic_cost( 500, 1000, $model );
				
			case 'blog_generation':
				// Estimate ~2000 input tokens for prompt, ~3000 output tokens for blog
				$model = $params['model'] ?? self::DEFAULT_ANTHROPIC_MODEL;
				$base_cost = self::calculate_anthropic_cost( 2000, 3000, $model );
				
				// Add image costs if requested
				if ( isset( $params['image_count'] ) && $params['image_count'] > 0 ) {
					$image_model = $params['image_model'] ?? self::DEFAULT_IMAGE_MODEL;
					$image_quality = $params['image_quality'] ?? 'standard';
					$base_cost += self::calculate_openai_image_cost( $params['image_count'], $image_model, $image_quality );
				}
				
				return $base_cost;
				
			case 'image_generation':
				$count = $params['count'] ?? 1;
				$model = $params['model'] ?? self::DEFAULT_IMAGE_MODEL;
				$quality = $params['quality'] ?? 'standard';
				$size = $params['size'] ?? '1024x1024';
				return self::calculate_openai_image_cost( $count, $model, $quality, $size );
				
			default:
				return 0;
		}
	}

	/**
	 * Get cost statistics for reporting.
	 *
	 * @param string $period Period for statistics.
	 * @return array Statistics data.
	 */
	public static function get_cost_statistics( $period = 'month' ) {
		$cost_model = new Cost_Model();
		
		// Get date range
		$breakdown = self::get_cost_breakdown( $period );
		$total = self::get_total_cost();
		
		// Calculate averages
		$stats = [
			'total_cost' => $total,
			'by_service' => self::get_cost_breakdown( $period, 'service' ),
			'by_type' => self::get_cost_breakdown( $period, 'type' ),
			'daily_average' => 0,
			'projected_monthly' => 0,
		];
		
		// Calculate daily average and projection
		if ( $period === 'month' ) {
			$days_in_month = date( 't' );
			$days_passed = date( 'j' );
			$stats['daily_average'] = $days_passed > 0 ? round( $total / $days_passed, 2 ) : 0;
			$stats['projected_monthly'] = round( $stats['daily_average'] * $days_in_month, 2 );
		}
		
		// Add token usage statistics
		$stats['token_usage'] = $cost_model->get_token_usage( 
			$period === 'month' ? date( 'Y-m-01' ) : null,
			$period === 'month' ? date( 'Y-m-d' ) : null
		);
		
		return $stats;
	}

	/**
	 * Check if operation is within budget.
	 *
	 * @param float $estimated_cost Estimated cost for operation.
	 * @return bool True if within budget, false otherwise.
	 */
	public static function is_within_budget( $estimated_cost ) {
		$monthly_budget = get_option( 'ai_blog_monthly_budget', 100 );
		$current_month_cost = self::get_total_cost( date( 'Y-m-01' ), date( 'Y-m-d' ) );
		
		return ( $current_month_cost + $estimated_cost ) <= $monthly_budget;
	}

	/**
	 * Update budget tracking.
	 *
	 * @param float $cost Cost to add to tracking.
	 */
	private static function update_budget_tracking( $cost ) {
		// Get current month's total
		$current_total = get_option( 'ai_blog_current_month_cost', 0 );
		$current_month = get_option( 'ai_blog_current_month', '' );
		
		// Reset if new month
		if ( $current_month !== date( 'Y-m' ) ) {
			$current_total = 0;
			update_option( 'ai_blog_current_month', date( 'Y-m' ) );
		}
		
		// Update total
		update_option( 'ai_blog_current_month_cost', $current_total + $cost );
		
		// Check if budget exceeded
		$monthly_budget = get_option( 'ai_blog_monthly_budget', 100 );
		if ( ( $current_total + $cost ) > $monthly_budget ) {
			// Trigger budget exceeded action
			do_action( 'ai_blog_budget_exceeded', $current_total + $cost, $monthly_budget );
			
			// Log warning
			Logger::warning( 'Monthly budget exceeded', [
				'budget' => $monthly_budget,
				'current_total' => $current_total + $cost,
				'last_cost' => $cost,
			] );
		}
	}

	/**
	 * Reset monthly cost tracking.
	 *
	 * @return bool Success status.
	 */
	public static function reset_monthly_tracking() {
		update_option( 'ai_blog_current_month_cost', 0 );
		update_option( 'ai_blog_current_month', date( 'Y-m' ) );
		
		Logger::info( 'Monthly cost tracking reset', [
			'month' => date( 'Y-m' ),
		] );
		
		return true;
	}

	/**
	 * Export cost data for reporting.
	 *
	 * @param string $format Format (csv, json).
	 * @param string $period Period to export.
	 * @return array Export data.
	 */
	public static function export_cost_data( $format = 'csv', $period = 'month' ) {
		$cost_model = new Cost_Model();
		
		// Get date range
		switch ( $period ) {
			case 'week':
				$date_from = date( 'Y-m-d', strtotime( '-7 days' ) );
				$date_to = date( 'Y-m-d' );
				break;
				
			case 'month':
				$date_from = date( 'Y-m-01' );
				$date_to = date( 'Y-m-d' );
				break;
				
			case 'year':
				$date_from = date( 'Y-01-01' );
				$date_to = date( 'Y-m-d' );
				break;
				
			default:
				$date_from = date( 'Y-m-01' );
				$date_to = date( 'Y-m-d' );
		}
		
		// Get detailed costs
		$costs = $cost_model->get_detailed_costs( [], $date_from, $date_to );
		
		// Prepare export data
		$export_data = [
			'period' => $period,
			'date_from' => $date_from,
			'date_to' => $date_to,
			'total_cost' => self::get_total_cost( $date_from, $date_to ),
			'records' => [],
		];
		
		foreach ( $costs as $cost ) {
			$export_data['records'][] = [
				'date' => $cost->created_at,
				'service' => $cost->service,
				'type' => $cost->type,
				'cost' => $cost->cost,
				'tokens' => $cost->tokens_used,
			];
		}
		
		return $export_data;
	}
} 
 
 