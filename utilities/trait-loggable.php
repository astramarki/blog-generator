<?php
/**
 * Loggable Trait
 *
 * @package AI_Blog_Generator
 * @subpackage Utilities
 */

namespace AI_Blog_Generator\Utilities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loggable Trait
 *
 * Provides easy access to centralized logging functionality.
 * Classes using this trait get automatic class context in logs.
 *
 * @since 1.0.0
 */
trait Loggable {

	/**
	 * Emergency memory check to prevent exhaustion.
	 *
	 * @return bool True if safe to proceed with logging.
	 */
	private function is_safe_to_log() {
		// Quick check - if memory usage is over 80% of limit, skip logging
		$memory_usage = memory_get_usage( true );
		$memory_limit_bytes = $this->parse_memory_limit( ini_get( 'memory_limit' ) );
		
		return $memory_usage <= ( $memory_limit_bytes * 0.8 );
	}

	/**
	 * Log debug message with automatic class context.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $method  Method name (automatically detected if not provided).
	 */
	protected function log_debug( $action, $message, $context = [], $method = '' ) {
		if ( ! $this->is_safe_to_log() ) {
			return;
		}
		
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::debug( $action, $message, $context, get_class( $this ), $method );
	}

	/**
	 * Log info message with automatic class context.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $method  Method name (automatically detected if not provided).
	 */
	protected function log_info( $action, $message, $context = [], $method = '' ) {
		if ( ! $this->is_safe_to_log() ) {
			return;
		}
		
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::info( $action, $message, $context, get_class( $this ), $method );
	}

	/**
	 * Log warning message with automatic class context.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $method  Method name (automatically detected if not provided).
	 */
	protected function log_warning( $action, $message, $context = [], $method = '' ) {
		if ( ! $this->is_safe_to_log() ) {
			return;
		}
		
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::warning( $action, $message, $context, get_class( $this ), $method );
	}

	/**
	 * Log error message with automatic class context.
	 *
	 * @param string $action  Action identifier.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @param string $method  Method name (automatically detected if not provided).
	 */
	protected function log_error( $action, $message, $context = [], $method = '' ) {
		// Always try to log errors, but use fallback if memory is low
		if ( ! $this->is_safe_to_log() ) {
			error_log( "AI Blog Generator Error (memory limit): {$action} - {$message}" );
			return;
		}
		
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::error( $action, $message, $context, get_class( $this ), $method );
	}

	/**
	 * Log exception with automatic class context.
	 *
	 * @param string     $action    Action identifier.
	 * @param \Exception $exception Exception object.
	 * @param array      $context   Additional context data.
	 * @param string     $method    Method name (automatically detected if not provided).
	 */
	protected function log_exception( $action, $exception, $context = [], $method = '' ) {
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::exception( $action, $exception, $context, get_class( $this ), $method );
	}

	/**
	 * Log function entry with automatic class context.
	 *
	 * @param array  $params Method parameters.
	 * @param string $method Method name (automatically detected if not provided).
	 */
	protected function log_function_entry( $params = [], $method = '' ) {
		// Skip if debug mode is off or approaching memory limit
		if ( ! defined( 'AI_BLOG_GENERATOR_DEBUG' ) || ! AI_BLOG_GENERATOR_DEBUG ) {
			return;
		}
		
		// Check memory usage
		$memory_usage = memory_get_usage( true );
		$memory_limit_bytes = $this->parse_memory_limit( ini_get( 'memory_limit' ) );
		if ( $memory_usage > ( $memory_limit_bytes * 0.8 ) ) {
			return;
		}
		
		// Reduce frequency to prevent spam
		static $entry_count = 0;
		$entry_count++;
		if ( $entry_count % 10 !== 0 ) {
			return;
		}
		
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::function_entry( get_class( $this ), $method, $params );
	}

	/**
	 * Log function exit with automatic class context.
	 *
	 * @param mixed  $result Return value.
	 * @param string $method Method name (automatically detected if not provided).
	 */
	protected function log_function_exit( $result = null, $method = '' ) {
		// Skip if debug mode is off or approaching memory limit
		if ( ! defined( 'AI_BLOG_GENERATOR_DEBUG' ) || ! AI_BLOG_GENERATOR_DEBUG ) {
			return;
		}
		
		// Check memory usage
		$memory_usage = memory_get_usage( true );
		$memory_limit_bytes = $this->parse_memory_limit( ini_get( 'memory_limit' ) );
		if ( $memory_usage > ( $memory_limit_bytes * 0.8 ) ) {
			return;
		}
		
		// Reduce frequency to prevent spam
		static $exit_count = 0;
		$exit_count++;
		if ( $exit_count % 10 !== 0 ) {
			return;
		}
		
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::function_exit( get_class( $this ), $method, $result );
	}

