<?php
/**
 * Content Generator Service
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Services\Anthropic_Service;
use AI_Blog_Generator\Services\OpenAI_Service;
use AI_Blog_Generator\Services\Prompt_Compiler_Service;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Loggable;
use AI_Blog_Generator\Utilities\Generation_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Generator Class
 *
 * Orchestrates the blog post generation process.
 *
 * @since 1.0.0
 */
class Content_Generator {

	use Loggable;

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
	 * Cost model instance.
	 *
	 * @var Cost_Model
	 */
	private $cost_model;

	/**
	 * Database manager instance.
	 *
	 * @var Database_Manager
	 */
	private $database_manager;

	/**
	 * Idea model instance.
	 *
	 * @var Blog_Ideas_Model_V2
	 */
	private $idea_model;

	/**
	 * Blog model instance.
	 *
	 * @var Blog_Model
	 */
	private $blog_model;

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
	 * Generation Logger instance for detailed logging.
	 *
	 * @var Generation_Logger
	 */
	private $generation_logger;

	/**
	 * Last time a status update was sent (for throttling)
	 *
	 * @var int
	 */
	private $last_status_update_time = 0;
	
	/**
	 * Minimum interval between status updates (in seconds)
	 *
	 * @var int
	 */
	private $status_update_interval = 3; // Only update every 3 seconds

	/**
	 * Batch interval for database status updates (in seconds)
	 *
	 * @var int
	 */
	private $batch_update_interval = 30;

	/**
	 * Last database update time for batched updates
	 *
	 * @var int
	 */
	private $last_db_update_time = 0;

	/**
	 * Current generation stage for tracking stage changes
	 *
	 * @var string
	 */
	private $current_stage = '';

	/**
	 * Pending status update data for batching
	 *
	 * @var array
	 */
	private $pending_status_update = null;

	/**
	 * Prompt Compiler Service instance.
	 *
	 * @var Prompt_Compiler_Service
	 */
	private $prompt_compiler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_function_entry();
		
		$this->database_manager = Database_Manager::get_instance();
		$this->idea_model = new Blog_Ideas_Model_V2();
		$this->blog_model = new Blog_Model();
		$this->context_model = new Context_Model();
		$this->cost_model = new Cost_Model();
		$this->persona_model = new Persona_Model();
		
		// Initialize API services.
		$this->anthropic_service = new Anthropic_Service();
		$this->openai_service = new OpenAI_Service();
		
		// Prompt Compiler Service will be initialized lazily when needed
		$this->prompt_compiler = null;
		
		// Only log initialization once per session to prevent log spam
		if ( ! get_transient( 'ai_blog_content_generator_init_logged' ) ) {
			$this->log_info( 'content_generator_init', 'Content Generator service initialized', [
				'database_manager_ready' => ! is_null( $this->database_manager ),
				'models_initialized' => 4,
				'api_services_ready' => 2,
				'prompt_compiler_lazy' => true
			] );
			set_transient( 'ai_blog_content_generator_init_logged', true, 300 ); // 5 minutes
		}
		
