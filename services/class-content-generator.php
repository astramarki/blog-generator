<?php
/**
 * Content Generator Service
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Services\Anthropic_Service;
use AI_Blog_Generator\Services\OpenAI_Service;
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
	 * @var Idea_Model
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
	 * Constructor.
	 */
	public function __construct() {
		$this->log_function_entry();
		
		$this->database_manager = Database_Manager::get_instance();
		$this->idea_model = new Idea_Model();
		$this->blog_model = new Blog_Model();
		$this->context_model = new Context_Model();
		$this->cost_model = new Cost_Model();
		$this->persona_model = new Persona_Model();
		
		// Initialize API services.
		$this->anthropic_service = new Anthropic_Service();
		$this->openai_service = new OpenAI_Service();
		
		// Only log initialization once per session to prevent log spam
		if ( ! get_transient( 'ai_blog_content_generator_init_logged' ) ) {
			$this->log_info( 'content_generator_init', 'Content Generator service initialized', [
				'database_manager_ready' => ! is_null( $this->database_manager ),
				'models_initialized' => 4,
				'api_services_ready' => 2
			] );
			set_transient( 'ai_blog_content_generator_init_logged', true, 300 ); // 5 minutes
		}
		
		$this->log_function_exit();
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
			
			// Start transaction.
			$this->log_info( 'content_generator_generate_blog_post', 'Starting database transaction', ['idea_id' => $idea_id]);
			
			// Validate database manager before transaction
			$this->log_info( 'validating_db_manager', 'Validating database manager before transaction', [
				'idea_id' => $idea_id,
				'db_manager_exists' => isset( $this->database_manager ),
				'db_manager_is_object' => is_object( $this->database_manager ),
				'db_manager_class' => is_object( $this->database_manager ) ? get_class( $this->database_manager ) : 'not_object'
			]);
			
			if ( ! $this->database_manager ) {
				$this->log_error( 'db_manager_missing', 'Database manager is not available', [ 'idea_id' => $idea_id ] );
				throw new \Exception( 'Database manager is not available' );
			}
			
			// Check if method exists
			if ( ! method_exists( $this->database_manager, 'start_transaction' ) ) {
				$this->log_error( 'start_transaction_method_missing', 'start_transaction method does not exist', [ 
					'idea_id' => $idea_id,
					'available_methods' => get_class_methods( $this->database_manager )
				] );
				throw new \Exception( 'start_transaction method not available' );
			}
			
			$this->log_info( 'db_manager_validated', 'Database manager validation passed', [ 'idea_id' => $idea_id ] );
			
			// Simple test log to verify logging is still working
			$this->log_info( 'test_log_before_transaction', 'Test log message before transaction', [ 
				'idea_id' => $idea_id,
				'time' => microtime( true ),
				'memory' => memory_get_usage(),
				'simple_test' => 'working'
			] );
			
			try {
				$this->log_info( 'calling_start_transaction', 'About to call start_transaction', [ 'idea_id' => $idea_id ] );
				
				// Set error handler to catch any PHP errors
				set_error_handler( function( $severity, $message, $file, $line ) use ( $idea_id ) {
					$this->log_error( 'php_error_during_transaction', 'PHP error during start_transaction', [
						'idea_id' => $idea_id,
						'severity' => $severity,
						'message' => $message,
						'file' => $file,
						'line' => $line
					]);
					throw new \ErrorException( $message, 0, $severity, $file, $line );
				});
				
				try {
					$transaction_result = $this->database_manager->start_transaction();
					
									// Log to same debug file for correlation
				$debug_log = __DIR__ . '/../debug-transaction.log';
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: start_transaction call returned with result: " . ($transaction_result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND );
					
					$this->log_info( 'start_transaction_call_completed', 'start_transaction call completed', [
						'idea_id' => $idea_id,
						'transaction_result' => $transaction_result,
						'result_type' => gettype( $transaction_result )
					]);
				} finally {
					// Restore previous error handler
					restore_error_handler();
				}
				
				$this->log_info( 'database_transaction_started', 'Database transaction started', [
					'idea_id' => $idea_id,
					'transaction_result' => $transaction_result
				]);
			} catch ( \Exception $e ) {
				$this->log_error( 'database_transaction_failed', 'Failed to start database transaction', [
					'idea_id' => $idea_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'error_type' => get_class( $e )
				]);
				throw new \Exception( 'Failed to start database transaction: ' . $e->getMessage() );
			} catch ( \Error $e ) {
				$this->log_error( 'database_transaction_fatal_error', 'Fatal error during database transaction', [
					'idea_id' => $idea_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'error_type' => get_class( $e )
				]);
				throw new \Exception( 'Fatal error during database transaction: ' . $e->getMessage() );
			}
			
			// Immediate status check right after transaction
			$this->log_info( 'post_transaction_immediate', 'Immediate status after transaction', [
				'idea_id' => $idea_id,
				'time_now' => microtime(true),
				'step' => 'right_after_transaction'
			]);
			
			// Test if we can still log
			error_log( "[AI_BLOG_GENERATOR] Debug: Post-transaction immediate log test for idea $idea_id" );
			
			try {
				// Check PHP error status and resources
				$this->log_info( 'php_status_check', 'PHP status after transaction start', [
					'idea_id' => $idea_id,
					'memory_usage' => memory_get_usage(true),
					'memory_peak' => memory_get_peak_usage(true),
					'memory_limit' => ini_get('memory_limit'),
					'max_execution_time' => ini_get('max_execution_time'),
					'time_limit' => ini_get('max_execution_time'),
					'last_error' => error_get_last()
				]);
			} catch ( \Exception $e ) {
				error_log( "[AI_BLOG_GENERATOR] Error during php_status_check: " . $e->getMessage() );
				throw $e;
			} catch ( \Error $e ) {
				error_log( "[AI_BLOG_GENERATOR] Fatal error during php_status_check: " . $e->getMessage() );
				throw $e;
			}
			
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
					$this->update_generation_status( $idea_id, 'starting', 'Initializing generation process...' );
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
				$this->update_generation_status( $idea_id, 'contexts', 'Compiling content contexts...' );
				
				try {
					$contexts = $this->compile_contexts_enhanced( 'content', [
						'max_contexts_per_type' => 5,
						'priority_threshold' => 0,
					] );
					$this->log_info( 'compiling_contexts_success', 'Context compilation successful', [ 
						'idea_id' => $idea_id,
						'context_types' => array_keys( $contexts ),
						'context_count' => count( $contexts )
					] );
					$this->update_generation_status( $idea_id, 'contexts', 'Content contexts ready. Preparing for AI generation...' );
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
				
				// Extract SEO keywords from contexts.
				$seo_keywords = [];
				if ( isset( $contexts['keywords'] ) ) {
					$this->log_info( 'extracting_seo_keywords', 'Extracting SEO keywords', [ 'idea_id' => $idea_id ] );
					
					// Only extract generic keywords if they're relevant to the specific idea
					$generic_keywords = $this->extract_seo_keywords( $contexts );
					$idea_title = is_array( $idea ) ? $idea['title'] : $idea->title;
					$idea_description = is_array( $idea ) ? $idea['description'] : $idea->description;
					$idea_title_lower = strtolower( $idea_title );
					$idea_desc_lower = strtolower( $idea_description );
					
					// Filter keywords to only include those relevant to the specific topic
					foreach ( $generic_keywords as $keyword ) {
						$keyword_lower = strtolower( $keyword );
						// Check if the keyword is relevant to the idea
						if ( strpos( $idea_title_lower, 'poster' ) !== false || 
						     strpos( $idea_desc_lower, 'poster' ) !== false ) {
							// Only include poster-related keywords if the idea is actually about posters
							$seo_keywords[] = $keyword;
						}
					}
					
					// Extract keywords from the idea title itself
					$title_words = explode( ' ', $idea_title );
					$potential_keywords = [];
					
					// Create 2-3 word combinations from the title
					for ( $i = 0; $i < count( $title_words ) - 1; $i++ ) {
						if ( strlen( $title_words[$i] ) > 3 ) { // Skip short words
							// Single important word
							$potential_keywords[] = strtolower( $title_words[$i] );
							
							// Two word combination
							if ( isset( $title_words[$i + 1] ) ) {
								$potential_keywords[] = strtolower( $title_words[$i] . ' ' . $title_words[$i + 1] );
							}
							
							// Three word combination
							if ( isset( $title_words[$i + 2] ) ) {
								$potential_keywords[] = strtolower( $title_words[$i] . ' ' . $title_words[$i + 1] . ' ' . $title_words[$i + 2] );
							}
						}
					}
					
					// Add the most relevant keywords from the title
					$seo_keywords = array_merge( $seo_keywords, array_slice( $potential_keywords, 0, 5 ) );
					$seo_keywords = array_unique( $seo_keywords );
					
					$this->log_info( 'seo_keywords_filtered', 'Filtered SEO keywords for specific topic', [
						'idea_title' => $idea_title,
						'generic_keywords_count' => count( $generic_keywords ),
						'filtered_keywords_count' => count( $seo_keywords ),
						'keywords' => $seo_keywords
					] );
				} else {
					$seo_keywords = [];
				}
				
				// Check for cancellation before AI content generation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'pre-content' );
				}
				
				// Generate content using Anthropic.
				$this->log_info( 'anthropic_generation_start', 'Starting Anthropic content generation', [ 'idea_id' => $idea_id ] );
				
				// Log to Generation Logger instead of debug file
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'About to call Anthropic service', [
						'idea_id' => $idea['id'] ?? 'no_id',
						'idea_title' => substr($idea['title'] ?? 'no_title', 0, 50),
						'idea_status' => $idea['status'] ?? 'no_status',
						'contexts' => array_keys( $contexts ),
						'seo_keywords' => $seo_keywords
					] );
				}
				
				$this->update_generation_status( $idea_id, 'content', 'Generating blog content with AI...' );
				
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
				
				// Fetch persona data if idea has persona_id
				$persona = null;
				if ( ! empty( $idea['persona_id'] ) ) {
					try {
						if ( ! $this->persona_model ) {
							$this->log_warning( 'persona_model_missing', 'Persona model not available', [
								'idea_id' => $idea_id,
								'persona_id' => $idea['persona_id']
							] );
						} else {
							$persona = $this->persona_model->get( $idea['persona_id'] );
							if ( $persona ) {
								// Convert to object format for backward compatibility
								$persona = (object) $persona;
								$this->log_info( 'persona_fetched', 'Persona data fetched successfully', [
									'idea_id' => $idea_id,
									'persona_id' => $idea['persona_id'],
									'persona_name' => $persona->name ?? 'unknown'
								] );
								if ( $this->generation_logger ) {
									$this->generation_logger->info( 'Persona fetched: ' . ($persona->name ?? 'unknown') );
								}
							} else {
								$this->log_warning( 'persona_not_found', 'Persona not found', [
									'idea_id' => $idea_id,
									'persona_id' => $idea['persona_id']
								] );
								if ( $this->generation_logger ) {
									$this->generation_logger->warning( 'Persona ID ' . $idea['persona_id'] . ' not found' );
								}
							}
						}
					} catch ( \Exception $e ) {
						$this->log_error( 'persona_fetch_failed', 'Failed to fetch persona data', [
							'idea_id' => $idea_id,
							'persona_id' => $idea['persona_id'],
							'error' => $e->getMessage()
						] );
						if ( $this->generation_logger ) {
							$this->generation_logger->error( 'Error fetching persona: ' . $e->getMessage() );
						}
						// Continue without persona - not critical
					}
				} else {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'No persona_id in idea data' );
					}
				}
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'About to call Anthropic service for content generation', [
						'idea_id' => $idea['id'] ?? 'no_id',
						'idea_title' => substr($idea['title'] ?? 'no_title', 0, 50),
						'idea_status' => $idea['status'] ?? 'no_status',
						'contexts' => array_keys( $contexts ),
						'seo_keywords' => $seo_keywords
					] );
				}
				
				try {
					$content_result = $this->anthropic_service->generate_blog_content( 
						$idea, 
						$contexts, 
						$seo_keywords,
						$persona
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
				
				$this->update_generation_status( $idea_id, 'content', 'AI content generated successfully. Validating...' );
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
					$this->generation_logger->info( 'Content result success confirmed, extracting content' );
				}
				
				$content = $content_result['content'];
				$total_cost = 0;
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Content extracted, initializing cost calculation' );
				}
				
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Content result success: " . ($content_result['success'] ?? 'not_set') . "\n", FILE_APPEND );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Content result keys: " . implode(', ', array_keys( $content_result )) . "\n", FILE_APPEND );
				
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Content extracted, initializing cost calculation\n", FILE_APPEND );
				
				// Calculate content generation cost.
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Checking if usage data exists in content_result' );
				}
				
				if ( isset( $content_result['usage'] ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Usage data found, using pre-calculated cost', [
							'tokens' => $content_result['usage']['total_tokens'] ?? 'unknown'
						] );
					}
					
					// Use the cost already calculated by the Anthropic service
					$content_cost = $content_result['cost'] ?? 0;
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Using pre-calculated cost: $' . $content_cost );
					}
					
					try {
						if ( $this->generation_logger ) {
							$this->generation_logger->debug( 'Calling cost_model->record_cost' );
						}
						
						// Check if cost_model is available
						if ( ! $this->cost_model ) {
							if ( $this->generation_logger ) {
								$this->generation_logger->warning( 'cost_model is not available, skipping cost recording' );
							}
						} else {
							if ( $this->generation_logger ) {
								$this->generation_logger->debug( 'cost_model is available, recording cost' );
							}
							$this->cost_model->record_cost( 'anthropic', 'content_generation', $content_cost, $content_result['usage'] );
							if ( $this->generation_logger ) {
								$this->generation_logger->info( 'Cost recorded successfully' );
							}
						}
					} catch ( \Exception $e ) {
						if ( $this->generation_logger ) {
							$this->generation_logger->error( 'ERROR in cost_model->record_cost: ' . $e->getMessage() );
						}
						// Don't throw here - cost recording failure shouldn't stop generation
						if ( $this->generation_logger ) {
							$this->generation_logger->debug( 'Continuing without cost recording' );
						}
					}
					
					try {
						if ( $this->generation_logger ) {
							$this->generation_logger->debug( 'Adding cost to total' );
						}
						$total_cost += $content_cost;
						if ( $this->generation_logger ) {
							$this->generation_logger->info( 'Total cost now: $' . $total_cost );
						}
					} catch ( \Exception $e ) {
						if ( $this->generation_logger ) {
							$this->generation_logger->error( 'ERROR adding to total cost: ' . $e->getMessage() );
						}
						throw $e;
					}
				} else {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'No usage data found, skipping cost calculation' );
					}
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
				
				$this->update_generation_status( $idea_id, 'content', 'Content validated successfully. Preparing for image generation...' );
				
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
				
				// Combine featured image and content images for batch generation but process differently
				$all_image_requirements = [];
				$featured_image_id = null;
				
				// Add featured image to requirements first (will be index 0)
				if ( ! empty( $content['featured_image'] ) ) {
					$all_image_requirements[] = $content['featured_image'];
				}
				
				// Add content images to requirements
				if ( ! empty( $content['images'] ) ) {
					$all_image_requirements = array_merge( $all_image_requirements, $content['images'] );
				}
				
				// Check for cancellation before image generation
				if ( $this->should_cancel_generation( $idea_id ) ) {
					$this->handle_cancellation( $idea_id, 'pre-images' );
				}
				
				if ( ! empty( $all_image_requirements ) && get_option( 'ai_blog_generator_enable_image_generation', true ) ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Starting image generation process' );
					}
					
					// Add timeout protection for image generation (5 minutes max)
					$image_timeout_start = time();
					$image_timeout_limit = 300; // 5 minutes (reduced from 10)
					
					try {
						$this->update_generation_status( $idea_id, 'images', 'Generating images...' );
						
						if ( $this->generation_logger ) {
							$this->generation_logger->log_phase( 'images', 75, [ 
								'message' => 'Starting image generation',
								'timeout_limit' => $image_timeout_limit
							] );
						}
						
						// Set a shorter execution time limit for image generation specifically
						set_time_limit( $image_timeout_limit );
						
						// CRITICAL: Add error suppression and try-catch for image generation
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
							$image_contexts = $this->compile_contexts_for_images();
							
							// Add seed images if available
							if ( $this->generation_logger ) {
								$this->generation_logger->debug( 'Adding seed images to requirements' );
							}
							$this->add_seed_images_to_requirements( $all_image_requirements, $idea, $image_contexts );
							
							// Check if OpenAI service is valid before calling
							if ( ! $this->openai_service || ! is_object( $this->openai_service ) ) {
								throw new \Exception( 'OpenAI service is not available for image generation' );
							}
							
							// Generate images in batch with timeout protection
							if ( $this->generation_logger ) {
								$this->generation_logger->info( 'Calling OpenAI image generation service', [
									'image_count' => count( $all_image_requirements )
								] );
							}
							
							$images = $this->openai_service->generate_batch_images( $all_image_requirements );
							
							// Check if we've exceeded the timeout
							if ( ( time() - $image_timeout_start ) > $image_timeout_limit ) {
								throw new \Exception( 'Image generation timeout exceeded' );
							}
							
						} catch ( \Exception $img_e ) {
							$this->log_error( 'image_generation_error', 'Error during image generation', [
								'error' => $img_e->getMessage(),
								'elapsed_time' => time() - $image_timeout_start
							] );
							
							// Continue without images rather than failing
							$images = [
								'results' => [],
								'summary' => [
									'total' => count( $all_image_requirements ),
									'successful' => 0,
									'failed' => count( $all_image_requirements )
								]
							];
							
							if ( $this->generation_logger ) {
								$this->generation_logger->log_phase( 'images', 80, [ 
									'message' => 'Image generation failed or timed out, continuing without images',
									'error' => $img_e->getMessage()
								] );
							}
						}
						
					} catch ( \Exception $timeout_e ) {
						$this->log_error( 'image_generation_timeout', 'Image generation phase timeout', [
							'error' => $timeout_e->getMessage(),
							'elapsed_time' => time() - $image_timeout_start
						] );
						
						// Continue without images
						$images = [
							'results' => [],
							'summary' => [
								'total' => count( $all_image_requirements ),
								'successful' => 0,
								'failed' => count( $all_image_requirements )
							]
						];
					}
					
					// Restore normal execution time limit
					set_time_limit( 600 ); // 10 minutes for rest of generation
					
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
					
					$this->update_generation_status( $idea_id, 'images', 'Image generation completed. Preparing WordPress post...' );
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
				
				// Create WordPress post.
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Starting WordPress post creation' );
				}
				
				$this->update_generation_status( $idea_id, 'post', 'Creating WordPress post...' );
				
				if ( $this->generation_logger ) {
					$this->generation_logger->info( 'Preparing post data', [
						'content_title' => substr( $content['title'] ?? 'no_title', 0, 100 ),
						'final_html_length' => strlen( $final_html ),
						'category_id' => $idea['category_id'] ?? 'no_category'
					] );
				}
				
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Post data prepared, calling wp_insert_post\n", FILE_APPEND );
				
				$post_data = [
					'post_title'   => $content['title'],
					'post_content' => $final_html,
					'post_status'  => 'draft',
					'post_type'    => 'post',
					'post_author'  => get_current_user_id(),
					'post_category' => [ $idea['category_id'] ],
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
				
				$this->update_generation_status( $idea_id, 'post', 'WordPress post created successfully. Finalizing...' );
				
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
				
				$blog_id = $this->blog_model->create([
					'idea_id' => $idea_id,
					'post_id' => $post_id,
					'title' => $content['title'],
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
					$idea_update_result = $this->idea_model->update( $idea_id, [ 'status' => 'generated' ] );
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
					$commit_start = microtime(true);
					$commit_result = $this->database_manager->commit();
					$commit_duration = microtime(true) - $commit_start;
					
					if ( $this->generation_logger ) {
						$this->generation_logger->info( 'Transaction commit completed', [
							'duration_seconds' => $commit_duration,
							'result' => $commit_result ? 'SUCCESS' : 'FAILED'
						] );
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
					
				} catch ( \Exception $e ) {
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
				$this->update_generation_status( $idea_id, 'complete', 'Blog post generated successfully!' );
				
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
					'title' => $content['title'],
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
					'trace' => $e->getTraceAsString()
				] );
				
				// Rollback transaction if needed
				try {
					if ( $this->database_manager ) {
						$this->database_manager->rollback();
					}
				} catch ( \Exception $rollback_exception ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Transaction rollback also failed: ' . $rollback_exception->getMessage() );
					}
				}
				
				// Update idea status back to approved
				try {
					if ( $this->idea_model ) {
						$this->idea_model->update( $idea_id, [ 'status' => 'approved' ] );
					}
				} catch ( \Exception $status_exception ) {
					if ( $this->generation_logger ) {
						$this->generation_logger->error( 'Failed to reset idea status: ' . $status_exception->getMessage() );
					}
				}
				
				return [
					'success' => false,
					'message' => $e->getMessage(),
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
			];

			$options = array_merge( $defaults, $options );
			
			$this->log_info( 'context_options_prepared', 'Context options prepared', [
				'usage' => $usage,
				'merged_options' => $options
			] );
			
			// Get contexts optimized for the specific usage
			$this->log_info( 'calling_context_model', 'Calling context model get_for_prompt', [
				'usage' => $usage,
				'context_model_class' => get_class( $this->context_model )
			] );
			
			try {
				$contexts = $this->context_model->get_for_prompt( $usage, $options );
				$this->log_info( 'context_model_success', 'Context model returned successfully', [
					'usage' => $usage,
					'contexts_returned' => is_array( $contexts ) ? count( $contexts ) : 'not_array',
					'contexts_keys' => is_array( $contexts ) ? array_keys( $contexts ) : 'not_array'
				] );
			} catch ( \Exception $e ) {
				$this->log_error( 'context_model_failed', 'Context model get_for_prompt failed', [
					'usage' => $usage,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
				throw new \Exception( 'Context model failed: ' . $e->getMessage() );
			}
			
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
			
			$this->log_info( 'contexts_compiled_enhanced', 'Enhanced contexts compilation completed', [
				'usage' => $usage,
				'types_included' => array_keys( $simple_contexts ),
				'total_context_types' => count( $simple_contexts ),
				'options' => $options,
			] );
			
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
		
		if ( $meta_desc_length > 160 ) {
			if ( $this->generation_logger ) {
				$this->generation_logger->warning( "Meta description too long ($meta_desc_length > 160)" );
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
		
			// Initialize seed images array
			$seed_images = [];
			
			// Try to get seed images from contexts that have seed_image_id
			try {
				if ( method_exists( $this->context_model, 'get_seed_images' ) ) {
					$seed_images = $this->context_model->get_seed_images();
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Retrieved seed images from context model: ' . count( $seed_images ) );
					}
				} else {
					if ( $this->generation_logger ) {
						$this->generation_logger->debug( 'Context model does not have get_seed_images method' );
					}
				}
			} catch ( \Exception $e ) {
				if ( $this->generation_logger ) {
					$this->generation_logger->error( 'Error getting seed images from context model: ' . $e->getMessage() );
				}
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
				
				if ( $this->generation_logger ) {
					$this->generation_logger->debug( 'Image ' . ($index + 1) . ' using seed image ' . ($seed_index + 1) . ': ' . $seed_url . ' (product: ' . $product_name . ')' );
				}
				
				// Update the prompt to specifically include the seed image product
				$requirement['prompt'] = "Include this exact product if it makes sense for this image. Do not change the look of the product at all, just include it in the context of the image. " . $requirement['prompt'];
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
		// Update transient for quick access
		$status_data = [
			'idea_id' => $idea_id,
			'stage' => $stage,
			'message' => $message,
			'progress' => $this->get_stage_progress( $stage ),
			'updated_at' => current_time( 'mysql' ),
			'updated_timestamp' => time()
		];
		
		set_transient( "ai_blog_generation_status_{$idea_id}", $status_data, HOUR_IN_SECONDS );
		
		// CRITICAL: Also update the database generation_status field
		try {
			// Use direct database update to ensure it works
			global $wpdb;
			$table = $wpdb->prefix . 'ai_blog_ideas';
			$wpdb->update(
				$table,
				array( 'generation_status' => $message ),
				array( 'id' => $idea_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Log the status update
			$this->log_debug( 'generation_status_updated', 'Updated generation status in database', [
				'idea_id' => $idea_id,
				'stage' => $stage,
				'message' => $message,
				'progress' => $status_data['progress']
			] );
			
			// If we have a generation logger, update it too
			if ( $this->generation_logger ) {
				$this->generation_logger->log_phase( $stage, $status_data['progress'], [
					'message' => $message,
					'status_updated' => true
				] );
			}
			
		} catch ( \Exception $e ) {
			$this->log_error( 'status_update_failed', 'Failed to update generation status in database', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );
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
		
		// Update status
		$this->update_generation_status( $idea_id, 'cancelled', 'Generation cancelled by user' );
		
		// Clear locks
		$this->clear_generation_lock( $idea_id );
		
		// Log to debug file
		$debug_log = __DIR__ . '/../debug-transaction.log';
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CONTENT_GENERATOR: Generation cancelled at stage '{$stage}' for idea {$idea_id}\n", FILE_APPEND );
		
		throw new \Exception( 'Generation cancelled by user' );
	}
} 
 