<?php
/**
 * AI Orchestrator Service
 *
 * Manages AI request queue, coordinates submissions across different AI providers,
 * and handles response routing. Does not parse responses - that's handled by controllers.
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Services\Budget_Manager;
use AI_Blog_Generator\Services\Anthropic_Service;
use AI_Blog_Generator\Services\OpenAI_Service;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Orchestrator Class
 *
 * Centralized AI request management and queue coordination.
 */
class AI_Orchestrator {

	/**
	 * Anthropic service instance.
	 *
	 * @var Anthropic_Service
	 */
	private $anthropic_service;

	/**
	 * OpenAI service instance.
	 *
	 * @var OpenAI_Service
	 */
	private $openai_service;

	/**
	 * Budget manager instance.
	 *
	 * @var Budget_Manager
	 */
	private $budget_manager;

	/**
	 * Cost model instance.
	 *
	 * @var Cost_Model
	 */
	private $cost_model;

	/**
	 * Active requests queue.
	 *
	 * @var array
	 */
	private $active_requests = [];

	/**
	 * Request callbacks.
	 *
	 * @var array
	 */
	private $request_callbacks = [];

	/**
	 * Max concurrent requests per provider.
	 *
	 * @var array
	 */
	private $max_concurrent = [
		'anthropic' => 3,
		'openai' => 3
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->anthropic_service = new Anthropic_Service();
		$this->openai_service = new OpenAI_Service();
		$this->budget_manager = new Budget_Manager();
		$this->cost_model = new Cost_Model();

		Logger::info( 'ai_orchestrator_init', 'AI Orchestrator initialized', [
			'max_concurrent_anthropic' => $this->max_concurrent['anthropic'],
			'max_concurrent_openai' => $this->max_concurrent['openai']
		] );
	}

	/**
	 * Submit text generation request.
	 *
	 * @param string   $prompt      The prompt text.
	 * @param string   $model       Model to use (claude-3-5-sonnet, gpt-4o, etc).
	 * @param array    $options     Additional options.
	 * @param callable $callback    Callback function for response.
	 * @param string   $request_id  Unique request identifier.
	 * @return array Request result with success status and request_id.
	 */
	public function submit_text_request( $prompt, $model, $options = [], $callback = null, $request_id = null ) {
		Logger::info( 'text_request_submit', 'Submitting text generation request', [
			'model' => $model,
			'prompt_length' => strlen( $prompt ),
			'request_id' => $request_id,
			'has_callback' => ! is_null( $callback )
		] );

		// Generate request ID if not provided
		if ( ! $request_id ) {
			$request_id = $this->generate_request_id( 'text' );
		}

		// Determine provider from model
		$provider = $this->get_provider_from_model( $model );
		if ( ! $provider ) {
			Logger::error( 'text_request_invalid_model', 'Invalid model specified', [
				'model' => $model,
				'request_id' => $request_id
			] );
			return [
				'success' => false,
				'message' => 'Invalid model specified: ' . $model,
				'request_id' => $request_id
			];
		}

		// Check if we can accept more requests for this provider
		if ( $this->get_active_requests_count( $provider ) >= $this->max_concurrent[ $provider ] ) {
			Logger::warning( 'text_request_queue_full', 'Provider queue is full', [
				'provider' => $provider,
				'active_requests' => $this->get_active_requests_count( $provider ),
				'max_concurrent' => $this->max_concurrent[ $provider ],
				'request_id' => $request_id
			] );
			return [
				'success' => false,
				'message' => "Provider {$provider} queue is full",
				'request_id' => $request_id
			];
		}

		// Estimate cost before submission
		$estimated_cost = $this->estimate_text_cost( $prompt, $model );
		Logger::debug( 'text_request_cost_estimate', 'Estimated cost for text request', [
			'estimated_cost' => $estimated_cost,
			'model' => $model,
			'request_id' => $request_id
		] );

		// Check budget
		if ( ! $this->budget_manager->can_afford( $estimated_cost ) ) {
			Logger::error( 'text_request_budget_exceeded', 'Insufficient budget for request', [
				'estimated_cost' => $estimated_cost,
				'available_budget' => $this->budget_manager->get_available_budget(),
				'request_id' => $request_id
			] );
			return [
				'success' => false,
				'message' => 'Insufficient budget for this request',
				'request_id' => $request_id,
				'estimated_cost' => $estimated_cost
			];
		}

		// Reserve budget
		$this->budget_manager->reserve_budget( $estimated_cost );

		// Store request info
		$this->active_requests[ $request_id ] = [
			'type' => 'text',
			'provider' => $provider,
			'model' => $model,
			'prompt' => $prompt,
			'options' => $options,
			'estimated_cost' => $estimated_cost,
			'started_at' => time(),
			'status' => 'queued'
		];

		// Store callback if provided
		if ( $callback ) {
			$this->request_callbacks[ $request_id ] = $callback;
		}

		// Submit to appropriate service
		$this->process_text_request( $request_id );

		Logger::info( 'text_request_queued', 'Text request queued successfully', [
			'request_id' => $request_id,
			'provider' => $provider,
			'model' => $model,
			'estimated_cost' => $estimated_cost
		] );

		return [
			'success' => true,
			'request_id' => $request_id,
			'provider' => $provider,
			'estimated_cost' => $estimated_cost
		];
	}

