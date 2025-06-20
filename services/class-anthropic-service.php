<?php
/**
 * Anthropic Service
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Loggable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anthropic Service Class
 *
 * Handles integration with Anthropic Claude Opus 4 API.
 *
 * @since 1.0.0
 */
class Anthropic_Service {

	use Loggable;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://api.anthropic.com/v1/messages';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Available Claude models.
	 *
	 * @var array
	 */
	private $available_models = [
		'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (May 2025)',
		'claude-opus-4-20250514' => 'Claude Opus 4 (May 2025)',
	];

	/**
	 * Model pricing data (cost per 1M tokens).
	 *
	 * @var array
	 */
	const MODEL_PRICING = [
		'claude-sonnet-4-20250514' => [
			'input_price' => 3.00,   // $3.00 per 1M input tokens
			'output_price' => 15.00, // $15.00 per 1M output tokens
		],
		'claude-opus-4-20250514' => [
			'input_price' => 15.00,  // $15.00 per 1M input tokens
			'output_price' => 75.00, // $75.00 per 1M output tokens
		],
	];

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
		$this->log_function_entry();
		
		$this->api_key = get_option( 'ai_blog_generator_anthropic_api_key', '' );
		$this->model = get_option( 'ai_blog_generator_anthropic_model', 'claude-sonnet-4-20250514' );
		$this->cost_model = new Cost_Model();
		
		if ( empty( $this->api_key ) ) {
			$this->log_error( 'anthropic_missing_api_key', 'Anthropic API key not configured', [
				'key_length' => 0,
				'key_configured' => false
			] );
		}

		// Only log initialization once per session to prevent log spam
		if ( ! get_transient( 'ai_blog_anthropic_service_init_logged' ) ) {
			$this->log_info( 'anthropic_service_init', 'Anthropic service initialized', [
				'api_key_configured' => ! empty( $this->api_key ),
				'api_key_length' => strlen( $this->api_key ),
				'model' => $this->model
			] );
			set_transient( 'ai_blog_anthropic_service_init_logged', true, 300 ); // 5 minutes
		}

