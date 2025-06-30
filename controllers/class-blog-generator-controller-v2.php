<?php
/**
 * Blog Generator Controller V2
 *
 * Handles complete blog post generation from approved ideas.
 * Runs as background process with status updates.
 *
 * @package AI_Blog_Generator
 * @subpackage Controllers
 */

namespace AI_Blog_Generator\Controllers;

use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Services\AI_Orchestrator;
use AI_Blog_Generator\Services\Background_Processor;
use AI_Blog_Generator\Controllers\Image_Controller;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Generation_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blog Generator Controller V2 Class
 *
 * Manages complete blog generation workflow.
 */
class Blog_Generator_Controller_V2 {

	/**
	 * Blog Ideas model instance.
	 *
	 * @var Blog_Ideas_Model_V2
	 */
	private $ideas_model;

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
	 * AI Orchestrator instance.
	 *
	 * @var AI_Orchestrator
	 */
	private $ai_orchestrator;

	/**
	 * Image controller instance.
	 *
	 * @var Image_Controller
	 */
	private $image_controller;

	/**
	 * Background processor instance.
	 *
	 * @var Background_Processor
	 */
	private $background_processor;

	/**
	 * Current idea being processed.
	 *
	 * @var array
	 */
	private $current_idea;

	/**
	 * Generation start time.
	 *
	 * @var int
	 */
	private $start_time;

	/**
	 * Text request ID for tracking.
	 *
	 * @var string
	 */
	private $text_request_id;

	/**
	 * Image request IDs for tracking.
	 *
	 * @var array
	 */
	private $image_request_ids = [];

	/**
	 * Generation Logger instance for detailed logging.
	 *
	 * @var Generation_Logger
	 */
	private $generation_logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ideas_model = new Blog_Ideas_Model_V2();
		$this->context_model = new Context_Model();
		$this->persona_model = new Persona_Model();
		$this->ai_orchestrator = new AI_Orchestrator();
		$this->image_controller = new Image_Controller();
		$this->background_processor = new Background_Processor();