	/**
	 * Submit image generation request.
	 *
	 * @param string   $prompt      The image prompt.
	 * @param string   $model       Model to use (dall-e-3, gpt-image-1, etc).
	 * @param array    $options     Additional options (size, quality, etc).
	 * @param callable $callback    Callback function for response.
	 * @param string   $request_id  Unique request identifier.
	 * @return array Request result with success status and request_id.
	 */
	public function submit_image_request( $prompt, $model, $options = [], $callback = null, $request_id = null ) {
		Logger::info( 'image_request_submit', 'Submitting image generation request', [
			'model' => $model,
			'prompt_length' => strlen( $prompt ),
			'request_id' => $request_id,
			'options' => $options
		] );

		// Generate request ID if not provided
		if ( ! $request_id ) {
			$request_id = $this->generate_request_id( 'image' );
		}

		// Determine provider from model
		$provider = $this->get_provider_from_model( $model );
		if ( ! $provider ) {
			Logger::error( 'image_request_invalid_model', 'Invalid image model specified', [
				'model' => $model,
				'request_id' => $request_id
			] );
			return [
				'success' => false,
				'message' => 'Invalid image model specified: ' . $model,
				'request_id' => $request_id
			];
		}

		// Check queue capacity
		if ( $this->get_active_requests_count( $provider ) >= $this->max_concurrent[ $provider ] ) {
			Logger::warning( 'image_request_queue_full', 'Provider queue is full for image request', [
				'provider' => $provider,
				'active_requests' => $this->get_active_requests_count( $provider ),
				'request_id' => $request_id
			] );
			return [
				'success' => false,
				'message' => "Provider {$provider} queue is full",
				'request_id' => $request_id
			];
		}

		// Estimate cost
		$estimated_cost = $this->estimate_image_cost( $prompt, $model, $options );
		Logger::debug( 'image_request_cost_estimate', 'Estimated cost for image request', [
			'estimated_cost' => $estimated_cost,
			'model' => $model,
			'request_id' => $request_id
		] );

		// Check budget
		if ( ! $this->budget_manager->can_afford( $estimated_cost ) ) {
			Logger::error( 'image_request_budget_exceeded', 'Insufficient budget for image request', [
				'estimated_cost' => $estimated_cost,
				'request_id' => $request_id
			] );
			return [
				'success' => false,
				'message' => 'Insufficient budget for this image request',
				'request_id' => $request_id,
				'estimated_cost' => $estimated_cost
			];
		}

		// Reserve budget
		$this->budget_manager->reserve_budget( $estimated_cost );

		// Store request info
		$this->active_requests[ $request_id ] = [
			'type' => 'image',
			'provider' => $provider,
			'model' => $model,
			'prompt' => $prompt,
			'options' => $options,
			'estimated_cost' => $estimated_cost,
			'started_at' => time(),
			'status' => 'queued'
		];

		// Store callback if provided
		if ( $callback ) {
			$this->request_callbacks[ $request_id ] = $callback;
		}

		// Submit to appropriate service
		$this->process_image_request( $request_id );

		Logger::info( 'image_request_queued', 'Image request queued successfully', [
			'request_id' => $request_id,
			'provider' => $provider,
			'model' => $model,
			'estimated_cost' => $estimated_cost
		] );

		return [
			'success' => true,
			'request_id' => $request_id,
			'provider' => $provider,
			'estimated_cost' => $estimated_cost
		];
	}

	/**
	 * Get request status.
	 *
	 * @param string $request_id Request identifier.
	 * @return array|null Request status or null if not found.
	 */
	public function get_request_status( $request_id ) {
		if ( ! isset( $this->active_requests[ $request_id ] ) ) {
			return null;
		}

		$request = $this->active_requests[ $request_id ];
		return [
			'request_id' => $request_id,
			'type' => $request['type'],
			'provider' => $request['provider'],
			'model' => $request['model'],
			'status' => $request['status'],
			'started_at' => $request['started_at'],
			'duration' => time() - $request['started_at'],
			'estimated_cost' => $request['estimated_cost']
		];
	}