		$this->log_function_exit();
	}

	/**
	 * Get available models.
	 *
	 * @return array Available Claude models.
	 */
	public function get_available_models() {
		return $this->available_models;
	}

	/**
	 * Get current model.
	 *
	 * @return string Current model name.
	 */
	public function get_current_model() {
		return $this->model;
	}

	/**
	 * Set model.
	 *
	 * @param string $model Model name.
	 * @return bool Success status.
	 */
	public function set_model( $model ) {
		if ( ! array_key_exists( $model, $this->available_models ) ) {
			$this->log_error( 'anthropic_invalid_model', 'Invalid model specified', [
				'requested_model' => $model,
				'available_models' => array_keys( $this->available_models )
			] );
			return false;
		}

		$this->model = $model;
		$this->log_info( 'anthropic_model_changed', 'Claude model changed', [
			'new_model' => $model
		] );
		
		return true;
	}

	/**
	 * Test API connection.
	 *
	 * @return array Test result.
	 */
	public function test_connection() {
		$start_time = $this->start_timer();
		$this->log_function_entry();
		
		if ( empty( $this->api_key ) ) {
			$this->log_error( 'anthropic_no_api_key', 'API key is missing for connection test' );
			$result = [
				'success' => false,
				'message' => __( 'API key is required.', 'ai-blog-generator' ),
			];
			$this->log_function_exit( $result );
			return $result;
		}

		$this->log_info( 'anthropic_connection_test_start', 'Starting Anthropic API connection test', [
			'model' => $this->model,
			'api_url' => $this->api_url
		] );

		try {
			$test_request = [
				'model' => $this->model,
				'max_tokens' => 10,
				'messages' => [
					[
						'role' => 'user',
						'content' => 'Hello',
					],
				],
			];

			$this->log_debug( 'anthropic_test_request', 'Sending test request to Anthropic API', [
				'request_data' => $test_request
			] );

			$response = $this->make_request( $test_request );

			if ( isset( $response['content'] ) ) {
				$this->log_info( 'anthropic_connection_test_success', 'Anthropic API connection test successful', [
					'response_type' => gettype( $response['content'] ),
					'response_size' => is_string( $response['content'] ) ? strlen( $response['content'] ) : count( $response['content'] ),
					'model' => $this->model,
					'max_tokens' => $this->get_max_tokens()
				] );
				
				$result = [
					'success' => true,
					'message' => sprintf( 
						__( 'Connection successful! Using model: %s (Max tokens: %d)', 'ai-blog-generator' ),
						$this->model,
						$this->get_max_tokens()
					),
				];
			} else {
				$this->log_error( 'anthropic_connection_test_invalid_response', 'Unexpected response format from Anthropic API', [
					'response_keys' => array_keys( $response ),
					'response' => $response
				] );
				
				$result = [
					'success' => false,
					'message' => __( 'Unexpected response format.', 'ai-blog-generator' ),
				];
			}

		} catch ( \Exception $e ) {
			$this->log_exception( 'anthropic_connection_test_exception', $e, [
				'api_key_length' => strlen( $this->api_key ),
				'model' => $this->model
			] );

			$result = [
				'success' => false,
				'message' => sprintf( __( 'Connection failed: %s', 'ai-blog-generator' ), $e->getMessage() ),
			];
		}

		$this->end_timer( $start_time, 'anthropic_connection_test', [ 'success' => $result['success'] ] );
		$this->log_function_exit( $result );

		return $result;
	}

	/**
	 * Generate blog ideas.
	 *
	 * @param array  $contexts       Associative array with keys: general, products, seo, keywords.
	 * @param array  $existing_titles Array of existing post titles.
	 * @param array  $denied_titles  Array of denied idea titles.
	 * @param int    $count          Number of ideas to generate (default: 5).
	 * @param string $custom_prompt  Optional custom prompt for idea generation.
	 * @return array Generated ideas or error.
	 */
	public function generate_blog_ideas( $contexts, $existing_titles, $denied_titles, $count = 5, $custom_prompt = '', $personas = [] ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [
			'contexts_count' => count( $contexts ),
			'existing_titles_count' => count( $existing_titles ),
			'denied_titles_count' => count( $denied_titles ),
			'ideas_to_generate' => $count,
			'has_custom_prompt' => ! empty( $custom_prompt ),
			'personas_count' => count( $personas )
		] );

		try {
			$this->log_info( 'anthropic_generate_ideas_start', 'Starting blog idea generation with Anthropic', [
				'contexts_available' => array_keys( $contexts ),
				'idea_count_requested' => $count,
				'custom_prompt_provided' => ! empty( $custom_prompt ),
				'personas_available' => count( $personas )
			] );

			$prompt = $this->build_ideas_prompt( $contexts, $existing_titles, $denied_titles, $count, $custom_prompt, $personas );
			
			$this->log_debug( 'anthropic_prompt_built', 'Blog ideas prompt constructed', [
				'prompt_length' => strlen( $prompt ),
				'contexts_used' => count( $contexts ),
				'exclusions_applied' => count( $existing_titles ) + count( $denied_titles )
			] );

			$request_data = [
				'model' => $this->model,
				'max_tokens' => $this->get_max_tokens(),
				'messages' => [
					[
						'role' => 'user',
						'content' => $prompt,
					],
				],
			];

			$this->log_api_request( 'anthropic', 'messages', [
				'model' => $this->model,
				'max_tokens' => $this->get_max_tokens(),
				'prompt_length' => strlen( $prompt )
			], [] );

			// Log the full request being sent to API
			$this->log_info( 'anthropic_full_request', 'Complete request data being sent to Anthropic API', [
				'request_data' => $request_data,
				'prompt_preview' => substr( $prompt, 0, 200 ) . '...',
				'full_prompt' => $prompt
			] );

			$response = $this->make_request( $request_data );

			if ( ! isset( $response['content'] ) || ! is_array( $response['content'] ) ) {
				$this->log_error( 'anthropic_invalid_response_format', 'Invalid response format from Anthropic API', [
					'response_keys' => is_array( $response ) ? array_keys( $response ) : 'not_array',
					'content_type' => isset( $response['content'] ) ? gettype( $response['content'] ) : 'missing'
				] );
				throw new \Exception( 'Invalid response format from API' );
			}

			$content = $response['content'][0]['text'] ?? '';
			
			$this->log_debug( 'anthropic_response_received', 'Received response from Anthropic API', [
				'content_length' => strlen( $content ),
				'usage_tokens' => $response['usage'] ?? 'not_provided'
			] );

			// Log the exact content that will be parsed
			$this->log_info( 'anthropic_content_to_parse', 'Content extracted from API response for parsing', [
				'raw_content' => $content,
				'content_length' => strlen( $content ),
				'content_preview' => substr( $content, 0, 500 ) . ( strlen( $content ) > 500 ? '...' : '' ),
				'content_lines' => explode( "\n", $content )
			] );

			$ideas = $this->parse_ideas_response( $content );

			$this->log_debug( 'anthropic_ideas_parsed', 'Blog ideas parsed from response', [
				'ideas_parsed' => count( $ideas ),
				'ideas_requested' => $count,
				'parsing_success_rate' => count( $ideas ) / max( $count, 1 ) * 100,
				'parsed_ideas' => $ideas
			] );

			// Record cost
			$tokens_used = $response['usage']['input_tokens'] + $response['usage']['output_tokens'];
			$cost = $this->calculate_cost( $response['usage']['input_tokens'], $response['usage']['output_tokens'] );
			$this->cost_model->record_anthropic_cost( 'generate_ideas', $cost, $tokens_used );

			$this->log_info( 'anthropic_cost_recorded', 'API cost recorded for idea generation', [
				'input_tokens' => $response['usage']['input_tokens'],
				'output_tokens' => $response['usage']['output_tokens'],
				'total_tokens' => $tokens_used,
				'cost_usd' => $cost
			] );

			$result = [
				'success' => true,
				'ideas' => $ideas,
				'cost' => $cost,
				'tokens_used' => $tokens_used,
			];

			$this->log_info( 'anthropic_ideas_generated_success', 'Blog ideas generated successfully', [
				'ideas_generated' => count( $ideas ),
				'total_cost' => $cost,
				'tokens_consumed' => $tokens_used,
				'cost_per_idea' => count( $ideas ) > 0 ? $cost / count( $ideas ) : 0
			] );

		} catch ( \Exception $e ) {
			$this->log_exception( 'anthropic_ideas_generation_failed', $e, [
				'contexts_count' => count( $contexts ),
				'existing_titles_count' => count( $existing_titles ),
				'denied_titles_count' => count( $denied_titles ),
				'requested_count' => $count
			] );

			$result = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		$this->end_timer( $start_time, 'anthropic_idea_generation', [
			'success' => $result['success'],
			'ideas_count' => $result['ideas'] ?? 0
		] );
		$this->log_function_exit( $result['success'] ? 'success_with_ideas' : 'failed' );

		return $result;
	}

	/**
	 * Generate blog content.
	 *
	 * @param object $idea Idea data.
	 * @param array $contexts Context data.
	 * @param array $seo_keywords SEO keywords.
	 * @param object|null $persona Persona data.
	 * @return array Generated content or error.
	 */
	public function generate_blog_content( $idea, $contexts, $seo_keywords = [], $persona = null ) {
		try {
			// Log to debug file for correlation
			$debug_log = __DIR__ . '/../debug-transaction.log';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Starting blog content generation\n", FILE_APPEND );
			
			$idea_id = is_array( $idea ) ? $idea['id'] : ($idea->id ?? null);
			$idea_title = is_array( $idea ) ? $idea['title'] : ( $idea->title ?? 'unknown' );
			
			Logger::info( 'anthropic_content_generation_start', 'Starting blog content generation', [
				'idea_id' => $idea_id,
				'idea_title' => $idea_title,
				'contexts_available' => array_keys( $contexts ),
				'seo_keywords_count' => count( $seo_keywords ),
				'model' => $this->model,
				'persona_provided' => ! is_null( $persona ),
				'persona_name' => $persona->name ?? 'none'
			] );

			// Build prompt
			Logger::info( 'building_content_prompt', 'Building content generation prompt', [
				'idea_id' => $idea_id
			] );
			
			try {
				$prompt = $this->build_content_prompt( $idea, $contexts, $seo_keywords, $persona );
				Logger::info( 'content_prompt_built', 'Content prompt built successfully', [
					'idea_id' => $idea_id,
					'prompt_length' => strlen( $prompt )
				] );
			} catch ( \Exception $e ) {
				Logger::error( 'content_prompt_build_failed', 'Failed to build content prompt', [
					'idea_id' => $idea_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
				throw new \Exception( 'Failed to build content prompt: ' . $e->getMessage() );
			}

			// Prepare request data
			$request_data = [
				'model' => $this->model,
				'max_tokens' => $this->get_max_tokens(),
				'messages' => [
					[
						'role' => 'user',
						'content' => $prompt,
					],
				],
			];

			Logger::info( 'making_anthropic_request', 'Making request to Anthropic API', [
				'idea_id' => $idea_id,
				'model' => $this->model,
				'max_tokens' => $this->get_max_tokens(),
				'prompt_length' => strlen( $prompt )
			] );

			// Make API request
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: About to make API request\n", FILE_APPEND );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Memory before API call: " . memory_get_usage(true) . " bytes\n", FILE_APPEND );
			
			$api_start_time = microtime(true);
			try {
				$response = $this->make_request( $request_data );
				$api_duration = microtime(true) - $api_start_time;
				
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: API request completed in {$api_duration} seconds\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Memory after API call: " . memory_get_usage(true) . " bytes\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Response type: " . gettype( $response ) . "\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Response keys: " . (is_array( $response ) ? implode(', ', array_keys( $response )) : 'not_array') . "\n", FILE_APPEND );
				
				Logger::info( 'anthropic_request_success', 'Anthropic API request successful', [
					'idea_id' => $idea_id,
					'response_keys' => is_array( $response ) ? array_keys( $response ) : 'not_array',
					'has_content' => isset( $response['content'] ),
					'has_usage' => isset( $response['usage'] ),
					'api_duration' => $api_duration
				] );
			} catch ( \Exception $e ) {
				$api_duration = microtime(true) - $api_start_time;
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: API request FAILED after {$api_duration} seconds: " . $e->getMessage() . "\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Exception trace: " . $e->getTraceAsString() . "\n", FILE_APPEND );
				
				Logger::error( 'anthropic_request_failed', 'Anthropic API request failed', [
					'idea_id' => $idea->id ?? null,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'api_duration' => $api_duration,
					'request_data' => array_diff_key( $request_data, [ 'messages' => '' ] ) // Log without full prompt
				] );
				throw new \Exception( 'Anthropic API request failed: ' . $e->getMessage() );
			}

			// Validate response format
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Starting response validation\n", FILE_APPEND );

			if ( ! isset( $response['content'] ) || ! is_array( $response['content'] ) ) {
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Invalid response format detected\n", FILE_APPEND );
				Logger::error( 'anthropic_invalid_response', 'Invalid response format from Anthropic API', [
					'idea_id' => $idea->id ?? null,
					'response_keys' => is_array( $response ) ? array_keys( $response ) : 'not_array',
					'content_type' => isset( $response['content'] ) ? gettype( $response['content'] ) : 'missing',
					'response_structure' => is_array( $response ) ? array_map( 'gettype', $response ) : 'not_array'
				] );
				throw new \Exception( 'Invalid response format from API' );
			}

			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Response validation passed, extracting content\n", FILE_APPEND );

			$content = $response['content'][0]['text'] ?? '';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Content extracted, length: " . strlen( $content ) . "\n", FILE_APPEND );
			
			Logger::info( 'anthropic_content_extracted', 'Content extracted from API response', [
				'idea_id' => $idea->id ?? null,
				'content_length' => strlen( $content ),
				'content_preview' => substr( $content, 0, 200 ) . '...'
			] );

			// Parse content
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Starting content parsing\n", FILE_APPEND );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Memory before parsing: " . memory_get_usage(true) . " bytes\n", FILE_APPEND );
			
			Logger::info( 'parsing_content_response', 'Parsing content response', [
				'idea_id' => $idea->id ?? null,
				'content_length' => strlen( $content )
			] );
			
			try {
				$parse_start_time = microtime(true);
			$parsed_content = $this->parse_content_response( $content );
				$parse_duration = microtime(true) - $parse_start_time;
				
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Content parsing completed in {$parse_duration} seconds\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Memory after parsing: " . memory_get_usage(true) . " bytes\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Parsed fields: " . implode(', ', array_keys( $parsed_content )) . "\n", FILE_APPEND );
				
				Logger::info( 'content_parsing_success', 'Content parsed successfully', [
					'idea_id' => $idea->id ?? null,
					'parsed_fields' => array_keys( $parsed_content ),
					'html_length' => strlen( $parsed_content['html'] ?? '' ),
					'has_title' => ! empty( $parsed_content['title'] ),
					'has_meta_description' => ! empty( $parsed_content['meta_description'] ),
					'parse_duration' => $parse_duration
				] );
			} catch ( \Exception $e ) {
				$parse_duration = microtime(true) - $parse_start_time;
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Content parsing FAILED after {$parse_duration} seconds: " . $e->getMessage() . "\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Parsing error trace: " . $e->getTraceAsString() . "\n", FILE_APPEND );
				
				Logger::error( 'content_parsing_failed', 'Failed to parse content response', [
					'idea_id' => $idea->id ?? null,
					'error' => $e->getMessage(),
					'content_preview' => substr( $content, 0, 500 ) . '...',
					'parse_duration' => $parse_duration
				] );
				throw new \Exception( 'Failed to parse content response: ' . $e->getMessage() );
			}

			// Record cost
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Starting cost recording\n", FILE_APPEND );
			
			$tokens_used = $response['usage']['input_tokens'] + $response['usage']['output_tokens'];
			$cost = $this->calculate_cost( $response['usage']['input_tokens'], $response['usage']['output_tokens'] );
			
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Cost calculated: $cost USD, Tokens: $tokens_used\n", FILE_APPEND );
			
			try {
			$this->cost_model->record_anthropic_cost( 'generate_content', $cost, $tokens_used );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Cost recording completed successfully\n", FILE_APPEND );
				
				Logger::info( 'cost_recorded', 'API cost recorded successfully', [
					'idea_id' => $idea->id ?? null,
					'cost' => $cost,
					'tokens_used' => $tokens_used
				] );
			} catch ( \Exception $e ) {
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Cost recording FAILED: " . $e->getMessage() . "\n", FILE_APPEND );
				
				Logger::error( 'cost_recording_failed', 'Failed to record API cost', [
					'idea_id' => $idea->id ?? null,
					'cost' => $cost,
					'tokens_used' => $tokens_used,
					'error' => $e->getMessage()
				] );
				// Don't throw - cost recording failure shouldn't break content generation
			}

			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Preparing final response\n", FILE_APPEND );

			Logger::info( 'anthropic_content_generated', 'Blog content generated successfully', [
				'idea_id' => $idea->id ?? null,
				'model' => $this->model,
				'max_tokens' => $this->get_max_tokens(),
				'word_count' => str_word_count( $parsed_content['html'] ?? '' ),
				'tokens_used' => $tokens_used,
				'input_tokens' => $response['usage']['input_tokens'] ?? 0,
				'output_tokens' => $response['usage']['output_tokens'] ?? 0,
				'cost' => $cost,
			] );

			$final_result = [
				'success' => true,
				'content' => $parsed_content,
				'cost' => $cost,
				'tokens_used' => $tokens_used,
				'usage' => $response['usage'] ?? []
			];
			
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Blog content generation completed successfully, returning result\n", FILE_APPEND );
			
			return $final_result;

		} catch ( \Exception $e ) {
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Blog content generation FAILED with exception: " . $e->getMessage() . "\n", FILE_APPEND );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Exception trace: " . $e->getTraceAsString() . "\n", FILE_APPEND );
			
			Logger::error( 'anthropic_content_failed', 'Blog content generation failed', [
				'error' => $e->getMessage(),
				'idea_id' => $idea->id ?? null,
				'trace' => $e->getTraceAsString(),
				'contexts_available' => array_keys( $contexts ),
				'seo_keywords_count' => count( $seo_keywords )
			] );

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Build prompt for idea generation.
	 *
	 * @param array  $contexts        Contexts data.
	 * @param array  $existing_titles Existing titles to avoid.
	 * @param array  $denied_titles   Denied titles to avoid.
	 * @param int    $count           Number of ideas to generate.
	 * @param string $custom_prompt   Optional custom prompt for idea generation.
	 * @param array  $personas        Available writing personas.
	 * @return string Formatted prompt.
	 */
	private function build_ideas_prompt( $contexts, $existing_titles, $denied_titles, $count, $custom_prompt, $personas = [] ) {
		$prompt = "You are a professional content strategist creating blog post ideas. Generate {$count} unique blog post ideas based on the following information:\n\n";

		// Add contexts with improved organization
		if ( ! empty( $contexts['general'] ) ) {
			$prompt .= "BUSINESS CONTEXT:\n" . $contexts['general'] . "\n\n";
		}

		if ( ! empty( $contexts['products'] ) ) {
			$prompt .= "PRODUCTS/SERVICES:\n" . $contexts['products'] . "\n\n";
		}

		if ( ! empty( $contexts['seo'] ) ) {
			$prompt .= "SEO GUIDELINES:\n" . $contexts['seo'] . "\n\n";
		}

		if ( ! empty( $contexts['keywords'] ) ) {
			$prompt .= "TARGET KEYWORDS:\n" . $contexts['keywords'] . "\n\n";
		}

		// Add available WordPress categories
		$categories = get_categories( [ 'hide_empty' => false ] );
		if ( ! empty( $categories ) ) {
			$prompt .= "AVAILABLE WORDPRESS CATEGORIES:\n";
			$prompt .= "Choose the most appropriate category (or suggest multiple categories) from these existing WordPress categories:\n";
			foreach ( $categories as $category ) {
				$prompt .= "- {$category->name}";
				if ( ! empty( $category->description ) ) {
					$prompt .= " ({$category->description})";
				}
				$prompt .= "\n";
			}
			$prompt .= "You can suggest multiple categories if the post fits multiple topics (e.g., 'Technology, Buying Guides').\n\n";
		}

		// Add personas
		if ( ! empty( $personas ) ) {
			$prompt .= "AVAILABLE WRITING PERSONAS:\n";
			$prompt .= "Choose the most appropriate persona for each blog post idea from these options:\n";
			foreach ( $personas as $persona ) {
				$prompt .= "- {$persona['name']} (ID: {$persona['id']}): {$persona['bio']}";
				if ( ! empty( $persona['expertise'] ) ) {
					$prompt .= " | Expertise: {$persona['expertise']}";
				}
				if ( ! empty( $persona['tone'] ) ) {
					$prompt .= " | Tone: {$persona['tone']}";
				}
				$prompt .= "\n";
			}
			$prompt .= "\n";
		}

		// Add custom prompt if provided
		if ( ! empty( $custom_prompt ) ) {
			$prompt .= "SPECIFIC REQUIREMENTS:\n" . $custom_prompt . "\n\n";
		}

		// Add restrictions with better organization
		if ( ! empty( $existing_titles ) ) {
			$title_count = count( $existing_titles );
			$sample_titles = array_slice( $existing_titles, 0, 20 );
			$prompt .= "AVOID THESE EXISTING TITLES ({$title_count} total, showing first 20):\n- " . implode( "\n- ", $sample_titles );
			if ( $title_count > 20 ) {
				$prompt .= "\n... and " . ( $title_count - 20 ) . " more existing titles.";
			}
			$prompt .= "\n\n";
		}

		if ( ! empty( $denied_titles ) ) {
			$denied_count = count( $denied_titles );
			$sample_denied = array_slice( $denied_titles, 0, 10 );
			$prompt .= "AVOID THESE PREVIOUSLY DENIED TITLES ({$denied_count} total):\n- " . implode( "\n- ", $sample_denied );
			if ( $denied_count > 10 ) {
				$prompt .= "\n... and " . ( $denied_count - 10 ) . " more denied titles.";
			}
			$prompt .= "\n\n";
		}

		$prompt .= "GENERATION REQUIREMENTS:\n";
		$prompt .= "- Generate exactly {$count} completely unique blog post ideas\n";
		$prompt .= "- Each idea must be distinct from existing and denied titles\n";
		$prompt .= "- Focus on topics that align with the business context and target audience\n";
		$prompt .= "- Ensure ideas are relevant to the products/services mentioned\n";
		$prompt .= "- Incorporate SEO guidelines and target keywords naturally\n\n";

		$prompt .= "FORMAT YOUR RESPONSE EXACTLY AS FOLLOWS:\n\n";
		$prompt .= "For each idea, use this exact format (one idea per numbered section):\n\n";
		$prompt .= "1.\n";
		$prompt .= "**Title:** [engaging, SEO-friendly title, 60-70 characters]\n";
		$prompt .= "**Description:** [2-3 sentences explaining the content and value proposition]\n";
		$prompt .= "**Category:** [choose from the WordPress categories listed above, can be multiple separated by commas]\n";
		$prompt .= "**Primary keyword:** [main SEO focus keyword from the target keywords]\n";
		if ( ! empty( $personas ) ) {
			$prompt .= "**Recommended persona:** [persona name and ID, e.g., 'John (ID: 1)']\n";
		}
		$prompt .= "\n";
		$prompt .= "2.\n";
		$prompt .= "**Title:** [next idea title]\n";
		$prompt .= "[...continue with same format]\n\n";
		$prompt .= "IMPORTANT: Follow this exact format with the **Field:** labels to ensure proper parsing.";

		return $prompt;
	}

	/**
	 * Build prompt for content generation.
	 *
	 * @param object $idea Idea data.
	 * @param array $contexts Context data.
	 * @param array $seo_keywords SEO keywords.
	 * @param object|null $persona Persona data.
	 * @return string Formatted prompt.
	 */
	private function build_content_prompt( $idea, $contexts, $seo_keywords = [], $persona = null ) {
		$prompt = "You are a MASTER AVADA BUILDER EXPERT and professional blog content creator. You have extensive experience with Avada theme, Fusion Builder, and all Avada shortcodes. 
					You create content using Avada Live Builder layouts that are fully editable, visually stunning, and SEO-optimized. 
					Your mission: create a modern, colorful, and professional blog post using AVADA SHORTCODES AND FUSION BUILDER ELEMENTS that contains strategic keyword placement, product promotion, and engaging visual design. 
					Follow every rule below exactly to produce Avada-ready content.\n\n";
		
		$prompt .= "🚨 MANDATORY REQUIREMENTS - FAILURE TO FOLLOW WILL RESULT IN REJECTION 🚨\n";
		$prompt .= "================================================================\n";
		$prompt .= "1. KEYWORD USAGE (ABSOLUTELY REQUIRED):\n";
		if ( ! empty( $seo_keywords ) ) {
			$prompt .= "   - PRIMARY FOCUS KEYWORD: " . $seo_keywords[0] . "\n";
			$prompt .= "   - You MUST use this keyword:\n";
			$prompt .= "     • In at least TWO fusion_title elements\n";
			$prompt .= "     • 5-7 times throughout the content (1-2% density)\n";
			$prompt .= "     • In the first paragraph\n";
			$prompt .= "     • In the meta description\n";
			$prompt .= "     • In the conclusion\n";
			$prompt .= "   - ALL KEYWORDS: " . implode( ', ', $seo_keywords ) . "\n\n";
		}
		
		$prompt .= "2. PRODUCT PROMOTION (ABSOLUTELY REQUIRED):\n";
		$prompt .= "   - You MUST promote at least 1-2 products from the products context\n";
		$prompt .= "   - Include product images using fusion_imageframe shortcode\n";
		$prompt .= "   - Create dedicated sections showcasing relevant products\n";
		$prompt .= "   - Link products naturally within the content\n";
		$prompt .= "   - Every post MUST have product recommendations\n\n";
		
		$prompt .= "3. AVADA BUILDER REQUIREMENTS:\n";
		$prompt .= "   - ALL titles MUST use [fusion_title] shortcode\n";
		$prompt .= "   - At least 2 titles MUST contain the primary keyword\n";
		$prompt .= "   - Titles should use colors and gradients matching the post palette\n";
		$prompt .= "   - Cards with headers must be HTML inside [fusion_text] blocks\n";
		$prompt .= "   - Use Avada columns, containers, and layout elements\n";
		$prompt .= "   - fusion-cards control is DEPRECATED - use HTML in text blocks\n\n";
		
		$prompt .= "4. VERIFICATION CHECKLIST (ALL MUST BE ✓):\n";
		$prompt .= "   □ Primary keyword appears in at least TWO [fusion_title] elements\n";
		$prompt .= "   □ Primary keyword used 5-7 times in content\n";
		$prompt .= "   □ At least 1-2 products are promoted with images\n";
		$prompt .= "   □ All content uses Avada shortcodes, not plain HTML\n";
		$prompt .= "   □ Titles have appropriate colors/gradients\n";
		$idea_title = is_array( $idea ) ? $idea['title'] : $idea->title;
		$idea_description = is_array( $idea ) ? $idea['description'] : $idea->description;
		// Note: primary_keyword field doesn't exist in database, use title-based keyword instead
		$idea_primary_keyword = is_array( $idea ) 
			? ( $idea['primary_keyword'] ?? $idea['title'] ) 
			: ( $idea->primary_keyword ?? $idea->title );
		
		$prompt .= "   □ Content stays on the specific topic: " . $idea_title . "\n";
		$prompt .= "================================================================\n\n";
		
		$prompt .= "🎯 YOUR SPECIFIC BLOG POST TOPIC 🎯\n";
		$prompt .= "================================================\n";
		$prompt .= "Create a blog post that SPECIFICALLY addresses this approved idea:\n\n";
		$prompt .= "BLOG POST TITLE: " . $idea_title . "\n";
		$prompt .= "BLOG POST DESCRIPTION: " . $idea_description . "\n";
		if ( ! empty( $idea_primary_keyword ) ) {
			$prompt .= "PRIMARY FOCUS KEYWORD: " . $idea_primary_keyword . "\n";
		}
		$prompt .= "\n";
		$prompt .= "⚠️ BALANCE REQUIREMENTS ⚠️\n";
		$prompt .= "- Write about the SPECIFIC topic while incorporating keywords and products\n";
		$prompt .= "- Example: If about 'SEL Visual Displays', discuss SEL strategies USING poster makers\n";
		$prompt .= "- Example: If about 'First Grade', show how poster makers help first-grade learning\n";
		$prompt .= "- The keywords and products should SUPPORT the topic, not replace it\n";
		$prompt .= "================================================\n\n";

		// Add persona information if provided
		if ( $persona ) {
			$prompt .= "WRITING PERSONA:\n";
			$prompt .= "Name: " . $persona->name . "\n";
			$prompt .= "Bio: " . $persona->bio . "\n";
			if ( ! empty( $persona->expertise ) ) {
				$prompt .= "Expertise: " . $persona->expertise . "\n";
			}
			if ( ! empty( $persona->tone ) ) {
				$prompt .= "Tone: " . $persona->tone . "\n";
			}
			if ( ! empty( $persona->writing_style ) ) {
				$prompt .= "Writing Style: " . $persona->writing_style . "\n";
			}
			$prompt .= "IMPORTANT: Write this blog post in the voice, tone, and style of this persona. Make the content reflect their personality and expertise.\n\n";
		}

		// Add contexts with enhanced organization
		if ( ! empty( $contexts['general'] ) ) {
			$prompt .= "BUSINESS CONTEXT (use when relevant to the specific topic):\n" . $contexts['general'] . "\n\n";
		}

		if ( ! empty( $contexts['products'] ) ) {
			$prompt .= "PRODUCTS/SERVICES (only mention when directly relevant to the specific topic):\n" . $contexts['products'] . "\n\n";
		}

		if ( ! empty( $contexts['seo'] ) ) {
			$prompt .= "SEO GUIDELINES (adapt to the specific topic):\n" . $contexts['seo'] . "\n\n";
		}

		if ( ! empty( $contexts['keywords'] ) ) {
			$prompt .= "ADDITIONAL KEYWORDS (only use those relevant to the specific topic):\n" . $contexts['keywords'] . "\n\n";
		}

		if ( ! empty( $contexts['layout'] ) ) {
			$prompt .= "LAYOUT REQUIREMENTS & GUIDELINES:\n" . $contexts['layout'] . "\n\n";
		}

		if ( ! empty( $seo_keywords ) ) {
			$prompt .= "SEO KEYWORDS (MANDATORY USAGE):\n" . implode( ', ', $seo_keywords ) . "\n";
			$prompt .= "MANDATORY KEYWORD USAGE RULES:\n";
			$prompt .= "- PRIMARY KEYWORD ('" . $seo_keywords[0] . "') MUST appear:\n";
			$prompt .= "  • In at least TWO [fusion_title] elements (exact match or natural variation)\n";
			$prompt .= "  • 5-7 times throughout the content body\n";
			$prompt .= "  • In the first 100 words\n";
			$prompt .= "  • In the conclusion\n";
			$prompt .= "- SECONDARY KEYWORDS: Use 1-2 other keywords from the list naturally\n";
			$prompt .= "- DO NOT skip keyword usage - it is MANDATORY for SEO\n";
			$prompt .= "- Integrate keywords naturally while maintaining topic focus\n\n";
		}

		// Add Avada Builder Guidelines
		$prompt .= "AVADA BUILDER ELEMENTS REFERENCE:\n";
		$prompt .= "You have access to ALL Avada Fusion Builder shortcodes. Use them extensively:\n\n";
		
		$prompt .= "LAYOUT STRUCTURE:\n";
		$prompt .= "[fusion_builder_container] - Main container for sections\n";
		$prompt .= "[fusion_builder_row] - Rows within containers\n";
		$prompt .= "[fusion_builder_column] - Columns with type='1_1', '1_2', '1_3', '1_4', '2_3', '3_4'\n\n";
		
		$prompt .= "CONTENT ELEMENTS:\n";
		$prompt .= "[fusion_title] - REQUIRED for ALL headings. Parameters:\n";
		$prompt .= "  • size='2' for main headings, size='3' for subheadings\n";
		$prompt .= "  • color='#hexcode' or gradient colors\n";
		$prompt .= "  • style_type='default' or 'double solid' for decorative lines\n";
		$prompt .= "  • sep_color='#hexcode' for separator color\n";
		$prompt .= "  • margin_top/bottom for spacing\n";
		$prompt .= "  • MUST include keywords in at least 2 titles\n\n";
		
		$prompt .= "[fusion_text] - For all text content and HTML cards\n";
		$prompt .= "[fusion_imageframe] - For images with parameters:\n";
		$prompt .= "  • image='{URL}' \n";
		$prompt .= "  • style_type='none', 'glow', 'dropshadow', 'bottomshadow'\n";
		$prompt .= "  • hover_type='none', 'zoomin', 'zoomout', 'liftup'\n";
		$prompt .= "  • align='center', 'left', 'right'\n\n";
		
		$prompt .= "[fusion_separator] - Decorative separators\n";
		$prompt .= "[fusion_alert] - Alert boxes with type='success', 'error', 'notice', 'general'\n";
		$prompt .= "[fusion_button] - Call-to-action buttons\n";
		$prompt .= "[fusion_checklist] - Styled lists with icons\n";
		$prompt .= "[fusion_counters_box] - Animated number counters\n";
		$prompt .= "[fusion_progress] - Progress bars\n";
		$prompt .= "[fusion_accordion] - Collapsible content sections\n";
		$prompt .= "[fusion_tabs] - Tabbed content\n";
		$prompt .= "[fusion_testimonials] - Quote/testimonial sliders\n\n";
		
		$prompt .= "COLOR PALETTE INSTRUCTIONS:\n";
		$prompt .= "- Choose a cohesive color scheme for the post (2-3 main colors)\n";
		$prompt .= "- Use vibrant colors for titles: #0099ff (blue), #20c997 (teal), #e91e63 (magenta), #9c27b0 (purple)\n";
		$prompt .= "- Apply gradient colors to key titles using gradient_start_color and gradient_end_color\n";
		$prompt .= "- Ensure colors complement each other and match the topic mood\n";
		$prompt .= "- Educational topics: bright, engaging colors\n";
		$prompt .= "- Professional topics: sophisticated color combinations\n\n";
		
		$prompt .= "CARD IMPLEMENTATION (IMPORTANT):\n";
		$prompt .= "Since fusion_card is DEPRECATED, create cards using HTML inside [fusion_text]:\n";
		$prompt .= "[fusion_text]\n";
		$prompt .= "<div style='border: 2px solid #0099ff; border-radius: 10px; padding: 20px; margin: 20px 0; box-shadow: 0 4px 15px rgba(0,153,255,0.2);'>\n";
		$prompt .= "  <div style='background: linear-gradient(135deg, #0099ff, #0066cc); color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0;'>\n";
		$prompt .= "    <h3>Card Header</h3>\n";
		$prompt .= "  </div>\n";
		$prompt .= "  <div>Card content goes here...</div>\n";
		$prompt .= "</div>\n";
		$prompt .= "[/fusion_text]\n\n";

		$prompt .= "CRITICAL LAYOUT REQUIREMENTS:\n";
		$prompt .= "- Use Avada's responsive column system for all layouts\n";
		$prompt .= "- Alternate between different column layouts for visual variety\n";
		$prompt .= "- Include colorful separators between major sections\n";
		$prompt .= "- Use background colors/gradients on containers for visual appeal\n";
		$prompt .= "- Ensure mobile responsiveness with Avada's built-in responsive settings\n\n";

		$prompt .= "🎨 AVADA LAYOUT VARIETY REQUIREMENTS - CREATE UNIQUE STRUCTURES 🎨\n";
		$prompt .= "================================================================\n";
		$prompt .= "CRITICAL: DO NOT follow the same Avada layout pattern for every post!\n\n";
		$prompt .= "FORBIDDEN FORMULA (DO NOT USE THIS PATTERN):\n";
		$prompt .= "❌ Always starting with full-width title → text block → alert → column grid → accordion\n";
		$prompt .= "❌ Always using 1_2 + 1_2 columns for everything\n";
		$prompt .= "❌ Always placing separators after every section\n";
		$prompt .= "❌ Always using the same container background styles\n\n";
		$prompt .= "REQUIRED: Choose ONE of these diverse Avada layout patterns:\n\n";
		$prompt .= "Pattern A - Magazine Style:\n";
		$prompt .= "• Start with [fusion_highlight] for a key statistic or quote\n";
		$prompt .= "• Use asymmetric columns (1_3 + 2_3 or 1_4 + 3_4)\n";
		$prompt .= "• Feature [fusion_testimonials] for pull quotes\n";
		$prompt .= "• Sidebar columns with [fusion_widget_area]\n";
		$prompt .= "• [fusion_dropcap] for magazine-style emphasis\n\n";
		$prompt .= "Pattern B - Story-Driven:\n";
		$prompt .= "• Open with [fusion_text] narrative in a colored container\n";
		$prompt .= "• Use [fusion_counters_box] for timeline elements\n";
		$prompt .= "• [fusion_accordion] for progressive story reveals\n";
		$prompt .= "• [fusion_testimonials] for character voices\n";
		$prompt .= "• Circular layout with [fusion_counters_circle]\n\n";
		$prompt .= "Pattern C - Visual-First:\n";
		$prompt .= "• Lead with [fusion_imageframe] in hero size\n";
		$prompt .= "• Use [fusion_fontawesome] icons throughout\n";
		$prompt .= "• Minimal [fusion_text] with large margins\n";
		$prompt .= "• [fusion_gallery] for visual storytelling\n";
		$prompt .= "• Background images on containers\n\n";
		$prompt .= "Pattern D - Interactive Learning:\n";
		$prompt .= "• Start with [fusion_alert type='custom'] challenge box\n";
		$prompt .= "• Use [fusion_tabs] for step-by-step content\n";
		$prompt .= "• [fusion_checklist] for action items\n";
		$prompt .= "• [fusion_toggle] for Q&A sections\n";
		$prompt .= "• Multiple [fusion_button] CTAs\n\n";
		$prompt .= "Pattern E - Modular Blocks:\n";
		$prompt .= "• Varied container backgrounds and padding\n";
		$prompt .= "• Mix full-width and boxed containers\n";
		$prompt .= "• [fusion_content_boxes] with different layouts\n";
		$prompt .= "• Overlapping elements with negative margins\n";
		$prompt .= "• [fusion_flip_boxes] for interactive modules\n\n";
		$prompt .= "AVADA VARIETY TECHNIQUES:\n";
		$prompt .= "• Vary container settings: background colors, patterns, padding\n";
		$prompt .= "• Mix column layouts: 1_1, 1_2, 1_3, 2_3, 1_4, 3_4\n";
		$prompt .= "• Use different title styles and separator types\n";
		$prompt .= "• Experiment with Avada's animation settings\n";
		$prompt .= "• Apply gradient backgrounds to containers\n";
		$prompt .= "• Use box shadows and borders creatively\n\n";
		$prompt .= "REMEMBER: Each post should use Avada elements uniquely!\n";
		$prompt .= "================================================================\n\n";

		$prompt .= "CONTENT REQUIREMENTS:\n";
		$prompt .= "- Write 2000-3000 words about '" . $idea_title . "'\n";
		$prompt .= "- MANDATORY PRODUCT INTEGRATION:\n";
		$prompt .= "  • You MUST promote 1-2 products from the products context\n";
		$prompt .= "  • Include product images using [fusion_imageframe] shortcode\n";
		$prompt .= "  • Create a dedicated 'Recommended Products' or 'Tools That Help' section using Avada elements\n";
		$prompt .= "  • Naturally mention products within the content flow\n";
		$prompt .= "  • Every blog post MUST have product recommendations - NO EXCEPTIONS\n";
		$prompt .= "- MANDATORY KEYWORD INTEGRATION:\n";
		$prompt .= "  • Primary keyword MUST appear in at least TWO [fusion_title] elements\n";
		$prompt .= "  • Use primary keyword 5-7 times throughout content\n";
		$prompt .= "  • Include keyword in first paragraph and conclusion\n";
		$prompt .= "- Balance topic focus with commercial intent:\n";
		$prompt .= "  • If about SEL: Show how poster makers enhance SEL learning\n";
		$prompt .= "  • If about First Grade: Demonstrate poster makers for first-grade activities\n";
		$prompt .= "  • If about Fundraising: Include poster creation as a fundraising tool\n";
		$prompt .= "- Include practical examples and actionable tips\n";
		$prompt .= "- Use a tone appropriate for the target audience\n";
		$prompt .= "- The word 'Transform' is banned in the content\n\n";

		$prompt .= "AVADA STRUCTURE REQUIREMENTS:\n";
		$prompt .= "- Use deep analysis and critical thinking to provide unique insights\n";
		$prompt .= "- Structure content with Avada's Fusion Builder shortcodes and containers\n";
		$prompt .= "- Create hierarchical structure with containers → rows → columns\n";
		$prompt .= "- Include hero sections using full-width containers with background colors\n";
		$prompt .= "- Create content that provides genuine value through data and research\n";
		$prompt .= "- Include actionable insights backed by evidence\n";
		$prompt .= "- Use [fusion_title] for ALL headings with appropriate size parameters\n";
		$prompt .= "- Add visual interest with Avada elements like alerts, checklists, and content boxes\n";
		$prompt .= "- Include data in [fusion_table] shortcodes where appropriate\n";
		$prompt .= "- NEVER use emojis or text based icons anywhere in the content - they are strictly banned\n\n";

		$prompt .= "SEO OPTIMIZATION REQUIREMENTS:\n";
		$prompt .= "- MANDATORY: Use PRIMARY KEYWORD ('" . (!empty($seo_keywords) ? $seo_keywords[0] : $idea_title) . "') as follows:\n";
		$prompt .= "  • In at least TWO [fusion_title] elements - THIS IS REQUIRED\n";
		$prompt .= "  • 5-7 times in the content (1-2% density) - THIS IS REQUIRED\n";
		$prompt .= "  • In the first paragraph - THIS IS REQUIRED\n";
		$prompt .= "  • In the meta description - THIS IS REQUIRED\n";
		$prompt .= "  • In the conclusion - THIS IS REQUIRED\n";
		$prompt .= "- Focus keyword MUST relate to '" . $idea_title . "' topic\n";
		$prompt .= "- Example integration: 'When teaching about emotions, a poster maker for schools helps create visual SEL displays'\n";
		$prompt .= "- CRITICAL: Use [fusion_title size='2'] for main headings (equivalent to H2)\n";
		$prompt .= "- REQUIRED: Use [fusion_title size='3'] for subheadings (equivalent to H3)\n";
		$prompt .= "- Continue with size='4', size='5' for deeper heading levels\n";
		$prompt .= "- Include internal linking opportunities with descriptive anchor text\n";
		$prompt .= "- Ensure meta description contains the primary keyword\n";
		$prompt .= "- Write compelling, click-worthy title under 60 characters\n";
		$prompt .= "- Structure content for featured snippets when possible\n\n";

		$prompt .= "RESEARCH AND CITATIONS REQUIREMENTS:\n";
		$prompt .= "- Include actual statistics, studies, and research findings relevant to the topic\n";
		$prompt .= "- Cite credible sources (academic papers, industry reports, government data, reputable organizations)\n";
		$prompt .= "- Use recent data (preferably from the last 2-3 years unless historical context is needed)\n";
		$prompt .= "- Format citations inline as (Source, Year) and include a references section at the end\n";
		$prompt .= "- Verify all statistics and facts are accurate and properly attributed\n";
		$prompt .= "- Include 2-4 credible sources minimum\n";
		$prompt .= "- Present data in a way that supports your arguments and enhances credibility\n\n";

		$prompt .= "DATA VISUALIZATION REQUIREMENTS:\n";
		$prompt .= "- CHARTS ARE OPTIONAL: Only include ApexCharts visualizations when they would genuinely enhance understanding of complex data\n";
		$prompt .= "- Good candidates for charts: multiple data points being compared, trends over time, statistical breakdowns, survey results\n";
		$prompt .= "- NOT suitable for charts: single statistics, simple percentages, brief mentions of data, general statements\n";
		$prompt .= "- It's perfectly fine to have NO charts in a post if the data doesn't warrant visualization\n";
		$prompt .= "- When you do include charts, use appropriate types (line for trends, bar for comparisons, pie for proportions)\n";
		$prompt .= "- Include interactive features like tooltips and responsive design\n";
		$prompt .= "- Place charts in dedicated containers with IDs like 'chart1', 'chart2', etc.\n";
		$prompt .= "- Add chart initialization scripts at the end of the content\n";
		$prompt .= "- Ensure charts are mobile-responsive and accessible\n";
		$prompt .= "- Use professional color schemes that match modern web design\n\n";

		$prompt .= "IMAGE PLACEMENT REQUIREMENTS:\n";
		$prompt .= "- Place {{image1}} near the beginning (after intro or first section)\n";
		$prompt .= "- Place {{image2}} in the middle or toward the end of content\n";
		$prompt .= "- Add more images ({{image3}}, {{image4}}) if the content benefits from them\n";
		$prompt .= "- Each image should be contextually relevant to surrounding content\n";
		$prompt .= "- Use [fusion_imageframe] with appropriate parameters for all images\n";
		$prompt .= "- Include proper alt text in the shortcode parameters\n\n";

		$prompt .= "PRODUCT IMAGE REQUIREMENTS:\n";
		$prompt .= "- When referencing products from the products context, include product images using Avada shortcodes\n";
		$prompt .= "- Product images should link directly to the product URL if provided\n";
		$prompt .= "- Use this Avada structure for product images:\n";
		$prompt .= "  [fusion_imageframe image_id='' max_width='250px' hover_type='liftup' align='center' \n";
		$prompt .= "   lightbox='no' link='[PRODUCT_URL]' linktarget='_blank' \n";
		$prompt .= "   style_type='dropshadow' bordercolor='' bordersize='0px' borderradius='8' \n";
		$prompt .= "   alt='[PRODUCT_NAME]' class='product-image'][/fusion_imageframe]\n";
		$prompt .= "- For featured product showcases, use a combination of Avada elements:\n";
		$prompt .= "  [fusion_builder_column type='1_3']\n";
		$prompt .= "    [fusion_imageframe image='[PRODUCT_IMAGE_URL]' max_width='250px' link='[PRODUCT_URL]' linktarget='_blank'][/fusion_imageframe]\n";
		$prompt .= "    [fusion_text]\n";
		$prompt .= "      <div class='product-name'>[PRODUCT_NAME]</div>\n";
		$prompt .= "      <div class='product-description'>[BRIEF_DESCRIPTION]</div>\n";
		$prompt .= "    [/fusion_text]\n";
		$prompt .= "  [/fusion_builder_column]\n";
		$prompt .= "- Always include product images when mentioning specific products from the context\n\n";

		$prompt .= "🔍 FINAL VERIFICATION BEFORE OUTPUT 🔍\n";
		$prompt .= "================================================================\n";
		$prompt .= "Before generating your response, verify ALL of the following:\n";
		$prompt .= "✓ Primary keyword appears in at least TWO [fusion_title] elements\n";
		$prompt .= "✓ Primary keyword used 5-7 times in content\n";
		$prompt .= "✓ Primary keyword in first paragraph\n";
		$prompt .= "✓ Primary keyword in meta description\n";
		$prompt .= "✓ At least 1-2 products promoted with [fusion_imageframe] and links\n";
		$prompt .= "✓ Product recommendation section included using Avada elements\n";
		$prompt .= "✓ Content focuses on specific topic: " . $idea_title . "\n";
		$prompt .= "✓ Keywords support the topic rather than dominate it\n";
		$prompt .= "✓ Layout is UNIQUE and uses Avada shortcodes creatively\n";
		$prompt .= "✓ Used one of the 5 Avada layout patterns (A-E) effectively\n";
		$prompt .= "✓ ALL titles use [fusion_title] with colors/gradients\n";
		$prompt .= "✓ All content wrapped in proper Avada container/row/column structure\n";
		$prompt .= "\nIF ANY ITEM IS NOT CHECKED, GO BACK AND FIX IT BEFORE OUTPUTTING!\n";
		$prompt .= "================================================================\n\n";

		$prompt .= "OUTPUT FORMAT REQUIREMENTS:\n";
		$prompt .= "CRITICAL: Do NOT wrap any content in markdown code blocks. Output clean, ready-to-use Avada shortcodes.\n";
		$prompt .= "CRITICAL: Never use ```html, ```javascript, or any other markdown code delimiters.\n";
		$prompt .= "CRITICAL: All content must be in Avada Fusion Builder shortcode format.\n\n";
		
		$prompt .= "Please structure your response exactly as follows:\n\n";
		$prompt .= "TITLE: [SEO-optimized title under 60 characters that includes the primary keyword]\n\n";
		$prompt .= "META_DESCRIPTION: [150-160 character description that MUST include the primary keyword]\n\n";
		$prompt .= "FOCUS_KEYPHRASE: [MUST be the primary keyword: " . (!empty($seo_keywords) ? $seo_keywords[0] : $idea_title) . "]\n\n";
		$prompt .= "TAGS: [5-8 relevant tags, MUST include the primary keyword as the first tag]\n\n";
		$prompt .= "AVADA_CONTENT: [full blog post using ONLY Avada Fusion Builder shortcodes - NO HTML except within [fusion_text] blocks]\n\n";
		$prompt .= "IMAGES:\n";
		$prompt .= "{{featured}}: [detailed description of featured image that relates to the SPECIFIC TOPIC, 2-3 sentences]\n";
		$prompt .= "{{image1}}: [detailed description of first content image that supports the SPECIFIC TOPIC, 2-3 sentences]\n";
		$prompt .= "{{image2}}: [detailed description of second content image that illustrates the SPECIFIC TOPIC, 2-3 sentences]\n";
		$prompt .= "[continue for each image token used]\n\n";
		$prompt .= "CHARTS:\n";
		$prompt .= "[OPTIONAL: Only include ApexCharts JavaScript if you created charts that genuinely enhance the content - leave empty if no charts are needed]\n\n";
		$prompt .= "REFERENCES:\n";
		$prompt .= "[List all sources cited in the content in a proper bibliography format]\n\n";
		$prompt .= "Remember: Output clean Avada shortcodes without any markdown code block delimiters. The content should be colorful, engaging, and reflect the persona's voice and style while staying true to the SPECIFIC TOPIC. No emojis allowed anywhere.\n\n";
		$prompt .= "FINAL REMINDER: Create a UNIQUE Avada layout that doesn't follow the standard formula. Each post should have its own distinctive visual structure using Avada's powerful builder elements!";

		return $prompt;
	}

	/**
	 * Parse ideas response from API.
	 *
	 * @param string $content API response content.
	 * @return array Parsed ideas.
	 */
	private function parse_ideas_response( $content ) {
		$this->log_info( 'parse_ideas_start', 'Starting to parse ideas from API response', [
			'content_length' => strlen( $content ),
			'raw_content' => $content
		] );

		$ideas = [];
		
		// Try multiple parsing approaches to handle different response formats
		
		// Approach 1: New structured format with numbered sections
		$structured_numbered_ideas = $this->parse_structured_numbered_ideas( $content );
		if ( ! empty( $structured_numbered_ideas ) ) {
			$this->log_info( 'parse_ideas_structured_numbered_success', 'Successfully parsed using structured numbered format', [
				'ideas_count' => count( $structured_numbered_ideas )
			] );
			return $structured_numbered_ideas;
		}
		
		// Approach 2: Look for structured format with clear separators
		$structured_ideas = $this->parse_structured_ideas( $content );
		if ( ! empty( $structured_ideas ) ) {
			$this->log_info( 'parse_ideas_structured_success', 'Successfully parsed using structured format', [
				'ideas_count' => count( $structured_ideas )
			] );
			return $structured_ideas;
		}
		
		// Approach 3: Look for numbered list format
		$numbered_ideas = $this->parse_numbered_ideas( $content );
		if ( ! empty( $numbered_ideas ) ) {
			$this->log_info( 'parse_ideas_numbered_success', 'Successfully parsed using numbered format', [
				'ideas_count' => count( $numbered_ideas )
			] );
			return $numbered_ideas;
		}
		
		// Approach 4: Look for any coherent idea patterns
		$pattern_ideas = $this->parse_pattern_ideas( $content );
		if ( ! empty( $pattern_ideas ) ) {
			$this->log_info( 'parse_ideas_pattern_success', 'Successfully parsed using pattern matching', [
				'ideas_count' => count( $pattern_ideas )
			] );
			return $pattern_ideas;
		}

		$this->log_error( 'parse_ideas_all_failed', 'All parsing approaches failed', [
			'content_length' => strlen( $content ),
			'raw_content' => $content
		] );

		return $ideas;
	}

	/**
	 * Parse structured numbered ideas format (1. **Title:** ... **Description:** ...).
	 */
	private function parse_structured_numbered_ideas( $content ) {
		$this->log_info( 'parse_structured_numbered_start', 'Attempting to parse structured numbered format', [
			'content_preview' => substr( $content, 0, 500 )
		] );
		
		$ideas = [];
		
		// Try multiple splitting approaches
		
		// Approach 1: Split by numbered sections on their own line
		$sections = preg_split( '/(?=^\d+\.\s*$)/m', $content );
		
		// Approach 2: If that doesn't work, try splitting by number followed by content
		if ( count( $sections ) < 2 ) {
			$sections = preg_split( '/(?=\d+\.\s*\*\*Title:\*\*)/m', $content );
		}
		
		// Approach 3: Split by just numbers with dots
		if ( count( $sections ) < 2 ) {
			$sections = preg_split( '/(?=\d+\.)/m', $content );
		}
		
		$this->log_debug( 'structured_numbered_sections', 'Split content into sections', [
			'sections_count' => count( $sections ),
			'first_section_preview' => isset( $sections[0] ) ? substr( $sections[0], 0, 200 ) : 'none'
		] );
		
		foreach ( $sections as $index => $section ) {
			$section = trim( $section );
			if ( empty( $section ) ) {
				continue;
			}
			
			// Skip section headers that are just numbers
			if ( preg_match( '/^\d+\.\s*$/', $section ) ) {
				continue;
			}
			
			$this->log_debug( 'processing_section', 'Processing section for idea extraction', [
				'section_index' => $index,
				'section_preview' => substr( $section, 0, 300 )
			] );
			
			$idea = [];
			
			// Extract title
			if ( preg_match( '/\*\*Title:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['title'] = trim( $matches[1] );
			}
			
			// Extract description  
			if ( preg_match( '/\*\*Description:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['description'] = trim( $matches[1] );
			}
			
			// Extract category
			if ( preg_match( '/\*\*Category:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['category'] = trim( $matches[1] );
			}
			
			// Extract primary keyword
			if ( preg_match( '/\*\*(Primary\s+[Kk]eyword|[Kk]eyword):\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['primary_keyword'] = trim( $matches[2] );
			}
			
			// Extract persona
			if ( preg_match( '/\*\*(Recommended\s+[Pp]ersona|[Pp]ersona):\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$persona_info = trim( $matches[2] );
				if ( preg_match( '/(\d+)/', $persona_info, $persona_matches ) ) {
					$idea['persona_id'] = (int) $persona_matches[1];
				}
			}
			
			// Only add if we have at least a title
			if ( ! empty( $idea['title'] ) ) {
				$this->log_debug( 'parsed_structured_numbered_idea', 'Parsed idea from structured numbered format', [
					'title' => $idea['title'],
					'has_description' => ! empty( $idea['description'] ),
					'has_category' => ! empty( $idea['category'] ),
					'has_keyword' => ! empty( $idea['primary_keyword'] ),
					'has_persona' => ! empty( $idea['persona_id'] )
				] );
				$ideas[] = $this->format_idea( $idea );
			}
		}
		
		// If we didn't find proper structured format, try to parse the mixed format
		if ( empty( $ideas ) ) {
			$this->log_info( 'trying_mixed_format_parsing', 'Structured numbered format failed, trying mixed format parsing' );
			$ideas = $this->parse_mixed_format_ideas( $content );
		}
		
		$this->log_info( 'parse_structured_numbered_complete', 'Completed structured numbered parsing', [
			'sections_processed' => count( $sections ),
			'ideas_extracted' => count( $ideas )
		] );
		
		return $ideas;
	}

	/**
	 * Parse mixed format where all structured data is in title/description fields.
	 */
	private function parse_mixed_format_ideas( $content ) {
		$this->log_info( 'parse_mixed_format_start', 'Attempting to parse mixed format (legacy broken format)', [
			'content_preview' => substr( $content, 0, 500 )
		] );
		
		$ideas = [];
		$lines = explode( "\n", $content );
		$current_idea = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}
			
			// Check for title line (might contain embedded data)
			if ( preg_match( '/^Title:\s*(.+)$/i', $line, $matches ) ) {
				// Save previous idea if exists
				if ( ! empty( $current_idea ) ) {
					$ideas[] = $this->format_idea( $this->extract_mixed_data( $current_idea ) );
				}
				
				$title_content = trim( $matches[1] );
				$current_idea = [ 'raw_title' => $title_content ];
				
				// Extract actual title from mixed content like "**2. Title:** Actual Title"
				if ( preg_match( '/\*\*\d+\.\s*Title:\*\*\s*(.+)/', $title_content, $title_matches ) ) {
					$current_idea['title'] = trim( $title_matches[1] );
				} else {
					$current_idea['title'] = $title_content;
				}
				
			} elseif ( preg_match( '/^Description:\s*(.+)$/i', $line, $matches ) ) {
				$desc_content = trim( $matches[1] );
				$current_idea['raw_description'] = $desc_content;
				
				// Extract actual description and other fields from the mixed content
				$this->extract_structured_from_description( $desc_content, $current_idea );
			}
		}
		
		// Add final idea
		if ( ! empty( $current_idea ) ) {
			$ideas[] = $this->format_idea( $this->extract_mixed_data( $current_idea ) );
		}
		
		$this->log_info( 'parse_mixed_format_complete', 'Completed mixed format parsing', [
			'lines_processed' => count( $lines ),
			'ideas_extracted' => count( $ideas )
		] );
		
		return $ideas;
	}

	/**
	 * Extract structured data from description field where everything is mixed together.
	 */
	private function extract_structured_from_description( $description, &$idea ) {
		// Extract actual description (before **Category:** or other fields)
		if ( preg_match( '/^\*\*Description:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $description, $matches ) ) {
			$idea['description'] = trim( $matches[1] );
		} else {
			// Fallback: description is everything before first **Field:**
			if ( preg_match( '/^(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $description, $matches ) ) {
				$idea['description'] = trim( $matches[1] );
			}
		}
		
		// Extract category
		if ( preg_match( '/\*\*Category:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $description, $matches ) ) {
			$idea['category'] = trim( $matches[1] );
		}
		
		// Extract primary keyword
		if ( preg_match( '/\*\*(Primary\s+[Kk]eyword|[Kk]eyword):\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $description, $matches ) ) {
			$idea['primary_keyword'] = trim( $matches[2] );
		}
		
		// Extract persona
		if ( preg_match( '/\*\*(Recommended\s+[Pp]ersona|[Pp]ersona):\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $description, $matches ) ) {
			$persona_info = trim( $matches[2] );
			if ( preg_match( '/(\d+)/', $persona_info, $persona_matches ) ) {
				$idea['persona_id'] = (int) $persona_matches[1];
			}
		}
		
		$this->log_debug( 'extracted_from_description', 'Extracted structured data from description field', [
			'original_description' => substr( $description, 0, 200 ),
			'extracted_description' => $idea['description'] ?? 'none',
			'extracted_category' => $idea['category'] ?? 'none',
			'extracted_keyword' => $idea['primary_keyword'] ?? 'none',
			'extracted_persona_id' => $idea['persona_id'] ?? 'none'
		] );
	}

	/**
	 * Extract and clean mixed data from idea array.
	 */
	private function extract_mixed_data( $idea_data ) {
		// Use extracted data if available, otherwise fall back to raw data
		$cleaned = [
			'title' => $idea_data['title'] ?? $idea_data['raw_title'] ?? '',
			'description' => $idea_data['description'] ?? $idea_data['raw_description'] ?? '',
			'category' => $idea_data['category'] ?? 'General',
			'primary_keyword' => $idea_data['primary_keyword'] ?? '',
		];
		
		// Include persona_id if it was extracted
		if ( isset( $idea_data['persona_id'] ) && is_numeric( $idea_data['persona_id'] ) ) {
			$cleaned['persona_id'] = (int) $idea_data['persona_id'];
		}
		
		return $cleaned;
	}

	/**
	 * Parse structured ideas format (with clear field labels).
	 */
	private function parse_structured_ideas( $content ) {
		$ideas = [];
		$lines = explode( "\n", $content );
		$current_idea = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Look for field patterns
			if ( preg_match( '/^(title|name):\s*(.+)$/i', $line, $matches ) ) {
				// Save previous idea if exists
				if ( ! empty( $current_idea ) ) {
					$ideas[] = $this->format_idea( $current_idea );
				}
				$current_idea = [ 'title' => trim( $matches[2] ) ];
				
			} elseif ( preg_match( '/^description:\s*(.+)$/i', $line, $matches ) ) {
				$current_idea['description'] = trim( $matches[1] );
				
			} elseif ( preg_match( '/^category:\s*(.+)$/i', $line, $matches ) ) {
				$current_idea['category'] = trim( $matches[1] );
				
			} elseif ( preg_match( '/^(keyword|primary keyword):\s*(.+)$/i', $line, $matches ) ) {
				$current_idea['primary_keyword'] = trim( $matches[2] );
				
			} elseif ( preg_match( '/^(persona|recommended persona):\s*(.+)$/i', $line, $matches ) ) {
				$persona_info = trim( $matches[2] );
				if ( preg_match( '/(\d+)/', $persona_info, $persona_matches ) ) {
					$current_idea['persona_id'] = (int) $persona_matches[1];
				}
			}
		}

		// Add final idea
		if ( ! empty( $current_idea ) ) {
			$ideas[] = $this->format_idea( $current_idea );
		}

		return $ideas;
	}

	/**
	 * Parse numbered list format.
	 */
	private function parse_numbered_ideas( $content ) {
		$ideas = [];
		$lines = explode( "\n", $content );
		$current_idea = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Check for numbered list start (1., 2., etc.)
			if ( preg_match( '/^(\d+)\.\s*(.+)/', $line, $matches ) ) {
				// Save previous idea
				if ( ! empty( $current_idea ) ) {
					$ideas[] = $this->format_idea( $current_idea );
				}
				
				// Start new idea - the title might be the whole line or just part of it
				$title_text = trim( $matches[2] );
				$current_idea = [ 'title' => $title_text ];
				
				// Check if this line contains more info (some formats put everything on one line)
				$this->extract_inline_info( $title_text, $current_idea );
				
			} elseif ( ! empty( $current_idea ) ) {
				// Try to extract additional info from continuation lines
				$this->extract_line_info( $line, $current_idea );
			}
		}

		// Add final idea
		if ( ! empty( $current_idea ) ) {
			$ideas[] = $this->format_idea( $current_idea );
		}

		return $ideas;
	}

	/**
	 * Parse using pattern matching for flexible formats.
	 */
	private function parse_pattern_ideas( $content ) {
		$ideas = [];
		
		// First try to parse bold markdown format (like **Title:** ... **Description:** ...)
		$bold_ideas = $this->parse_bold_markdown_format( $content );
		if ( ! empty( $bold_ideas ) ) {
			return $bold_ideas;
		}
		
		// Try to find ideas separated by double newlines or clear breaks
		$sections = preg_split( '/\n\s*\n|\n-{3,}|\n={3,}/', $content );
		
		foreach ( $sections as $section ) {
			$section = trim( $section );
			if ( empty( $section ) ) {
				continue;
			}
			
			$idea = $this->extract_idea_from_text( $section );
			if ( ! empty( $idea['title'] ) ) {
				$ideas[] = $this->format_idea( $idea );
			}
		}
		
		return $ideas;
	}

	/**
	 * Parse bold markdown format like **Title:** ... **Description:** ...
	 */
	private function parse_bold_markdown_format( $content ) {
		$ideas = [];
		
		// Split content by double newlines to get individual ideas
		$sections = preg_split( '/\n\s*\n/', $content );
		
		foreach ( $sections as $section ) {
			$section = trim( $section );
			if ( empty( $section ) ) {
				continue;
			}
			
			$idea = [];
			
			// Extract title
			if ( preg_match( '/\*\*Title:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['title'] = trim( $matches[1] );
			}
			
			// Extract description  
			if ( preg_match( '/\*\*Description:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['description'] = trim( $matches[1] );
			}
			
			// Extract category
			if ( preg_match( '/\*\*Category:\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['category'] = trim( $matches[1] );
			}
			
			// Extract primary keyword
			if ( preg_match( '/\*\*(Primary\s+[Kk]eyword|[Kk]eyword):\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$idea['primary_keyword'] = trim( $matches[2] );
			}
			
			// Extract persona
			if ( preg_match( '/\*\*(Recommended\s+[Pp]ersona|[Pp]ersona):\*\*\s*(.+?)(?=\s*\*\*[A-Za-z\s]+:\*\*|$)/s', $section, $matches ) ) {
				$persona_info = trim( $matches[2] );
				if ( preg_match( '/(\d+)/', $persona_info, $persona_matches ) ) {
					$idea['persona_id'] = (int) $persona_matches[1];
				}
			}
			
			// Only add if we have at least a title
			if ( ! empty( $idea['title'] ) ) {
				$ideas[] = $this->format_idea( $idea );
			}
		}
		
		return $ideas;
	}

	/**
	 * Extract idea information from a text block.
	 */
	private function extract_idea_from_text( $text ) {
		$idea = [];
		$lines = explode( "\n", $text );
		
		// First non-empty line is likely the title
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) && empty( $idea['title'] ) ) {
				// Remove numbering if present
				$title = preg_replace( '/^\d+\.\s*/', '', $line );
				$idea['title'] = $title;
				break;
			}
		}
		
		// Extract other information
		foreach ( $lines as $line ) {
			$this->extract_line_info( $line, $idea );
		}
		
		// If no description found, try to use remaining text as description
		if ( empty( $idea['description'] ) && count( $lines ) > 1 ) {
			$desc_lines = array_slice( $lines, 1 );
			$description = '';
			foreach ( $desc_lines as $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) && ! $this->is_field_line( $line ) ) {
					$description .= ' ' . $line;
				}
			}
			if ( ! empty( trim( $description ) ) ) {
				$idea['description'] = trim( $description );
			}
		}
		
		return $idea;
	}

	/**
	 * Extract information from a line of text.
	 */
	private function extract_line_info( $line, &$idea ) {
		$line = trim( $line );
		
		if ( preg_match( '/^(description|desc):\s*(.+)$/i', $line, $matches ) ) {
			$idea['description'] = trim( $matches[2] );
		} elseif ( preg_match( '/^category:\s*(.+)$/i', $line, $matches ) ) {
			$idea['category'] = trim( $matches[1] );
		} elseif ( preg_match( '/^(keyword|primary keyword):\s*(.+)$/i', $line, $matches ) ) {
			$idea['primary_keyword'] = trim( $matches[2] );
		} elseif ( preg_match( '/^(persona|recommended persona):\s*(.+)$/i', $line, $matches ) ) {
			$persona_info = trim( $matches[2] );
			if ( preg_match( '/(\d+)/', $persona_info, $persona_matches ) ) {
				$idea['persona_id'] = (int) $persona_matches[1];
			}
		}
	}

	/**
	 * Extract inline information from title text.
	 */
	private function extract_inline_info( $text, &$idea ) {
		// Check for patterns like "Title - Description" or "Title (keyword: something)"
		if ( preg_match( '/^(.+?)\s*[-–—]\s*(.+)$/', $text, $matches ) ) {
			$idea['title'] = trim( $matches[1] );
			$idea['description'] = trim( $matches[2] );
		}
	}

	/**
	 * Check if a line contains field information.
	 */
	private function is_field_line( $line ) {
		return preg_match( '/^(title|description|category|keyword|persona|primary keyword|recommended persona):/i', $line );
	}

	/**
	 * Parse content response from API.
	 *
	 * @param string $content API response content.
	 * @return array Parsed content.
	 */
	private function parse_content_response( $content ) {
		$result = [
			'title' => '',
			'meta_description' => '',
			'focus_keyphrase' => '',
			'tags' => [],
			'html' => '',
			'images' => [],
			'featured_image' => null,
			'charts' => '',
			'references' => '',
		];

		// Extract title
		if ( preg_match( '/TITLE:\s*(.+?)(?=\n|META_DESCRIPTION:|$)/s', $content, $matches ) ) {
			$result['title'] = trim( $matches[1] );
		}

		// Extract meta description
		if ( preg_match( '/META_DESCRIPTION:\s*(.+?)(?=\n|FOCUS_KEYPHRASE:|TAGS:|HTML:|$)/s', $content, $matches ) ) {
			$result['meta_description'] = trim( $matches[1] );
		}

		// Extract focus keyphrase
		if ( preg_match( '/FOCUS_KEYPHRASE:\s*(.+?)(?=\n|TAGS:|HTML:|$)/s', $content, $matches ) ) {
			$result['focus_keyphrase'] = trim( $matches[1] );
		}

		// Extract tags
		if ( preg_match( '/TAGS:\s*(.+?)(?=\n|HTML:|IMAGES:|$)/s', $content, $matches ) ) {
			$tags = explode( ',', $matches[1] );
			$result['tags'] = array_map( 'trim', $tags );
		}

		// Extract HTML content
		if ( preg_match( '/HTML:\s*(.+?)(?=\nIMAGES:|CHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			$result['html'] = trim( $matches[1] );
		} elseif ( preg_match( '/AVADA_CONTENT:\s*(.+?)(?=\nIMAGES:|CHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			// Check for AVADA_CONTENT section for Avada layouts
			$result['html'] = trim( $matches[1] );
		} elseif ( preg_match( '/CONTENT:\s*(.+?)(?=\nIMAGES:|CHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			// Backward compatibility: check for CONTENT section
			$result['html'] = trim( $matches[1] );
		}

		// Extract image descriptions
		if ( preg_match( '/IMAGES:\s*(.+?)(?=\nCHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			$images_section = trim( $matches[1] );
			
			// Parse featured image description separately
			if ( preg_match( '/{{featured}}:\s*(.+?)(?={{image\d+}}:|{{featured}}:|$)/s', $images_section, $featured_match ) ) {
				$description = trim( $featured_match[1] );
				$result['featured_image'] = [
					'token' => '{{featured}}',
					'prompt' => $description,
					'alt_text' => $this->generate_alt_text_from_prompt( $description ),
				];
			}
			
			// Parse content image descriptions ({{image1}}, {{image2}}, etc.)
			preg_match_all( '/{{image(\d+)}}:\s*(.+?)(?={{image\d+}}:|{{featured}}:|$)/s', $images_section, $image_matches, PREG_SET_ORDER );
			
			foreach ( $image_matches as $match ) {
				$image_num = $match[1];
				$description = trim( $match[2] );
				
				$result['images'][] = [
					'token' => '{{image' . $image_num . '}}',
					'prompt' => $description,
					'alt_text' => $this->generate_alt_text_from_prompt( $description ),
				];
			}
		}

		// Extract charts scripts
		if ( preg_match( '/CHARTS:\s*(.+?)(?=\nREFERENCES:|$)/s', $content, $matches ) ) {
			$charts_content = trim( $matches[1] );
			// Wrap charts in a script tag if not already wrapped
			if ( ! empty( $charts_content ) && stripos( $charts_content, '<script' ) === false ) {
				$result['charts'] = '<script>' . "\n" . $charts_content . "\n" . '</script>';
		} else {
				$result['charts'] = $charts_content;
			}
		}

		// Extract references
		if ( preg_match( '/REFERENCES:\s*(.+)$/s', $content, $matches ) ) {
			$references_content = trim( $matches[1] );
			// Format references as an Avada-styled section if not empty
			if ( ! empty( $references_content ) && $references_content !== '[No references]' ) {
				$result['references'] = '[fusion_builder_container padding_top="40px" padding_bottom="40px" hundred_percent="no" equal_height_columns="no" hide_on_mobile="no"]' . "\n";
				$result['references'] .= '[fusion_builder_row]' . "\n";
				$result['references'] .= '[fusion_builder_column type="1_1" spacing="yes" center_content="no" hover_type="none" link="" min_height="" hide_on_mobile="no" class="" id="" background_color="" background_image="" background_position="left top" undefined="" background_repeat="no-repeat" border_size="0" border_color="" border_style="solid" border_position="all" padding_top="" padding_right="" padding_bottom="" padding_left="" margin_top="" margin_bottom="" animation_type="" animation_direction="left" animation_speed="0.1" animation_offset="" last="no"]' . "\n";
				$result['references'] .= '[fusion_separator style_type="single solid" hide_on_mobile="small-visibility,medium-visibility,large-visibility" sep_color="#e0e0e0" top_margin="20" bottom_margin="40" /]' . "\n";
				$result['references'] .= '[fusion_title size="2" content_align="left" style_type="default" sep_color="" margin_top="" margin_bottom="20" class="" id=""]References[/fusion_title]' . "\n";
				$result['references'] .= '[fusion_text]' . "\n";
				
				// Convert each reference line to a paragraph
				$reference_lines = explode( "\n", $references_content );
				foreach ( $reference_lines as $line ) {
					$line = trim( $line );
					if ( ! empty( $line ) ) {
						$result['references'] .= '<p>' . esc_html( $line ) . '</p>' . "\n";
					}
				}
				
				$result['references'] .= '[/fusion_text]' . "\n";
				$result['references'] .= '[/fusion_builder_column]' . "\n";
				$result['references'] .= '[/fusion_builder_row]' . "\n";
				$result['references'] .= '[/fusion_builder_container]';
			}
		}

		// If no images found in IMAGES section, check for tokens in HTML
		if ( empty( $result['images'] ) && ! empty( $result['html'] ) ) {
			preg_match_all( '/{{image(\d+)}}/', $result['html'], $token_matches );
			
			if ( ! empty( $token_matches[1] ) ) {
				foreach ( array_unique( $token_matches[1] ) as $image_num ) {
					$result['images'][] = [
						'token' => '{{image' . $image_num . '}}',
						'prompt' => 'Professional image related to ' . ( $result['title'] ?: 'blog content' ),
						'alt_text' => 'Image ' . $image_num . ' for ' . ( $result['title'] ?: 'blog post' ),
					];
		}
			}
		}

		Logger::info( 'content_parsed', 'Parsed content from API response', [
			'has_title' => ! empty( $result['title'] ),
			'has_meta' => ! empty( $result['meta_description'] ),
			'has_focus_keyphrase' => ! empty( $result['focus_keyphrase'] ),
			'tags_count' => count( $result['tags'] ),
			'html_length' => strlen( $result['html'] ),
			'images_count' => count( $result['images'] ),
			'has_featured_image' => ! empty( $result['featured_image'] ),
			'has_charts' => ! empty( $result['charts'] ),
			'has_references' => ! empty( $result['references'] ),
		] );

		return $result;
	}

	/**
	 * Generate alt text from image prompt.
	 *
	 * @param string $prompt Image generation prompt.
	 * @return string Generated alt text.
	 */
	private function generate_alt_text_from_prompt( $prompt ) {
		// Remove common prompt phrases and clean up
		$alt_text = preg_replace( '/^(create|generate|show|display|illustrate)\s+/i', '', $prompt );
		$alt_text = preg_replace( '/\s+/', ' ', $alt_text );
		$alt_text = trim( $alt_text );
		
		// Limit length for alt text
		if ( strlen( $alt_text ) > 125 ) {
			$alt_text = substr( $alt_text, 0, 122 ) . '...';
		}
		
		return $alt_text;
	}

	/**
	 * Format idea data.
	 *
	 * @param array $idea_data Raw idea data.
	 * @return array Formatted idea.
	 */
	private function format_idea( $idea_data ) {
		$formatted = [
			'title' => sanitize_text_field( $idea_data['title'] ?? '' ),
			'description' => sanitize_textarea_field( $idea_data['description'] ?? '' ),
			'category' => sanitize_text_field( $idea_data['category'] ?? 'General' ),
			'primary_keyword' => sanitize_text_field( $idea_data['primary_keyword'] ?? '' ),
			'status' => 'pending',
		];
		
		// Include persona_id if it was extracted
		if ( isset( $idea_data['persona_id'] ) && is_numeric( $idea_data['persona_id'] ) ) {
			$formatted['persona_id'] = (int) $idea_data['persona_id'];
		}
		
		return $formatted;
	}

	/**
	 * Make API request to Anthropic.
	 *
	 * @param array $data Request data.
	 * @return array Response data.
	 * @throws \Exception On API errors.
	 */
	private function make_request( $data ) {
		$headers = [
			'Content-Type' => 'application/json',
			'x-api-key' => $this->api_key,
			'anthropic-version' => '2023-06-01',
		];

		$args = [
			'method' => 'POST',
			'headers' => $headers,
			'body' => wp_json_encode( $data ),
			'timeout' => 600, // 10 minutes for complex blog generation requests
		];
		
		// Check for global cancellation before making request
		if ( get_transient( 'ai_blog_global_cancel_flag' ) ) {
			$this->log_warning( 'anthropic_request_cancelled', 'Request cancelled due to global cancellation flag' );
			
			// Log to debug file
			$debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: Request cancelled due to global cancellation\n", FILE_APPEND );
			
			throw new \Exception( 'Generation cancelled by user' );
		}

		// For local development environments, disable SSL verification
		// This is needed to avoid SSL certificate errors on local Windows systems
		// Check if we're in a local environment (multiple indicators)
		$is_local = false;
		
		// Check for web-based local environment indicators
		if ( isset( $_SERVER['HTTP_HOST'] ) && 
			( $_SERVER['HTTP_HOST'] === 'localhost' || 
			  strpos( $_SERVER['HTTP_HOST'], '.local' ) !== false ||
			  strpos( $_SERVER['HTTP_HOST'], '127.0.0.1' ) !== false ) ) {
			$is_local = true;
		}
		
		// Check if running from a local Windows path (D:, C:, etc)
		if ( ! $is_local && defined( 'ABSPATH' ) && preg_match( '/^[A-Z]:\\\\/i', ABSPATH ) ) {
			$is_local = true;
		}
		
		// Check for local server names (CLI or web)
		if ( ! $is_local && isset( $_SERVER['SERVER_NAME'] ) && 
			( $_SERVER['SERVER_NAME'] === 'localhost' || 
			  strpos( $_SERVER['SERVER_NAME'], '.local' ) !== false ||
			  strpos( $_SERVER['SERVER_NAME'], '127.0.0.1' ) !== false ) ) {
			$is_local = true;
		}
		
		// Check for AI_BLOG_GENERATOR_DEBUG constant
		if ( ! $is_local && defined( 'AI_BLOG_GENERATOR_DEBUG' ) && AI_BLOG_GENERATOR_DEBUG ) {
			$is_local = true;
		}
		
		// FORCE SSL bypass for all requests when running from command line or local environment
		// This specifically fixes the "SSL certificate problem: unable to get local issuer certificate" error
		if ( $is_local || php_sapi_name() === 'cli' || ! isset( $_SERVER['HTTP_HOST'] ) ) {
			$args['sslverify'] = false;
			$is_local = true; // Update flag for logging
			
			// Log that SSL verification is disabled for debugging
			$this->log_info( 'ssl_verification_disabled', 'SSL verification disabled for local development', [
				'host' => $_SERVER['HTTP_HOST'] ?? 'none',
				'server_name' => $_SERVER['SERVER_NAME'] ?? 'none',
				'abspath' => defined( 'ABSPATH' ) ? ABSPATH : 'unknown',
				'php_sapi' => php_sapi_name(),
				'is_local' => $is_local,
				'force_disabled' => true
			] );
			
			// Also log to debug file for correlation
			$debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ANTHROPIC_SERVICE: SSL verification DISABLED for local environment\n", FILE_APPEND );
		}

		// Log the complete request being sent
		$this->log_info( 'anthropic_request_details', 'Sending request to Anthropic API', [
			'url' => $this->api_url,
			'headers' => array_merge( $headers, [ 'x-api-key' => '[REDACTED]' ] ), // Don't log the actual API key
			'request_body' => wp_json_encode( $data ),
			'request_data' => $data
		] );

		$response = wp_remote_request( $this->api_url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'anthropic_request_error', 'HTTP request failed', [
				'error_message' => $response->get_error_message(),
				'error_data' => $response->get_error_data()
			] );
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response );

		// Log the complete response received
		$this->log_info( 'anthropic_response_details', 'Received response from Anthropic API', [
			'response_code' => $response_code,
			'response_headers' => $response_headers,
			'response_body' => $response_body,
			'response_body_length' => strlen( $response_body )
		] );

		if ( $response_code !== 200 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? 'Unknown API error';
			
			$this->log_error( 'anthropic_api_error', 'API returned error response', [
				'response_code' => $response_code,
				'error_data' => $error_data,
				'error_message' => $error_message,
				'full_response_body' => $response_body
			] );
			
			throw new \Exception( "API error (HTTP {$response_code}): {$error_message}" );
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_error( 'anthropic_json_decode_error', 'Failed to decode JSON response', [
				'json_error' => json_last_error_msg(),
				'response_body' => $response_body
			] );
			throw new \Exception( 'Invalid JSON response from API' );
		}

		// Log the decoded response structure
		$this->log_info( 'anthropic_response_decoded', 'Successfully decoded API response', [
			'decoded_response' => $decoded_response,
			'response_keys' => array_keys( $decoded_response ),
			'content_structure' => isset( $decoded_response['content'] ) ? $decoded_response['content'] : 'no_content'
		] );

		return $decoded_response;
	}

	/**
	 * Calculate cost based on input and output tokens used.
	 *
	 * @param int $input_tokens Number of input tokens used.
	 * @param int $output_tokens Number of output tokens used.
	 * @return float Cost in USD.
	 */
	private function calculate_cost( $input_tokens, $output_tokens ) {
		// Determine model type for pricing
		$model_type = 'sonnet'; // Default fallback
		
		if ( strpos( $this->model, 'sonnet-4' ) !== false ) {
			$model_type = 'sonnet-4';
		} elseif ( strpos( $this->model, 'opus-4' ) !== false ) {
			$model_type = 'opus-4';
		}

		// Calculate cost directly based on model type and pricing
		$pricing = self::MODEL_PRICING[$this->model] ?? self::MODEL_PRICING['claude-sonnet-4-20250514'];
		$input_cost = ( $input_tokens / 1000000 ) * $pricing['input_price'];
		$output_cost = ( $output_tokens / 1000000 ) * $pricing['output_price'];
		$cost = $input_cost + $output_cost;

		$this->log_debug( 'anthropic_cost_calculated', 'Calculated API cost', [
			'model' => $this->model,
			'model_type' => $model_type,
			'input_tokens' => $input_tokens,
			'output_tokens' => $output_tokens,
			'total_tokens' => $input_tokens + $output_tokens,
			'cost_usd' => $cost
		] );

		return $cost;
	}

	/**
	 * Get the model pricing for cost calculation.
	 *
	 * @return array Model pricing data.
	 */
	private function get_model_pricing() {
		return self::MODEL_PRICING[ $this->model ] ?? self::MODEL_PRICING['claude-sonnet-4-20250514'];
	}

	/**
	 * Get maximum tokens for the current model.
	 *
	 * @return int Maximum tokens allowed for the model.
	 */
	private function get_max_tokens() {
		// Check if it's an Opus model
		if ( strpos( $this->model, 'opus' ) !== false ) {
			return 32000; // 32k tokens for Opus models
		}
		
		// Check if it's a Sonnet model
		if ( strpos( $this->model, 'sonnet' ) !== false ) {
			return 64000; // 64k tokens for Sonnet models
		}
		
		// Default fallback
		return 32000;
	}

	/**
	 * Generate text using generic prompt.
	 *
	 * @param string $prompt The text prompt.
	 * @param array  $options Optional parameters.
	 * @return array Response with success/failure and content.
	 */
	public function generate_text( $prompt, $options = [] ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [
			'prompt_length' => strlen( $prompt ),
			'options' => $options
		] );

		try {
			if ( empty( $this->api_key ) ) {
				$result = [
					'success' => false,
					'message' => __( 'API key is required.', 'ai-blog-generator' ),
				];
				$this->log_function_exit( $result );
				return $result;
			}

			$this->log_info( 'anthropic_text_generation_start', 'Starting text generation request', [
				'model' => $this->model,
				'prompt_length' => strlen( $prompt ),
				'max_tokens' => $this->get_max_tokens()
			] );

			$request_data = [
				'model' => $this->model,
				'max_tokens' => $options['max_tokens'] ?? $this->get_max_tokens(),
				'messages' => [
					[
						'role' => 'user',
						'content' => $prompt,
					],
				],
			];

			// Add optional parameters
			if ( isset( $options['temperature'] ) ) {
				$request_data['temperature'] = (float) $options['temperature'];
			}

			if ( isset( $options['top_p'] ) ) {
				$request_data['top_p'] = (float) $options['top_p'];
			}

			$this->log_api_request( 'anthropic', 'messages', [
				'model' => $this->model,
				'max_tokens' => $request_data['max_tokens'],
				'prompt_length' => strlen( $prompt )
			], [] );

			$response = $this->make_request( $request_data );

			if ( ! isset( $response['content'] ) || ! is_array( $response['content'] ) ) {
				$this->log_error( 'anthropic_invalid_response_format', 'Invalid response format from Anthropic API', [
					'response_keys' => is_array( $response ) ? array_keys( $response ) : 'not_array',
					'content_type' => isset( $response['content'] ) ? gettype( $response['content'] ) : 'missing'
				] );
				
				$result = [
					'success' => false,
					'message' => 'Invalid response format from API',
				];
				$this->log_function_exit( $result );
				return $result;
			}

			$content = $response['content'][0]['text'] ?? '';
			
			// Record cost
			if ( isset( $response['usage'] ) ) {
				$tokens_used = $response['usage']['input_tokens'] + $response['usage']['output_tokens'];
				$cost = $this->calculate_cost( $response['usage']['input_tokens'], $response['usage']['output_tokens'] );
				$this->cost_model->record_anthropic_cost( 'generate_text', $cost, $tokens_used );

				$this->log_info( 'anthropic_text_cost_recorded', 'API cost recorded for text generation', [
					'input_tokens' => $response['usage']['input_tokens'],
					'output_tokens' => $response['usage']['output_tokens'],
					'total_tokens' => $tokens_used,
					'cost_usd' => $cost
				] );
			}

			$result = [
				'success' => true,
				'content' => $content,
				'cost' => $cost ?? 0,
				'tokens_used' => $tokens_used ?? 0,
				'usage' => $response['usage'] ?? []
			];

			$this->log_info( 'anthropic_text_generation_success', 'Text generation completed successfully', [
				'content_length' => strlen( $content ),
				'total_cost' => $result['cost'],
				'tokens_consumed' => $result['tokens_used']
			] );

		} catch ( \Exception $e ) {
			$this->log_exception( 'anthropic_text_generation_failed', $e, [
				'prompt_length' => strlen( $prompt )
			] );

			$result = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		$this->end_timer( $start_time, 'anthropic_text_generation', [
			'success' => $result['success']
		] );
		$this->log_function_exit( $result['success'] ? 'success' : 'failed' );

		return $result;
	}
}
