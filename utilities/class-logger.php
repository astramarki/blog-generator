<?php
/**
 * Centralized Logger Class
 *
 * @package AI_Blog_Generator
 * @subpackage Utilities
 */

namespace AI_Blog_Generator\Utilities;

use AI_Blog_Generator\Models\Log_Model;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized Logger Class
 *
 * Handles all logging operations using Log_Model.
 * Provides debug/console logging and centralized error handling.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private static $instance = null;

	/**
	 * Log model instance.
	 *
	 * @var Log_Model
	 */
	private $log_model;

	/**
	 * Debug mode flag.
	 *
	 * @var bool
	 */
	private $debug_mode;

	/**
	 * Recursion protection flag.
	 *
	 * @var bool
	 */
	private static $logging_in_progress = false;

	/**
	 * Console log buffer to prevent duplicate outputs.
	 *
	 * @var array
	 */
	private $console_buffer = [];

	/**
	 * Maximum memory usage before stopping detailed logging.
	 *
	 * @var int
	 */
	private $memory_limit;

	/**
	 * Get singleton instance.
	 *
	 * @return Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->debug_mode = defined( 'AI_BLOG_GENERATOR_DEBUG' ) ? AI_BLOG_GENERATOR_DEBUG : false;
		
		// Set memory limit to 80% of available memory
		$memory_limit_bytes = ini_get( 'memory_limit' );
		$this->memory_limit = $this->parse_memory_limit( $memory_limit_bytes ) * 0.8;
		
		// Only initialize Log_Model if we're not already logging to prevent recursion
		if ( ! self::$logging_in_progress ) {
			try {
				$this->log_model = new Log_Model();
			} catch ( \Exception $e ) {
				// If Log_Model fails, disable logging to prevent further issues
				$this->debug_mode = false;
				error_log( 'AI Blog Generator: Failed to initialize Log_Model: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Parse memory limit string to bytes.
	 *
	 * @param string $memory_limit Memory limit string (e.g., '128M', '1G').
	 * @return int Memory limit in bytes.
	 */
	private function parse_memory_limit( $memory_limit ) {
		$memory_limit = trim( $memory_limit );
		$last = strtolower( $memory_limit[ strlen( $memory_limit ) - 1 ] );
		$number = (int) $memory_limit;
		
		switch ( $last ) {
			case 'g':
				$number *= 1024;
			case 'm':
				$number *= 1024;
			case 'k':
				$number *= 1024;
		}
		
		return $number;
	}

	/**
	 * Check if we're approaching memory limit.
	 *
	 * @return bool True if memory usage is getting too high.
	 */
	private function is_memory_limit_approaching() {
		return memory_get_usage( true ) > $this->memory_limit;
	}

	/**
	 * Log debug message (only if debug mode is enabled).
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class   Class name for context.
	 * @param string $method  Method name for context.
	 */
	public static function debug( $action, $message, $context = [], $class = '', $method = '' ) {
		$instance = self::get_instance();
		
		// Skip if debug mode is off or we're approaching memory limit
		if ( ! $instance->debug_mode || $instance->is_memory_limit_approaching() ) {
			return;
		}

		// Prevent recursion
		if ( self::$logging_in_progress ) {
			return;
		}

		self::$logging_in_progress = true;

		try {
			if ( $instance->log_model ) {
				$enhanced_context = $instance->enhance_context( $context, $class, $method );
				$instance->log_model->info( $action, $message, $enhanced_context );
				$instance->console_log( 'DEBUG', $action, $message, $enhanced_context );
	}
		} catch ( \Exception $e ) {
			// Log to error_log as fallback
			error_log( "AI Blog Generator Debug Log Error: {$e->getMessage()}" );
		} finally {
			self::$logging_in_progress = false;
		}
	}

	/**
	 * Log info message.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class   Class name for context.
	 * @param string $method  Method name for context.
	 */
	public static function info( $action, $message, $context = [], $class = '', $method = '' ) {
		$instance = self::get_instance();

		// Prevent recursion
		if ( self::$logging_in_progress ) {
			return;
		}

		self::$logging_in_progress = true;

		try {
			if ( $instance->log_model ) {
				$enhanced_context = $instance->enhance_context( $context, $class, $method );
				$instance->log_model->info( $action, $message, $enhanced_context );
				
				if ( $instance->debug_mode && ! $instance->is_memory_limit_approaching() ) {
					$instance->console_log( 'INFO', $action, $message, $enhanced_context );
				}
			}
		} catch ( \Exception $e ) {
			// Log to error_log as fallback
			error_log( "AI Blog Generator Info Log Error: {$e->getMessage()}" );
		} finally {
			self::$logging_in_progress = false;
		}
	}

	/**
	 * Log warning message.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class   Class name for context.
	 * @param string $method  Method name for context.
	 */
	public static function warning( $action, $message, $context = [], $class = '', $method = '' ) {
		$instance = self::get_instance();

		// Prevent recursion
		if ( self::$logging_in_progress ) {
			return;
		}

		self::$logging_in_progress = true;

		try {
			if ( $instance->log_model ) {
				$enhanced_context = $instance->enhance_context( $context, $class, $method );
				$instance->log_model->warning( $action, $message, $enhanced_context );
				$instance->console_log( 'WARNING', $action, $message, $enhanced_context );
	}
		} catch ( \Exception $e ) {
			// Log to error_log as fallback
			error_log( "AI Blog Generator Warning Log Error: {$e->getMessage()}" );
		} finally {
			self::$logging_in_progress = false;
		}
	}

	/**
	 * Log error message.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $class   Class name for context.
	 * @param string $method  Method name for context.
	 */
	public static function error( $action, $message, $context = [], $class = '', $method = '' ) {
		$instance = self::get_instance();

		// Prevent recursion
		if ( self::$logging_in_progress ) {
			// For critical errors, still log to error_log as fallback
			error_log( "AI Blog Generator Error (recursion prevented): {$action} - {$message}" );
			return;
		}

		self::$logging_in_progress = true;

		try {
			if ( $instance->log_model ) {
				$enhanced_context = $instance->enhance_context( $context, $class, $method );
				$instance->log_model->error( $action, $message, $enhanced_context );
				$instance->console_log( 'ERROR', $action, $message, $enhanced_context );
			} else {
				// Fallback to error_log if log_model is not available
				error_log( "AI Blog Generator Error: {$action} - {$message}" );
			}
		} catch ( \Exception $e ) {
			// Log to error_log as fallback
			error_log( "AI Blog Generator Error Log Error: {$e->getMessage()}" );
		} finally {
			self::$logging_in_progress = false;
		}
	}

	/**
	 * Log exception with full stack trace.
	 *
	 * @param string     $action    Action identifier.
	 * @param \Exception $exception Exception object.
	 * @param array      $context   Additional context data.
	 * @param string     $class     Class name for context.
	 * @param string     $method    Method name for context.
	 */
	public static function exception( $action, $exception, $context = [], $class = '', $method = '' ) {
		$instance = self::get_instance();
		
		$exception_context = array_merge( $context, [
			'exception_message' => $exception->getMessage(),
			'exception_file'    => $exception->getFile(),
			'exception_line'    => $exception->getLine(),
			'exception_trace'   => $exception->getTraceAsString(),
		] );

		$enhanced_context = $instance->enhance_context( $exception_context, $class, $method );
		$message = sprintf( 'Exception: %s in %s:%d', $exception->getMessage(), basename( $exception->getFile() ), $exception->getLine() );
		
		$instance->log_model->error( $action, $message, $enhanced_context );
		$instance->console_log( 'EXCEPTION', $action, $message, $enhanced_context );
		}
		
	/**
	 * Log function entry (debug mode only).
	 *
	 * @param string $class  Class name.
	 * @param string $method Method name.
	 * @param array  $params Method parameters.
	 */
	public static function function_entry( $class, $method, $params = [] ) {
		$instance = self::get_instance();
		
		// Skip if debug mode is off, memory limit approaching, or already logging
		if ( ! $instance->debug_mode || $instance->is_memory_limit_approaching() || self::$logging_in_progress ) {
			return;
		}
		
		// Limit function entry logging to prevent excessive memory usage
		static $entry_count = 0;
		$entry_count++;
		
		// Only log every 10th function entry to reduce noise
		if ( $entry_count % 10 !== 0 ) {
			return;
		}

		$action = 'function_entry';
		$message = sprintf( 'Entering %s::%s', $class, $method );
		
		// Limit parameter size to prevent memory issues
		$limited_params = [];
		if ( is_array( $params ) && count( $params ) > 5 ) {
			$limited_params = array_slice( $params, 0, 5 );
			$limited_params['_note'] = 'Parameters truncated (showing first 5)';
			} else {
			$limited_params = $params;
		}
		
		$context = [ 'parameters' => $limited_params ];
		
		self::debug( $action, $message, $context, $class, $method );
	}

	/**
	 * Log function exit (debug mode only).
	 *
	 * @param string $class  Class name.
	 * @param string $method Method name.
	 * @param mixed  $result Return value.
	 */
	public static function function_exit( $class, $method, $result = null ) {
		$instance = self::get_instance();
		
		// Skip if debug mode is off, memory limit approaching, or already logging
		if ( ! $instance->debug_mode || $instance->is_memory_limit_approaching() || self::$logging_in_progress ) {
			return;
		}
		
		// Limit function exit logging to prevent excessive memory usage
		static $exit_count = 0;
		$exit_count++;
		
		// Only log every 10th function exit to reduce noise
		if ( $exit_count % 10 !== 0 ) {
			return;
		}

		$action = 'function_exit';
		$message = sprintf( 'Exiting %s::%s', $class, $method );
		
		// Limit return value size
		$limited_result = $result;
		if ( is_array( $result ) && count( $result ) > 5 ) {
			$limited_result = array_slice( $result, 0, 5 );
			$limited_result['_note'] = 'Return value truncated (showing first 5 items)';
		} elseif ( is_string( $result ) && strlen( $result ) > 200 ) {
			$limited_result = substr( $result, 0, 200 ) . '... [truncated]';
			}
		
		$context = [ 'return_value' => $limited_result ];
		
		self::debug( $action, $message, $context, $class, $method );
	}

	/**
	 * Log database operation.
	 *
	 * @param string $operation Database operation (insert, update, delete, select).
	 * @param string $table     Table name.
	 * @param array  $data      Operation data.
	 * @param string $class     Class name.
	 * @param string $method    Method name.
	 */
	public static function database( $operation, $table, $data = [], $class = '', $method = '' ) {
		$action = 'database_' . $operation;
		$message = sprintf( 'Database %s on table %s', $operation, $table );
		$context = [ 'table' => $table, 'data' => $data ];
		
		if ( self::get_instance()->debug_mode ) {
			self::debug( $action, $message, $context, $class, $method );
		} else {
			self::info( $action, $message, $context, $class, $method );
		}
	}

	/**
	 * Log API request.
	 *
	 * @param string $service   API service name.
	 * @param string $endpoint  API endpoint.
	 * @param array  $request   Request data.
	 * @param array  $response  Response data.
	 * @param string $class     Class name.
	 * @param string $method    Method name.
	 */
	public static function api_request( $service, $endpoint, $request = [], $response = [], $class = '', $method = '' ) {
		$action = 'api_request_' . $service;
		$message = sprintf( 'API request to %s: %s', $service, $endpoint );
		$context = [
			'service'  => $service,
			'endpoint' => $endpoint,
			'request'  => $request,
			'response' => $response,
		];
		
		if ( self::get_instance()->debug_mode ) {
			self::debug( $action, $message, $context, $class, $method );
		} else {
			self::info( $action, $message, [ 'service' => $service, 'endpoint' => $endpoint ], $class, $method );
		}
	}

	/**
	 * Log performance metrics.
	 *
	 * @param string $operation Operation name.
	 * @param float  $start_time Start time (microtime).
	 * @param array  $context   Additional context.
	 * @param string $class     Class name.
	 * @param string $method    Method name.
	 */
	public static function performance( $operation, $start_time, $context = [], $class = '', $method = '' ) {
		$instance = self::get_instance();
		
		// Skip if debug mode is off, memory limit approaching, or already logging
		if ( ! $instance->debug_mode || $instance->is_memory_limit_approaching() || self::$logging_in_progress ) {
			return;
		}

		// Limit performance logging to prevent excessive memory usage
		static $perf_count = 0;
		$perf_count++;
		
		// Only log every 5th performance metric to reduce noise
		if ( $perf_count % 5 !== 0 ) {
			return;
		}
		
		$execution_time = microtime( true ) - $start_time;
		$action = 'performance_' . $operation;
		$message = sprintf( 'Operation %s completed in %.4f seconds', $operation, $execution_time );
		
		// Limit context size for performance logs
		$limited_context = array_merge( 
			[ 'execution_time' => $execution_time ],
			$instance->limit_context_size( $context )
		);

		self::debug( $action, $message, $limited_context, $class, $method );
	}

	/**
	 * Enhance context with additional debug information.
	 *
	 * @param array  $context Original context.
	 * @param string $class   Class name.
	 * @param string $method  Method name.
	 * @return array Enhanced context.
	 */
	private function enhance_context( $context, $class = '', $method = '' ) {
		$enhanced = $context;
		
		if ( ! empty( $class ) ) {
			$enhanced['class'] = $class;
		}
		
		if ( ! empty( $method ) ) {
			$enhanced['method'] = $method;
		}
		
		// Only add memory usage in debug mode and if we're not approaching memory limit
		if ( $this->debug_mode && ! $this->is_memory_limit_approaching() ) {
			$enhanced['memory_usage'] = round( memory_get_usage( true ) / 1024 / 1024, 2 ) . 'MB';
		}
		
		// Add user context if available and not already logging
		if ( function_exists( 'get_current_user_id' ) && ! self::$logging_in_progress ) {
			$user_id = get_current_user_id();
			if ( $user_id ) {
				$enhanced['user_id'] = $user_id;
			}
		}
		
		return $enhanced;
	}

	/**
	 * Output log to browser console for debugging.
	 *
	 * @param string $level   Log level.
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 */
	private function console_log( $level, $action, $message, $context = [] ) {
		// Skip console logging if not in debug mode, not in admin, or approaching memory limit
		if ( ! $this->debug_mode || ! is_admin() || $this->is_memory_limit_approaching() ) {
			return;
		}
		
		// Limit context size to prevent memory issues
		$limited_context = $this->limit_context_size( $context );
		
		$console_data = [
			'level'     => $level,
			'action'    => $action,
			'message'   => $message,
			'context'   => $limited_context,
			'timestamp' => current_time( 'mysql' ),
		];
		
		// Prevent duplicate console logs
		$log_hash = md5( serialize( $console_data ) );
		if ( isset( $this->console_buffer[ $log_hash ] ) ) {
			return;
		}
		$this->console_buffer[ $log_hash ] = true;

		// Limit console buffer size
		if ( count( $this->console_buffer ) > 100 ) {
			$this->console_buffer = array_slice( $this->console_buffer, -50, null, true );
	}

		// Add to footer for console output
		add_action( 'admin_footer', function() use ( $console_data ) {
			echo '<script>console.log("AI Blog Generator [' . esc_js( $console_data['level'] ) . ']", ' . wp_json_encode( $console_data ) . ');</script>';
		} );
	}

	/**
	 * Limit context size to prevent memory issues.
	 *
	 * @param array $context Context data.
	 * @return array Limited context data.
	 */
	private function limit_context_size( $context ) {
		$limited = [];
		$max_string_length = 500;
		$max_array_items = 10;

		foreach ( $context as $key => $value ) {
			if ( is_string( $value ) && strlen( $value ) > $max_string_length ) {
				$limited[ $key ] = substr( $value, 0, $max_string_length ) . '... [truncated]';
			} elseif ( is_array( $value ) && count( $value ) > $max_array_items ) {
				$limited[ $key ] = array_slice( $value, 0, $max_array_items );
				$limited[ $key ]['_truncated'] = 'Array truncated, showing first ' . $max_array_items . ' items';
			} else {
				$limited[ $key ] = $value;
			}
		}

		return $limited;
	}

	/**
	 * Get logs with filtering (delegated to Log_Model).
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public static function get_logs( $filters = [] ) {
		return self::get_instance()->log_model->get_filtered( $filters );
	}

	/**
	 * Clean old logs (delegated to Log_Model).
	 *
	 * @param int $days Number of days to keep.
	 * @return int Number of logs deleted.
	 */
	public static function clean_old_logs( $days = 30 ) {
		return self::get_instance()->log_model->clean_old_logs( $days );
	}

	/**
	 * Get log statistics (delegated to Log_Model).
	 *
	 * @param string $period Time period.
	 * @return array
	 */
	public static function get_statistics( $period = 'day' ) {
		return self::get_instance()->log_model->get_statistics( $period );
	}

	/**
	 * Get recent errors (delegated to Log_Model).
	 *
	 * @param int $limit Number of errors.
	 * @return array
	 */
	public static function get_recent_errors( $limit = 10 ) {
		return self::get_instance()->log_model->get_recent_errors( $limit );
	}
} 