	/**
	 * Cancel a request.
	 *
	 * @param string $request_id Request identifier.
	 * @return bool Success status.
	 */
	public function cancel_request( $request_id ) {
		if ( ! isset( $this->active_requests[ $request_id ] ) ) {
			Logger::warning( 'cancel_request_not_found', 'Request not found for cancellation', [
				'request_id' => $request_id
			] );
			return false;
		}

		$request = $this->active_requests[ $request_id ];

		// Release reserved budget
		$this->budget_manager->release_budget( $request['estimated_cost'] );

		// Remove from active requests
		unset( $this->active_requests[ $request_id ] );
		unset( $this->request_callbacks[ $request_id ] );

		Logger::info( 'request_cancelled', 'Request cancelled successfully', [
			'request_id' => $request_id,
			'type' => $request['type'],
			'provider' => $request['provider']
		] );

		return true;
	}

	/**
	 * Process text request with appropriate service.
	 *
	 * @param string $request_id Request identifier.
	 */
	private function process_text_request( $request_id ) {
		$request = $this->active_requests[ $request_id ];
		$request['status'] = 'processing';
		$this->active_requests[ $request_id ] = $request;

		Logger::debug( 'process_text_request', 'Processing text request', [
			'request_id' => $request_id,
			'provider' => $request['provider'],
			'model' => $request['model']
		] );

		// Submit to appropriate service
		if ( $request['provider'] === 'anthropic' ) {
			$response = $this->anthropic_service->generate_text( $request['prompt'], $request['options'] );
		} else {
			$response = $this->openai_service->generate_text( $request['prompt'], $request['options'] );
		}

		// Handle response
		$this->handle_text_response( $request_id, $response );
	}

	/**
	 * Process image request with appropriate service.
	 *
	 * @param string $request_id Request identifier.
	 */
	private function process_image_request( $request_id ) {
		$request = $this->active_requests[ $request_id ];
		$request['status'] = 'processing';
		$this->active_requests[ $request_id ] = $request;

		Logger::debug( 'process_image_request', 'Processing image request', [
			'request_id' => $request_id,
			'provider' => $request['provider'],
			'model' => $request['model']
		] );

		// Submit to appropriate service
		if ( $request['provider'] === 'anthropic' ) {
			// Anthropic doesn't have image generation, this should not happen
			$response = [
				'success' => false,
				'message' => 'Anthropic does not support image generation'
			];
		} else {
			$response = $this->openai_service->generate_image( $request['prompt'], $request['options'] );
		}

		// Handle response
		$this->handle_image_response( $request_id, $response );
	}

	/**
	 * Handle text generation response.
	 *
	 * @param string $request_id Request identifier.
	 * @param array  $response   Service response.
	 */
	private function handle_text_response( $request_id, $response ) {
		$request = $this->active_requests[ $request_id ];

		Logger::info( 'text_response_received', 'Text generation response received', [
			'request_id' => $request_id,
			'success' => $response['success'] ?? false,
			'provider' => $request['provider'],
			'duration' => time() - $request['started_at']
		] );

		// Record actual cost
		$actual_cost = $response['cost'] ?? $request['estimated_cost'];
		$this->cost_model->record_cost( [
			'type' => 'text_generation',
			'provider' => $request['provider'],
			'model' => $request['model'],
			'estimated_cost' => $request['estimated_cost'],
			'actual_cost' => $actual_cost,
			'tokens_used' => $response['tokens_used'] ?? 0,
			'success' => $response['success'] ?? false
		] );

		// Update budget with actual cost
		$this->budget_manager->finalize_cost( $request['estimated_cost'], $actual_cost );

		// Call callback if provided
		if ( isset( $this->request_callbacks[ $request_id ] ) ) {
			$callback = $this->request_callbacks[ $request_id ];
			call_user_func( $callback, $request_id, $response );
		}

		// Clean up
		unset( $this->active_requests[ $request_id ] );
		unset( $this->request_callbacks[ $request_id ] );
	}

	/**
	 * Handle image generation response.
	 *
	 * @param string $request_id Request identifier.
	 * @param array  $response   Service response.
	 */
	private function handle_image_response( $request_id, $response ) {
		$request = $this->active_requests[ $request_id ];

		Logger::info( 'image_response_received', 'Image generation response received', [
			'request_id' => $request_id,
			'success' => $response['success'] ?? false,
			'provider' => $request['provider'],
			'duration' => time() - $request['started_at']
		] );

		// Record actual cost
		$actual_cost = $response['cost'] ?? $request['estimated_cost'];
		$this->cost_model->record_cost( [
			'type' => 'image_generation',
			'provider' => $request['provider'],
			'model' => $request['model'],
			'estimated_cost' => $request['estimated_cost'],
			'actual_cost' => $actual_cost,
			'images_generated' => count( $response['images'] ?? [] ),
			'success' => $response['success'] ?? false
		] );

		// Update budget with actual cost
		$this->budget_manager->finalize_cost( $request['estimated_cost'], $actual_cost );

		// Call callback if provided
		if ( isset( $this->request_callbacks[ $request_id ] ) ) {
			$callback = $this->request_callbacks[ $request_id ];
			call_user_func( $callback, $request_id, $response );
		}

		// Clean up
		unset( $this->active_requests[ $request_id ] );
		unset( $this->request_callbacks[ $request_id ] );
	}

