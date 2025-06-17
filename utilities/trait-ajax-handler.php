<?php
/**
 * AJAX Handler Trait
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
 * AJAX Handler Trait
 *
 * Provides centralized AJAX security checking and error handling.
 * Eliminates duplicate security code across controllers.
 *
 * @since 1.0.0
 */
trait Ajax_Handler {

	use Loggable;

	/**
	 * Verify AJAX security and permissions.
	 *
	 * @param string $capability Required capability (default: 'manage_options').
	 * @param string $nonce_action Nonce action (default: 'ai_blog_admin_nonce').
	 * @param string $nonce_field Nonce field name (default: 'nonce').
	 * @return bool True if security checks pass, false otherwise (automatically sends JSON error).
	 */
	protected function verify_ajax_security( $capability = 'manage_options', $nonce_action = 'ai_blog_admin_nonce', $nonce_field = 'nonce' ) {
		// Start output buffering to prevent warnings from contaminating JSON response
		ob_start();

		// Verify nonce
		if ( ! check_ajax_referer( $nonce_action, $nonce_field, false ) ) {
			ob_end_clean();
			$this->log_ajax_security_failure();
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ai-blog-generator' ) ] );
			return false;
		}

		// Check capabilities
		if ( ! current_user_can( $capability ) ) {
			ob_end_clean();
			$this->log_permission_failure( $capability );
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-blog-generator' ) ] );
			return false;
		}

		// Clean output buffer but keep it going for the rest of the request
		ob_end_clean();
		ob_start();

		return true;
	}

	/**
	 * Send AJAX success response with logging.
	 *
	 * @param array  $data Response data.
	 * @param string $message Success message.
	 * @param string $action Action identifier for logging.
	 */
	protected function send_ajax_success( $data = [], $message = '', $action = '' ) {
		// Aggressively clean all output buffers
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( ! empty( $action ) ) {
			$this->log_info( 'ajax_success_' . $action, $message ?: 'AJAX request successful', [
				'data_keys' => array_keys( $data ),
				'response_size' => count( $data )
			] );
		}

		if ( ! empty( $message ) ) {
			$data['message'] = $message;
		}

		wp_send_json_success( $data );
		exit; // Force exit to prevent any further output
	}

	/**
	 * Send AJAX error response with logging.
	 *
	 * @param string $message Error message.
	 * @param array  $data Additional error data.
	 * @param string $action Action identifier for logging.
	 * @param string $error_code Error code for logging.
	 */
	protected function send_ajax_error( $message, $data = [], $action = '', $error_code = '' ) {
		// Aggressively clean all output buffers
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( ! empty( $action ) ) {
			$this->log_error( 'ajax_error_' . $action, $message, array_merge( $data, [
				'error_code' => $error_code,
				'user_id' => get_current_user_id()
			] ) );
		}

		wp_send_json_error( array_merge( [ 'message' => $message ], $data ) );
		exit; // Force exit to prevent any further output
	}

	/**
	 * Handle AJAX exceptions with proper logging and response.
	 *
	 * @param \Exception $exception Exception object.
	 * @param string     $action Action identifier.
	 * @param array      $context Additional context for logging.
	 */
	protected function handle_ajax_exception( $exception, $action = '', $context = [] ) {
		// Clean output buffer
		ob_end_clean();

		$this->log_exception( 'ajax_exception_' . $action, $exception, array_merge( $context, [
			'ajax_action' => $action,
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
		] ) );

		$this->send_ajax_error( 
			$exception->getMessage(),
			[ 'exception_code' => $exception->getCode() ],
			$action,
			'exception'
		);
	}

	/**
	 * Validate required AJAX parameters.
	 *
	 * @param array $required_params Array of required parameter names.
	 * @return array|false Validated parameters or false if validation fails.
	 */
	protected function validate_ajax_params( $required_params = [] ) {
		$validated = [];
		
		foreach ( $required_params as $param ) {
			if ( ! isset( $_POST[ $param ] ) || empty( $_POST[ $param ] ) ) {
				$this->send_ajax_error(
					sprintf( __( 'Required parameter "%s" is missing.', 'ai-blog-generator' ), $param ),
					[ 'missing_param' => $param ],
					'validate_params',
					'missing_required_param'
				);
				return false;
			}
			$validated[ $param ] = $_POST[ $param ];
		}
		
		return $validated;
	}

	/**
	 * Sanitize AJAX data using specified sanitization functions.
	 *
	 * @param array $data Raw data to sanitize.
	 * @param array $sanitizers Map of field => sanitization_function.
	 * @return array Sanitized data.
	 */
	protected function sanitize_ajax_data( $data, $sanitizers = [] ) {
		$sanitized = [];
		
		foreach ( $sanitizers as $field => $sanitizer ) {
			if ( isset( $data[ $field ] ) ) {
				if ( is_callable( $sanitizer ) ) {
					$sanitized[ $field ] = call_user_func( $sanitizer, $data[ $field ] );
				} else {
					// Default to sanitize_text_field
					$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}
		
		return $sanitized;
	}
} 