		Logger::info( 'blog_generator_v2_init', 'Blog Generator Controller V2 initialized' );
	}

	/**
	 * Start generation for an approved idea.
	 *
	 * @param int $idea_id Idea ID to generate.
	 * @return array Generation result.
	 */
	public function start_generation( $idea_id ) {
		$this->start_time = time();

		Logger::info( 'generation_start', 'Starting blog generation process', [
			'idea_id' => $idea_id
		] );

		// Get the idea details
		$this->current_idea = $this->ideas_model->get_idea( $idea_id );
		if ( ! $this->current_idea ) {
			Logger::error( 'generation_idea_not_found', 'Idea not found for generation', [
				'idea_id' => $idea_id
			] );
			return [
				'success' => false,
				'message' => 'Idea not found'
			];
		}

		// Check if idea is approved or already generating (to handle race conditions)
		if ( ! in_array( $this->current_idea['status'], [ 'approved', 'generating' ], true ) ) {
			Logger::error( 'generation_idea_not_approved', 'Idea is not approved or generating for generation', [
				'idea_id' => $idea_id,
				'current_status' => $this->current_idea['status'],
				'allowed_statuses' => [ 'approved', 'generating' ]
			] );
			return [
				'success' => false,
				'message' => 'Idea must be approved or already generating for generation'
			];
		}

		// Initialize generation logger for detailed debugging
		$this->generation_logger = new Generation_Logger( $idea_id );
		$this->generation_logger->info( 'Generation started for idea', [
			'title' => $this->current_idea['title'],
			'description' => $this->current_idea['description'],
			'persona_id' => $this->current_idea['persona_id']
		] );

		// Update status to generating
		$this->update_generation_status( $idea_id, 'generating', 'Compiling Context' );

		// Start background processing directly
		$started = $this->background_processor->start_generation( $idea_id );

		if ( $started ) {
			Logger::info( 'generation_started', 'Blog generation started successfully', [
				'idea_id' => $idea_id
			] );

			return [
				'success' => true,
				'message' => 'Generation started',
				'idea_id' => $idea_id
			];
		} else {
			Logger::error( 'generation_start_failed', 'Failed to start background generation', [
				'idea_id' => $idea_id
			] );

			return [
				'success' => false,
				'message' => 'Failed to start generation'
			];
		}
	}

	/**
	 * Process blog generation (called by background processor).
	 *
	 * @param array $item Queue item data.
	 */
	public function process_generation( $item ) {
		$idea_id = $item['idea_id'];

		Logger::info( 'background_generation_start', 'Starting background blog generation', [
			'idea_id' => $idea_id
		] );

		// Initialize generation logger if not already set
		if ( ! $this->generation_logger ) {
			$this->generation_logger = new Generation_Logger( $idea_id );
			$this->generation_logger->info( 'Background generation process started' );
		}

		try {
			// Step 1: Compile all context information
			$this->compile_context_information( $idea_id );

			// Step 2: Generate content via AI
			$this->generate_content( $idea_id );

		} catch ( \Exception $e ) {
			Logger::error( 'generation_failed', 'Blog generation failed with exception', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			if ( $this->generation_logger ) {
				$this->generation_logger->log_exception( $e, [
					'step' => 'background_generation_process'
				] );
				$this->generation_logger->log_generation_complete( false, [
					'error' => $e->getMessage()
				] );
			}

			$this->update_generation_status( $idea_id, 'generating', 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Compile all context information for generation.
	 *
	 * @param int $idea_id Idea ID.
	 */
	private function compile_context_information( $idea_id ) {
		$this->update_generation_status( $idea_id, 'generating', 'Compiling Context' );

		if ( $this->generation_logger ) {
			$this->generation_logger->log_phase( 'Compiling Context', 10, [
				'step' => 'context_compilation_start'
			] );
		}

		Logger::debug( 'compile_context_start', 'Starting context compilation', [
			'idea_id' => $idea_id
		] );

		// Get the current idea if not already loaded
		if ( ! $this->current_idea || $this->current_idea['id'] != $idea_id ) {
			// if ( $this->generation_logger ) {
			// 	$this->generation_logger->debug( 'Loading idea from database', [
			// 		'idea_id' => $idea_id,
			// 		'current_idea_loaded' => ! empty( $this->current_idea )
			// 	] );
			// }
			
			$this->current_idea = $this->ideas_model->get_idea( $idea_id );
			
			// if ( $this->generation_logger ) {
			// 	$this->generation_logger->debug( 'Idea loaded from database', [
			// 		'idea_loaded' => ! empty( $this->current_idea ),
			// 		'idea_title' => $this->current_idea['title'] ?? 'unknown'
			// 	] );
			// }
		}

		// Get persona information
		$persona = null;
		if ( ! empty( $this->current_idea['persona_id'] ) ) {
			// if ( $this->generation_logger ) {
			// 	$this->generation_logger->debug( 'Loading persona information', [
			// 		'persona_id' => $this->current_idea['persona_id']
			// 	] );
			// }
			
			try {
				$persona = $this->persona_model->get_by_id( $this->current_idea['persona_id'] );
				
				// if ( $this->generation_logger ) {
				// 	$this->generation_logger->debug( 'Persona loaded successfully', [
				// 		'persona_id' => $this->current_idea['persona_id'],
				// 		'persona_name' => $persona['name'] ?? 'unknown',
				// 		'persona_tone' => $persona['tone'] ?? 'unknown'
				// 	] );
				// }
			} catch ( \Exception $e ) {
				// if ( $this->generation_logger ) {
				// 	$this->generation_logger->log_exception( $e, [
				// 		'step' => 'persona_loading',
				// 		'persona_id' => $this->current_idea['persona_id']
				// 	] );
				// }
				throw $e;
			}
		} else {
			// if ( $this->generation_logger ) {
			// 	$this->generation_logger->debug( 'No persona specified for this idea' );
			// }
		}

		// Get all contexts for content generation
		// if ( $this->generation_logger ) {
		// 	$this->generation_logger->debug( 'Loading context information for content generation' );
		// }
		
		try {
			$contexts = $this->context_model->get_compiled_for_usage( 'content' );
			
			// if ( $this->generation_logger ) {
			// 	$this->generation_logger->debug( 'Contexts loaded successfully', [
			// 		'context_types' => array_keys( $contexts ),
			// 		'context_count' => count( $contexts ),
			// 		'context_details' => array_map( function( $context ) {
			// 			return [
			// 				'type' => $context['type'] ?? 'unknown',
			// 				'content_length' => strlen( $context['content'] ?? '' ),
			// 				'has_content' => ! empty( $context['content'] )
			// 			];
			// 		}, $contexts )
			// 	] );
			// }
		} catch ( \Exception $e ) {
			// if ( $this->generation_logger ) {
			// 	$this->generation_logger->log_exception( $e, [
			// 		'step' => 'context_loading'
			// 	] );
			// }
			throw $e;
		}

		// Store compiled information for later use
		$this->current_idea['compiled_persona'] = $persona;
		$this->current_idea['compiled_contexts'] = $contexts;

		if ( $this->generation_logger ) {
			$this->generation_logger->info( 'Context compilation completed successfully', [
				'has_persona' => ! empty( $persona ),
				'context_types' => array_keys( $contexts ),
				'persona_name' => $persona['name'] ?? 'None',
				'total_context_length' => array_sum( array_map( function( $context ) {
					return strlen( $context['content'] ?? '' );
				}, $contexts ) )
			] );
			
			$this->generation_logger->log_phase( 'Context Compiled', 15, [
				'step' => 'context_compilation_complete'
			] );
		}

		Logger::info( 'context_compiled', 'Context information compiled successfully', [
			'idea_id' => $idea_id,
			'has_persona' => ! empty( $persona ),
			'context_types' => array_keys( $contexts ),
			'persona_name' => $persona['name'] ?? 'None'
		] );
	}

	/**
	 * Generate blog content via AI.
	 *
	 * @param int $idea_id Idea ID.
	 */
	private function generate_content( $idea_id ) {
		$this->update_generation_status( $idea_id, 'generating', 'Submitting request' );

		Logger::info( 'content_generation_start', 'Starting AI content generation', [
			'idea_id' => $idea_id
		] );

		// Build content generation prompt
		$prompt = $this->build_content_prompt();

		// Get preferred model
		$model = get_option( 'ai_blog_generator_preferred_service', 'anthropic' ) === 'anthropic' 
			? 'claude-3-5-sonnet' 
			: 'gpt-4o';

		Logger::debug( 'content_prompt_built', 'Content generation prompt built', [
			'idea_id' => $idea_id,
			'prompt_length' => strlen( $prompt ),
			'model' => $model
		] );

		// Submit text generation request
		$this->update_generation_status( $idea_id, 'generating', 'Waiting for Content Response' );

		$result = $this->ai_orchestrator->submit_text_request(
			$prompt,
			$model,
			[],
			[ $this, 'handle_content_response' ],
			'content_' . $idea_id
		);

		if ( ! $result['success'] ) {
			throw new \Exception( 'Failed to submit content generation request: ' . $result['message'] );
		}

		$this->text_request_id = $result['request_id'];

		Logger::info( 'content_request_submitted', 'Content generation request submitted', [
			'idea_id' => $idea_id,
			'request_id' => $this->text_request_id,
			'estimated_cost' => $result['estimated_cost']
		] );
	}

	/**
	 * Handle content generation response.
	 *
	 * @param string $request_id Request ID.
	 * @param array  $response   AI response.
	 */
	public function handle_content_response( $request_id, $response ) {
		$idea_id = str_replace( 'content_', '', $request_id );

		Logger::info( 'content_response_received', 'Content generation response received', [
			'idea_id' => $idea_id,
			'request_id' => $request_id,
			'success' => $response['success'] ?? false
		] );

		try {
			if ( ! $response['success'] ) {
				throw new \Exception( 'Content generation failed: ' . ( $response['message'] ?? 'Unknown error' ) );
			}

			// Parse the content response
			$parsed_content = $this->parse_content_response( $response['content'] );

			if ( empty( $parsed_content ) ) {
				throw new \Exception( 'Failed to parse content from AI response' );
			}

			// Store parsed content
			$this->current_idea['generated_content'] = $parsed_content;

			Logger::info( 'content_parsed', 'Content parsed successfully', [
				'idea_id' => $idea_id,
				'content_length' => strlen( $parsed_content['content'] ?? '' ),
				'has_image_prompts' => ! empty( $parsed_content['image_prompts'] )
			] );

			// Start image generation if image prompts exist
			if ( ! empty( $parsed_content['image_prompts'] ) ) {
				$this->generate_images( $idea_id, $parsed_content['image_prompts'] );
			} else {
				// No images needed, proceed to publishing
				$this->publish_post( $idea_id );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'content_response_error', 'Error handling content response', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );

			$this->update_generation_status( $idea_id, 'generating', 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate images for the blog post.
	 *
	 * @param int   $idea_id       Idea ID.
	 * @param array $image_prompts Image prompts from content.
	 */
	private function generate_images( $idea_id, $image_prompts ) {
		$this->update_generation_status( $idea_id, 'generating', 'Compiling image prompts' );

		Logger::info( 'image_generation_start', 'Starting image generation', [
			'idea_id' => $idea_id,
			'image_count' => count( $image_prompts )
		] );

		$this->image_request_ids = [];

		foreach ( $image_prompts as $index => $prompt ) {
			$this->update_generation_status( $idea_id, 'generating', 'Submitting Images Request' );

			// Use image generation model
			$model = 'dall-e-3'; // Default to DALL-E 3

			$result = $this->ai_orchestrator->submit_image_request(
				$prompt,
				$model,
				[
					'size' => '1024x1024',
					'quality' => 'standard'
				],
				[ $this, 'handle_image_response' ],
				'image_' . $idea_id . '_' . $index
			);

			if ( $result['success'] ) {
				$this->image_request_ids[] = $result['request_id'];
				Logger::debug( 'image_request_submitted', 'Image request submitted', [
					'idea_id' => $idea_id,
					'request_id' => $result['request_id'],
					'prompt' => $prompt
				] );
			} else {
				Logger::warning( 'image_request_failed', 'Failed to submit image request', [
					'idea_id' => $idea_id,
					'prompt' => $prompt,
					'error' => $result['message']
				] );
			}
		}

		if ( empty( $this->image_request_ids ) ) {
			Logger::warning( 'no_image_requests', 'No image requests were successful, proceeding without images', [
				'idea_id' => $idea_id
			] );
			$this->publish_post( $idea_id );
		} else {
			$this->update_generation_status( $idea_id, 'generating', 'Waiting for images Response' );
		}
	}

	/**
	 * Handle image generation response.
	 *
	 * @param string $request_id Request ID.
	 * @param array  $response   AI response.
	 */
	public function handle_image_response( $request_id, $response ) {
		$parts = explode( '_', $request_id );
		$idea_id = $parts[1];

		Logger::info( 'image_response_received', 'Image generation response received', [
			'idea_id' => $idea_id,
			'request_id' => $request_id,
			'success' => $response['success'] ?? false
		] );

		try {
			if ( $response['success'] && ! empty( $response['images'] ) ) {
				$this->update_generation_status( $idea_id, 'generating', 'Saving Images' );

				// Save images to media library
				$saved_images = [];
				foreach ( $response['images'] as $image_url ) {
					$media_id = $this->image_controller->save_image_from_url( 
						$image_url, 
						$this->current_idea['title'] . ' - Generated Image' 
					);
					
					if ( $media_id ) {
						$saved_images[] = $media_id;
					}
				}

				// Store saved images
				if ( ! isset( $this->current_idea['generated_images'] ) ) {
					$this->current_idea['generated_images'] = [];
				}
				$this->current_idea['generated_images'] = array_merge( 
					$this->current_idea['generated_images'], 
					$saved_images 
				);

				Logger::info( 'images_saved', 'Images saved to media library', [
					'idea_id' => $idea_id,
					'saved_count' => count( $saved_images )
				] );
			}

			// Remove this request from pending list
			$this->image_request_ids = array_filter( $this->image_request_ids, function( $id ) use ( $request_id ) {
				return $id !== $request_id;
			} );

			// Check if all image requests are complete
			if ( empty( $this->image_request_ids ) ) {
				Logger::info( 'all_images_complete', 'All image generation completed', [
					'idea_id' => $idea_id
				] );
				$this->publish_post( $idea_id );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'image_response_error', 'Error handling image response', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );
		}
	}

	/**
	 * Publish the final blog post.
	 *
	 * @param int $idea_id Idea ID.
	 */
	private function publish_post( $idea_id ) {
		$this->update_generation_status( $idea_id, 'generating', 'Publishing Post' );

		Logger::info( 'publish_post_start', 'Starting post publication', [
			'idea_id' => $idea_id
		] );

		try {
			$content = $this->current_idea['generated_content'];
			
			// Convert title to proper case
			$original_title = $content['title'] ?? $this->current_idea['title'];
			$proper_title = \AI_Blog_Generator\Utilities\Logger::to_proper_case( $original_title );
			
			Logger::info( 'title_converted_to_proper_case', 'Title converted to proper case', [
				'original_title' => $original_title,
				'proper_case_title' => $proper_title
			] );
			
			// Prepare post data
			$post_data = [
				'post_title' => $proper_title,
				'post_content' => $content['content'] ?? '',
				'post_status' => 'draft', // Always create as draft for review
				'post_type' => 'post',
				'post_author' => get_current_user_id(),
				'meta_input' => [
					'ai_generated' => true,
					'source_idea_id' => $idea_id,
					'generation_timestamp' => current_time( 'mysql' ),
					'ai_model_used' => $this->text_request_id ?? 'unknown'
				]
			];

			// Add categories if available
			if ( ! empty( $this->current_idea['category_ids'] ) ) {
				$post_data['post_category'] = array_map( 'intval', $this->current_idea['category_ids'] );
			}

			// Insert the post
			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				throw new \Exception( 'Failed to create post: ' . $post_id->get_error_message() );
			}

			// Set featured image if available
			if ( ! empty( $this->current_idea['generated_images'] ) ) {
				$featured_image_id = $this->current_idea['generated_images'][0];
				set_post_thumbnail( $post_id, $featured_image_id );

				Logger::debug( 'featured_image_set', 'Featured image set for post', [
					'post_id' => $post_id,
					'image_id' => $featured_image_id
				] );
			}

			// Update idea status to generated
			$this->ideas_model->update_status( $idea_id, 'generated' );
			$this->update_generation_status( $idea_id, 'generated', 'Complete!' );

			$duration = time() - $this->start_time;

			Logger::info( 'generation_complete', 'Blog generation completed successfully', [
				'idea_id' => $idea_id,
				'post_id' => $post_id,
				'duration' => $duration . ' seconds',
				'images_count' => count( $this->current_idea['generated_images'] ?? [] )
			] );

		} catch ( \Exception $e ) {
			Logger::error( 'publish_post_error', 'Error publishing post', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );

			$this->update_generation_status( $idea_id, 'generating', 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Build content generation prompt.
	 *
	 * @return string Complete prompt for content generation.
	 */
	private function build_content_prompt() {
		$idea = $this->current_idea;
		$persona = $idea['compiled_persona'];
		$contexts = $idea['compiled_contexts'];

		// Build context information
		$context_info = '';
		foreach ( $contexts as $type => $context_data ) {
			if ( ! empty( $context_data['content'] ) ) {
				$context_info .= ucfirst( $type ) . " Context:\n" . $context_data['content'] . "\n\n";
			}
		}

		// Build persona information
		$persona_info = '';
		if ( $persona ) {
			$persona_info = "Writing Persona: {$persona['name']}\n";
			$persona_info .= "Bio: {$persona['bio']}\n";
			if ( ! empty( $persona['writing_style'] ) ) {
				$persona_info .= "Writing Style: {$persona['writing_style']}\n";
			}
			$persona_info .= "\n";
		}

		$prompt = "You are a professional blog content writer. Create a comprehensive blog post based on the following requirements:

BLOG IDEA:
Title: {$idea['title']}
Description: {$idea['description']}

{$persona_info}{$context_info}REQUIREMENTS:
1. Create a complete, engaging blog post of 800-1200 words
2. Use SEO-friendly structure with clear headings (H2, H3)
3. Include an engaging introduction and strong conclusion
4. Write in a conversational yet informative tone
5. Include 2-3 image prompts for relevant illustrations
6. Ensure content is original and valuable to readers

RESPONSE FORMAT (JSON):
```json
{
  \"title\": \"Optimized blog post title (may improve on the original)\",
  \"content\": \"Complete blog post content with HTML formatting\",
  			\"excerpt\": \"Brief excerpt/meta description (120-140 characters)\",
  \"image_prompts\": [
    \"Detailed image prompt 1 for featured image\",
    \"Detailed image prompt 2 for content illustration\",
    \"Detailed image prompt 3 for supporting visual\"
  ],
  \"seo_keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
  \"tags\": [\"tag1\", \"tag2\", \"tag3\"]
}
```

Generate the blog post now:";

		return $prompt;
	}

	/**
	 * Parse content response from AI.
	 *
	 * @param string $response AI response content.
	 * @return array|null Parsed content or null on failure.
	 */
	private function parse_content_response( $response ) {
		Logger::debug( 'parse_content_start', 'Parsing AI content response', [
			'response_length' => strlen( $response )
		] );

		// Try to extract JSON from response
		$json_start = strpos( $response, '{' );
		$json_end = strrpos( $response, '}' );

		if ( false !== $json_start && false !== $json_end ) {
			$json_content = substr( $response, $json_start, $json_end - $json_start + 1 );
			$parsed = json_decode( $json_content, true );

			if ( json_last_error() === JSON_ERROR_NONE && ! empty( $parsed['content'] ) ) {
				Logger::info( 'content_parsed_success', 'Content parsed successfully from JSON', [
					'title_length' => strlen( $parsed['title'] ?? '' ),
					'content_length' => strlen( $parsed['content'] ?? '' ),
					'image_prompts_count' => count( $parsed['image_prompts'] ?? [] )
				] );

				return $parsed;
			}
		}

		// Fallback parsing
		Logger::warning( 'content_parse_fallback', 'JSON parsing failed, attempting fallback parsing' );
		
		return [
			'title' => $this->current_idea['title'],
			'content' => $response,
			'excerpt' => substr( strip_tags( $response ), 0, 160 ),
			'image_prompts' => [ 
				"Professional blog illustration for: {$this->current_idea['title']}" 
			],
			'seo_keywords' => [],
			'tags' => []
		];
	}

	/**
	 * Update generation status in database.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $status  Status (generating, generated, etc).
	 * @param string $message Status message.
	 */
	private function update_generation_status( $idea_id, $status, $message ) {
		global $wpdb;

		$table_name = AI_BLOG_GENERATOR_TABLE_IDEAS;
		
		$result = $wpdb->update(
			$table_name,
			[
				'status' => $status,
				'generation_status' => $message,
				'updated_at' => current_time( 'mysql' )
			],
			[ 'id' => $idea_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		Logger::debug( 'generation_status_updated', 'Generation status updated', [
			'idea_id' => $idea_id,
			'status' => $status,
			'message' => $message,
			'update_result' => $result
		] );
	}

	/**
	 * Get generation progress for an idea.
	 *
	 * @param int $idea_id Idea ID.
	 * @return array Progress information.
	 */
	public function get_generation_progress( $idea_id ) {
		$idea = $this->ideas_model->get_idea( $idea_id );
		
		if ( ! $idea ) {
			return [
				'status' => 'not_found',
				'message' => 'Idea not found'
			];
		}

		$progress = [
			'idea_id' => $idea_id,
			'status' => $idea['status'],
			'generation_status' => $idea['generation_status'],
			'updated_at' => $idea['updated_at']
		];

		// Add progress percentage based on status
		if ( $idea['status'] === 'generating' ) {
			$status_stages = [
				'Compiling Context' => 10,
				'Submitting request' => 20,
				'Waiting for Content Response' => 40,
				'Compiling image prompts' => 60,
				'Submitting Images Request' => 70,
				'Waiting for images Response' => 80,
				'Saving Images' => 90,
				'Publishing Post' => 95,
				'Complete!' => 100
			];

			$progress['percentage'] = $status_stages[ $idea['generation_status'] ] ?? 0;
		} else {
			$progress['percentage'] = $idea['status'] === 'generated' ? 100 : 0;
		}

		return $progress;
	}

	/**
	 * Cancel generation for an idea.
	 *
	 * @param int $idea_id Idea ID.
	 * @return array Result with success status and message.
	 */
	public function cancel_generation( $idea_id ) {
		Logger::info( 'cancel_generation_start', 'Canceling generation for idea', [
			'idea_id' => $idea_id
		] );

		try {
			// Check if idea exists and is generating
			$idea = $this->ideas_model->get_idea( $idea_id );
			
			if ( ! $idea ) {
				return [
					'success' => false,
					'message' => 'Idea not found'
				];
			}

			if ( $idea['status'] !== 'generating' ) {
				return [
					'success' => false,
					'message' => 'Idea is not currently being generated'
				];
			}

			// Cancel any pending AI requests in the orchestrator
			if ( ! empty( $this->text_request_id ) ) {
				$this->ai_orchestrator->cancel_request( $this->text_request_id );
				Logger::debug( 'text_request_canceled', 'Text generation request canceled', [
					'request_id' => $this->text_request_id
				] );
			}

			if ( ! empty( $this->image_request_ids ) ) {
				foreach ( $this->image_request_ids as $request_id ) {
					$this->ai_orchestrator->cancel_request( $request_id );
				}
				Logger::debug( 'image_requests_canceled', 'Image generation requests canceled', [
					'request_ids' => $this->image_request_ids
				] );
			}

			// Reset idea status to approved
			$result = $this->ideas_model->update_status( $idea_id, 'approved', [
				'generation_status' => null
			] );

			if ( $result ) {
				Logger::info( 'generation_canceled_success', 'Generation canceled successfully', [
					'idea_id' => $idea_id
				] );

				return [
					'success' => true,
					'message' => 'Generation canceled successfully'
				];
			} else {
				Logger::error( 'generation_cancel_db_failed', 'Failed to update database when canceling generation', [
					'idea_id' => $idea_id
				] );

				return [
					'success' => false,
					'message' => 'Failed to update idea status'
				];
			}

		} catch ( \Exception $e ) {
			Logger::error( 'cancel_generation_error', 'Error canceling generation', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			return [
				'success' => false,
				'message' => 'Error canceling generation: ' . $e->getMessage()
			];
		}
	}
} 