	/**
	 * Get provider from model name.
	 *
	 * @param string $model Model name.
	 * @return string|null Provider name or null if not found.
	 */
	private function get_provider_from_model( $model ) {
		$anthropic_models = [ 'claude-3-5-sonnet', 'claude-3-opus', 'claude-3-haiku' ];
		$openai_models = [ 'gpt-4o', 'gpt-4o-mini', 'o3', 'dall-e-3', 'gpt-image-1' ];

		if ( in_array( $model, $anthropic_models, true ) ) {
			return 'anthropic';
		}

		if ( in_array( $model, $openai_models, true ) ) {
			return 'openai';
		}

		return null;
	}

	/**
	 * Generate unique request ID.
	 *
	 * @param string $type Request type (text or image).
	 * @return string Unique request ID.
	 */
	private function generate_request_id( $type ) {
		return $type . '_' . uniqid() . '_' . time();
	}

	/**
	 * Get active requests count for provider.
	 *
	 * @param string $provider Provider name.
	 * @return int Active requests count.
	 */
	private function get_active_requests_count( $provider ) {
		$count = 0;
		foreach ( $this->active_requests as $request ) {
			if ( $request['provider'] === $provider ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Estimate text generation cost.
	 *
	 * @param string $prompt Text prompt.
	 * @param string $model  Model name.
	 * @return float Estimated cost.
	 */
	private function estimate_text_cost( $prompt, $model ) {
		// Simple token estimation (roughly 4 characters per token)
		$estimated_tokens = strlen( $prompt ) / 4;
		
		// Add estimated response tokens (assume 500 token response)
		$estimated_tokens += 500;

		// Model-specific pricing (per 1000 tokens)
		$pricing = [
			'claude-3-5-sonnet' => 0.003,
			'claude-3-opus' => 0.015,
			'claude-3-haiku' => 0.00025,
			'gpt-4o' => 0.005,
			'gpt-4o-mini' => 0.00015,
			'o3' => 0.020
		];

		$price_per_1k = $pricing[ $model ] ?? 0.005; // Default price
		return ( $estimated_tokens / 1000 ) * $price_per_1k;
	}

	/**
	 * Estimate image generation cost.
	 *
	 * @param string $prompt  Image prompt.
	 * @param string $model   Model name.
	 * @param array  $options Generation options.
	 * @return float Estimated cost.
	 */
	private function estimate_image_cost( $prompt, $model, $options ) {
		// Fixed pricing for image models
		$pricing = [
			'dall-e-3' => 0.040, // Standard quality
			'gpt-image-1' => 0.020 // Custom pricing
		];

		$base_cost = $pricing[ $model ] ?? 0.040;

		// Adjust for quality/size if specified
		if ( isset( $options['quality'] ) && $options['quality'] === 'hd' ) {
			$base_cost *= 2;
		}

		if ( isset( $options['size'] ) ) {
			switch ( $options['size'] ) {
				case '1792x1024':
				case '1024x1792':
					$base_cost *= 2;
					break;
			}
		}

		return $base_cost;
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array Queue statistics.
	 */
	public function get_queue_stats() {
		$stats = [
			'total_active' => count( $this->active_requests ),
			'by_provider' => [],
			'by_type' => [],
			'by_status' => []
		];

		foreach ( $this->active_requests as $request ) {
			// By provider
			if ( ! isset( $stats['by_provider'][ $request['provider'] ] ) ) {
				$stats['by_provider'][ $request['provider'] ] = 0;
			}
			$stats['by_provider'][ $request['provider'] ]++;

			// By type
			if ( ! isset( $stats['by_type'][ $request['type'] ] ) ) {
				$stats['by_type'][ $request['type'] ] = 0;
			}
			$stats['by_type'][ $request['type'] ]++;

			// By status
			if ( ! isset( $stats['by_status'][ $request['status'] ] ) ) {
				$stats['by_status'][ $request['status'] ] = 0;
			}
			$stats['by_status'][ $request['status'] ]++;
		}

		return $stats;
	}
} 