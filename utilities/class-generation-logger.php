<?php
/**
 * Generation Logger Class
 * 
 * Creates individual log files for each blog idea generation process
 * with comprehensive debugging and real-time logging capabilities.
 *
 * @package AI_Blog_Generator
 * @subpackage Utilities
 */

namespace AI_Blog_Generator\Utilities;

/**
 * Class Generation_Logger
 */
class Generation_Logger {

	/**
	 * Base directory for generation logs
	 */
	private static $log_directory;

	/**
	 * Current idea ID being logged
	 */
	private $idea_id;

	/**
	 * Log file path for current generation
	 */
	private $log_file;

	/**
	 * Initialize the logger for a specific idea
	 *
	 * @param int $idea_id The ID of the idea being generated
	 */
	public function __construct( $idea_id ) {
		$this->idea_id = $idea_id;
		$this->setup_log_directory();
		$this->setup_log_file();
		$this->log_generation_start();
	}

	/**
	 * Setup the log directory
	 */
	private function setup_log_directory() {
		if ( ! self::$log_directory ) {
			// Create logs directory in WordPress uploads folder
			$wp_upload_dir = wp_upload_dir();
			self::$log_directory = $wp_upload_dir['basedir'] . '/ai-blog-generator-logs/generations/';
			
			// Create directory if it doesn't exist
			if ( ! file_exists( self::$log_directory ) ) {
				$created = wp_mkdir_p( self::$log_directory );
				if ( ! $created ) {
					error_log( "AI Blog Generator: Failed to create log directory: " . self::$log_directory );
					throw new \Exception( 'Unable to create generation log directory: ' . self::$log_directory );
				}
			}
			
			// Verify directory is writable
			if ( ! is_writable( self::$log_directory ) ) {
				error_log( "AI Blog Generator: Log directory is not writable: " . self::$log_directory );
				throw new \Exception( 'Generation log directory is not writable: ' . self::$log_directory );
			}
		}
	}

	/**
	 * Setup the log file for this generation
	 */
	private function setup_log_file() {
		$timestamp = date( 'Y-m-d_H-i-s' );
		$filename = "idea_{$this->idea_id}_{$timestamp}.log";
		$this->log_file = self::$log_directory . $filename;
		
		// Create the log file with error handling
		if ( ! touch( $this->log_file ) ) {
			error_log( "AI Blog Generator: Failed to create log file: " . $this->log_file );
			throw new \Exception( 'Unable to create generation log file: ' . $this->log_file );
		}
		
		// Set file permissions with error handling
		if ( ! chmod( $this->log_file, 0644 ) ) {
			error_log( "AI Blog Generator: Failed to set permissions on log file: " . $this->log_file );
			// Don't throw here as the file exists, just log the warning
		}
	}

	/**
	 * Log the start of generation
	 */
	private function log_generation_start() {
		$this->log( 'GENERATION_START', 'Blog generation process started', [
			'idea_id' => $this->idea_id,
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'memory_usage' => $this->format_bytes( memory_get_usage() ),
			'memory_peak' => $this->format_bytes( memory_get_peak_usage() ),
			'time_limit' => ini_get( 'max_execution_time' ),
			'php_version' => PHP_VERSION,
			'wordpress_version' => get_bloginfo( 'version' )
		] );
	}

