<?php
/**
 * Logger utility class for AI Blog Generator - File-based logging.
 *
 * @package AI_Blog_Generator
 */

namespace AI_Blog_Generator\Utilities;

/**
 * Logger utility class - writes to debug-transaction.log file.
 */
class Logger {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Debug file path.
	 *
	 * @var string
	 */
	private $debug_file;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->debug_file = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
	}

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Log debug message to file.
	 *
	 * @param string $action Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function debug( $action, $message, $context = [], $class_name = '', $method_name = '' ) {
		self::log_to_file( 'DEBUG', $action, $message, $context, $class_name, $method_name );
	}

	/**
	 * Log info message to file.
	 *
	 * @param string $action Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function info( $action, $message, $context = [], $class_name = '', $method_name = '' ) {
		self::log_to_file( 'INFO', $action, $message, $context, $class_name, $method_name );
	}

	/**
	 * Log warning message to file.
	 *
	 * @param string $action Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function warning( $action, $message, $context = [], $class_name = '', $method_name = '' ) {
		self::log_to_file( 'WARNING', $action, $message, $context, $class_name, $method_name );
	}

	/**
	 * Log error message to file.
	 *
	 * @param string $action Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function error( $action, $message, $context = [], $class_name = '', $method_name = '' ) {
		self::log_to_file( 'ERROR', $action, $message, $context, $class_name, $method_name );
	}

	/**
	 * Log exception to file.
	 *
	 * @param string     $action Action identifier.
	 * @param \Exception $exception Exception object.
	 * @param array      $context Additional context data.
	 * @param string     $class_name Class name.
	 * @param string     $method_name Method name.
	 */
	public static function exception( $action, $exception, $context = [], $class_name = '', $method_name = '' ) {
		$context['exception_message'] = $exception->getMessage();
		$context['exception_file'] = $exception->getFile();
		$context['exception_line'] = $exception->getLine();
		$context['exception_trace'] = $exception->getTraceAsString();
		
		self::log_to_file( 'ERROR', $action, 'Exception: ' . $exception->getMessage(), $context, $class_name, $method_name );
	}

	/**
	 * Log function entry.
	 *
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 * @param array  $params Parameters.
	 */
	public static function function_entry( $class_name, $method_name, $params = [] ) {
		self::log_to_file( 'DEBUG', 'function_entry', "Entering {$class_name}::{$method_name}", [ 'params' => $params ], $class_name, $method_name );
	}

	/**
	 * Log function exit.
	 *
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 * @param mixed  $result Result value.
	 */
	public static function function_exit( $class_name, $method_name, $result = null ) {
		self::log_to_file( 'DEBUG', 'function_exit', "Exiting {$class_name}::{$method_name}", [ 'result' => $result ], $class_name, $method_name );
	}

	/**
	 * Log database operation.
	 *
	 * @param string $operation Operation type.
	 * @param string $table Table name.
	 * @param array  $data Operation data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function database( $operation, $table, $data = [], $class_name = '', $method_name = '' ) {
		self::log_to_file( 'DEBUG', 'database_' . $operation, "Database {$operation} on {$table}", $data, $class_name, $method_name );
	}

	/**
	 * Log API request.
	 *
	 * @param string $service Service name.
	 * @param string $endpoint Endpoint.
	 * @param array  $request Request data.
	 * @param array  $response Response data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function api_request( $service, $endpoint, $request = [], $response = [], $class_name = '', $method_name = '' ) {
		$context = [
			'service' => $service,
			'endpoint' => $endpoint,
			'request' => $request,
			'response' => $response,
		];
		self::log_to_file( 'INFO', 'api_request', "API request to {$service} {$endpoint}", $context, $class_name, $method_name );
	}

	/**
	 * Log performance metric.
	 *
	 * @param string $operation Operation name.
	 * @param float  $start_time Start timestamp.
	 * @param array  $context Additional context.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	public static function performance( $operation, $start_time, $context = [], $class_name = '', $method_name = '' ) {
		$duration = microtime( true ) - $start_time;
		$context['duration'] = $duration;
		$context['operation'] = $operation;
		self::log_to_file( 'INFO', 'performance', "Performance: {$operation} took {$duration}s", $context, $class_name, $method_name );
	}

	/**
	 * Write log entry to file.
	 *
	 * @param string $level Log level.
	 * @param string $action Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class_name Class name.
	 * @param string $method_name Method name.
	 */
	private static function log_to_file( $level, $action, $message, $context = [], $class_name = '', $method_name = '' ) {
		// Define actions to exclude from logging (routine operations)
		$excluded_actions = [
			'blog_ideas_model_v2_init',
			'blog_ideas_controller_v2_init',
			'controller_initialization',
			'ajax_handlers_registration',
			'ajax_handlers_registration_completed',
			'ssl_fix_applied',
			'ajax_get_status_updates_start',
			'ajax_status_updates_requested',
			'ajax_duplicate_idea_removed',
			'ajax_ideas_deduplicated',
			'get_statistics_start',
			'ideas_counted_by_status',
			'statistics_calculated',
			'ajax_status_updates_retrieved',
			'approved_ideas_controller_init_start',
			'approved_ideas_model_created',
			'generation_queue_created',
			'approved_ideas_controller_init',
			'ajax_hooks_registered',
			'blog_ideas_v2_ajax_registered',
			'function_entry',
			'function_exit',
			'database_get',
			'database_get_success',
			'database_insert_success',
			'database_update_success',
			'context_get_for_prompt_start',
			'context_calling_get_compiled',
			'context_compiled_received',
		];
		
		// Define important actions that should always be logged
		$important_actions = [
			// Generation process milestones
			'generation_start',
			'generation_complete',
			'generation_failed',
			'background_processor_success',
			'background_processor_failed',
			'background_processor_daily_limit',
			
			// Critical errors
			'database_error',
			'database_insert_failed',
			'database_update_failed',
			'api_error',
			'generation_error',
			'background_processor_error',
			
			// Important state changes
			'idea_status_changed',
			'blog_post_created',
			'blog_post_published',
			
			// Transaction management
			'transaction_start',
			'transaction_commit',
			'transaction_rollback',
			
			// API milestones
			'api_request',
			'anthropic_request',
			'openai_request',
			
			// User actions
			'ajax_submit_generation_start',
			'generation_queue_add',
			'generation_queue_start',
			
			// Memory warnings
			'memory_limit_approaching',
			'memory_exhausted',
		];
		
		// Convert action to lowercase for comparison
		$action_lower = strtolower( $action );
		
		// Skip excluded actions unless they're errors
		// Also skip specific warnings that are too frequent
		if ( in_array( $action_lower, $excluded_actions, true ) ) {
			// Always log errors
			if ( $level === 'ERROR' ) {
				// Continue logging
			} 
			// Skip specific warnings that are routine
			elseif ( $level === 'WARNING' && $action_lower === 'ajax_duplicate_idea_removed' ) {
				return;
			}
			// Skip all other excluded actions that aren't errors
			elseif ( $level !== 'ERROR' ) {
				return;
			}
		}
		
		// For debug level, only log if it's an important action
		if ( $level === 'DEBUG' && ! in_array( $action_lower, $important_actions, true ) ) {
			return;
		}
		
		$instance = self::get_instance();
		
		// Format timestamp
		$timestamp = date( 'Y-m-d H:i:s' );
		
		// Build log entry
		$log_entry = sprintf(
			"%s - %s [%s]: %s",
			$timestamp,
			strtoupper( $action ),
			$level,
			$message
		);
		
		// Add class and method if provided
		if ( ! empty( $class_name ) ) {
			$log_entry .= " ({$class_name}";
			if ( ! empty( $method_name ) ) {
				$log_entry .= "::{$method_name}";
			}
			$log_entry .= ")";
		}
		
		// Add context if provided
		if ( ! empty( $context ) ) {
			// Remove sensitive data
			if ( isset( $context['api_key'] ) ) {
				$context['api_key'] = '[REDACTED]';
			}
			if ( isset( $context['anthropic_api_key'] ) ) {
				$context['anthropic_api_key'] = '[REDACTED]';
			}
			if ( isset( $context['openai_api_key'] ) ) {
				$context['openai_api_key'] = '[REDACTED]';
			}
			
			// Convert context to string
			$context_str = wp_json_encode( $context );
			if ( strlen( $context_str ) > 1000 ) {
				// Truncate very large context
				$context_str = substr( $context_str, 0, 1000 ) . '... [truncated]';
			}
			$log_entry .= " - Context: " . $context_str;
		}
		
		$log_entry .= "\n";
		
		// Write to file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $instance->debug_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Convert a string to proper case (title case).
	 * 
	 * This function intelligently converts strings to title case while:
	 * - Keeping certain words lowercase (articles, conjunctions, prepositions)
	 * - Preserving all-caps words (acronyms)
	 * - Capitalizing the first and last words
	 * 
	 * @param string $title The title to convert.
	 * @return string The title in proper case.
	 */
	public static function to_proper_case( $title ) {
		// Words that should remain lowercase unless they're the first or last word
		$lowercase_words = [
			'a', 'an', 'the', // articles
			'and', 'but', 'or', 'nor', 'for', 'yet', 'so', // conjunctions
			'as', 'at', 'by', 'for', 'from', 'in', 'into', 'of', 'on', 'to', 'with', 'up' // prepositions
		];
		
		// First, check if the entire title is uppercase
		if ( strtoupper( $title ) === $title ) {
			// Convert to lowercase first for all-caps titles
			$title = strtolower( $title );
		}
		
		// Split the title into words
		$words = preg_split( '/\s+/', $title );
		$result = [];
		
		foreach ( $words as $index => $word ) {
			// Skip empty words
			if ( empty( $word ) ) {
				continue;
			}
			
			// Check if word contains special characters or numbers at the beginning
			if ( preg_match( '/^[^a-zA-Z]/', $word ) ) {
				// Keep the word as is if it starts with non-letter
				$result[] = $word;
				continue;
			}
			
			// Check if the word is all uppercase (likely an acronym)
			if ( strlen( $word ) > 1 && strtoupper( $word ) === $word && strtolower( $word ) !== $word ) {
				// Keep acronyms as they are
				$result[] = $word;
				continue;
			}
			
			// First word or last word should always be capitalized
			if ( $index === 0 || $index === count( $words ) - 1 ) {
				$result[] = ucfirst( strtolower( $word ) );
			}
			// Check if it's a word that should be lowercase
			elseif ( in_array( strtolower( $word ), $lowercase_words, true ) ) {
				$result[] = strtolower( $word );
			}
			// Otherwise, capitalize the first letter
			else {
				$result[] = ucfirst( strtolower( $word ) );
			}
		}
		
		return implode( ' ', $result );
	}
} 