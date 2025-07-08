<?php
/**
 * Debug Logger utility for safe debug log writing with UTF-8 encoding
 *
 * @package AI_Blog_Generator
 */

namespace AI_Blog_Generator\Utilities;

/**
 * Debug Logger class
 */
class Debug_Logger {

	/**
	 * Write to debug log with proper UTF-8 encoding
	 *
	 * @param string $message The message to log.
	 * @param mixed  $data    Optional data to log.
	 */
	public static function log( $message, $data = null ) {
		if ( ! defined( 'AI_BLOG_GENERATOR_DEBUG' ) || ! AI_BLOG_GENERATOR_DEBUG ) {
			return;
		}
		
		$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
		$timestamp = date( 'Y-m-d H:i:s' );
		
		// Build log entry
		$log_entry = "$timestamp - $message";
		
		if ( $data !== null ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				// Convert to JSON for safe logging
				$log_entry .= " - DATA: " . json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} else {
				$log_entry .= " - DATA: " . $data;
			}
		}
		
		$log_entry .= "\n";
		
		// Ensure UTF-8 encoding
		if ( ! mb_check_encoding( $log_entry, 'UTF-8' ) ) {
			$log_entry = mb_convert_encoding( $log_entry, 'UTF-8', mb_detect_encoding( $log_entry ) );
		}
		
		// Write to log file
		file_put_contents( $debug_log, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Clear the debug log
	 */
	public static function clear() {
		$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
		file_put_contents( $debug_log, '' );
	}

	/**
	 * Initialize debug log with UTF-8 BOM
	 */
	public static function init() {
		$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
		if ( ! file_exists( $debug_log ) ) {
			// Create with UTF-8 BOM
			file_put_contents( $debug_log, "\xEF\xBB\xBF" );
		}
	}
} 