	/**
	 * Log a message with detailed context
	 *
	 * @param string $level Log level (INFO, DEBUG, ERROR, WARNING)
	 * @param string $message The log message
	 * @param array  $context Additional context data
	 * @param string $function_name Current function name
	 * @param string $class_name Current class name
	 */
	public function log( $level, $message, $context = [], $function_name = '', $class_name = '' ) {
		$timestamp = date( 'Y-m-d H:i:s' );
		$memory_usage = $this->format_bytes( memory_get_usage() );
		$memory_peak = $this->format_bytes( memory_get_peak_usage() );
		
		// Get calling function info if not provided
		if ( empty( $function_name ) || empty( $class_name ) ) {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
			$caller = $backtrace[1] ?? [];
			$function_name = $caller['function'] ?? 'unknown';
			$class_name = $caller['class'] ?? 'unknown';
		}

		$log_entry = [
			'timestamp' => $timestamp,
			'level' => $level,
			'idea_id' => $this->idea_id,
			'memory_usage' => $memory_usage,
			'memory_peak' => $memory_peak,
			'class' => $class_name,
			'function' => $function_name,
			'message' => $message,
			'context' => $context
		];

		$formatted_entry = $this->format_log_entry( $log_entry );
		
		
		
		// Also log to main WordPress debug log if enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( "[GENERATION-{$this->idea_id}] [{$level}] {$class_name}::{$function_name}() - {$message}" );
		}
	}

	/**
	 * Log info level message
	 */
	public function info( $message, $context = [], $function_name = '', $class_name = '' ) {
		$this->log( 'INFO', $message, $context, $function_name, $class_name );
	}

	/**
	 * Log debug level message
	 */
	public function debug( $message, $context = [], $function_name = '', $class_name = '' ) {
		$this->log( 'DEBUG', $message, $context, $function_name, $class_name );
	}

	/**
	 * Log warning level message
	 */
	public function warning( $message, $context = [], $function_name = '', $class_name = '' ) {
		$this->log( 'WARNING', $message, $context, $function_name, $class_name );
	}

	/**
	 * Log error level message
	 */
	public function error( $message, $context = [], $function_name = '', $class_name = '' ) {
		$this->log( 'ERROR', $message, $context, $function_name, $class_name );
	}

	/**
	 * Log SQL query with timing
	 */
	public function log_sql( $query, $execution_time = null, $num_rows = null ) {
		$context = [
			'query' => $query,
			'execution_time' => $execution_time,
			'num_rows' => $num_rows,
			'query_hash' => md5( $query )
		];
		
		$this->debug( 'SQL Query executed', $context );
	}

	/**
	 * Log API request/response
	 */
	public function log_api_request( $provider, $endpoint, $request_data, $response_data, $execution_time ) {
		$context = [
			'provider' => $provider,
			'endpoint' => $endpoint,
			'request_size' => strlen( json_encode( $request_data ) ),
			'response_size' => strlen( json_encode( $response_data ) ),
			'execution_time' => $execution_time,
			'request_data' => $request_data,
			'response_data' => $response_data
		];
		
		$this->info( "API Request to {$provider}", $context );
	}

	/**
	 * Log generation phase change
	 */
	public function log_phase( $phase, $percentage, $details = [] ) {
		$context = [
			'phase' => $phase,
			'percentage' => $percentage,
			'details' => $details,
			'elapsed_time' => $this->get_elapsed_time()
		];
		
		$this->info( "Generation phase: {$phase} ({$percentage}%)", $context );
	}

	/**
	 * Log exception with full stack trace
	 */
	public function log_exception( \Exception $e, $additional_context = [] ) {
		$context = array_merge( [
			'exception_class' => get_class( $e ),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'stack_trace' => $e->getTraceAsString(),
			'previous_exception' => $e->getPrevious() ? get_class( $e->getPrevious() ) : null
		], $additional_context );
		
		$this->error( 'Exception occurred during generation', $context );
	}

	/**
	 * Log generation completion
	 */
	public function log_generation_complete( $success, $result_data = [] ) {
		$context = [
			'success' => $success,
			'total_time' => $this->get_elapsed_time(),
			'final_memory_usage' => $this->format_bytes( memory_get_usage() ),
			'peak_memory_usage' => $this->format_bytes( memory_get_peak_usage() ),
			'result_data' => $result_data
		];
		
		$level = $success ? 'INFO' : 'ERROR';
		$message = $success ? 'Generation completed successfully' : 'Generation failed';
		
		$this->log( $level, $message, $context );
	}

	/**
	 * Get the current log file path
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * Get log file contents
	 */
	public function get_log_contents() {
		if ( file_exists( $this->log_file ) ) {
			return file_get_contents( $this->log_file );
		}
		return '';
	}

	/**
	 * Get log file size
	 */
	public function get_log_size() {
		if ( file_exists( $this->log_file ) ) {
			return filesize( $this->log_file );
		}
		return 0;
	}

	/**
	 * Get log file last modified time
	 */
	public function get_log_modified_time() {
		if ( file_exists( $this->log_file ) ) {
			return filemtime( $this->log_file );
		}
		return 0;
	}

	/**
	 * Format log entry for file output
	 */
	private function format_log_entry( $log_entry ) {
		$formatted = sprintf(
			"[%s] [%s] [Memory: %s/%s] %s::%s() - %s",
			$log_entry['timestamp'],
			$log_entry['level'],
			$log_entry['memory_usage'],
			$log_entry['memory_peak'],
			$log_entry['class'],
			$log_entry['function'],
			$log_entry['message']
		);
		
		if ( ! empty( $log_entry['context'] ) ) {
			$formatted .= "\nContext: " . json_encode( $log_entry['context'], JSON_PRETTY_PRINT );
		}
		
		$formatted .= "\n" . str_repeat( '-', 120 );
		
		return $formatted;
	}

	/**
	 * Format bytes in human readable format
	 */
	private function format_bytes( $bytes ) {
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}

	/**
	 * Get elapsed time since generation start
	 */
	private function get_elapsed_time() {
		static $start_time = null;
		if ( $start_time === null ) {
			$start_time = microtime( true );
		}
		return round( microtime( true ) - $start_time, 2 ) . 's';
	}

	/**
	 * Static method to get all log files for an idea
	 */
	public static function get_idea_log_files( $idea_id ) {
		$log_directory = self::get_log_directory();
		$pattern = $log_directory . "idea_{$idea_id}_*.log";
		$files = glob( $pattern );
		
		// Sort by modification time (newest first)
		usort( $files, function( $a, $b ) {
			return filemtime( $b ) - filemtime( $a );
		} );
		
		return $files;
	}

	/**
	 * Static method to get the most recent log file for an idea
	 */
	public static function get_latest_log_file( $idea_id ) {
		$files = self::get_idea_log_files( $idea_id );
		return $files ? $files[0] : null;
	}

	/**
	 * Static method to get log directory
	 */
	public static function get_log_directory() {
		if ( ! self::$log_directory ) {
			$wp_upload_dir = wp_upload_dir();
			self::$log_directory = $wp_upload_dir['basedir'] . '/ai-blog-generator-logs/generations/';
			
			// Ensure directory exists
			if ( ! file_exists( self::$log_directory ) ) {
				$created = wp_mkdir_p( self::$log_directory );
				if ( ! $created ) {
					error_log( "AI Blog Generator: Failed to create log directory: " . self::$log_directory );
					return false;
				}
			}
		}
		return self::$log_directory;
	}

	/**
	 * Static method to clean up old log files
	 */
	public static function cleanup_old_logs( $days_to_keep = 7 ) {
		$log_directory = self::get_log_directory();
		$cutoff_time = time() - ( $days_to_keep * 24 * 60 * 60 );
		
		$files = glob( $log_directory . '*.log' );
		$deleted = 0;
		
		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				unlink( $file );
				$deleted++;
			}
		}
		
		return $deleted;
	}

	/**
	 * Static method to delete all log files for a specific idea
	 */
	public static function delete_idea_logs( $idea_id ) {
		$files = self::get_idea_log_files( $idea_id );
		$deleted = 0;
		
		foreach ( $files as $file ) {
			if ( unlink( $file ) ) {
				$deleted++;
			}
		}
		
		return $deleted;
	}
} 