		$this->log_function_exit();
	}

	/**
	 * Get Prompt Compiler Service instance (lazy initialization).
	 *
	 * @return Prompt_Compiler_Service
	 */
	private function get_prompt_compiler() {
		if ( $this->prompt_compiler === null ) {
			$this->prompt_compiler = new Prompt_Compiler_Service();
		}
		return $this->prompt_compiler;
	}

	/**
	 * Generate blog ideas.
	 *
	 * @param int    $count         Number of ideas to generate (default: 5).
	 * @param string $custom_prompt Optional custom prompt for idea generation.
	 * @return array Result with success status and generated ideas.
	 */
	public function generate_ideas( $count = 5, $custom_prompt = '' ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [ 
			'count' => $count,
			'has_custom_prompt' => ! empty( $custom_prompt )
		] );

		try {
			$this->log_info( 'content_generation_start', 'Starting idea generation process', [
				'ideas_requested' => $count,
				'custom_prompt_provided' => ! empty( $custom_prompt )
			] );
			
			// Check if generation is paused due to budget.
			$generation_paused = get_option( 'ai_blog_generator_generation_paused', false );
			if ( $generation_paused ) {
				$this->log_error( 'generation_paused', 'Generation is paused due to budget limits', [
					'budget_status' => 'exceeded',
					'paused_option' => $generation_paused
				] );
				throw new \Exception( __( 'Generation is paused due to budget limits', 'ai-blog-generator' ) );
			}
			
			// Get filters for idea generation.
			$this->log_debug( 'gathering_filters', 'Gathering filters for idea generation' );
			
			$existing_titles = $this->database_manager->get_existing_post_titles();
			$denied_titles = $this->database_manager->get_denied_idea_titles();
			$category_counts = $this->database_manager->get_category_post_counts();
			
			$this->log_debug( 'filters_gathered', 'Filters gathered for duplicate prevention', [
				'existing_titles_count' => count( $existing_titles ),
				'denied_titles_count' => count( $denied_titles ),
				'categories_with_posts' => count( $category_counts )
			] );
			
			// Compile contexts.
			$contexts = $this->compile_contexts();
			
			if ( empty( $contexts ) ) {
				$this->log_error( 'no_active_contexts', 'No active contexts found for idea generation', [
					'available_contexts' => $this->context_model->count(),
					'active_contexts' => $this->context_model->count( [ 'active' => 1 ] )
				] );
				throw new \Exception( __( 'No active contexts found. Please configure contexts first.', 'ai-blog-generator' ) );
			}

			$this->log_debug( 'contexts_compiled', 'Contexts compiled for idea generation', [
				'contexts_available' => array_keys( $contexts ),
				'total_context_length' => array_sum( array_map( 'strlen', $contexts ) )
			] );
			
			// Generate ideas using Anthropic.
			$this->log_info( 'calling_anthropic_service', 'Calling Anthropic service for idea generation', [
				'service_class' => get_class( $this->anthropic_service )
			] );
			
			$result = $this->anthropic_service->generate_blog_ideas( 
				$contexts, 
				$existing_titles, 
				$denied_titles,
				$count,
				$custom_prompt
			);
			
			if ( ! $result['success'] ) {
				$this->log_error( 'anthropic_service_failed', 'Anthropic service returned failure', [
					'error_message' => $result['message'] ?? 'unknown_error',
					'contexts_used' => count( $contexts )
				] );
				throw new \Exception( $result['message'] );
			}
			
			$generated_ideas = $result['ideas'];
			$saved_ideas = [];
			
			$this->log_info( 'ideas_received_from_anthropic', 'Received ideas from Anthropic service', [
				'ideas_received' => count( $generated_ideas ),
				'ideas_requested' => $count,
				'success_rate' => count( $generated_ideas ) / max( $count, 1 ) * 100
			] );
			
			// Start transaction.
			$this->database_manager->start_transaction();
			
			try {
				// Save each idea to database.
				foreach ( $generated_ideas as $index => $idea_data ) {
					$this->log_debug( 'processing_idea', 'Processing individual idea', [
						'idea_index' => $index,
						'idea_title' => $idea_data['title'] ?? 'no_title'
					] );
					
					// Validate idea data.
					if ( ! $this->validate_idea_data( $idea_data ) ) {
						$this->log_warning( 'invalid_idea_data', 'Skipping invalid idea', [
							'idea_index' => $index,
							'idea_data_keys' => array_keys( $idea_data ),
							'validation_failed' => true
						] );
						continue;
					}
					
					// Check for duplicate title one more time.
					if ( $this->idea_model->is_duplicate_title( $idea_data['title'] ) ) {
						$this->log_warning( 'duplicate_idea_title', 'Skipping duplicate title', [
							'title' => $idea_data['title'],
							'idea_index' => $index
						] );
						continue;
					}
					
					// Determine category ID.
					$category_id = $this->get_or_create_category( $idea_data['category'] );
					
					$this->log_debug( 'saving_idea', 'Saving idea to database', [
						'idea_title' => $idea_data['title'],
						'category_id' => $category_id,
						'idea_index' => $index
					] );
					
					// Save idea (removed keyword field as it doesn't exist in database).
					$idea_id = $this->idea_model->create([
						'title' => $idea_data['title'],
						'description' => $idea_data['description'],
						'category_id' => $category_id,
						'status' => 'pending',
					]);
					
					if ( $idea_id ) {
						$saved_ideas[] = array_merge( $idea_data, [
							'id' => $idea_id,
							'category_id' => $category_id,
						] );
						
						$this->log_debug( 'idea_saved_success', 'Idea saved successfully', [
							'idea_id' => $idea_id,
							'idea_title' => $idea_data['title']
						] );
					} else {
						$this->log_error( 'idea_save_failed', 'Failed to save idea to database', [
							'idea_title' => $idea_data['title'],
							'idea_index' => $index
						] );
					}
				}
				
				// Commit transaction.
				$this->database_manager->commit();
				
				$this->log_info( 'ideas_transaction_committed', 'Ideas saved to database successfully', [
					'ideas_processed' => count( $generated_ideas ),
					'ideas_saved' => count( $saved_ideas ),
					'save_success_rate' => count( $saved_ideas ) / max( count( $generated_ideas ), 1 ) * 100
				] );
				
				// Record cost if available.
				if ( isset( $result['usage'] ) ) {
					$cost = $this->anthropic_service->calculate_cost( $result['usage'] );
					$this->cost_model->record_cost( 'anthropic', 'idea_generation', $cost, $result['usage'] );
					
					$this->log_info( 'cost_recorded', 'Cost recorded for idea generation', [
						'cost_usd' => $cost,
						'tokens_used' => $result['usage']
					] );
				}
				
				$final_result = [
					'success' => true,
					'ideas' => $saved_ideas,
					'message' => sprintf(
						/* translators: %d: Number of ideas generated */
						__( 'Successfully generated %d blog ideas', 'ai-blog-generator' ),
						count( $saved_ideas )
					),
				];
				
				$this->log_info( 'ideas_generation_complete', 'Blog ideas generation completed successfully', [
					'total_generated' => count( $generated_ideas ),
					'total_saved' => count( $saved_ideas ),
					'final_success' => true
				] );
				
			} catch ( \Exception $e ) {
				// Rollback transaction on error.
				$this->database_manager->rollback();
				$this->log_exception( 'ideas_transaction_failed', $e, [
					'ideas_processed' => count( $generated_ideas ),
					'ideas_saved_before_error' => count( $saved_ideas )
				] );
				throw $e;
			}
			
		} catch ( \Exception $e ) {
			$this->log_exception( 'ideas_generation_failed', $e, [
				'requested_count' => $count,
				'custom_prompt_used' => ! empty( $custom_prompt )
			] );
			
			$final_result = [
				'success' => false,
				'message' => $e->getMessage(),
				'ideas' => [],
			];
		}

		$this->end_timer( $start_time, 'content_generator_ideas', [
			'success' => $final_result['success'],
			'ideas_generated' => count( $final_result['ideas'] ?? [] )
		] );
		$this->log_function_exit( $final_result['success'] ? 'success' : 'failed' );

		return $final_result;
	}

	/**
	 * Generate a complete blog post from an approved idea.
	 *
	 * @param int $idea_id The idea ID to generate content for.
	 * @return array Result with success status and post data.
	 */
	public function generate_blog_post( $idea_id ) {
		$transaction_started = false; // Track if we started a transaction
		$debug_log = __DIR__ . '/../debug-transaction.log'; // Debug log path
		
		try {
			// Prevent multiple simultaneous generations for the same idea
			$generation_lock = "ai_blog_generation_lock_{$idea_id}";
			
			// Check if lock exists and if it's expired
			$lock_value = get_transient( $generation_lock );
			if ( $lock_value ) {
				// Handle both array format (from Background Processor) and integer format (legacy)
				$lock_timestamp = is_array( $lock_value ) ? 
					( $lock_value['started_timestamp'] ?? time() ) : 
					$lock_value;
					
				$lock_age = time() - $lock_timestamp;
				if ( $lock_age > 600 ) { // 10 minutes
					// Lock is expired, clear it
					delete_transient( $generation_lock );
					$this->log_warning( 'expired_lock_cleared', 'Cleared expired generation lock', [
						'idea_id' => $idea_id,
						'lock_age_seconds' => $lock_age
					] );
				} else {
					// Lock is still valid
					$this->log_warning( 'generation_already_running', 'Generation already in progress for this idea', [
						'idea_id' => $idea_id,
						'lock_exists' => true,
						'lock_age_seconds' => $lock_age,
						'lock_format' => is_array( $lock_value ) ? 'array' : 'integer'
					] );
					throw new \Exception( __( 'Generation is already in progress for this idea', 'ai-blog-generator' ) );
				}
			}
			
			// Set generation lock (expires in 10 minutes)
			set_transient( $generation_lock, time(), 600 );
			
			// Ensure lock is cleared on completion or error
			$clear_lock = function() use ( $generation_lock, $idea_id ) {
				$deleted = delete_transient( $generation_lock );
				error_log( "[AI_BLOG_GENERATOR] Lock cleared for idea {$idea_id}: " . ( $deleted ? 'SUCCESS' : 'NOT_FOUND' ) );
			};
			
			// Register shutdown function to clear lock even on fatal errors
			register_shutdown_function( function() use ( $generation_lock, $idea_id ) {
				$error = error_get_last();
				if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
					delete_transient( $generation_lock );
					error_log( "[AI_BLOG_GENERATOR] Fatal error detected, cleared lock for idea {$idea_id}" );
				}
			} );
			
			$this->log_info( 'content_generator_generate_blog_post', 'Starting blog post generation', [
				'idea_id' => $idea_id,
				'generation_lock_set' => true
			] );
			
			// Check if generation is paused.
			$this->log_info( 'content_generator_generate_blog_post', 'Checking if generation is paused');
			if ( get_option( 'ai_blog_generator_generation_paused', false ) ) {
				$this->log_info( 'content_generator_generate_blog_post', 'Generation is paused');
				throw new \Exception( __( 'Generation is paused due to budget limits', 'ai-blog-generator' ) );
			}
			
			// Get idea details.
			$this->log_info( 'content_generator_generate_blog_post', 'Getting idea details', ['idea_id' => $idea_id]);
			$idea = $this->idea_model->get( $idea_id );
			$this->log_info( 'content_generator_generate_blog_post', 'Idea details retrieved', [
				'idea_id' => $idea_id,
				'idea_data' => $idea,
				'idea_is_array' => is_array( $idea ),
				'idea_keys' => is_array( $idea ) ? array_keys( $idea ) : 'not_array'
			]);
			
			$this->log_info( 'content_generator_generate_blog_post', 'Checking if idea exists', ['idea_id' => $idea_id]);
			if ( ! $idea ) {
				$this->log_error( 'content_generator_generate_blog_post', 'Idea not found', ['idea_id' => $idea_id]);
				throw new \Exception( __( 'Idea not found', 'ai-blog-generator' ) );
			}
			
			$this->log_info( 'content_generator_generate_blog_post', 'Idea exists, checking status field', [
				'idea_id' => $idea_id,
				'has_status' => isset( $idea['status'] ),
				'status_value' => $idea['status'] ?? 'not_set',
				'status_raw' => var_export( $idea['status'] ?? null, true ),
				'status_type' => gettype( $idea['status'] ?? null ),
				'status_length' => strlen( $idea['status'] ?? '' ),
				'all_idea_keys' => array_keys( $idea ),
				'full_idea_data' => $idea,
				'idea_data_type' => gettype( $idea ),
				'idea_is_array' => is_array( $idea )
			]);
			
			if(!isset($idea['status'])){
				$this->log_error( 'content_generator_generate_blog_post', 'Idea status not set', [
					'idea_id' => $idea_id,
					'idea_structure' => array_keys( $idea ),
					'idea_data_type' => gettype( $idea )
				]);
				throw new \Exception( __( 'Idea status not set', 'ai-blog-generator' ) );
			}
			
			// Check idea status.
			$this->log_info( 'content_generator_generate_blog_post', 'Checking idea status value', [
				'idea_id' => $idea_id,
				'current_status' => $idea['status'],
				'current_status_raw' => var_export( $idea['status'], true ),
				'current_status_type' => gettype( $idea['status'] ),
				'current_status_length' => strlen( $idea['status'] ),
				'required_status' => 'approved',
				'strict_comparison' => $idea['status'] === 'approved',
				'loose_comparison' => $idea['status'] == 'approved',
				'trimmed_comparison' => trim( $idea['status'] ) === 'approved'
			]);
			
			if ( ! in_array( $idea['status'], [ 'approved', 'generating' ], true ) ) {
				$this->log_error( 'content_generator_generate_blog_post', 'Idea not approved or generating', [
					'idea_id' => $idea_id,
					'current_status' => $idea['status'],
					'current_status_raw' => var_export( $idea['status'], true ),
					'current_status_hex' => bin2hex( $idea['status'] ),
					'required_statuses' => [ 'approved', 'generating' ],
					'comparison_result' => ! in_array( $idea['status'], [ 'approved', 'generating' ], true ),
					'idea_data_type' => gettype( $idea ),
					'idea_is_array' => is_array( $idea ),
					'idea_keys' => is_array( $idea ) ? array_keys( $idea ) : 'not_array'
				]);
				throw new \Exception( sprintf( 
					__( 'Idea must be approved or already generating. Current status: %s', 'ai-blog-generator' ),
					$idea['status'] ?? 'null'
				) );
			}
			
			$this->log_info( 'content_generator_generate_blog_post', 'All idea validations passed, starting transaction', ['idea_id' => $idea_id]);
			
			// Note: Transaction will only be started when saving the post to minimize lock time
			$this->log_info( 'content_generator_generate_blog_post', 'Ready to begin generation process', ['idea_id' => $idea_id]);
			
			try {
				// Check for cancellation before starting generation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'start' );
				}
				
				// Update idea status to generating.
				$this->log_info( 'pre_status_update', 'About to update idea status to generating', [ 'idea_id' => $idea_id ] );
				
				// Initialize Generation Logger for detailed logging
				try {
					$this->generation_logger = new Generation_Logger( $idea_id );
					$this->generation_logger->info( 'Generation process initialized', [
						'idea_id' => $idea_id,
						'idea_title' => $idea['title'] ?? 'unknown',
						'idea_status' => $idea['status'] ?? 'unknown'
					] );
				} catch ( \Exception $e ) {
					$this->log_error( 'generation_logger_init_failed', 'Failed to initialize Generation Logger', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage()
					] );
					// Continue without detailed logging - not critical
					$this->generation_logger = null;
				}
				
				// Validate idea_model is available
				if ( ! $this->idea_model ) {
					$this->log_error( 'idea_model_missing', 'Idea model is not instantiated', [ 'idea_id' => $idea_id ] );
					throw new \Exception( 'Idea model is not available' );
				}
				
				$this->log_info( 'idea_model_validated', 'Idea model is available', [ 
					'idea_id' => $idea_id,
					'idea_model_class' => get_class( $this->idea_model )
				] );
				
				try {
					$this->log_info( 'updating_idea_status', 'Updating idea status to generating', [ 'idea_id' => $idea_id ] );
					$update_result = $this->idea_model->update( $idea_id, [ 'status' => 'generating' ] );
					$this->log_info( 'idea_status_updated', 'Idea status update completed', [ 
						'idea_id' => $idea_id,
						'update_result' => $update_result
					] );
				} catch ( \Exception $e ) {
					$this->log_error( 'idea_status_update_failed', 'Failed to update idea status', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					throw $e;
				}
				
				try {
					$this->log_info( 'updating_generation_status', 'Updating generation status', [ 'idea_id' => $idea_id ] );
					$this->update_generation_status( $idea_id, 'starting', 'Compiling Context' );
					$this->log_info( 'generation_status_updated', 'Generation status update completed', [ 'idea_id' => $idea_id ] );
				} catch ( \Exception $e ) {
					$this->log_error( 'generation_status_update_failed', 'Failed to update generation status', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					// Don't throw here - generation status is not critical
				}
				
				// Check for cancellation after initial setup
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'setup' );
				}
				
				// Compile contexts specifically for content generation.
				$this->log_info( 'compiling_contexts_start', 'Starting context compilation', [ 'idea_id' => $idea_id ] );
				$this->update_generation_status( $idea_id, 'contexts', 'Compiling Context' );
				
				try {
					$contexts = $this->compile_contexts_enhanced( 'content', [
						'max_contexts_per_type' => 5,
						'priority_threshold' => 0,
						'persona_id' => ! empty( $idea['persona_id'] ) ? $idea['persona_id'] : null,
					] );
					$this->log_info( 'compiling_contexts_success', 'Context compilation successful', [ 
						'idea_id' => $idea_id,
						'context_types' => array_keys( $contexts ),
						'context_count' => count( $contexts )
					] );
					$this->update_generation_status( $idea_id, 'contexts', 'Submitting request' );
				} catch ( \Exception $e ) {
					$this->log_error( 'compiling_contexts_failed', 'Context compilation failed', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					throw new \Exception( 'Failed to compile contexts: ' . $e->getMessage() );
				}
				
				// Check for cancellation after context compilation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'contexts' );
				}
				
				// **NEW FUNCTIONALITY** - Use Prompt Compiler Service
				$this->log_info( 'using_prompt_compiler', 'Using Prompt Compiler Service for prompt generation', [ 'idea_id' => $idea_id ] );
				
				try {
					// Generate prompts using the Prompt Compiler Service (lazy initialization)
					$prompt_data = $this->get_prompt_compiler()->generate_content_prompts( $idea_id );
					
					$this->log_info( 'prompts_compiled', 'Prompts compiled successfully', [
						'idea_id' => $idea_id,
						'system_prompts_count' => count( $prompt_data['system_prompts'] ),
						'user_prompt_length' => strlen( $prompt_data['user_prompt'] )
					] );
					
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Prompts compiled by Prompt Compiler Service', [
							'system_prompts_count' => count( $prompt_data['system_prompts'] ),
							'user_prompt_preview' => substr( $prompt_data['user_prompt'], 0, 200 ) . '...'
						] );
					}
				} catch ( \Exception $e ) {
					$this->log_error( 'prompt_compilation_failed', 'Failed to compile prompts', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage()
					] );
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Prompt compilation failed: ' . $e->getMessage() );
					}
					throw new \Exception( 'Failed to compile prompts: ' . $e->getMessage() );
				}
				
				// Check for cancellation before AI content generation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'pre-content' );
				}
				
				// Generate content using new Anthropic method
				$this->log_info( 'anthropic_generation_start', 'Starting Anthropic content generation with pre-compiled prompts', [ 'idea_id' => $idea_id ] );
				
				// Log to Generation Logger instead of debug file
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'About to call Anthropic service with pre-compiled prompts', [
						'idea_id' => $idea['id'] ?? 'no_id',
						'idea_title' => substr($idea['title'] ?? 'no_title', 0, 50),
						'system_prompts_count' => count( $prompt_data['system_prompts'] )
					] );
				}
				
				$this->update_generation_status( $idea_id, 'content', 'Waiting for Content Response' );
				
				// Validate anthropic service before calling
				if ( ! $this->anthropic_service ) {
					$this->log_error( 'anthropic_service_missing', 'Anthropic service is not instantiated', [ 'idea_id' => $idea_id ] );
					throw new \Exception( 'Anthropic service is not available' );
				}
				
				$this->log_info( 'anthropic_service_validated', 'Anthropic service is available', [ 
					'idea_id' => $idea_id,
					'service_class' => get_class( $this->anthropic_service )
				] );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Anthropic service validated, making call' );
				}
				
				// Use the idea and persona from prompt compiler results
				$idea = $prompt_data['idea'];
				$persona = $prompt_data['persona'];
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'About to call Anthropic service for content generation', [
						'idea_id' => $idea['id'] ?? 'no_id',
						'idea_title' => substr($idea['title'] ?? 'no_title', 0, 50),
						'idea_status' => $idea['status'] ?? 'no_status',
						'has_persona' => ! is_null( $persona )
					] );
				}
				
				try {
					// Call new generate_content method with pre-compiled prompts
					$content_result = $this->anthropic_service->generate_content( 
						$prompt_data['system_prompts'], 
						$prompt_data['user_prompt']
					);
					
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Anthropic call completed successfully', [
							'success' => $content_result['success'] ?? 'not_set',
							'result_keys' => array_keys( $content_result )
						] );
					}
					
					$this->log_info( 'anthropic_generation_success', 'Anthropic content generation completed', [ 
						'idea_id' => $idea_id,
						'success' => $content_result['success'] ?? false
					] );
					
					$this->update_generation_status( $idea_id, 'content', 'Compiling image prompts' );
				} catch ( \Exception $e ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Anthropic call FAILED with exception', [
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString()
						] );
					}
					
					$this->log_error( 'anthropic_generation_failed', 'Anthropic content generation failed', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					throw new \Exception( 'Failed to generate content with Anthropic: ' . $e->getMessage() );
				} catch ( \Error $e ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Anthropic call FAILED with fatal error', [
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString()
						] );
					}
					
					throw new \Exception( 'Fatal error during Anthropic call: ' . $e->getMessage() );
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Checking content result success' );
				}
				
				if ( ! $content_result['success'] ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Content result failed: ' . ($content_result['message'] ?? 'no_message') );
					}
					throw new \Exception( $content_result['message'] );
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Content result success confirmed, parsing content' );
				}
				
				// Parse the content response
				$content = $this->parse_content_response( $content_result['content'] );
				$total_cost = $content_result['cost'] ?? 0;
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Content parsed successfully', [
						'has_html' => ! empty( $content['html'] ),
						'has_title' => ! empty( $content['title'] ),
						'has_meta' => ! empty( $content['meta_description'] ),
						'has_images' => ! empty( $content['images'] ),
						'image_count' => count( $content['images'] ?? [] )
					] );
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Cost calculation section completed' );
				}
				
				// Validate generated content.
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Starting content validation' );
				}
				
				if ( ! $this->validate_generated_content( $content ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Content validation FAILED' );
					}
					throw new \Exception( __( 'Generated content failed validation', 'ai-blog-generator' ) );
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Content validation passed' );
				}
				
				// Check for cancellation after content generation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'post-content' );
				}
				
				$this->update_generation_status( $idea_id, 'content', 'Compiling image prompts' );
				
				// Convert any H1 headings to H2 headings (safety measure)
				$content['html'] = $this->convert_h1_to_h2( $content['html'] );
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'H1 to H2 conversion completed' );
				}
				
							// Generate images if required.
			$final_html = $content['html'];
			$featured_image_id = null;
			
			if ( $this->generation_logger ) {
				$this->generation_logger->info( 'Checking if images should be generated', [
					'images_in_content' => empty( $content['images'] ) ? 'none' : count( $content['images'] ),
					'featured_image' => empty( $content['featured_image'] ) ? 'none' : 'present',
					'image_generation_enabled' => get_option( 'ai_blog_generator_enable_image_generation', true ) ? 'yes' : 'no'
				] );
			}
			
			// Check for transaction timeout before starting image generation
			if ( $this->database_manager->is_transaction_timed_out() ) {
				$this->log_error( 'transaction_timeout_before_images', 'Transaction timed out before image generation', [
					'idea_id' => $idea_id
				] );
				throw new \Exception( 'Transaction timeout before image generation' );
			}
				
				// Combine featured image and content images for batch generation but process differently
				$all_image_requirements = [];
				$featured_image_id = null;
				
				// Initialize images array to prevent undefined variable errors
				$images = [
					'results' => [],
					'summary' => [
						'total' => 0,
						'successful' => 0,
						'failed' => 0,
						'total_cost' => 0,
					],
				];
				
				// Add featured image to requirements first (will be index 0)
				if ( ! empty( $content['featured_image'] ) ) {
					$featured_requirement = $content['featured_image'];
					// Add focus keyphrase for SEO-friendly filename generation
					$featured_requirement['focus_keyphrase'] = $content['focus_keyphrase'] ?? '';
					$all_image_requirements[] = $featured_requirement;
				}
				
				// Add content images to requirements
				if ( ! empty( $content['images'] ) ) {
					foreach ( $content['images'] as $image_requirement ) {
						// Add focus keyphrase for SEO-friendly filename generation
						$image_requirement['focus_keyphrase'] = $content['focus_keyphrase'] ?? '';
						$all_image_requirements[] = $image_requirement;
					}
				}
				
				// Check for cancellation before image generation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'pre-images' );
				}
				
				if ( ! empty( $all_image_requirements ) && get_option( 'ai_blog_generator_enable_image_generation', true ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Starting image generation process' );
					}
					
					// Add timeout protection for image generation (8 minutes max for up to 3 images)
					$image_timeout_start = time();
					$image_timeout_limit = 480; // 8 minutes max for all image generation
					
					// Set process timeout protection
					$original_time_limit = ini_get( 'max_execution_time' );
					set_time_limit( $image_timeout_limit + 60 ); // Give extra 60 seconds for cleanup
					
					try {
						$this->update_generation_status( $idea_id, 'images', 'Submitting Images Request' );
						
						if ( $this->generation_logger ) {
							$this->generation_logger->log_phase( 'images', 75, [ 
								'message' => 'Starting image generation',
								'timeout_limit' => $image_timeout_limit
							] );
						}
						
						// Set a shorter execution time limit for image generation specifically
						set_time_limit( $image_timeout_limit );
						
						// Check timeout before even starting
						if ( ( time() - $image_timeout_start ) > $image_timeout_limit ) {
							throw new \Exception( 'Image generation timeout before start' );
						}
						
						try {
							$this->log_info( 'image_generation_start', 'Starting image generation', [
								'total_image_count' => count( $all_image_requirements ),
								'content_image_count' => count( $content['images'] ),
								'has_featured_image' => ! empty( $content['featured_image'] ),
							] );
							
							// Get image-specific contexts for better image generation
							if ( $this->generation_logger ) {
								$this->generation_logger->debug( 'Compiling image contexts' );
							}
							$image_contexts = $this->compile_contexts_for_images([
								'persona_id' => ! empty( $idea['persona_id'] ) ? $idea['persona_id'] : null,
							]);
							
							// Check timeout again
							if ( ( time() - $image_timeout_start ) > $image_timeout_limit ) {
								throw new \Exception( 'Image generation timeout during context compilation' );
							}
							
							// Add seed images if available
							if ( $this->generation_logger ) {
								$this->generation_logger->debug( 'Adding seed images to requirements' );
							}
							$this->add_seed_images_to_requirements( $all_image_requirements, $idea, $image_contexts );
							
							// Final timeout check before API call
							if ( ( time() - $image_timeout_start ) > $image_timeout_limit ) {
								throw new \Exception( 'Image generation timeout before API call' );
							}
							
							// Check if OpenAI service is valid before calling
							if ( ! $this->openai_service || ! is_object( $this->openai_service ) ) {
								throw new \Exception( 'OpenAI service is not available for image generation' );
							}
							
							// Generate images sequentially with progress tracking
							if ( $this->generation_logger ) {
								$this->generation_logger->info( 'Calling OpenAI sequential image generation service', [
									'image_count' => count( $all_image_requirements )
								] );
							}
							
							// Create progress callback for real-time status updates
							$progress_callback = function( $progress_data ) use ( $idea_id ) {
								if ( isset( $progress_data['message'] ) ) {
									$this->update_generation_status( $idea_id, 'images', $progress_data['message'] );
									
									if ( $this->generation_logger ) {
										$this->generation_logger->info( 'Image Generation Progress: ' . $progress_data['message'], [
											'current_image' => $progress_data['current_image'] ?? 0,
											'total_images' => $progress_data['total_images'] ?? 0,
											'progress_percentage' => $progress_data['progress'] ?? 0,
											'token' => $progress_data['token'] ?? 'unknown'
										] );
									}
								}
							};
							
							$images = $this->openai_service->generate_images_sequentially( $all_image_requirements, $progress_callback );
							
							// CRITICAL: Final timeout check after API call
							if ( ( time() - $image_timeout_start ) > $image_timeout_limit ) {
								throw new \Exception( 'Image generation timeout after API call' );
							}
							
						} catch ( \Exception $img_e ) {
							// Log detailed error information
							$elapsed_time = time() - $image_timeout_start;
							file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - IMAGE_GENERATION_ERROR: " . $img_e->getMessage() . " (elapsed: {$elapsed_time}s)\n", FILE_APPEND );
							
							$this->log_warning( 'image_generation_error_but_continuing', 'Image generation encountered error but continuing with post creation', [
								'error' => $img_e->getMessage(),
								'elapsed_time' => $elapsed_time,
								'idea_id' => $idea_id,
								'timeout_limit' => $image_timeout_limit,
								'is_timeout' => $elapsed_time > $image_timeout_limit
							] );
							
							if ( $this->generation_logger ) {
								$this->generation_logger->log_phase( 'images', 75, [ 
									'message' => 'Image generation failed - continuing without images',
									'error' => $img_e->getMessage(),
									'elapsed_time' => $elapsed_time
								] );
							}
							
							// Set empty images result so post can continue
							$images = [
								'results' => [],
								'summary' => [
									'total' => count( $all_image_requirements ),
									'successful' => 0,
									'failed' => count( $all_image_requirements ),
									'total_cost' => 0,
								],
							];
							
							// Update status to show we're continuing
							$this->update_generation_status( $idea_id, 'images', 'Image generation failed - continuing with post creation...' );
						}
						
					} catch ( \Exception $timeout_e ) {
						// Log timeout details
						$elapsed_time = time() - $image_timeout_start;
						file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - IMAGE_GENERATION_TIMEOUT: " . $timeout_e->getMessage() . " (elapsed: {$elapsed_time}s)\n", FILE_APPEND );
						
						$this->log_warning( 'image_generation_timeout_but_continuing', 'Image generation timeout - continuing with post creation', [
							'error' => $timeout_e->getMessage(),
							'elapsed_time' => $elapsed_time,
							'idea_id' => $idea_id,
							'timeout_limit' => $image_timeout_limit
						] );
						
						if ( $this->generation_logger ) {
							$this->generation_logger->log_phase( 'images', 75, [ 
								'message' => 'Image generation timeout - continuing without images',
								'error' => $timeout_e->getMessage(),
								'elapsed_time' => $elapsed_time
							] );
						}
						
						// Set empty images result so post can continue
						$images = [
							'results' => [],
							'summary' => [
								'total' => count( $all_image_requirements ),
								'successful' => 0,
								'failed' => count( $all_image_requirements ),
								'total_cost' => 0,
							],
						];
						
						// Update status to show we're continuing
						$this->update_generation_status( $idea_id, 'images', 'Image generation timeout - continuing with post creation...' );
					} finally {
						// Always restore timeout limits
						if ( isset( $original_time_limit ) && $original_time_limit ) {
							set_time_limit( $original_time_limit );
						}
					}
					
					if ( $images['summary']['successful'] > 0 ) {
						foreach ( $images['results'] as $index => $image ) {
							if ( $image['success'] ) {
								// Handle featured image separately (index 0 if featured image was present)
								if ( $index === 0 && ! empty( $content['featured_image'] ) && $image['token'] === '{{featured}}' ) {
									// Set as featured image only, don't insert into content
									$featured_image_id = $image['attachment_id'];
									if ( $this->generation_logger ) {
										$this->generation_logger->info( 'Featured image generated with ID: ' . $featured_image_id );
									}
								} else {
									// Handle content images - insert into content
									$img_html = $this->create_image_html( $image );
									$final_html = str_replace( $image['token'], $img_html, $final_html );
									if ( $this->generation_logger ) {
										$this->generation_logger->info( 'Content image ' . $image['token'] . ' inserted into HTML' );
									}
								}
							}
						}
						
						// Add image generation cost.
						$total_cost += $images['summary']['total_cost'];
					}
					
					$this->log_info( 'image_generation_complete', 'Image generation completed', [
						'successful' => $images['summary']['successful'],
						'failed' => $images['summary']['failed'],
						'cost' => $images['summary']['total_cost'],
					] );
					
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Image generation process completed' );
					}
					
					$this->update_generation_status( $idea_id, 'images', 'Saving Images' );
				} else {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'No image generation needed (no images or disabled)' );
					}
					
					$this->update_generation_status( $idea_id, 'post', 'Skipping image generation. Preparing WordPress post...' );
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Processing additional content (references/charts)' );
				}
				
				// Append references if present
				if ( ! empty( $content['references'] ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Adding references to content' );
					}
					$final_html .= "\n\n" . $content['references'];
				}
				
				// Append chart scripts if present
				if ( ! empty( $content['charts'] ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Adding chart scripts to content' );
					}
					$final_html .= "\n\n" . $content['charts'];
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Final HTML preparation completed' );
				}
				
				// Clean fusion_code content to fix Avada's HTML markup issues
				$final_html = $this->clean_fusion_code_content( $final_html );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Fusion_code cleanup completed' );
				}
				
				// Log all fusion_code blocks in the final HTML to debug
				preg_match_all( '/\[fusion_code\](.*?)\[\/fusion_code\]/s', $final_html, $fusion_matches );
				if ( ! empty( $fusion_matches[1] ) ) {
					file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: === FINAL FUSION_CODE BLOCKS BEFORE WP_INSERT_POST ===\n", FILE_APPEND );
					foreach ( $fusion_matches[1] as $index => $fusion_content ) {
						file_put_contents( $debug_log, "Block " . ($index + 1) . ":\n", FILE_APPEND );
						file_put_contents( $debug_log, $fusion_content . "\n", FILE_APPEND );
						file_put_contents( $debug_log, "---\n", FILE_APPEND );
					}
					file_put_contents( $debug_log, "=== END FINAL FUSION_CODE BLOCKS ===\n\n", FILE_APPEND );
				}
				
				// Create WordPress post.
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Starting WordPress post creation' );
				}
				
				$this->update_generation_status( $idea_id, 'post', 'Creating WordPress post...' );
				
				// Get the full idea data including all categories
				$full_idea = $this->idea_model->get_idea( $idea_id );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Preparing post data', [
						'content_title' => substr( $content['title'] ?? 'no_title', 0, 100 ),
						'final_html_length' => strlen( $final_html ),
						'category_ids' => $full_idea['category_ids'] ?? [],
						'category_count' => count( $full_idea['category_ids'] ?? [] )
					] );
				}
				
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Post data prepared, calling wp_insert_post\n", FILE_APPEND );
				
				// Convert title to proper case
				$proper_title = \AI_Blog_Generator\Utilities\Logger::to_proper_case( $content['title'] );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Title converted to proper case', [
						'original_title' => $content['title'],
						'proper_case_title' => $proper_title
					] );
				}
				
				// Prepare categories array - ensure all IDs are integers
				$post_categories = [];
				if ( ! empty( $full_idea['category_ids'] ) && is_array( $full_idea['category_ids'] ) ) {
					$post_categories = array_map( 'intval', $full_idea['category_ids'] );
				} elseif ( ! empty( $idea['category_id'] ) ) {
					// Fallback to single category if available
					$post_categories = [ intval( $idea['category_id'] ) ];
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Categories prepared for post', [
						'categories' => $post_categories,
						'category_names' => $full_idea['category_names'] ?? []
					] );
				}
				
				$post_data = [
					'post_title'   => $proper_title,
					'post_content' => $final_html,
					'post_status'  => 'draft',
					'post_type'    => 'post',
					'post_author'  => get_current_user_id(),
					'post_category' => $post_categories,
					'meta_input'   => [
						'_yoast_wpseo_metadesc' => $content['meta_description'],
						'_yoast_wpseo_focuskw'  => $content['focus_keyphrase'],
						'_ai_blog_idea_id'      => $idea_id,
						'_ai_blog_generation_cost' => $total_cost,
						
						'_ai_blog_generated_date' => current_time( 'mysql' ),
						'_ai_blog_has_charts' => ! empty( $content['charts'] ) ? '1' : '0',
						'_ai_blog_has_references' => ! empty( $content['references'] ) ? '1' : '0',
					],
				];
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Post data prepared, calling wp_insert_post' );
				}
				
				$post_id = wp_insert_post( $post_data, true );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'wp_insert_post completed', [
						'result' => is_numeric($post_id) ? "POST_ID=$post_id" : 'ERROR'
					] );
				}
				
				if ( is_wp_error( $post_id ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'wp_insert_post ERROR: ' . $post_id->get_error_message() );
					}
					throw new \Exception( $post_id->get_error_message() );
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'WordPress post created successfully, ID: ' . $post_id );
				}
				
				$this->update_generation_status( $idea_id, 'post', 'Publishing Post' );
				
				// Set featured image if available.
				if ( $featured_image_id ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Setting featured image: ' . $featured_image_id );
					}
					set_post_thumbnail( $post_id, $featured_image_id );
				}
				
				// Add tags if available.
				if ( ! empty( $content['tags'] ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Adding tags: ' . implode( ', ', $content['tags'] ) );
					}
					wp_set_post_tags( $post_id, $content['tags'] );
				}
				
				// Create blog record.
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Creating blog model record' );
				}
				
				// Start transaction only for critical database operations
				$this->log_info( 'starting_save_transaction', 'Starting transaction for critical saves', ['idea_id' => $idea_id]);
				
				$transaction_started = false;
				try {
					if ( $this->database_manager && method_exists( $this->database_manager, 'start_transaction' ) ) {
						$transaction_started = $this->database_manager->start_transaction();
						$this->log_info( 'save_transaction_started', 'Save transaction started', [
							'idea_id' => $idea_id,
							'result' => $transaction_started
						]);
					}
				} catch ( \Exception $e ) {
					$this->log_warning( 'save_transaction_failed', 'Failed to start save transaction', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage()
					]);
					// Continue without transaction
				}
				
				$blog_id = $this->blog_model->create([
					'idea_id' => $idea_id,
					'post_id' => $post_id,
					'title' => $proper_title,
					'status' => 'draft',
					'generation_cost' => $total_cost,
				]);
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Blog record created, ID: ' . $blog_id );
				}
				
				// Update idea status with timeout protection.
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Updating idea status to generated (with timeout protection)' );
				}
				
				try {
					// Set a shorter timeout for this specific operation
					$original_timeout = ini_get('max_execution_time');
					if ($original_timeout > 30) {
						set_time_limit(30); // Limit this operation to 30 seconds
					}
					
					$idea_update_start = microtime(true);
					$idea_update_result = $this->idea_model->update( $idea_id, [ 
						'status' => 'generated',
						'failed_status' => null, // Clear any previous failure status
						'generation_error' => null // Clear any previous error
					] );
					$idea_update_duration = microtime(true) - $idea_update_start;
					
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Idea status update completed', [
							'duration_seconds' => $idea_update_duration,
							'result' => $idea_update_result ? 'SUCCESS' : 'FAILED'
						] );
					}
					
					// Restore original timeout
					if ($original_timeout > 30) {
						set_time_limit($original_timeout);
					}
					
					if (!$idea_update_result) {
						if ( $this->generation_logger ) {
							$this->generation_logger->warning( 'Idea status update failed, but continuing with generation completion' );
						}
						$this->log_warning( 'idea_status_update_failed', 'Failed to update idea status but generation completed', [
							'idea_id' => $idea_id,
							'blog_id' => $blog_id,
							'post_id' => $post_id
						] );
					}
					
				} catch ( \Exception $e ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Exception during idea status update: ' . $e->getMessage() );
					}
					$this->log_error( 'idea_status_update_exception', 'Exception during idea status update', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage(),
						'blog_id' => $blog_id,
						'post_id' => $post_id
					] );
					// Continue with generation completion even if status update fails
				}
				
				// Commit transaction with timeout protection.
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Committing database transaction (with timeout protection)' );
				}
				
				try {
					// Only commit if we actually started a transaction
					if ( ! $transaction_started ) {
						$this->log_info( 'no_transaction_to_commit', 'No transaction to commit', ['idea_id' => $idea_id]);
					} else {
						// Check for transaction timeout before committing
						if ( $this->database_manager->is_transaction_timed_out() ) {
							$this->log_error( 'transaction_timeout_before_commit', 'Transaction timed out before commit', [
								'idea_id' => $idea_id,
								'blog_id' => $blog_id,
								'post_id' => $post_id
							] );
							// Force rollback and throw exception
							$this->database_manager->rollback();
							throw new \Exception( 'Transaction timeout detected before commit' );
						}
						
						$commit_start = microtime(true);
						$commit_result = $this->database_manager->commit();
						$commit_duration = microtime(true) - $commit_start;
						
						file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Transaction commit attempt completed in {$commit_duration}s, result: " . ($commit_result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND );
						
						if ( $this->generation_logger ) {
							$this->generation_logger->info( 'Transaction commit completed', [
								'duration_seconds' => $commit_duration,
								'result' => $commit_result ? 'SUCCESS' : 'FAILED'
							]);
						}
						
						if (!$commit_result) {
							if ( $this->generation_logger ) {
								$this->generation_logger->warning( 'Transaction commit failed, but generation is complete' );
							}
							$this->log_warning( 'transaction_commit_failed', 'Transaction commit failed but generation completed', [
								'idea_id' => $idea_id,
								'blog_id' => $blog_id,
								'post_id' => $post_id
							] );
						}
					}
					
				} catch ( \Exception $e ) {
					file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Exception during commit: " . $e->getMessage() . "\n", FILE_APPEND );
					
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Exception during transaction commit: ' . $e->getMessage() );
					}
					$this->log_error( 'transaction_commit_exception', 'Exception during transaction commit', [
						'idea_id' => $idea_id,
						'error' => $e->getMessage(),
						'blog_id' => $blog_id,
						'post_id' => $post_id
					] );
					// Continue with generation completion even if commit fails
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Starting final success logging' );
				}
				
				$this->log_info( 'blog_generation_complete', 'Successfully generated blog post', [
					'idea_id' => $idea_id,
					'post_id' => $post_id,
					'blog_id' => $blog_id,
					'total_cost' => $total_cost,
				] );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Updating generation status to complete' );
				}
				// Flush any pending status updates before final update
				$this->flush_pending_status_updates( $idea_id );
				$this->update_generation_status( $idea_id, 'complete', 'Complete!' );
				
				// Force clear any stuck generation locks
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Clearing generation lock' );
				}
				$this->clear_generation_lock( $idea_id );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Generation process completed successfully' );
				}
				
				// Clear generation lock using closure
				$clear_lock();
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Preparing final success response' );
				}
				
				$final_result = [
					'success' => true,
					'post_id' => $post_id,
					'blog_id' => $blog_id,
					'title' => $proper_title,
					'cost' => $total_cost,
					'edit_link' => get_edit_post_link( $post_id, 'raw' ),
					'view_link' => get_permalink( $post_id ),
				];
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'SUCCESS! Returning final response with post_id: ' . $post_id );
					$this->generation_logger->log_generation_complete( true, $final_result );
				}
				
				return $final_result;
				
			} catch ( \Exception $e ) {
				// Log to debug file immediately
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Generation failed with exception: " . $e->getMessage() . "\n", FILE_APPEND );
				
				// Clear generation lock using closure
				$clear_lock();
				
				if ( $this->generation_logger ) {
					$this->generation_logger->error( 'Generation failed with exception', [
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					$this->generation_logger->log_generation_complete( false, [
						'error' => $e->getMessage()
					] );
				}
				
				$this->log_error( 'blog_generation_failed', 'Blog generation failed', [
					'idea_id' => $idea_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'is_timeout_related' => strpos( $e->getMessage(), 'timeout' ) !== false
				] );
				
				// Enhanced rollback with timeout detection
				try {
					if ( $this->database_manager && $transaction_started ) {
						$rollback_start = microtime( true );
						$rollback_result = $this->database_manager->rollback();
						$rollback_duration = microtime( true ) - $rollback_start;
						
						file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Rollback attempt completed in {$rollback_duration}s, result: " . ($rollback_result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND );
						
						if ( ! $rollback_result ) {
							file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Rollback failed, attempting emergency cleanup\n", FILE_APPEND );
							$this->database_manager->emergency_transaction_cleanup();
						}
					} else if ( ! $transaction_started ) {
						$this->log_info( 'no_transaction_to_rollback', 'No transaction to rollback', ['idea_id' => $idea_id]);
					}
				} catch ( \Exception $rollback_exception ) {
					file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Rollback exception: " . $rollback_exception->getMessage() . "\n", FILE_APPEND );
					
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Transaction rollback also failed: ' . $rollback_exception->getMessage() );
					}
					
					// Try emergency cleanup as last resort
					try {
						$this->database_manager->emergency_transaction_cleanup();
					} catch ( \Exception $emergency_exception ) {
						// Log but don't fail - this is best effort cleanup
						file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Emergency cleanup exception: " . $emergency_exception->getMessage() . "\n", FILE_APPEND );
					}
				}
				
				// Update idea status back to approved with timeout protection
				try {
					if ( $this->idea_model ) {
						// Determine failure reason for failed_status
						$failed_status = 'Failed - Unknown';
						$error_msg = strtolower( $e->getMessage() );
						
						if ( strpos( $error_msg, 'timeout' ) !== false || strpos( $error_msg, 'timed out' ) !== false ) {
							$failed_status = 'Failed - Timeout';
						} elseif ( strpos( $error_msg, 'overloaded' ) !== false || strpos( $error_msg, 'capacity' ) !== false || strpos( $error_msg, 'rate limit' ) !== false ) {
							$failed_status = 'Failed - Overloaded';
						} elseif ( strpos( $error_msg, 'api' ) !== false || strpos( $error_msg, 'service' ) !== false ) {
							$failed_status = 'Failed - API Error';
						} elseif ( strpos( $error_msg, 'cancelled' ) !== false ) {
							$failed_status = 'Failed - Cancelled';
						} elseif ( strpos( $error_msg, 'memory' ) !== false ) {
							$failed_status = 'Failed - Memory Limit';
						} elseif ( strpos( $error_msg, 'network' ) !== false || strpos( $error_msg, 'connection' ) !== false ) {
							$failed_status = 'Failed - Network Error';
						}
						
						$status_update_start = microtime( true );
						$status_result = $this->idea_model->update( $idea_id, [ 
							'status' => 'approved',
							'failed_status' => $failed_status,
							'generation_status' => 'Generation failed: ' . substr( $e->getMessage(), 0, 100 ),
							'generation_error' => $e->getMessage(),
							'updated_at' => current_time( 'mysql' )
						] );
						$status_duration = microtime( true ) - $status_update_start;
						
						file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Status reset completed in {$status_duration}s, result: " . ($status_result ? 'SUCCESS' : 'FAILED') . ", failed_status: {$failed_status}\n", FILE_APPEND );
					}
				} catch ( \Exception $status_exception ) {
					file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Status reset exception: " . $status_exception->getMessage() . "\n", FILE_APPEND );
					
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Failed to reset idea status: ' . $status_exception->getMessage() );
					}
				}
				
				// Clear any stuck generation status
				try {
					// Flush any pending updates before failure
					$this->flush_pending_status_updates( $idea_id );
					$this->update_generation_status( $idea_id, 'failed', 'Generation failed: ' . substr( $e->getMessage(), 0, 100 ) );
				} catch ( \Exception $status_update_exception ) {
					// Ignore - this is cleanup
				}
				
				return [
					'success' => false,
					'message' => $e->getMessage(),
					'error_type' => strpos( $e->getMessage(), 'timeout' ) !== false ? 'timeout' : 'generation_error',
				];
			}
		} catch ( \Exception $e ) {
			if ( $this->generation_logger ) {
				$this->generation_logger->error( 'Generation failed with outer exception', [
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
				$this->generation_logger->log_generation_complete( false, [
					'error' => $e->getMessage()
				] );
			}
			
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Get generation status for an idea.
	 *
	 * @param int $idea_id Idea ID.
	 * @return array|false Status array or false if not found.
	 */
	public function get_generation_status( $idea_id ) {
		return get_transient( 'ai_blog_gen_status_' . $idea_id );
	}

	/**
	 * Clear generation status for an idea.
	 *
	 * @param int $idea_id Idea ID.
	 */
	public function clear_generation_status( $idea_id ) {
		delete_transient( 'ai_blog_gen_status_' . $idea_id );
	}

	/**
	 * Get progress percentage for each stage.
	 *
	 * @param string $stage Generation stage.
	 * @return int Progress percentage (0-100).
	 */
	private function get_stage_progress( $stage ) {
		$stages = [
			'starting' => 10,
			'contexts' => 20,
			'content' => 60,
			'images' => 85,
			'post' => 95,
			'complete' => 100
		];

		return $stages[ $stage ] ?? 0;
	}

	/**
	 * Clear generation lock for an idea.
	 *
	 * @param int $idea_id Idea ID.
	 */
	private function clear_generation_lock( $idea_id ) {
		delete_transient( "ai_blog_generation_lock_{$idea_id}" );
	}

	/**
	 * Compile active contexts for AI generation with enhanced filtering.
	 *
	 * @param string $usage Usage type (ideas, content, images).
	 * @param array  $options Additional compilation options.
	 * @return array Enhanced compiled contexts by type.
	 */
	private function compile_contexts_enhanced( $usage = 'content', $options = [] ) {
		try {
			$this->log_info( 'compile_contexts_enhanced_start', 'Starting enhanced context compilation', [
				'usage' => $usage,
				'options' => $options
			] );

			$defaults = [
				'max_contexts_per_type' => 5,
				'priority_threshold' => 0,
				'type_filters' => [],
				'include_metadata' => false,
				'persona_id' => null,
			];

			$options = array_merge( $defaults, $options );
			
			$this->log_info( 'context_options_prepared', 'Context options prepared', [
				'usage' => $usage,
				'merged_options' => $options
			] );
			
			// Check if we have a persona_id to get persona-specific contexts
			$persona_id = $options['persona_id'];
			
			if ( $persona_id ) {
				// Get contexts for specific persona (includes always-include contexts)
				$this->log_info( 'calling_context_model_persona', 'Getting contexts for persona', [
					'usage' => $usage,
					'persona_id' => $persona_id,
					'context_model_class' => get_class( $this->context_model )
				] );
				
				try {
					$contexts_array = $this->context_model->get_contexts_for_persona( $persona_id, $usage );
					
					// Convert array format to grouped format expected by the rest of the method
					$contexts = [];
					foreach ( $contexts_array as $context ) {
						$type = $context['type'];
						if ( ! isset( $contexts[ $type ] ) ) {
							$contexts[ $type ] = [
								'contexts' => [],
								'content' => ''
							];
						}
						$contexts[ $type ]['contexts'][] = $context;
						// Append content with separator if multiple contexts of same type
						if ( ! empty( $contexts[ $type ]['content'] ) ) {
							$contexts[ $type ]['content'] .= "\n\n";
						}
						$contexts[ $type ]['content'] .= $context['content'];
					}
					
					// Add metadata
					foreach ( $contexts as $type => &$data ) {
						$data['context_count'] = count( $data['contexts'] );
					}
					
					$this->log_info( 'context_model_persona_success', 'Persona contexts retrieved successfully', [
						'usage' => $usage,
						'persona_id' => $persona_id,
						'contexts_returned' => count( $contexts_array ),
						'contexts_types' => array_keys( $contexts )
					] );
					
				} catch ( \Exception $e ) {
					$this->log_error( 'context_model_persona_failed', 'Context model get_contexts_for_persona failed', [
						'usage' => $usage,
						'persona_id' => $persona_id,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					throw new \Exception( 'Context model failed: ' . $e->getMessage() );
				}
			} else {
				// Get contexts based on always-include flags only
				$this->log_info( 'calling_context_model_always', 'Getting always-include contexts', [
					'usage' => $usage,
					'context_model_class' => get_class( $this->context_model )
				] );
				
				try {
					// Get contexts that should always be included
					if ( $usage === 'content' ) {
						$contexts_array = $this->context_model->get_always_include_content();
					} else if ( $usage === 'images' ) {
						$contexts_array = $this->context_model->get_always_include_images();
					} else {
						// Fallback to original method for other usages
						$contexts = $this->context_model->get_for_prompt( $usage, $options );
					}
					
					// Convert array format to grouped format if we got an array
					if ( isset( $contexts_array ) ) {
						$contexts = [];
						foreach ( $contexts_array as $context ) {
							$type = $context['type'];
							if ( ! isset( $contexts[ $type ] ) ) {
								$contexts[ $type ] = [
									'contexts' => [],
									'content' => ''
								];
							}
							$contexts[ $type ]['contexts'][] = $context;
							// Append content with separator if multiple contexts of same type
							if ( ! empty( $contexts[ $type ]['content'] ) ) {
								$contexts[ $type ]['content'] .= "\n\n";
							}
							$contexts[ $type ]['content'] .= $context['content'];
						}
						
						// Add metadata
						foreach ( $contexts as $type => &$data ) {
							$data['context_count'] = count( $data['contexts'] );
						}
					}
					
					$this->log_info( 'context_model_always_success', 'Always-include contexts retrieved successfully', [
						'usage' => $usage,
						'contexts_returned' => isset( $contexts_array ) ? count( $contexts_array ) : 'using_original',
						'contexts_types' => array_keys( $contexts )
					] );
					
				} catch ( \Exception $e ) {
					$this->log_error( 'context_model_always_failed', 'Context model get_always_include failed', [
						'usage' => $usage,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					] );
					throw new \Exception( 'Context model failed: ' . $e->getMessage() );
				}
			}
			
			$this->log_info( 'contexts_compiled_enhanced', 'Enhanced contexts compilation completed', [
				'usage' => $usage,
				'types_included' => array_keys( $contexts ),
				'total_context_types' => count( $contexts ),
				'options' => $options,
			] );
			
			// Convert to simple string format for backward compatibility with prompts
			$this->log_info( 'converting_contexts', 'Converting contexts to simple format', [
				'usage' => $usage,
				'contexts_to_convert' => count( $contexts )
			] );
			
			$simple_contexts = [];
			try {
				foreach ( $contexts as $type => $data ) {
					if ( is_array( $data ) && isset( $data['content'] ) ) {
						$simple_contexts[ $type ] = $data['content'];
					} else {
						$this->log_warning( 'invalid_context_format', 'Context data has unexpected format', [
							'type' => $type,
							'data_type' => gettype( $data ),
							'has_content_key' => is_array( $data ) ? isset( $data['content'] ) : false
						] );
						// Try to use data as-is if it's a string
						if ( is_string( $data ) ) {
							$simple_contexts[ $type ] = $data;
						}
					}
				}
				
				$this->log_info( 'contexts_converted', 'Contexts converted successfully', [
					'usage' => $usage,
					'original_count' => count( $contexts ),
					'converted_count' => count( $simple_contexts )
				] );
			} catch ( \Exception $e ) {
				$this->log_error( 'context_conversion_failed', 'Failed to convert contexts', [
					'usage' => $usage,
					'error' => $e->getMessage(),
					'contexts_structure' => array_map( 'gettype', $contexts )
				] );
				throw new \Exception( 'Context conversion failed: ' . $e->getMessage() );
			}
			
			return $simple_contexts;
			
		} catch ( \Exception $e ) {
			$this->log_error( 'compile_contexts_enhanced_failed', 'Enhanced context compilation failed', [
				'usage' => $usage,
				'options' => $options,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}

	/**
	 * Compile active contexts for AI generation (backward compatibility wrapper).
	 *
	 * @return array Compiled contexts by type.
	 */
	private function compile_contexts() {
		// Use enhanced method with content defaults
		return $this->compile_contexts_enhanced( 'content' );
	}

	/**
	 * Compile contexts specifically for idea generation.
	 *
	 * @param array $options Additional compilation options.
	 * @return array Compiled contexts optimized for idea generation.
	 */
	public function compile_contexts_for_ideas( $options = [] ) {
		$idea_options = array_merge( [
			'type_filters' => [ 'general', 'products', 'seo', 'keywords' ], // Exclude image contexts for ideas
			'priority_threshold' => 25, // Higher threshold for ideas
		], $options );
		
		return $this->compile_contexts_enhanced( 'ideas', $idea_options );
	}

	/**
	 * Compile contexts specifically for image generation.
	 *
	 * @param array $options Additional compilation options.
	 * @return array Compiled contexts optimized for image generation.
	 */
	public function compile_contexts_for_images( $options = [] ) {
		$image_options = array_merge( [
			'type_filters' => [ 'general', 'products', 'image' ], // Focus on relevant contexts for images
			'priority_threshold' => 0,
		], $options );
		
		return $this->compile_contexts_enhanced( 'images', $image_options );
	}

	/**
	 * Extract SEO keywords from contexts.
	 *
	 * @param array $contexts Compiled contexts.
	 * @return array SEO keywords.
	 */
	private function extract_seo_keywords( $contexts ) {
		$keywords = [];
		
		// Extract from keywords context.
		if ( ! empty( $contexts['keywords'] ) ) {
			// Split by newlines and commas.
			$lines = preg_split( '/[\n,]+/', $contexts['keywords'] );
			foreach ( $lines as $line ) {
				$keyword = trim( $line );
				if ( ! empty( $keyword ) ) {
					$keywords[] = $keyword;
				}
			}
		}
		
		// Limit to reasonable number.
		$keywords = array_slice( array_unique( $keywords ), 0, 10 );
		
		return $keywords;
	}

	/**
	 * Validate idea data structure.
	 *
	 * @param array $idea_data Idea data to validate.
	 * @return bool True if valid.
	 */
	private function validate_idea_data( $idea_data ) {
		$required_fields = [ 'title', 'description', 'category' ];
		
		foreach ( $required_fields as $field ) {
			if ( empty( $idea_data[ $field ] ) ) {
				return false;
			}
		}
		
		// Validate title length.
		if ( strlen( $idea_data['title'] ) > 200 ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Validate generated content structure.
	 *
	 * @param array $content Generated content to validate.
	 * @return bool True if valid.
	 */
	private function validate_generated_content( $content ) {
		if ( $this->generation_logger ) {
			$this->generation_logger->debug( 'Starting detailed content validation', [
				'content_structure' => array_keys( $content )
			] );
		}
		
		// Check required fields.
		$required_fields = [ 'html', 'title', 'meta_description', 'focus_keyphrase' ];
		
		foreach ( $required_fields as $field ) {
			$field_exists = isset( $content[ $field ] );
			$field_empty = empty( $content[ $field ] );
			$field_value_preview = $field_exists ? substr( $content[ $field ], 0, 100 ) : 'NOT_SET';
			
			if ( $this->generation_logger ) {
				$this->generation_logger->debug( "Field '$field' validation", [
					'exists' => $field_exists ? 'YES' : 'NO',
					'empty' => $field_empty ? 'YES' : 'NO',
					'preview' => $field_value_preview
				] );
			}
			
			if ( empty( $content[ $field ] ) ) {
				if ( $this->generation_logger ) {
					$this->generation_logger->error( 'VALIDATION FAILED - Missing required field: ' . $field );
				}
				
				$this->log_error( 'content_validation_failed', 'Missing required field', [
					'field' => $field,
					'field_exists' => $field_exists,
					'field_empty' => $field_empty,
					'all_fields' => array_keys( $content )
				] );
				return false;
			}
		}
		
		if ( $this->generation_logger ) {
			$this->generation_logger->debug( 'All required fields present, checking HTML content length' );
		}
		
		// Validate HTML content length.
		$html_content = $content['html'];
		$html_length = strlen( $html_content );
		$stripped_content = strip_tags( $html_content );
		$stripped_length = strlen( $stripped_content );
		$word_count = str_word_count( $stripped_content );
		
		if ( $this->generation_logger ) {
			$this->generation_logger->debug( 'HTML validation metrics', [
				'original_length' => $html_length,
				'stripped_length' => $stripped_length,
				'word_count' => $word_count,
				'html_preview' => substr( $html_content, 0, 200 )
			] );
		}
		
		if ( $word_count < 200 ) {
			if ( $this->generation_logger ) {
				$this->generation_logger->error( "VALIDATION FAILED - Content too short (need 200+ words, got $word_count)" );
			}
			
			$this->log_error( 'content_validation_failed', 'Content too short', [
				'word_count' => $word_count,
				'html_length' => $html_length,
				'stripped_length' => $stripped_length,
				'min_required' => 200
			] );
			return false;
		}
		
		// Validate meta description length.
		$meta_desc_length = strlen( $content['meta_description'] );
		if ( $this->generation_logger ) {
			$this->generation_logger->debug( 'Meta description length: ' . $meta_desc_length );
		}
		
		if ( $meta_desc_length > 140 ) {
			if ( $this->generation_logger ) {
				$this->generation_logger->warning( "Meta description too long ($meta_desc_length > 140)" );
			}
			
			$this->log_warning( 'content_validation_warning', 'Meta description too long', [
				'length' => $meta_desc_length,
			] );
		}
		
		if ( $this->generation_logger ) {
			$this->generation_logger->info( 'VALIDATION PASSED - All checks successful' );
		}
		
		return true;
	}

	/**
	 * Convert H1 headings to H2 headings as a safety measure.
	 * WordPress post titles are already H1, so content should start with H2.
	 *
	 * @param string $html The HTML content to process.
	 * @return string The processed HTML with H1 tags converted to H2.
	 */
	private function convert_h1_to_h2( $html ) {
		// Convert opening H1 tags to H2 tags
		$html = preg_replace( '/<h1(\s[^>]*)?>/i', '<h2$1>', $html );
		
		// Convert closing H1 tags to H2 tags
		$html = preg_replace( '/<\/h1>/i', '</h2>', $html );
		
		$this->log_info( 'h1_to_h2_conversion', 'Converted H1 headings to H2 headings', [
			'original_h1_count' => substr_count( strtolower( $html ), '<h1' ),
			'final_html_length' => strlen( $html )
		] );
		
		return $html;
	}

	/**
	 * Clean fusion_code content by removing HTML markup and wrapping in script tags.
	 * 
	 * This fixes the issue where Avada adds HTML markup (p tags, br tags) to JavaScript
	 * code within fusion_code shortcodes, breaking the JavaScript functionality.
	 *
	 * @param string $html The HTML content to process.
	 * @return string The processed HTML with cleaned fusion_code content.
	 */
	private function clean_fusion_code_content( $html ) {
		if ( $this->generation_logger ) {
			$this->generation_logger->debug( 'Starting fusion_code cleanup' );
		}
		
		// Debug log file path
		$debug_log = __DIR__ . '/../debug-transaction.log';
		
		// Find all fusion_code blocks
		$pattern = '/\[fusion_code\](.*?)\[\/fusion_code\]/s';
		
		// Count fusion_code blocks found
		$fusion_code_count = preg_match_all( $pattern, $html, $matches_count );
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: Found {$fusion_code_count} fusion_code blocks\n", FILE_APPEND );
		
		$cleaned_html = preg_replace_callback( $pattern, function( $matches ) use ( $debug_log ) {
			$original_content = $matches[1];
			$code_content = $matches[1];
			
			// Log the ORIGINAL content before any cleaning
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: === BEFORE CLEANING ===\n", FILE_APPEND );
			file_put_contents( $debug_log, "Original fusion_code content:\n", FILE_APPEND );
			file_put_contents( $debug_log, $original_content . "\n", FILE_APPEND );
			file_put_contents( $debug_log, "=== END BEFORE ===\n\n", FILE_APPEND );
			
			if ( $this->generation_logger ) {
				$this->generation_logger->debug( 'Found fusion_code block', [
					'original_length' => strlen( $code_content ),
					'sample' => substr( $code_content, 0, 100 ) . '...'
				] );
			}
			
			// First check if the content already has script tags
			$has_script_tags = preg_match( '/<script[^>]*>.*<\/script>/is', $code_content );
			
			if ( $has_script_tags ) {
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: Content already has <script> tags, cleaning only HTML\n", FILE_APPEND );
				
				// If it already has script tags, just clean any HTML that might have been added around it
				// Remove <p> and </p> tags
				$code_content = preg_replace( '/<\/?p[^>]*>/i', '', $code_content );
				
				// Remove <br> and <br /> tags
				$code_content = preg_replace( '/<br\s*\/?>/i', "\n", $code_content );
				
				// Trim any extra whitespace
				$code_content = trim( $code_content );
			} else {
				// No script tags, so clean and add them if it's JavaScript
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: No script tags found, processing content\n", FILE_APPEND );
				
				// Remove all HTML tags that Avada might have added
				// Remove <p> and </p> tags
				$code_content = preg_replace( '/<\/?p[^>]*>/i', '', $code_content );
				
				// Remove <br> and <br /> tags
				$code_content = preg_replace( '/<br\s*\/?>/i', "\n", $code_content );
				
				// Remove any other HTML tags that might have been added
				// But preserve the actual JavaScript/code content
				$code_content = strip_tags( $code_content );
				
				// Trim any extra whitespace
				$code_content = trim( $code_content );
				
				// Check if this is JavaScript code (contains function, var, const, etc.)
				$js_indicators = ['function', 'var ', 'const ', 'let ', 'document.', 'window.', 'new ', 'ApexCharts'];
				$is_javascript = false;
				
				foreach ( $js_indicators as $indicator ) {
					if ( stripos( $code_content, $indicator ) !== false ) {
						$is_javascript = true;
						break;
					}
				}
				
				// If it's JavaScript, wrap in script tags
				if ( $is_javascript ) {
					$code_content = '<script>' . "\n" . $code_content . "\n" . '</script>';
					
					file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: Detected JavaScript content, wrapping in script tags\n", FILE_APPEND );
					
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Wrapped JavaScript code in script tags' );
					}
				}
			}
			
			// Log the CLEANED content after processing
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: === AFTER CLEANING ===\n", FILE_APPEND );
			file_put_contents( $debug_log, "Cleaned fusion_code content:\n", FILE_APPEND );
			file_put_contents( $debug_log, $code_content . "\n", FILE_APPEND );
			file_put_contents( $debug_log, "=== END AFTER ===\n\n", FILE_APPEND );
			
			// Return the cleaned fusion_code block
			return '[fusion_code]' . $code_content . '[/fusion_code]';
		}, $html );
		
		if ( $this->generation_logger ) {
			$this->generation_logger->debug( 'Fusion_code cleanup completed' );
		}
		
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FUSION_CODE_CLEANUP: Cleanup completed\n\n", FILE_APPEND );
		
		return $cleaned_html;
	}

	/**
	 * Get or create WordPress category.
	 *
	 * @param string $category_name Category name.
	 * @return int Category ID.
	 */
	private function get_or_create_category( $category_name ) {
		// Clean category name.
		$category_name = trim( $category_name );
		
		if ( empty( $category_name ) ) {
			return get_option( 'default_category' );
		}
		
		// Check if category exists.
		$category = get_term_by( 'name', $category_name, 'category' );
		
		if ( $category ) {
			return $category->term_id;
		}
		
		// Create new category.
		$result = wp_insert_term( $category_name, 'category' );
		
		if ( is_wp_error( $result ) ) {
			$this->log_warning( 'category_creation_failed', 'Failed to create category', [
				'category' => $category_name,
				'error' => $result->get_error_message(),
			] );
			return get_option( 'default_category' );
		}
		
		return $result['term_id'];
	}

	/**
	 * Add seed images to image requirements.
	 *
	 * @param array &$image_requirements Image requirements array (passed by reference).
	 * @param array $idea               Idea data.
	 * @param array $image_contexts     Image-specific contexts (optional).
	 */
	private function add_seed_images_to_requirements( &$image_requirements, $idea, $image_contexts = [] ) {
		try {
			if ( $this->generation_logger ) {
				$this->generation_logger->debug( 'Getting seed images from context model' );
			}
		
			$seed_images = [];
			
			// Try to get seed images from contexts that have seed_image_id
			// The Context_Model doesn't have a get_seed_images method, so we'll query directly
			if ( $this->generation_logger ) {
				$this->generation_logger->debug( 'Attempting to get seed images from database' );
			}
			
			// Also try to get seed images from the general seed images table
			if ( empty( $seed_images ) ) {
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'No context seed images found, trying general seed images' );
				}
				
				global $wpdb;
				$seed_images_table = $wpdb->prefix . 'ai_blog_seed_images';
				
				// Check if the general seed images table exists
				$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$seed_images_table'" );
				if ( $table_exists ) {
					$general_seed_images = $wpdb->get_results( "SELECT * FROM $seed_images_table ORDER BY created_at DESC LIMIT 5" );
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Found ' . count( $general_seed_images ) . ' general seed images' );
					}
					
					// Convert to expected format
					foreach ( $general_seed_images as $seed ) {
						$seed_images[] = [
							'id' => $seed->id,
							'url' => $seed->image_url,
							'keywords' => $seed->product_name, // Use product name as keywords
							'product_name' => $seed->product_name,
							'context' => $seed->context, // Include the context field
						];
					}
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Total seed images after adding general: ' . count( $seed_images ) );
					}
				} else {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'General seed images table does not exist' );
					}
				}
			}
			
			if ( empty( $seed_images ) ) {
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'No seed images available, skipping' );
				}
				return;
			}
			
			if ( $this->generation_logger ) {
				$this->generation_logger->info( 'Processing ' . count( $image_requirements ) . ' image requirements with ' . count( $seed_images ) . ' seed images' );
			}
			
			// Use DIFFERENT seed images for each requirement (cycle through available seed images)
			$num_requirements = count( $image_requirements );
			$num_seed_images = count( $seed_images );
			
			if ( $this->generation_logger ) {
				$this->generation_logger->debug( 'Using different seed images for each requirement' );
			}
			
			// Apply different seed images to each image requirement
			foreach ( $image_requirements as $index => &$requirement ) {
				// Cycle through seed images - use modulo to wrap around if we have more requirements than seed images
				$seed_index = $index % $num_seed_images;
				$selected_seed = $seed_images[$seed_index];
				
				$seed_url = isset( $selected_seed['url'] ) ? $selected_seed['url'] : ( isset( $selected_seed['image_url'] ) ? $selected_seed['image_url'] : 'unknown' );
				$product_name = isset( $selected_seed['product_name'] ) ? $selected_seed['product_name'] : ( isset( $selected_seed['keywords'] ) ? $selected_seed['keywords'] : 'unknown' );
				$seed_context = isset( $selected_seed['context'] ) ? $selected_seed['context'] : '';
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Image ' . ($index + 1) . ' using seed image ' . ($seed_index + 1) . ': ' . $seed_url . ' (product: ' . $product_name . ')' );
				}
				
				// Update the prompt to specifically include the seed image product and context
				$prompt_prefix = "Include this exact product if it makes sense for this image. Do not change the look of the product at all, just include it in the context of the image.";
				
				// Add seed image context if available
				if ( ! empty( $seed_context ) ) {
					$prompt_prefix .= " " . $seed_context;
				}
				
				$requirement['prompt'] = $prompt_prefix . " " . $requirement['prompt'];
				$requirement['seed_image'] = $seed_url;
				$requirement['preserve_product'] = true; // Flag for OpenAI service
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Added seed image to requirement with token: ' . ($requirement['token'] ?? 'unknown') );
				}
				
				$this->log_info( 'seed_image_applied', 'Applied different seed image to requirement', [
					'requirement_index' => $index,
					'seed_index' => $seed_index,
					'seed_id' => isset( $selected_seed['id'] ) ? $selected_seed['id'] : 'unknown',
					'seed_url' => $seed_url,
					'product_name' => $product_name,
					'has_context' => ! empty( $seed_context ),
					'token' => $requirement['token'] ?? 'unknown',
				] );
			}
		
			if ( $this->generation_logger ) {
				$this->generation_logger->info( 'Seed images added to all requirements' );
			}
		
		} catch ( \Exception $e ) {
			if ( $this->generation_logger ) {
				$this->generation_logger->error( 'Error in add_seed_images_to_requirements: ' . $e->getMessage() );
			}
			
			$this->log_error( 'seed_images_error', 'Error adding seed images to requirements', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'idea_id' => isset( $idea['id'] ) ? $idea['id'] : 'unknown',
				'image_requirements_count' => count( $image_requirements )
			] );
			// Continue without seed images - don't let this stop the generation
		}
	}

	/**
	 * Create image HTML with proper attributes.
	 *
	 * @param array $image Image data from generation.
	 * @return string Image HTML.
	 */
	private function create_image_html( $image ) {
		// Determine image size class based on context
		$size_class = 'img-fluid';
		$wrapper_class = 'my-4';
		
		// If the image is meant to be full-width
		if ( strpos( strtolower( $image['prompt'] ?? '' ), 'hero' ) !== false || 
		     strpos( strtolower( $image['prompt'] ?? '' ), 'banner' ) !== false ) {
			$wrapper_class = 'my-5';
		}
		
		$img_html = sprintf(
			'<figure class="figure %s ai-generated-image">
				<img src="%s" alt="%s" class="figure-img %s rounded" loading="lazy" />
			</figure>',
			esc_attr( $wrapper_class ),
			esc_url( $image['url'] ),
			esc_attr( $image['alt_text'] ),
			esc_attr( $size_class )
		);
		
		return $img_html;
	}

	/**
	 * Check if services are properly configured.
	 *
	 * @return array Status of each service.
	 */
	public function check_services_status() {
		$status = [
			'anthropic' => false,
			'openai' => false,
			'contexts' => false,
		];
		
		// Check Anthropic.
		$anthropic_test = $this->anthropic_service->test_connection();
		$status['anthropic'] = $anthropic_test['success'];
		
		// Check OpenAI.
		$openai_test = $this->openai_service->test_connection();
		$status['openai'] = $openai_test['success'];
		
		// Check contexts.
		$contexts = $this->compile_contexts();
		$status['contexts'] = ! empty( $contexts );
		
		return $status;
	}

	/**
	 * Update generation status for real-time tracking.
	 *
	 * @param int    $idea_id  Idea ID being generated.
	 * @param string $stage    Current generation stage.
	 * @param string $message  Status message.
	 */
	private function update_generation_status( $idea_id, $stage, $message ) {
		$current_time = time();
		
		// Determine if this is a stage change
		$is_stage_change = ( $this->current_stage !== '' && $stage !== $this->current_stage );
		$is_critical_stage = in_array( $stage, [ 'complete', 'failed', 'cancelled' ], true );
		
		// Determine if this is a minor progress update
		$is_minor_progress = ( 
			strpos( $message, 'Generating image' ) !== false ||
			strpos( $message, 'Processing' ) !== false ||
			strpos( $message, 'Waiting' ) !== false
		);
		
		// Skip minor progress updates entirely for database writes
		if ( $is_minor_progress && ! $is_stage_change && ! $is_critical_stage ) {
			// Only update memory (transient) for real-time display
			$status_data = [
				'idea_id' => $idea_id,
				'stage' => $stage,
				'message' => $message,
				'progress' => $this->get_stage_progress( $stage ),
				'updated_at' => current_time( 'mysql' ),
				'updated_timestamp' => $current_time
			];
			set_transient( "ai_blog_generation_status_{$idea_id}", $status_data, HOUR_IN_SECONDS );
			
			// Store as pending update for batch processing
			$this->pending_status_update = [
				'idea_id' => $idea_id,
				'message' => $message,
				'time' => $current_time
			];
			
			$this->log_debug( 'status_update_memory_only', 'Status update stored in memory only', [
				'idea_id' => $idea_id,
				'stage' => $stage,
				'message' => substr( $message, 0, 50 ) . '...',
				'is_minor_progress' => true
			] );
			
			return;
		}
		
		// Check if we should batch this update (non-critical updates)
		$time_since_last_db_update = $current_time - $this->last_db_update_time;
		$should_batch = ! $is_critical_stage && ! $is_stage_change && $time_since_last_db_update < $this->batch_update_interval;
		
		if ( $should_batch ) {
			// Store for batch update
			$this->pending_status_update = [
				'idea_id' => $idea_id,
				'message' => $message,
				'time' => $current_time
			];
			
			// Update memory only
			$status_data = [
				'idea_id' => $idea_id,
				'stage' => $stage,
				'message' => $message,
				'progress' => $this->get_stage_progress( $stage ),
				'updated_at' => current_time( 'mysql' ),
				'updated_timestamp' => $current_time
			];
			set_transient( "ai_blog_generation_status_{$idea_id}", $status_data, HOUR_IN_SECONDS );
			
			$this->log_debug( 'status_update_batched', 'Status update batched for later', [
				'idea_id' => $idea_id,
				'stage' => $stage,
				'time_until_batch' => $this->batch_update_interval - $time_since_last_db_update
			] );
			
			return;
		}
		
		// Update current stage
		if ( $is_stage_change ) {
			$this->current_stage = $stage;
		}
		
		// Update last database update time
		$this->last_db_update_time = $current_time;
		
		// Update transient for quick access
		$status_data = [
			'idea_id' => $idea_id,
			'stage' => $stage,
			'message' => $message,
			'progress' => $this->get_stage_progress( $stage ),
			'updated_at' => current_time( 'mysql' ),
			'updated_timestamp' => $current_time
		];
		
		set_transient( "ai_blog_generation_status_{$idea_id}", $status_data, HOUR_IN_SECONDS );
		
		// Only update database for stage changes or critical updates
		if ( $is_stage_change || $is_critical_stage || ! $should_batch ) {
			try {
				// Log the update attempt
				$this->log_info( 'generation_status_db_update', 'Updating generation status in database', [
					'idea_id' => $idea_id,
					'stage' => $stage,
					'message' => substr( $message, 0, 100 ) . '...',
					'is_stage_change' => $is_stage_change,
					'is_critical' => $is_critical_stage,
					'is_batch_update' => ( $this->pending_status_update !== null )
				] );
				
				// Use the most recent message (either current or pending)
				$db_message = $this->pending_status_update ? $this->pending_status_update['message'] : $message;
				
				// Use the Blog_Ideas_Model_V2 for consistent database operations
				$update_result = $this->idea_model->update_idea( $idea_id, [
					'generation_status' => $db_message,
					'updated_at' => current_time( 'mysql' )
				] );
				
				// Clear pending update
				$this->pending_status_update = null;
				
				if ( $update_result ) {
					$this->log_debug( 'generation_status_db_updated', 'Database status update successful', [
						'idea_id' => $idea_id,
						'stage' => $stage,
						'is_stage_change' => $is_stage_change
					] );
				} else {
					$this->log_error( 'generation_status_db_update_failed', 'Database status update failed', [
						'idea_id' => $idea_id,
						'stage' => $stage,
						'message' => $db_message
					] );
				}
				
				// If we have a generation logger, update it too
				if ( $this->generation_logger ) {
					$this->generation_logger->log_phase( $stage, $status_data['progress'], [
						'message' => $message,
						'status_updated' => $update_result,
						'is_db_update' => true
					] );
				}
				
			} catch ( \Exception $e ) {
				$this->log_error( 'status_update_exception', 'Exception during status update', [
					'idea_id' => $idea_id,
					'stage' => $stage,
					'error' => $e->getMessage()
				] );
			}
		}
	}

	/**
	 * Force flush any pending status updates to database
	 *
	 * @param int $idea_id Idea ID
	 */
	private function flush_pending_status_updates( $idea_id ) {
		if ( $this->pending_status_update && $this->pending_status_update['idea_id'] === $idea_id ) {
			try {
				$this->log_debug( 'flushing_pending_status', 'Flushing pending status update', [
					'idea_id' => $idea_id,
					'message' => $this->pending_status_update['message']
				] );
				
				$update_result = $this->idea_model->update_idea( $idea_id, [
					'generation_status' => $this->pending_status_update['message'],
					'updated_at' => current_time( 'mysql' )
				] );
				
				$this->pending_status_update = null;
				
			} catch ( \Exception $e ) {
				$this->log_error( 'flush_status_failed', 'Failed to flush pending status update', [
					'idea_id' => $idea_id,
					'error' => $e->getMessage()
				] );
			}
		}
	}

	/**
	 * Check if generation should be cancelled
	 *
	 * @param int $idea_id The idea ID being generated
	 * @return bool True if generation should be cancelled
	 */
	private function should_cancel_generation( $idea_id = null ) {
		// Check global cancel flag
		$global_cancel = get_transient( 'ai_blog_global_cancel_flag' );
		if ( $global_cancel ) {
			$this->log_warning( 'global_cancel_detected', 'Global cancellation flag detected', [
				'idea_id' => $idea_id,
				'cancel_time' => $global_cancel,
				'current_time' => time()
			] );
			
			// Log to debug file
			$debug_log = __DIR__ . '/../debug-transaction.log';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Global cancel detected for idea {$idea_id}\n", FILE_APPEND );
			
			return true;
		}
		
		// Check individual idea cancel flag (if provided)
		if ( $idea_id ) {
			$idea_cancel = get_transient( "ai_blog_cancel_generation_{$idea_id}" );
			if ( $idea_cancel ) {
				$this->log_warning( 'individual_cancel_detected', 'Individual cancellation flag detected', [
					'idea_id' => $idea_id,
					'cancel_time' => $idea_cancel
				] );
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Handle generation cancellation
	 *
	 * @param int $idea_id The idea ID
	 * @param string $stage The stage where cancellation occurred
	 * @throws \Exception
	 */
	private function handle_cancellation( $idea_id, $stage ) {
		$this->log_warning( 'generation_cancelled', 'Generation cancelled', [
			'idea_id' => $idea_id,
			'stage' => $stage
		] );
		
		// Flush any pending updates before cancellation
		$this->flush_pending_status_updates( $idea_id );
		
		// Update status
		$this->update_generation_status( $idea_id, 'cancelled', 'Generation cancelled by user' );
		
		// Clear locks
		$this->clear_generation_lock( $idea_id );
		
		// Log to debug file
		$debug_log = __DIR__ . '/../debug-transaction.log';
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Generation cancelled at stage '{$stage}' for idea {$idea_id}\n", FILE_APPEND );
		
		throw new \Exception( 'Generation cancelled by user' );
	}

	/**
	 * Parse content response from Anthropic API.
	 *
	 * @param string $content Raw content from API.
	 * @return array Parsed content data.
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
		if ( preg_match( '/HTML_CONTENT:\s*(.+?)(?=\nIMAGES:|CHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			$result['html'] = trim( $matches[1] );
		} elseif ( preg_match( '/AVADA_CONTENT:\s*(.+?)(?=\nIMAGES:|CHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			// Check for AVADA_CONTENT section for Avada layouts
			$result['html'] = trim( $matches[1] );
		} elseif ( preg_match( '/HTML:\s*(.+?)(?=\nIMAGES:|CHARTS:|REFERENCES:|$)/s', $content, $matches ) ) {
			// Backward compatibility: check for HTML section
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
					'alt_text' => $this->generate_alt_text_from_prompt( $description, $result['focus_keyphrase'] ),
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
					'alt_text' => $this->generate_alt_text_from_prompt( $description, $result['focus_keyphrase'] ),
				];
			}
		}

		// Extract charts scripts
		if ( preg_match( '/CHARTS:\s*(.+?)(?=\nREFERENCES:|$)/s', $content, $matches ) ) {
			$charts_content = trim( $matches[1] );
			
			// Check if this is placeholder text (contains instructions or brackets)
			if ( preg_match( '/\[(OPTIONAL|If you|leave.*empty|no.*text)/i', $charts_content ) || 
			     preg_match( '/^\[.*\]$/s', $charts_content ) ) {
				// This is instruction text, not actual JavaScript - ignore it
				$result['charts'] = '';
			} elseif ( ! empty( $charts_content ) && stripos( $charts_content, '<script' ) === false ) {
				// Wrap charts in a script tag if not already wrapped
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
				$result['references'] = '[fusion_builder_container hundred_percent="no" equal_height_columns="no" hide_on_mobile="no" background_color="" background_image="" background_position="left top" background_repeat="no-repeat" border_size="0" border_color="" border_style="solid" padding_top="40px" padding_right="" padding_bottom="40px" padding_left="" margin_top="" margin_bottom="" animation_type="" animation_direction="left" animation_speed="0.1" animation_offset="" last="no" class="" id="" alpha_background_color=""]' . "\n";
				$result['references'] .= '[fusion_builder_row]' . "\n";
				$result['references'] .= '[fusion_builder_column type="1_1" spacing="yes" center_content="no" hover_type="none" link="" min_height="" hide_on_mobile="no" background_color="" background_image="" background_position="left top" background_repeat="no-repeat" border_size="0" border_color="" border_style="solid" padding_top="" padding_right="" padding_bottom="" padding_left="" margin_top="" margin_bottom="" animation_type="" animation_direction="left" animation_speed="0.1" animation_offset="" last="no" class="" id="" alpha_background_color=""]' . "\n";
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
					// Create fallback alt text with focus keyphrase if available
					$fallback_alt = 'Image ' . $image_num . ' for ' . ( $result['title'] ?: 'blog post' );
					if ( ! empty( $result['focus_keyphrase'] ) ) {
						$fallback_alt = $result['focus_keyphrase'] . ' - ' . $fallback_alt;
					}
					
					$result['images'][] = [
						'token' => '{{image' . $image_num . '}}',
						'prompt' => 'Professional image related to ' . ( $result['title'] ?: 'blog content' ),
						'alt_text' => $fallback_alt,
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
	 * @param string $focus_keyphrase Optional focus keyphrase to include in alt text.
	 * @return string Generated alt text.
	 */
	private function generate_alt_text_from_prompt( $prompt, $focus_keyphrase = '' ) {
		// Remove AI prompt prefixes and common phrases
		$alt_text = preg_replace( '/^(create|generate|show|display|illustrate|image of|photo of|picture of)\s+/i', '', $prompt );
		
		// Remove AI-specific prompt language
		$alt_text = preg_replace( '/\b(professional|high quality|detailed|realistic|vibrant|modern|clean|bright)\s+/i', '', $alt_text );
		$alt_text = preg_replace( '/\b(in the style of|featuring|showing|with|including|containing)\s+/i', '', $alt_text );
		$alt_text = preg_replace( '/\b(professional\s+)?photography\b/i', '', $alt_text );
		$alt_text = preg_replace( '/\b(stock photo|commercial|marketing)\s*/i', '', $alt_text );
		
		// Clean up spacing and formatting
		$alt_text = preg_replace( '/\s+/', ' ', $alt_text );
		$alt_text = trim( $alt_text );
		
		// Ensure it starts with a capital letter
		$alt_text = ucfirst( $alt_text );
		
		// Include focus keyphrase for SEO if provided and not already present
		if ( ! empty( $focus_keyphrase ) && stripos( $alt_text, $focus_keyphrase ) === false ) {
			// If alt text is short, prepend the keyphrase
			if ( strlen( $alt_text ) < 50 ) {
				$alt_text = ucfirst( $focus_keyphrase ) . ' - ' . lcfirst( $alt_text );
			} else {
				// If alt text is longer, append the keyphrase naturally
				$alt_text = rtrim( $alt_text, '.' ) . ' for ' . $focus_keyphrase;
			}
		}
		
		// Extend length limit to 200 characters (WordPress recommendation)
		if ( strlen( $alt_text ) > 200 ) {
			// Find last complete word before 197 characters to avoid cutting mid-word
			$truncated = substr( $alt_text, 0, 197 );
			$last_space = strrpos( $truncated, ' ' );
			if ( $last_space !== false && $last_space > 150 ) { // Don't truncate too aggressively
				$alt_text = substr( $alt_text, 0, $last_space );
			} else {
				$alt_text = $truncated . '...';
			}
		}
		
		return $alt_text;
	}
} 
 