	/**
	 * Log database operation with automatic class context.
	 *
	 * @param string $operation Database operation (insert, update, delete, select).
	 * @param string $table     Table name.
	 * @param array  $data      Operation data.
	 * @param string $method    Method name (automatically detected if not provided).
	 */
	protected function log_database( $operation, $table, $data = [], $method = '' ) {
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::database( $operation, $table, $data, get_class( $this ), $method );
	}

	/**
	 * Log API request with automatic class context.
	 *
	 * @param string $service   API service name.
	 * @param string $endpoint  API endpoint.
	 * @param array  $request   Request data.
	 * @param array  $response  Response data.
	 * @param string $method    Method name (automatically detected if not provided).
	 */
	protected function log_api_request( $service, $endpoint, $request = [], $response = [], $method = '' ) {
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::api_request( $service, $endpoint, $request, $response, get_class( $this ), $method );
	}

	/**
	 * Log performance metrics with automatic class context.
	 *
	 * @param string $operation Operation name.
	 * @param float  $start_time Start time (microtime).
	 * @param array  $context   Additional context.
	 * @param string $method    Method name (automatically detected if not provided).
	 */
	protected function log_performance( $operation, $start_time, $context = [], $method = '' ) {
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		Logger::performance( $operation, $start_time, $context, get_class( $this ), $method );
	}

	/**
	 * Get the calling method name automatically.
	 *
	 * @return string Method name.
	 */
	private function get_calling_method() {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
		
		// Trace stack:
		// 0: get_calling_method
		// 1: log_* method
		// 2: actual calling method
		if ( isset( $trace[2]['function'] ) ) {
			return $trace[2]['function'];
		}
		
		return 'unknown_method';
	}

	/**
	 * Create a performance timer.
	 *
	 * @return float Start time for performance measuring.
	 */
	protected function start_timer() {
		return microtime( true );
	}

	/**
	 * End performance timer and log the result.
	 *
	 * @param float  $start_time Start time from start_timer().
	 * @param string $operation  Operation description.
	 * @param array  $context    Additional context.
	 * @param string $method     Method name.
	 */
	protected function end_timer( $start_time, $operation = '', $context = [], $method = '' ) {
		// Skip if we're approaching memory limit or if logging would cause recursion
		if ( defined( 'AI_BLOG_GENERATOR_DEBUG' ) && ! AI_BLOG_GENERATOR_DEBUG ) {
			return;
		}
		
		// Check memory usage before proceeding
		$memory_usage = memory_get_usage( true );
		$memory_limit = ini_get( 'memory_limit' );
		
		// Parse memory limit
		$memory_limit_bytes = $this->parse_memory_limit( $memory_limit );
		if ( $memory_usage > ( $memory_limit_bytes * 0.8 ) ) {
			return; // Skip logging if approaching memory limit
		}
		
		if ( empty( $operation ) ) {
			$operation = $this->get_calling_method();
		}
		
		// Use static counter to reduce logging frequency
		static $timer_count = 0;
		$timer_count++;
		
		// Only log every 5th timer to reduce memory usage
		if ( $timer_count % 5 !== 0 ) {
			return;
		}
		
		$this->log_performance( $operation, $start_time, $context, $method );
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
	 * Log and handle AJAX security check failure.
	 *
	 * @param string $method Method name.
	 */
	protected function log_ajax_security_failure( $method = '' ) {
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		
		$this->log_error( 'ajax_security_failure', 'AJAX security check failed', [
			'user_id' => get_current_user_id(),
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
		], $method );
	}

	/**
	 * Log and handle permission check failure.
	 *
	 * @param string $capability Required capability.
	 * @param string $method     Method name.
	 */
	protected function log_permission_failure( $capability = 'manage_options', $method = '' ) {
		if ( empty( $method ) ) {
			$method = $this->get_calling_method();
		}
		
		$this->log_error( 'permission_failure', 'User lacks required capability', [
			'required_capability' => $capability,
			'user_id' => get_current_user_id(),
			'user_capabilities' => wp_get_current_user()->allcaps ?? [],
		], $method );
	}
} 