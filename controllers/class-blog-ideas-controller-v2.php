<?php
/**
 * Blog Ideas Controller V2
 *
 * Handles all business logic and AJAX operations for blog ideas.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Services\Anthropic_Service;
use AI_Blog_Generator\Services\OpenAI_Service;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Ajax_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blog Ideas Controller V2 Class
 */
class Blog_Ideas_Controller_V2 {

	use Ajax_Handler;

	/**
	 * Blog Ideas model instance.
	 *
	 * @var Blog_Ideas_Model_V2
	 */
	private $model;

	/**
	 * Context model instance.
	 *
	 * @var Context_Model
	 */
	private $context_model;

	/**
	 * Persona model instance.
	 *
	 * @var Persona_Model
	 */
	private $persona_model;

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
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new Blog_Ideas_Model_V2();
		$this->context_model = new Context_Model();
		$this->persona_model = new Persona_Model();
		$this->anthropic_service = new Anthropic_Service();
		$this->openai_service = new OpenAI_Service();

		Logger::info( 'blog_ideas_controller_v2_init', 'Blog Ideas Controller V2 initialized' );
	}

	/**
	 * Register AJAX handlers for this controller.
	 */
	public function register_ajax_handlers() {
		// Log that registration is starting
		Logger::debug( 'ajax_handlers_registration', 'Starting Idea Generator AJAX handlers registration', [], __CLASS__, __METHOD__ );

		// Data retrieval
		add_action( 'wp_ajax_ai_blog_v2_get_pending_ideas', [ $this, 'ajax_get_pending_ideas' ] );
		add_action( 'wp_ajax_ai_blog_v2_get_statistics', [ $this, 'ajax_get_statistics' ] );
		add_action( 'wp_ajax_ai_blog_v2_get_idea_details', [ $this, 'ajax_get_idea_details' ] );

		// Idea generation
		add_action( 'wp_ajax_ai_blog_v2_generate_ideas', [ $this, 'ajax_generate_ideas' ] );

		// Idea saving
		add_action( 'wp_ajax_ai_blog_v2_save_generated_ideas', [ $this, 'ajax_save_generated_ideas' ] );

		// Idea management
		add_action( 'wp_ajax_ai_blog_v2_approve_idea', [ $this, 'ajax_approve_idea' ] );
		add_action( 'wp_ajax_ai_blog_v2_deny_idea', [ $this, 'ajax_deny_idea' ] );
		add_action( 'wp_ajax_ai_blog_v2_bulk_approve_ideas', [ $this, 'ajax_bulk_approve_ideas' ] );
		add_action( 'wp_ajax_ai_blog_v2_bulk_deny_ideas', [ $this, 'ajax_bulk_deny_ideas' ] );

		// Log that registration completed
		Logger::debug( 'ajax_handlers_registration_completed', 'Idea Generator AJAX handlers registered successfully', [], __CLASS__, __METHOD__ );

		Logger::info( 'blog_ideas_v2_ajax_registered', 'All Idea Generator AJAX handlers registered' );
	}

	/**
	 * AJAX handler to get pending ideas.
	 */
	public function ajax_get_pending_ideas() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			Logger::info( 'get_pending_ideas_request', 'Processing request for pending ideas' );

			$filters = [];
			
			// Optional filters
			if ( ! empty( $_POST['date_from'] ) ) {
				$filters['date_from'] = sanitize_text_field( $_POST['date_from'] );
			}
			
			if ( ! empty( $_POST['date_to'] ) ) {
				$filters['date_to'] = sanitize_text_field( $_POST['date_to'] );
			}
			
			if ( ! empty( $_POST['persona_id'] ) ) {
				$filters['persona_id'] = absint( $_POST['persona_id'] );
			}

			$ideas = $this->model->get_pending_ideas( $filters );

			$this->send_ajax_success( [
				'ideas' => $ideas,
				'count' => count( $ideas )
			], 'Pending ideas retrieved successfully', 'get_pending_ideas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_pending_ideas' );
		}

		$this->end_timer( $start_time, 'get_pending_ideas' );
	}

	/**
	 * AJAX handler to get statistics.
	 */
	public function ajax_get_statistics() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			Logger::info( 'get_statistics_request', 'Processing request for idea statistics' );

			$statistics = $this->model->get_statistics();

			$this->send_ajax_success( 
				$statistics, 
				'Statistics retrieved successfully', 
				'get_statistics' 
			);

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_statistics' );
		}

		$this->end_timer( $start_time, 'get_statistics' );
	}

	/**
	 * AJAX handler to get idea details.
	 */
	public function ajax_get_idea_details() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$params = $this->validate_ajax_params( [ 'idea_id' ] );
			if ( false === $params ) {
				return;
			}

			$idea_id = absint( $params['idea_id'] );

			Logger::info( 'get_idea_details_request', 'Processing request for idea details', [
				'idea_id' => $idea_id
			] );

			$idea = $this->model->get_idea( $idea_id );

			if ( ! $idea ) {
				$this->send_ajax_error( 
					'Idea not found', 
					[ 'idea_id' => $idea_id ], 
					'get_idea_details',
					'not_found'
				);
				return;
			}

			$this->send_ajax_success( 
				$idea, 
				'Idea details retrieved successfully', 
				'get_idea_details' 
			);

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_idea_details' );
		}

		$this->end_timer( $start_time, 'get_idea_details' );
	}

	/**
	 * AJAX handler to generate new ideas.
	 */
	public function ajax_generate_ideas() {
		$start_time = $this->start_timer();

		try {
            Logger::debug( 'generate_ideas_request', 'Processing request for idea generation', [], __CLASS__, __METHOD__ );
            
			if ( ! $this->verify_ajax_security() ) {
				return;
			}
			Logger::debug( 'generate_ideas_request_verified', 'AJAX security verified', [], __CLASS__, __METHOD__ );

			$params = $this->validate_ajax_params( [ 'count' ] );
			if ( false === $params ) {
				return;
			}
            Logger::debug( 'generate_ideas_request_validated', 'AJAX parameters validated', [], __CLASS__, __METHOD__ );
            
			$count = absint( $params['count'] );
			$context_prompt = ! empty( $_POST['context_prompt'] ) ? sanitize_textarea_field( $_POST['context_prompt'] ) : '';
			Logger::debug( 'generate_ideas_request_processed', 'AJAX parameters processed', [], __CLASS__, __METHOD__ );
            
			// Validate count
			if ( $count < 1 || $count > 30 ) {
				$this->send_ajax_error( 
					'Invalid count. Must be between 1 and 30.', 
					[ 'provided_count' => $count ], 
					'generate_ideas',
					'invalid_count'
				);
				return;
			}

			Logger::info( 'generate_ideas_request', 'Starting idea generation process', [
				'count' => $count,
				'has_context_prompt' => ! empty( $context_prompt ),
				'context_prompt_length' => strlen( $context_prompt )
			] );

			// Build the AI prompt
			$prompt = $this->build_generation_prompt( $count, $context_prompt );

			// Choose AI service based on settings
			$ai_service = get_option( 'ai_blog_generator_preferred_service', 'anthropic' );
			
			Logger::info( 'ai_service_selected', 'Selected AI service for generation', [
				'service' => $ai_service
			] );

			// Generate ideas using selected service
			if ( 'anthropic' === $ai_service ) {
				$response = $this->anthropic_service->generate_text( $prompt );
			} else {
				$response = $this->openai_service->generate_text( $prompt );
			}

			if ( ! $response['success'] ) {
				Logger::error( 'ai_generation_failed', 'AI service failed to generate ideas', [
					'service' => $ai_service,
					'error' => $response['message'] ?? 'unknown_error'
				] );
				
				$this->send_ajax_error( 
					'Failed to generate ideas: ' . ( $response['message'] ?? 'Unknown error' ),
					[ 'service' => $ai_service ],
					'generate_ideas',
					'ai_service_failed'
				);
				return;
			}

			// Parse the AI response
			$ideas = $this->parse_generated_ideas( $response['content'] );

			if ( empty( $ideas ) ) {
				Logger::error( 'ideas_parsing_failed', 'Failed to parse any ideas from AI response', [
					'response_length' => strlen( $response['content'] ),
					'raw_response' => $response['content']
				] );
				
				$this->send_ajax_error( 
					'Failed to parse generated ideas from AI response',
					[ 'response_preview' => substr( $response['content'], 0, 200 ) ],
					'generate_ideas',
					'parsing_failed'
				);
				return;
			}

			Logger::info( 'ideas_generated_successfully', 'Ideas generated and parsed successfully', [
				'ideas_parsed' => count( $ideas ),
				'ideas_requested' => $count
			] );

			$this->send_ajax_success( [
				'ideas' => $ideas,
				'count' => count( $ideas ),
				'service_used' => $ai_service,
				'cost' => $response['cost'] ?? 0,
				'tokens_used' => $response['tokens_used'] ?? 0
			], 'Ideas generated successfully', 'generate_ideas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'generate_ideas' );
		}

		$this->end_timer( $start_time, 'generate_ideas' );
	}

	/**
	 * AJAX handler to save generated ideas to database.
	 */
	public function ajax_save_generated_ideas() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$params = $this->validate_ajax_params( [ 'ideas' ] );
			if ( false === $params ) {
				return;
			}

			$ideas = $params['ideas'];

			if ( ! is_array( $ideas ) || empty( $ideas ) ) {
				$this->send_ajax_error( 
					'Invalid ideas data provided',
					[ 'ideas_type' => gettype( $ideas ) ],
					'save_generated_ideas',
					'invalid_data'
				);
				return;
			}

			Logger::info( 'save_generated_ideas_request', 'Processing save generated ideas request', [
				'ideas_count' => count( $ideas )
			] );

			// Log the first idea for debugging
			if ( ! empty( $ideas[0] ) ) {
				Logger::debug( 'save_generated_ideas_sample', 'Sample idea data being saved', [
					'sample_idea' => $ideas[0],
					'idea_keys' => array_keys( $ideas[0] )
				], __CLASS__, __METHOD__ );
			}

			// Save the ideas
			$result = $this->save_generated_ideas( $ideas );

			if ( ! $result['success'] ) {
				$this->send_ajax_error( 
					'Failed to save ideas to database',
					[ 'result' => $result ],
					'save_generated_ideas',
					'database_error'
				);
				return;
			}

			// Get updated statistics
			$statistics = $this->model->get_statistics();

			Logger::info( 'save_generated_ideas_success', 'Ideas saved successfully', [
				'created_count' => $result['created_count'],
				'total_count' => $result['total_count'],
				'updated_statistics' => $statistics
			] );

			$this->send_ajax_success( [
				'result' => $result,
				'statistics' => $statistics
			], sprintf( 
				'%d of %d ideas saved successfully', 
				$result['created_count'], 
				$result['total_count'] 
			), 'save_generated_ideas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'save_generated_ideas' );
		}

		$this->end_timer( $start_time, 'save_generated_ideas' );
	}

	/**
	 * AJAX handler to approve a single idea.
	 */
	public function ajax_approve_idea() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$params = $this->validate_ajax_params( [ 'idea_id' ] );
			if ( false === $params ) {
				return;
			}

			$idea_id = absint( $params['idea_id'] );

			Logger::info( 'approve_idea_request', 'Processing idea approval', [
				'idea_id' => $idea_id
			] );

			$result = $this->model->update_status( $idea_id, 'approved' );

			if ( ! $result ) {
				$this->send_ajax_error( 
					'Failed to approve idea',
					[ 'idea_id' => $idea_id ],
					'approve_idea',
					'database_error'
				);
				return;
			}

			// Get updated statistics
			$statistics = $this->model->get_statistics();

			$this->send_ajax_success( [
				'idea_id' => $idea_id,
				'statistics' => $statistics
			], 'Idea approved successfully', 'approve_idea' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'approve_idea' );
		}

		$this->end_timer( $start_time, 'approve_idea' );
	}

	/**
	 * AJAX handler to deny a single idea.
	 */
	public function ajax_deny_idea() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$params = $this->validate_ajax_params( [ 'idea_id' ] );
			if ( false === $params ) {
				return;
			}

			$idea_id = absint( $params['idea_id'] );

			Logger::info( 'deny_idea_request', 'Processing idea denial', [
				'idea_id' => $idea_id
			] );

			$result = $this->model->update_status( $idea_id, 'denied' );

			if ( ! $result ) {
				$this->send_ajax_error( 
					'Failed to deny idea',
					[ 'idea_id' => $idea_id ],
					'deny_idea',
					'database_error'
				);
				return;
			}

			// Get updated statistics
			$statistics = $this->model->get_statistics();

			$this->send_ajax_success( [
				'idea_id' => $idea_id,
				'statistics' => $statistics
			], 'Idea denied successfully', 'deny_idea' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'deny_idea' );
		}

		$this->end_timer( $start_time, 'deny_idea' );
	}

	/**
	 * AJAX handler to bulk approve ideas.
	 */
	public function ajax_bulk_approve_ideas() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$params = $this->validate_ajax_params( [ 'idea_ids' ] );
			if ( false === $params ) {
				return;
			}

			$idea_ids = array_map( 'absint', $params['idea_ids'] );

			Logger::info( 'bulk_approve_request', 'Processing bulk idea approval', [
				'idea_ids' => $idea_ids,
				'count' => count( $idea_ids )
			] );

			$result = $this->model->bulk_update_status( $idea_ids, 'approved' );

			// Get updated statistics
			$statistics = $this->model->get_statistics();

			$this->send_ajax_success( [
				'result' => $result,
				'statistics' => $statistics
			], sprintf( 
				'%d of %d ideas approved successfully', 
				$result['updated_count'], 
				$result['total_count'] 
			), 'bulk_approve_ideas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'bulk_approve_ideas' );
		}

		$this->end_timer( $start_time, 'bulk_approve_ideas' );
	}

	/**
	 * AJAX handler to bulk deny ideas.
	 */
	public function ajax_bulk_deny_ideas() {
		$start_time = $this->start_timer();

		try {
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$params = $this->validate_ajax_params( [ 'idea_ids' ] );
			if ( false === $params ) {
				return;
			}

			$idea_ids = array_map( 'absint', $params['idea_ids'] );

			Logger::info( 'bulk_deny_request', 'Processing bulk idea denial', [
				'idea_ids' => $idea_ids,
				'count' => count( $idea_ids )
			] );

			$result = $this->model->bulk_update_status( $idea_ids, 'denied' );

			// Get updated statistics
			$statistics = $this->model->get_statistics();

			$this->send_ajax_success( [
				'result' => $result,
				'statistics' => $statistics
			], sprintf( 
				'%d of %d ideas denied successfully', 
				$result['updated_count'], 
				$result['total_count'] 
			), 'bulk_deny_ideas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'bulk_deny_ideas' );
		}

		$this->end_timer( $start_time, 'bulk_deny_ideas' );
	}

	/**
	 * Save generated ideas to database.
	 *
	 * @param array $ideas Array of parsed ideas.
	 * @return array Result with success/failure info.
	 */
	public function save_generated_ideas( $ideas ) {
		Logger::info( 'save_generated_ideas', 'Saving generated ideas to database', [
			'ideas_count' => count( $ideas )
		] );

		return $this->model->create_ideas( $ideas );
	}

	/**
	 * Build the AI prompt for idea generation.
	 *
	 * @param int    $count          Number of ideas to generate.
	 * @param string $context_prompt Optional user context.
	 * @return string The complete prompt.
	 */
	private function build_generation_prompt( $count, $context_prompt = '' ) {
		Logger::info( 'build_generation_prompt', 'Building AI prompt for idea generation', [
			'count' => $count,
			'has_context_prompt' => ! empty( $context_prompt )
		] );

		// Get all available data
		$categories = $this->model->get_wordpress_categories();
		$existing_content = $this->model->get_existing_content();
		$contexts = $this->context_model->get_compiled_for_usage( 'ideas' );
		$personas = $this->persona_model->get_all();

		Logger::debug( 'prompt_data_retrieved', 'Retrieved data for prompt building', [
			'categories_count' => count( $categories ),
			'categories_type' => ! empty( $categories ) ? gettype( $categories[0] ) : 'empty',
			'existing_ideas_count' => count( $existing_content['ideas'] ?? [] ),
			'existing_ideas_type' => ! empty( $existing_content['ideas'] ) ? gettype( $existing_content['ideas'][0] ) : 'empty',
			'existing_posts_count' => count( $existing_content['posts'] ?? [] ),
			'existing_posts_type' => ! empty( $existing_content['posts'] ) ? gettype( $existing_content['posts'][0] ) : 'empty',
			'context_types_available' => array_keys( $contexts ),
			'personas_count' => count( $personas ),
			'personas_type' => ! empty( $personas ) ? gettype( $personas[0] ) : 'empty'
		], __CLASS__, __METHOD__ );

		// Build categories list
		$categories_list = '';
		foreach ( $categories as $category ) {
			// Handle both array and object formats
			$name = is_array( $category ) ? $category['name'] : $category->name;
			$id = is_array( $category ) ? $category['id'] : $category->term_id;
			$categories_list .= "- {$name} (ID: {$id})\n";
		}

		// Build existing titles list
		$existing_titles = [];
		foreach ( $existing_content['ideas'] as $idea ) {
			// Handle both array and object formats
			$title = is_array( $idea ) ? $idea['title'] : $idea->title;
			$existing_titles[] = $title;
		}
		foreach ( $existing_content['posts'] as $post ) {
			// Handle both array and object formats
			$title = is_array( $post ) ? $post['title'] : $post->title;
			$existing_titles[] = $title;
		}

		// Build personas list
		$personas_list = '';
		foreach ( $personas as $persona ) {
			// Handle both array and object formats
			$name = is_array( $persona ) ? $persona['name'] : $persona->name;
			$bio = is_array( $persona ) ? $persona['bio'] : $persona->bio;
			$personas_list .= "- {$name}: {$bio}\n";
		}

		// Build context information
		$context_info = '';
		if ( ! empty( $contexts['general']['content'] ) ) {
			$context_info .= "Company/Website Description: {$contexts['general']['content']}\n\n";
		}
		if ( ! empty( $contexts['products']['content'] ) ) {
			$context_info .= "Products/Services: {$contexts['products']['content']}\n\n";
		}
		if ( ! empty( $contexts['keywords']['content'] ) ) {
			$context_info .= "Target Keywords: {$contexts['keywords']['content']}\n\n";
		}
		if ( ! empty( $contexts['seo']['content'] ) ) {
			$context_info .= "SEO Guidelines: {$contexts['seo']['content']}\n\n";
		}

		$prompt = "You are a professional blog content strategist. Generate {$count} unique blog post ideas based on the following requirements:

CONTEXT INFORMATION:
{$context_info}";

		if ( ! empty( $context_prompt ) ) {
			$prompt .= "ADDITIONAL CONTEXT FROM USER:
{$context_prompt}

";
		}

		$prompt .= "AVAILABLE CATEGORIES (choose appropriate ones for each idea):
{$categories_list}

AVAILABLE PERSONAS (choose the best fit for each idea):
{$personas_list}

EXISTING CONTENT TO AVOID DUPLICATING:
" . implode( "\n- ", $existing_titles ) . "

REQUIREMENTS:
1. Generate exactly {$count} unique blog post ideas
2. Each idea must be completely different from existing content
3. Assign appropriate WordPress categories (use category names from the list above)
4. Select the most suitable persona for each idea
5. Focus on topics that would be valuable for the target audience
6. Ensure ideas are SEO-friendly and engaging

RESPONSE FORMAT (JSON):
```json
[
  {
    \"title\": \"Blog Post Title Here\",
    \"description\": \"Detailed description of what the blog post would cover (2-3 sentences)\",
    \"categories\": [\"Category Name 1\", \"Category Name 2\"],
    \"persona_name\": \"Persona Name\",
    \"reasoning\": \"Brief explanation of why this persona and categories were chosen\"
  }
]
```

Generate {$count} ideas now:";

		Logger::info( 'prompt_built', 'AI prompt constructed successfully', [
			'prompt_length' => strlen( $prompt ),
			'categories_count' => count( $categories ),
			'existing_titles_count' => count( $existing_titles ),
			'personas_count' => count( $personas )
		] );

		return $prompt;
	}

	/**
	 * Parse generated ideas from AI response.
	 *
	 * @param string $response The AI response content.
	 * @return array Array of parsed ideas.
	 */
	private function parse_generated_ideas( $response ) {
		Logger::info( 'parse_generated_ideas', 'Parsing AI response for generated ideas', [
			'response_length' => strlen( $response )
		] );

		$ideas = [];

		// Try to extract JSON from the response
		$json_start = strpos( $response, '[' );
		$json_end = strrpos( $response, ']' );

		if ( false !== $json_start && false !== $json_end ) {
			$json_content = substr( $response, $json_start, $json_end - $json_start + 1 );
			$parsed_ideas = json_decode( $json_content, true );

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $parsed_ideas ) ) {
				// Get all personas for ID lookup
				$personas = $this->persona_model->get_all();
				$persona_lookup = [];
				foreach ( $personas as $persona ) {
					// Handle both array and object formats
					$name = is_array( $persona ) ? $persona['name'] : $persona->name;
					$id = is_array( $persona ) ? $persona['id'] : $persona->id;
					$persona_lookup[ strtolower( $name ) ] = $id;
				}

				foreach ( $parsed_ideas as $idea ) {
					if ( ! empty( $idea['title'] ) && ! empty( $idea['description'] ) ) {
						// Find persona ID
						$persona_id = null;
						if ( ! empty( $idea['persona_name'] ) ) {
							$persona_key = strtolower( $idea['persona_name'] );
							$persona_id = $persona_lookup[ $persona_key ] ?? null;
						}

						$ideas[] = [
							'title' => sanitize_text_field( $idea['title'] ),
							'description' => sanitize_textarea_field( $idea['description'] ),
							'categories' => is_array( $idea['categories'] ) ? $idea['categories'] : [],
							'persona_id' => $persona_id,
							'persona_name' => $idea['persona_name'] ?? '',
							'reasoning' => $idea['reasoning'] ?? ''
						];
					}
				}
			}
		}

		// Fallback parsing if JSON parsing fails
		if ( empty( $ideas ) ) {
			Logger::warning( 'json_parsing_failed', 'JSON parsing failed, attempting fallback parsing' );
			$ideas = $this->fallback_parse_ideas( $response );
		}

		Logger::info( 'ideas_parsed', 'Ideas parsing completed', [
			'ideas_parsed' => count( $ideas ),
			'parsing_method' => empty( $ideas ) ? 'failed' : ( $json_start !== false ? 'json' : 'fallback' )
		] );

		return $ideas;
	}

	/**
	 * Fallback method to parse ideas if JSON parsing fails.
	 *
	 * @param string $response The AI response content.
	 * @return array Array of parsed ideas.
	 */
	private function fallback_parse_ideas( $response ) {
		$ideas = [];
		
		// Simple pattern matching for title/description pairs
		$lines = explode( "\n", $response );
		$current_idea = null;
		
		foreach ( $lines as $line ) {
			$line = trim( $line );
			
			// Look for title patterns
			if ( preg_match( '/^(?:\d+\.?\s*)?(.+?)(?:\s*[-–—]\s*(.+))?$/', $line, $matches ) ) {
				if ( ! empty( $matches[1] ) && strlen( $matches[1] ) > 10 ) {
					if ( $current_idea ) {
						$ideas[] = $current_idea;
					}
					
					$current_idea = [
						'title' => sanitize_text_field( $matches[1] ),
						'description' => ! empty( $matches[2] ) ? sanitize_textarea_field( $matches[2] ) : 'Blog post about ' . $matches[1],
						'categories' => [],
						'persona_id' => null,
						'persona_name' => '',
						'reasoning' => ''
					];
				}
			}
		}
		
		// Add the last idea if exists
		if ( $current_idea ) {
			$ideas[] = $current_idea;
		}
		
		return $ideas;
	}
} 