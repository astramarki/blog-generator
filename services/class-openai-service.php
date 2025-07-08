<?php
/**
 * OpenAI Service
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Service Class
 *
 * Handles integration with OpenAI GPT-Image-1 API for image generation.
 *
 * @since 1.0.0
 */
class OpenAI_Service {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://api.openai.com/v1/images/generations';

	/**
	 * API edit URL.
	 *
	 * @var string
	 */
	private $api_edit_url = 'https://api.openai.com/v1/images/edits';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private $model = 'gpt-image-1';

	/**
	 * Cost model instance.
	 *
	 * @var Cost_Model
	 */
	private $cost_model;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Optional API key to override the saved option.
	 */
	public function __construct( $api_key = null ) {
		// Use provided API key or fall back to saved option
		if ( ! is_null( $api_key ) && ! empty( $api_key ) ) {
			$this->api_key = $api_key;
		} else {
			$this->api_key = get_option( 'ai_blog_generator_openai_api_key', '' );
		}
		
		$this->cost_model = new \AI_Blog_Generator\Models\Cost_Model();
	}



	/**
	 * Test API connection.
	 *
	 * @return array Test result.
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return [
				'success' => false,
				'message' => __( 'API key is required.', 'ai-blog-generator' ),
			];
		}

		try {
			// Use minimal parameters for gpt-image-1 test - only supported parameters
			$response = $this->make_request( [
				'model' => 'gpt-image-1',
				'prompt' => 'A simple test image',
				'n' => 1,
				'size' => '1024x1024',
			] );

			// Check for successful response - gpt-image-1 may return different format
			if ( isset( $response['data'] ) && is_array( $response['data'] ) && count( $response['data'] ) > 0 ) {
				// Check for either b64_json or url response format
				$image_data = $response['data'][0];
				if ( isset( $image_data['b64_json'] ) || isset( $image_data['url'] ) ) {
					return [
						'success' => true,
						'message' => __( 'Connection successful! GPT-Image-1 is working correctly.', 'ai-blog-generator' ),
					];
				}
			}

			return [
				'success' => false,
				'message' => __( 'Unexpected response format. Check API configuration.', 'ai-blog-generator' ),
			];

		} catch ( \Exception $e ) {
			Logger::error( 'openai_connection_test', 'Connection test failed', [
				'error' => $e->getMessage(),
			] );

			return [
				'success' => false,
				'message' => sprintf( __( 'Connection failed: %s', 'ai-blog-generator' ), $e->getMessage() ),
			];
		}
	}

	/**
	 * Generate image from prompt.
	 *
	 * @param string $prompt Image description prompt.
	 * @param array  $options Generation options (size, quality, etc.).
	 * @return array Generated image data or error.
	 */
	public function generate_image( $prompt, $options = [] ) {
		$method_start = microtime( true );
		Logger::info( 'openai_generate_image_start', 'Starting generate_image method', [
			'prompt_length' => strlen( $prompt ),
			'options' => $options,
			'memory_usage' => memory_get_usage(),
			'method_start_time' => $method_start,
		] );
		
		try {
			// Force specific model and size as per requirements
			$default_options = [
				'model' => 'gpt-image-1', // Always use gpt-image-1 as requested
				'prompt' => $prompt,
				'n' => 1,
				'size' => '1024x1024', // Always use 1024x1024 as specified
				'quality' => 'high',

				// Note: quality parameter may not be supported by gpt-image-1, removed to avoid errors
			];

			$request_data = array_merge( $default_options, $options );
			
			// Override model and size to ensure they're never changed
			$request_data['model'] = 'gpt-image-1';
			$request_data['size'] = '1024x1024';
			
			// Remove any parameters that gpt-image-1 doesn't support
			//unset( $request_data['quality'] );
			unset( $request_data['response_format'] );

			Logger::info( 'openai_image_request', 'Generating image with OpenAI', [
				'prompt_length' => strlen( $prompt ),
				'size' => $request_data['size'],
				'model' => $request_data['model'],
				'parameters' => array_keys( $request_data ),
			] );

			Logger::info( 'openai_about_to_make_request', 'About to call make_request method', [
				'memory_before_make_request' => memory_get_usage(),
				'api_url' => $this->api_url,
			] );
			
			$api_call_start = microtime( true );
			
			// Add pre-call timestamp
			Logger::info( 'openai_api_call_starting', 'Making actual API call NOW', [
				'timestamp' => date( 'Y-m-d H:i:s' ),
				'prompt_preview' => substr( $prompt, 0, 100 ),
			] );
			
			$response = $this->make_request( $request_data );
			$api_call_duration = microtime( true ) - $api_call_start;
			
			Logger::info( 'openai_make_request_returned', 'make_request method returned', [
				'api_call_duration_seconds' => $api_call_duration,
				'memory_after_make_request' => memory_get_usage(),
				'response_keys' => is_array( $response ) ? array_keys( $response ) : 'not_array',
			] );

			if ( ! isset( $response['data'][0] ) ) {
				throw new \Exception( 'Invalid response format - no image data returned' );
			}

			$image_response = $response['data'][0];
			$revised_prompt = $image_response['revised_prompt'] ?? $prompt;
			$image_data = null;
			$format = 'unknown';

			// Handle different response formats from gpt-image-1
			if ( isset( $image_response['b64_json'] ) ) {
				$image_data = $image_response['b64_json'];
				$format = 'base64';
			} elseif ( isset( $image_response['url'] ) ) {
				// If URL is returned, download and convert to base64
				$image_data = $this->download_image_as_base64( $image_response['url'] );
				$format = 'base64';
			} else {
				throw new \Exception( 'Invalid response format - no b64_json or url field found' );
			}

			if ( empty( $image_data ) ) {
				throw new \Exception( 'Failed to retrieve image data' );
			}

			// Calculate cost (GPT-Image-1 pricing)
			$cost = $this->calculate_image_cost( $request_data['size'], 'standard' );
			$this->cost_model->record_openai_cost( 'generate_image', $cost );

			Logger::info( 'openai_image_generated', 'Image generated successfully', [
				'data_size' => strlen( $image_data ),
				'revised_prompt_length' => strlen( $revised_prompt ),
				'cost' => $cost,
				'format' => $format,
				'model' => $request_data['model'],
			] );

			return [
				'success' => true,
				'image_data' => $image_data,
				'revised_prompt' => $revised_prompt,
				'cost' => $cost,
				'format' => $format,
				'method' => 'generate',
			];

		} catch ( \Exception $e ) {
			Logger::error( 'openai_image_failed', 'Image generation failed', [
				'error' => $e->getMessage(),
				'prompt_length' => strlen( $prompt ),
			] );

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Edit image using seed image and prompt.
	 *
	 * @param string $prompt Image description prompt.
	 * @param string $seed_image_url URL of the seed image.
	 * @param array  $options Generation options (size, quality, etc.).
	 * @return array Generated image data or error.
	 */
	public function edit_image( $prompt, $seed_image_url, $options = [] ) {
		try {
			// Add the required prompt text about preserving the product
			$enhanced_prompt = "Include this exact product if it makes sense for this image. Do not change the look of the product at all, just include it in the context of the image. " . $prompt;

			Logger::info( 'openai_image_edit_start', 'Starting image edit with seed image', [
				'seed_image_url' => $seed_image_url,
				'original_prompt_length' => strlen( $prompt ),
				'enhanced_prompt_length' => strlen( $enhanced_prompt ),
			] );

			// Download the seed image to a temporary file
			$temp_file = $this->download_seed_image_to_temp( $seed_image_url );
			if ( ! $temp_file ) {
				Logger::error( 'openai_seed_download_failed', 'Failed to download seed image', [
					'seed_image_url' => $seed_image_url,
				] );
				throw new \Exception( 'Failed to download seed image from URL: ' . $seed_image_url );
			}

			// Verify the temp file exists and has content
			if ( ! file_exists( $temp_file ) ) {
				Logger::error( 'openai_temp_file_missing', 'Temporary file does not exist', [
					'temp_file' => $temp_file,
				] );
				throw new \Exception( 'Temporary file does not exist: ' . $temp_file );
			}

			$file_size = filesize( $temp_file );
			if ( $file_size === 0 ) {
				Logger::error( 'openai_temp_file_empty', 'Temporary file is empty', [
					'temp_file' => $temp_file,
				] );
				unlink( $temp_file );
				throw new \Exception( 'Downloaded file is empty' );
			}

			Logger::info( 'openai_temp_file_ready', 'Temporary file created successfully', [
				'temp_file' => $temp_file,
				'file_size' => $file_size,
			] );

			// Prepare form data for multipart upload
			$form_data = [
				'model' => 'gpt-image-1', // Always use gpt-image-1
				'prompt' => $enhanced_prompt,
				'size' => '1024x1024', // Always use 1024x1024
				'n' => 1,
				// Note: gpt-image-1 returns base64 by default, no response_format parameter needed
			];

			Logger::info( 'openai_image_edit_request', 'Making edit request to OpenAI', [
				'model' => $form_data['model'],
				'size' => $form_data['size'],
				'temp_file' => $temp_file,
				'prompt_preview' => substr( $enhanced_prompt, 0, 100 ) . '...',
			] );

			// Make multipart request to edits endpoint
			$response = $this->make_multipart_request( $form_data, $temp_file );

			// Clean up temporary file
			unlink( $temp_file );

			if ( ! isset( $response['data'][0] ) ) {
				Logger::error( 'openai_invalid_response', 'Invalid response format - no image data', [
					'response_keys' => array_keys( $response ),
					'has_data' => isset( $response['data'] ),
					'data_count' => isset( $response['data'] ) ? count( $response['data'] ) : 0,
				] );
				throw new \Exception( 'Invalid response format - no image data returned' );
			}

			$image_response = $response['data'][0];
			$image_data = null;
			$format = 'base64';

			// Handle response format - gpt-image-1 returns base64 by default
			if ( isset( $image_response['b64_json'] ) ) {
				$image_data = $image_response['b64_json'];
				Logger::info( 'openai_edit_response_b64', 'Received base64 response from gpt-image-1', [
					'data_size' => strlen( $image_data ),
				] );
			} elseif ( isset( $image_response['url'] ) ) {
				// Fallback: download and convert to base64 if URL is returned
				$image_data = $this->download_image_as_base64( $image_response['url'] );
				Logger::info( 'openai_edit_response_url', 'Received URL response, converted to base64', [
					'url' => $image_response['url'],
					'data_size' => strlen( $image_data ),
				] );
			} else {
				Logger::error( 'openai_response_no_image', 'No image data in response', [
					'response_keys' => array_keys( $image_response ),
				] );
				throw new \Exception( 'Invalid response format - no b64_json or url field found in response' );
			}

			if ( empty( $image_data ) ) {
				throw new \Exception( 'Failed to retrieve edited image data' );
			}

			// Calculate cost
			$cost = $this->calculate_image_cost( '1024x1024', $options['quality'] ?? 'standard' );
			$this->cost_model->record_openai_cost( 'edit_image', $cost );

			Logger::info( 'openai_image_edit_success', 'Image edited successfully', [
				'data_size' => strlen( $image_data ),
				'cost' => $cost,
				'format' => $format,
			] );

			return [
				'success' => true,
				'image_data' => $image_data,
				'revised_prompt' => $enhanced_prompt,
				'cost' => $cost,
				'format' => $format,
				'method' => 'edit',
			];

		} catch ( \Exception $e ) {
			Logger::error( 'openai_image_edit_failed', 'Image edit failed', [
				'error' => $e->getMessage(),
				'error_class' => get_class( $e ),
				'seed_image_url' => $seed_image_url,
				'prompt_length' => strlen( $prompt ),
				'trace' => $e->getTraceAsString(),
			] );

			// Log to debug transaction log as well for easier debugging
			$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
			$log_entry = sprintf(
				"[%s] OPENAI_IMAGE_EDIT_ERROR: %s\nSeed URL: %s\nPrompt: %s\nTrace:\n%s\n\n",
				date( 'Y-m-d H:i:s' ),
				$e->getMessage(),
				$seed_image_url,
				substr( $prompt, 0, 200 ) . '...',
				$e->getTraceAsString()
			);
			file_put_contents( $debug_log, $log_entry, FILE_APPEND );

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Download seed image to temporary file.
	 *
	 * @param string $image_url URL of the image to download.
	 * @return string|false Path to temporary file or false on failure.
	 */
	private function download_seed_image_to_temp( $image_url ) {
		try {
					$args = [
			'timeout' => 60,
		];
		
		$response = wp_remote_get( $image_url, $args );

			if ( is_wp_error( $response ) ) {
				Logger::error( 'seed_image_download_failed', 'Failed to download seed image', [
					'url' => $image_url,
					'error' => $response->get_error_message(),
				] );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				Logger::error( 'seed_image_download_failed', 'Seed image download returned non-200 status', [
					'url' => $image_url,
					'status_code' => $response_code,
				] );
				return false;
			}

			$image_data = wp_remote_retrieve_body( $response );
			if ( empty( $image_data ) ) {
				return false;
			}

			// Create temporary file
			$temp_file = tempnam( sys_get_temp_dir(), 'ai_blog_seed_' );
			if ( ! $temp_file ) {
				return false;
			}

			// Write image data to temporary file
			if ( file_put_contents( $temp_file, $image_data ) === false ) {
				unlink( $temp_file );
				return false;
			}

			Logger::info( 'seed_image_downloaded_to_temp', 'Seed image downloaded to temporary file', [
				'url' => $image_url,
				'temp_file' => $temp_file,
				'file_size' => strlen( $image_data ),
			] );

			return $temp_file;

		} catch ( \Exception $e ) {
			Logger::error( 'seed_image_download_exception', 'Exception downloading seed image', [
				'url' => $image_url,
				'error' => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Make multipart API request for image edits.
	 *
	 * @param array  $form_data Form data for the request.
	 * @param string $image_file Path to the image file.
	 * @return array Response data.
	 * @throws \Exception On API errors.
	 */
	private function make_multipart_request( $form_data, $image_file ) {
		// Create boundary for multipart
		$boundary = wp_generate_password( 24, false );
		
		// Build multipart body
		$body = '';
		
		// Add form fields
		foreach ( $form_data as $key => $value ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
			$body .= "{$value}\r\n";
		}
		
		// Add image file
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"image\"; filename=\"" . basename( $image_file ) . "\"\r\n";
		$body .= "Content-Type: image/png\r\n\r\n";
		$body .= file_get_contents( $image_file ) . "\r\n";
		$body .= "--{$boundary}--\r\n";

		$headers = [
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			'Authorization' => 'Bearer ' . $this->api_key,
		];

		$args = [
			'method' => 'POST',
			'headers' => $headers,
			'body' => $body,
			'timeout' => 150, // 2m 30s timeout for individual image editing
		];

		Logger::info( 'openai_multipart_request', 'Sending multipart request to OpenAI edits endpoint', [
			'url' => $this->api_edit_url,
			'form_data' => $form_data,
			'body_size' => strlen( $body ),
			'boundary' => $boundary,
			'image_file' => $image_file,
			'image_file_size' => filesize( $image_file ),
		] );

		$response = wp_remote_request( $this->api_edit_url, $args );

		if ( is_wp_error( $response ) ) {
				Logger::error( 'openai_multipart_request_error', 'HTTP request failed', [
					'error_message' => $response->get_error_message(),
					'error_data' => $response->get_error_data(),
					'error_code' => $response->get_error_code(),
				] );
				throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
			}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response );

		// Log the complete response received
		Logger::info( 'openai_response_details', 'Received response from OpenAI API', [
			'response_code' => $response_code,
			'response_headers' => $response_headers,
			'response_body_length' => strlen( $response_body ),
			// Log first 500 chars of response for debugging
			'response_preview' => substr( $response_body, 0, 500 ),
		] );

		// Handle different response codes
		if ( $response_code === 400 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? 'Bad request - invalid parameters';
			
			Logger::error( 'openai_bad_request', 'Bad request to OpenAI API', [
				'response_code' => $response_code,
				'error_data' => $error_data,
				'error_message' => $error_message,
				'form_data' => $form_data,
			] );
			
			throw new \Exception( "Bad request (HTTP 400): {$error_message}" );
		}
		
		if ( $response_code === 401 ) {
			Logger::error( 'openai_unauthorized', 'Unauthorized - check API key', [
				'response_code' => $response_code,
			] );
			throw new \Exception( "Unauthorized (HTTP 401): Check your OpenAI API key" );
		}
		
		if ( $response_code === 413 ) {
			Logger::error( 'openai_payload_too_large', 'Image file too large', [
				'response_code' => $response_code,
				'image_size' => filesize( $image_file ),
			] );
			throw new \Exception( "Payload too large (HTTP 413): Image file is too large for API" );
		}
		
		if ( $response_code === 429 ) {
			$error_data = json_decode( $response_body, true );
			Logger::error( 'openai_rate_limit', 'Rate limit exceeded', [
				'response_code' => $response_code,
				'error_data' => $error_data,
			] );
			throw new \Exception( "Rate limit exceeded (HTTP 429): Please try again later" );
		}
		
		if ( $response_code === 500 || $response_code === 502 || $response_code === 503 ) {
			Logger::error( 'openai_server_error', 'OpenAI server error', [
				'response_code' => $response_code,
			] );
			throw new \Exception( "Server error (HTTP {$response_code}): OpenAI service temporarily unavailable" );
		}

		if ( $response_code !== 200 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? 'Unknown API error';
			$error_type = $error_data['error']['type'] ?? 'unknown_error';
			
			Logger::error( 'openai_edits_api_error', 'API returned error response', [
				'response_code' => $response_code,
				'error_type' => $error_type,
				'error_data' => $error_data,
				'error_message' => $error_message,
				'response_body' => $response_body,
			] );
			
			throw new \Exception( "API error (HTTP {$response_code}): {$error_message}" );
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::error( 'openai_edits_json_decode_error', 'Failed to decode JSON response', [
				'json_error' => json_last_error_msg(),
				'json_error_code' => json_last_error(),
				'response_preview' => substr( $response_body, 0, 1000 ),
				'response_length' => strlen( $response_body ),
			] );
			throw new \Exception( 'Invalid JSON response from API: ' . json_last_error_msg() );
		}

		// Validate response structure
		if ( ! isset( $decoded_response['data'] ) || ! is_array( $decoded_response['data'] ) || empty( $decoded_response['data'] ) ) {
			Logger::error( 'openai_invalid_response_structure', 'Response missing expected data structure', [
				'has_data' => isset( $decoded_response['data'] ),
				'data_type' => gettype( $decoded_response['data'] ?? null ),
				'response_keys' => array_keys( $decoded_response ),
			] );
			throw new \Exception( 'Invalid response structure from API - missing data array' );
		}

		Logger::info( 'openai_multipart_success', 'Multipart request successful', [
			'data_count' => count( $decoded_response['data'] ),
			'has_usage' => isset( $decoded_response['usage'] ),
		] );

		return $decoded_response;
	}

	/**
	 * Save base64 image to WordPress media library.
	 *
	 * @param string $base64_data Base64 encoded image data.
	 * @param string $filename Desired filename.
	 * @param string $title Image title.
	 * @param string $alt_text Image alt text.
	 * @return array Result with attachment ID or error.
	 */
	public function save_to_media_library( $base64_data, $filename, $title = '', $alt_text = '' ) {
		try {
			// Decode base64 data
			$image_data = base64_decode( $base64_data );
			if ( $image_data === false ) {
				throw new \Exception( 'Invalid base64 data' );
			}

			// Detect image format from binary data
			$format_info = $this->detect_image_format( $image_data );
			if ( ! $format_info ) {
				throw new \Exception( 'Unsupported image format' );
			}

			// Ensure filename has correct extension
			$filename = $this->ensure_file_extension( $filename, $format_info['extension'] );

			// Get WordPress upload directory
			$upload_dir = wp_upload_dir();
			if ( $upload_dir['error'] ) {
				throw new \Exception( 'Upload directory error: ' . $upload_dir['error'] );
			}

			$file_path = $upload_dir['path'] . '/' . $filename;
			$file_url = $upload_dir['url'] . '/' . $filename;

			// Save image data to file
			if ( file_put_contents( $file_path, $image_data ) === false ) {
				throw new \Exception( 'Failed to save image file' );
			}

			// Prepare attachment data
			$attachment = [
				'guid' => $file_url,
				'post_mime_type' => $format_info['mime_type'],
				'post_title' => $title ?: pathinfo( $filename, PATHINFO_FILENAME ),
				'post_content' => '',
				'post_status' => 'inherit',
			];

			// Insert attachment into WordPress
			$attachment_id = wp_insert_attachment( $attachment, $file_path );
			if ( is_wp_error( $attachment_id ) ) {
				throw new \Exception( 'Failed to create attachment: ' . $attachment_id->get_error_message() );
			}

			// Generate attachment metadata
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $attachment_data );

			// Set alt text if provided
			if ( $alt_text ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
			}

			Logger::info( 'openai_image_saved', 'Image saved to media library', [
				'attachment_id' => $attachment_id,
				'filename' => $filename,
				'file_size' => strlen( $image_data ),
				'format' => $format_info['format'],
			] );

			return [
				'success' => true,
				'attachment_id' => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
				'filename' => $filename,
			];

		} catch ( \Exception $e ) {
			Logger::error( 'openai_image_save_failed', 'Failed to save image to media library', [
				'error' => $e->getMessage(),
				'filename' => $filename,
			] );

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Detect image format from binary data.
	 *
	 * @param string $image_data Binary image data.
	 * @return array|false Format information or false if unsupported.
	 */
	private function detect_image_format( $image_data ) {
		// Check for common image formats by magic bytes
		$formats = [
			'jpeg' => [
				'signature' => "\xFF\xD8\xFF",
				'mime_type' => 'image/jpeg',
				'extension' => 'jpg',
			],
			'png' => [
				'signature' => "\x89\x50\x4E\x47",
				'mime_type' => 'image/png',
				'extension' => 'png',
			],
			'webp' => [
				'signature' => 'RIFF',
				'secondary' => 'WEBP',
				'mime_type' => 'image/webp',
				'extension' => 'webp',
			],
		];

		foreach ( $formats as $format => $info ) {
			if ( substr( $image_data, 0, strlen( $info['signature'] ) ) === $info['signature'] ) {
				// Special check for WebP (RIFF container)
				if ( $format === 'webp' ) {
					if ( substr( $image_data, 8, 4 ) === $info['secondary'] ) {
						return array_merge( $info, [ 'format' => $format ] );
					}
				} else {
					return array_merge( $info, [ 'format' => $format ] );
				}
			}
		}

		// Fallback: try to get info using PHP's getimagesizefromstring
		$temp_file = wp_tempnam( 'ai_blog_image_' );
		if ( file_put_contents( $temp_file, $image_data ) ) {
			$image_info = getimagesize( $temp_file );
			unlink( $temp_file );

			if ( $image_info ) {
				$extensions = [
					IMAGETYPE_JPEG => 'jpg',
					IMAGETYPE_PNG => 'png',
					IMAGETYPE_WEBP => 'webp',
				];

				if ( isset( $extensions[ $image_info[2] ] ) ) {
					return [
						'format' => $extensions[ $image_info[2] ],
						'mime_type' => $image_info['mime'],
						'extension' => $extensions[ $image_info[2] ],
					];
				}
			}
		}

		return false;
	}

	/**
	 * Ensure filename has correct extension.
	 *
	 * @param string $filename Original filename.
	 * @param string $extension Required extension.
	 * @return string Filename with correct extension.
	 */
	private function ensure_file_extension( $filename, $extension ) {
		$path_info = pathinfo( $filename );
		$current_extension = strtolower( $path_info['extension'] ?? '' );

		if ( $current_extension !== $extension ) {
			$base_name = $path_info['filename'] ?? $filename;
			return $base_name . '.' . $extension;
		}

		return $filename;
	}

	/**
	 * Download image from URL and convert to base64.
	 *
	 * @param string $url Image URL.
	 * @return string|false Base64 encoded image data or false on failure.
	 */
	private function download_image_as_base64( $url ) {
		try {
			$args = [
			'timeout' => 60,
		];
		
		$response = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				Logger::error( 'openai_image_download_failed', 'Failed to download image from URL', [
					'url' => $url,
					'error' => $response->get_error_message(),
				] );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				Logger::error( 'openai_image_download_failed', 'Image download returned non-200 status', [
					'url' => $url,
					'status_code' => $response_code,
				] );
				return false;
			}

			$image_data = wp_remote_retrieve_body( $response );
			if ( empty( $image_data ) ) {
				Logger::error( 'openai_image_download_failed', 'Empty image data from URL', [
					'url' => $url,
				] );
				return false;
			}

			$base64_data = base64_encode( $image_data );
			
			Logger::info( 'openai_image_downloaded', 'Successfully downloaded image from URL', [
				'url' => $url,
				'data_size' => strlen( $image_data ),
				'base64_size' => strlen( $base64_data ),
			] );

			return $base64_data;

		} catch ( \Exception $e ) {
			Logger::error( 'openai_image_download_exception', 'Exception downloading image from URL', [
				'url' => $url,
				'error' => $e->getMessage(),
			] );
			return false;
		}
	}

	/**
	 * Calculate cost for image generation.
	 *
	 * @param string $size Image size.
	 * @param string $quality Image quality.
	 * @return float Cost in USD.
	 */
	private function calculate_image_cost( $size, $quality ) {
		// GPT-Image-1 pricing (example rates - adjust based on actual pricing)
		$base_costs = [
			'256x256' => 0.016,
			'512x512' => 0.018,
			'1024x1024' => 0.020,
			'1024x1792' => 0.080,
			'1792x1024' => 0.080,
			'auto' => 0.020, // Default rate for auto size
		];

		$base_cost = $base_costs[ $size ] ?? $base_costs['auto'];

		// Quality multiplier
		if ( $quality === 'hd' ) {
			$base_cost *= 2; // HD costs twice as much
		}

		return $base_cost;
	}

	/**
	 * Generate image for blog post.
	 *
	 * @param string $title Blog post title.
	 * @param string $description Blog post description.
	 * @param array  $seed_images Optional seed images for consistency.
	 * @param string $focus_keyphrase Optional focus keyphrase for SEO-friendly filename.
	 * @return array Generated image result.
	 */
	public function generate_blog_image( $title, $description, $seed_images = [], $focus_keyphrase = '' ) {
		// Build prompt for blog image
		$prompt = $this->build_image_prompt( $title, $description, $seed_images );

		// Generate image with blog-appropriate settings
		$options = [
			'size' => '1024x1024',
			'quality' => 'standard',
		];

		$result = $this->generate_image( $prompt, $options );

		if ( $result['success'] ) {
			// Generate filename based on title with focus keyphrase for SEO
			$filename = $this->generate_filename( $title, $focus_keyphrase );
			
			// Save to media library
			$save_result = $this->save_to_media_library(
				$result['image_data'],
				$filename,
				$title,
				$description
			);

			if ( $save_result['success'] ) {
				$result['attachment_id'] = $save_result['attachment_id'];
				$result['url'] = $save_result['url'];
				$result['filename'] = $save_result['filename'];
			}
		}

		return $result;
	}

	/**
	 * Build image prompt for blog post.
	 *
	 * @param string $title Blog post title.
	 * @param string $description Blog post description.
	 * @param array  $seed_images Seed images for consistency.
	 * @return string Image prompt.
	 */
	private function build_image_prompt( $title, $description, $seed_images = [] ) {
		$prompt = "Create a professional, high-quality featured image for a blog post titled: '{$title}'. ";
		$prompt .= "The post is about: {$description}.";
		$prompt .= "Style: Modern, clean, visually appealing, suitable for a professional blog. ";
		$prompt .= "Include relevant visual elements that represent the topic. ";
		$prompt .= "Avoid text overlays. Use vibrant but professional colors. If there is text in the picture, pay special attention to fonts and spelling";
		$prompt .= "The image should be ultra-realistic, and special attention should be paid to faces and details.";

		// Add seed image context if available
		if ( ! empty( $seed_images ) ) {
			$prompt .= " Use the exact product image and do not change it at all.Maintain visual consistency with existing brand elements.";
		}

		return $prompt;
	}

	/**
	 * Generate filename from title.
	 *
	 * @param string $title Blog post title.
	 * @param string $focus_keyphrase Optional focus keyphrase to include in filename.
	 * @return string Generated filename.
	 */
	private function generate_filename( $title, $focus_keyphrase = '' ) {
		// Start with focus keyphrase if available for better SEO
		$filename_parts = [];
		
		if ( ! empty( $focus_keyphrase ) ) {
			$clean_keyphrase = sanitize_file_name( $focus_keyphrase );
			$clean_keyphrase = preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $clean_keyphrase );
			$clean_keyphrase = preg_replace( '/-+/', '-', $clean_keyphrase );
			$clean_keyphrase = trim( $clean_keyphrase, '-' );
			if ( ! empty( $clean_keyphrase ) ) {
				$filename_parts[] = $clean_keyphrase;
			}
		}
		
		// Clean title for filename
		$clean_title = sanitize_file_name( $title );
		$clean_title = preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $clean_title );
		$clean_title = preg_replace( '/-+/', '-', $clean_title );
		$clean_title = trim( $clean_title, '-' );
		if ( ! empty( $clean_title ) ) {
			$filename_parts[] = $clean_title;
		}
		
		$filename = implode( '-', $filename_parts );
		
		// Add timestamp to ensure uniqueness
		$filename .= '-' . time();
		
		return strtolower( $filename );
	}

	/**
	 * Generate descriptive filename from image prompt.
	 *
	 * @param string $prompt Image generation prompt.
	 * @param string $token Image token (e.g., {{image1}}).
	 * @param int $index Image index.
	 * @param string $focus_keyphrase Optional focus keyphrase to include in filename.
	 * @return string Descriptive filename.
	 */
	private function generate_descriptive_filename( $prompt, $token, $index, $focus_keyphrase = '' ) {
		$filename_parts = [];
		
		// Start with focus keyphrase if available for better SEO
		if ( ! empty( $focus_keyphrase ) ) {
			$clean_keyphrase = sanitize_file_name( $focus_keyphrase );
			$clean_keyphrase = preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $clean_keyphrase );
			$clean_keyphrase = preg_replace( '/-+/', '-', $clean_keyphrase );
			$clean_keyphrase = trim( $clean_keyphrase, '-' );
			if ( ! empty( $clean_keyphrase ) ) {
				$filename_parts[] = $clean_keyphrase;
			}
		}
		
		// Extract meaningful keywords from prompt
		$clean_prompt = $this->extract_filename_keywords( $prompt );
		
		// Fallback to token if prompt doesn't yield good keywords
		if ( empty( $clean_prompt ) || strlen( $clean_prompt ) < 3 ) {
			$clean_prompt = str_replace( [ '{{', '}}' ], '', $token );
		}
		
		// Add prompt keywords if they don't overlap with keyphrase
		if ( ! empty( $clean_prompt ) ) {
			$prompt_parts = array_filter( explode( '-', $clean_prompt ) );
			$keyphrase_parts = ! empty( $focus_keyphrase ) ? array_filter( explode( '-', strtolower( str_replace( ' ', '-', $focus_keyphrase ) ) ) ) : [];
			
			// Only add prompt parts that aren't already in the keyphrase
			foreach ( $prompt_parts as $part ) {
				if ( ! in_array( strtolower( $part ), $keyphrase_parts, true ) && strlen( $part ) > 2 ) {
					$filename_parts[] = $part;
				}
			}
		}
		
		$filename = implode( '-', $filename_parts );
		
		// Sanitize for filename
		$filename = sanitize_file_name( $filename );
		$filename = preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $filename );
		$filename = preg_replace( '/-+/', '-', $filename );
		$filename = trim( $filename, '-' );
		
		// Limit length and add uniqueness
		$filename = substr( $filename, 0, 40 ); // Increased length slightly for keyphrase
		$filename = trim( $filename, '-' );
		$filename .= '-' . substr( md5( $prompt ), 0, 6 ); // Short hash for uniqueness
		
		return strtolower( $filename );
	}

	/**
	 * Extract meaningful keywords from image prompt for filename.
	 *
	 * @param string $prompt Image generation prompt.
	 * @return string Cleaned keywords for filename.
	 */
	private function extract_filename_keywords( $prompt ) {
		// Remove AI prompt prefixes and common phrases
		$clean = preg_replace( '/^(create|generate|show|display|illustrate|image of|photo of|picture of)\s+/i', '', $prompt );
		
		// Remove common prompt modifiers
		$clean = preg_replace( '/\b(professional|high quality|detailed|realistic|vibrant|modern|clean|bright)\s+/i', '', $clean );
		
		// Remove instruction words
		$clean = preg_replace( '/\b(with|showing|featuring|including|containing|that|has|have)\s+/i', '', $clean );
		
		// Extract key nouns and adjectives (first 3-4 meaningful words)
		$words = explode( ' ', trim( $clean ) );
		$meaningful_words = [];
		
		foreach ( $words as $word ) {
			$word = trim( preg_replace( '/[^a-zA-Z0-9]/', '', $word ) );
			if ( strlen( $word ) > 2 && count( $meaningful_words ) < 4 ) {
				$meaningful_words[] = $word;
			}
		}
		
		return implode( '-', $meaningful_words );
	}

	/**
	 * Generate multiple images sequentially with detailed progress tracking.
	 * Better alternative to batch generation - generates images one by one with status updates.
	 *
	 * @param array $image_requirements Array of image requirements with prompts and tokens.
	 * @param callable $progress_callback Optional callback for progress updates.
	 * @return array Results with success/failure for each image.
	 */
	public function generate_images_sequentially( $image_requirements, $progress_callback = null ) {
		$generation_start_time = microtime( true );
		
		$results = [
			'results' => [],
			'summary' => [
				'total' => count( $image_requirements ),
				'successful' => 0,
				'failed' => 0,
				'total_cost' => 0,
			],
		];

		// SAFETY: Limit images to prevent timeout issues
		$max_images = (int) get_option( 'ai_blog_generator_max_images_per_post', 2 );
		if ( $max_images < 1 ) {
			$max_images = 2; // Default to 2 if invalid
		}
		
		if ( count( $image_requirements ) > $max_images ) {
			Logger::warning( 'sequential_image_limit_applied', 'Limiting image generation to prevent timeouts', [
				'requested_images' => count( $image_requirements ),
				'limited_to' => $max_images,
				'reason' => 'max_images_per_post setting'
			] );
			$image_requirements = array_slice( $image_requirements, 0, $max_images );
		}
		
		Logger::info( 'sequential_image_generation_start', 'Starting sequential image generation', [
			'total_images' => count( $image_requirements ),
			'memory_usage' => memory_get_usage(),
			'generation_start_time' => $generation_start_time,
			] );

		// Track seed images used
		$seed_image_used = null;
		$has_seed_images = false;

		// First pass: check if any requirements have seed images and select one
		foreach ( $image_requirements as $requirement ) {
			if ( ! empty( $requirement['seed_image'] ) ) {
				$seed_image_used = $requirement['seed_image'];
				$has_seed_images = true;
				Logger::info( 'seed_image_selected', 'Selected seed image for sequential processing', [
					'seed_image_url' => $seed_image_used,
				] );
				break; // Use only the first seed image found
			}
		}

		// Process each image individually
		foreach ( $image_requirements as $index => $requirement ) {
			$image_start = microtime( true );
			$prompt = $requirement['prompt'] ?? '';
			$token = $requirement['token'] ?? '{{image' . ( $index + 1 ) . '}}';
			$alt_text = $requirement['alt_text'] ?? '';
			$image_number = $index + 1;
			$total_images = count( $image_requirements );
			
			// CRITICAL: Add hard time limit check BEFORE starting each image
			$total_elapsed = microtime( true ) - $generation_start_time;
			$per_image_time_limit = 140; // 2m 20s hard limit per image (slightly less than timeout)
			$expected_time_for_this_image = $per_image_time_limit * ( $index + 1 );
			
			if ( $total_elapsed > $expected_time_for_this_image ) {
				Logger::error( 'sequential_image_hard_timeout', "HARD TIMEOUT: Skipping image {$image_number} - exceeded time limit", [
					'image_number' => $image_number,
					'total_elapsed' => $total_elapsed,
					'expected_time' => $expected_time_for_this_image,
					'token' => $token
				] );
				
				// Add failed result
				$results['results'][] = [
					'success' => false,
					'token' => $token,
					'error' => 'Skipped due to time limit - previous images took too long',
					'stage' => 'hard_timeout',
					'index' => $index,
				];
				
				$results['summary']['failed']++;
				
				if ( $progress_callback ) {
					call_user_func( $progress_callback, [
						'stage' => 'images',
						'message' => "Skipping image {$image_number} of {$total_images} - time limit exceeded",
						'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
						'current_image' => $image_number,
						'total_images' => $total_images,
						'token' => $token,
						'failed' => true,
						'skipped' => true
					] );
				}
				
				continue; // Skip to next image
			}
			
			// Calculate progress percentage for this image
			$progress_percentage = round( ( $index / $total_images ) * 100 );
			
			// Call progress callback if provided
			if ( $progress_callback ) {
				call_user_func( $progress_callback, [
					'stage' => 'images',
					'message' => "Waiting for images Response",
					'progress' => $progress_percentage,
					'current_image' => $image_number,
					'total_images' => $total_images,
					'token' => $token
				] );
			}
			
			Logger::info( 'sequential_image_processing', "Processing image {$image_number} of {$total_images}", [
				'index' => $index,
				'token' => $token,
				'prompt_length' => strlen( $prompt ),
				'has_seed_image' => ! empty( $requirement['seed_image'] ),
				'using_seed_image' => $has_seed_images,
				'progress_percentage' => $progress_percentage,
				'memory_usage' => memory_get_usage(),
			] );

			$image_result = null;
			$individual_image_start = time();
			$individual_timeout = 150; // 2m 30s per image

			try {
				// Set a PHP timeout for this individual image
				$original_time_limit = ini_get( 'max_execution_time' );
				set_time_limit( $individual_timeout + 30 ); // Add 30s buffer for cleanup
				
				// Add a WordPress-specific timeout mechanism
				$timeout_check_start = time();
				$timeout_reached = false;
				
				// Register a shutdown handler to detect timeouts
				$shutdown_function = function() use ( &$timeout_reached, $timeout_check_start, $individual_timeout, $image_number, $token ) {
					if ( ! $timeout_reached && ( time() - $timeout_check_start ) > $individual_timeout ) {
						$timeout_reached = true;
						Logger::error( 'sequential_image_shutdown_timeout', "Image generation shutdown detected - likely timeout for image {$image_number}", [
							'elapsed' => time() - $timeout_check_start,
							'token' => $token
						] );
					}
				};
				register_shutdown_function( $shutdown_function );
				
				// Use seed image editing if we have a seed image
			if ( $has_seed_images && $seed_image_used ) {
					Logger::info( 'sequential_seed_image_edit', "Starting seed image edit for image {$image_number}", [
					'token' => $token,
					'seed_image_url' => $seed_image_used,
						'prompt_preview' => substr( $prompt, 0, 100 ),
						'timeout_limit' => $individual_timeout,
				] );
				
				// Check if we've been running too long
				if ( ( time() - $timeout_check_start ) > $individual_timeout ) {
					throw new \Exception( 'Image generation timeout before API call' );
				}
				
				$image_result = $this->edit_image( $prompt, $seed_image_used );
				
					Logger::info( 'sequential_seed_edit_result', "Seed image edit completed for image {$image_number}", [
					'token' => $token,
					'success' => $image_result['success'] ?? false,
					'error_message' => $image_result['message'] ?? 'none',
						'elapsed_time' => time() - $individual_image_start,
				] );
			} else {
					Logger::info( 'sequential_standard_generation', "Starting standard generation for image {$image_number}", [
					'token' => $token,
						'prompt_preview' => substr( $prompt, 0, 100 ),
						'timeout_limit' => $individual_timeout,
				] );
				
				// Check if we've been running too long
				if ( ( time() - $timeout_check_start ) > $individual_timeout ) {
					throw new \Exception( 'Image generation timeout before API call' );
				}
				
				$image_result = $this->generate_image( $prompt );
				
					Logger::info( 'sequential_standard_result', "Standard generation completed for image {$image_number}", [
					'token' => $token,
					'success' => $image_result['success'] ?? false,
					'error_message' => $image_result['message'] ?? 'none',
						'elapsed_time' => time() - $individual_image_start,
				] );
			}
				
				// Restore original time limit
				if ( $original_time_limit ) {
					set_time_limit( $original_time_limit );
				}

			if ( $image_result['success'] ) {
				// Generate descriptive filename based on prompt content with focus keyphrase for SEO
					$focus_keyphrase = $requirement['focus_keyphrase'] ?? '';
					$filename = $this->generate_descriptive_filename( $prompt, $token, $index, $focus_keyphrase );
					
					// Update progress: saving image
					if ( $progress_callback ) {
						call_user_func( $progress_callback, [
							'stage' => 'images',
							'message' => "Saving Images",
							'progress' => $progress_percentage + 50 / $total_images, // Add proportional progress
							'current_image' => $image_number,
							'total_images' => $total_images,
							'token' => $token
						] );
					}
				
				// Save to media library
				$save_result = $this->save_to_media_library(
					$image_result['image_data'],
					$filename,
					$alt_text,
					$alt_text
				);

				if ( $save_result['success'] ) {
					$results['results'][] = [
						'success' => true,
						'token' => $token,
						'attachment_id' => $save_result['attachment_id'],
						'url' => $save_result['url'],
						'alt_text' => $alt_text,
						'cost' => $image_result['cost'],
						'method' => $image_result['method'],
							'index' => $index,
					];
					
					$results['summary']['successful']++;
					$results['summary']['total_cost'] += $image_result['cost'];
					
						$image_duration = microtime( true ) - $image_start;
						
						Logger::info( 'sequential_image_success', "Successfully generated and saved image {$image_number}", [
						'token' => $token,
						'attachment_id' => $save_result['attachment_id'],
						'cost' => $image_result['cost'],
						'method' => $image_result['method'],
							'duration_seconds' => $image_duration,
						] );
						
						// Update progress: image completed
						if ( $progress_callback ) {
							call_user_func( $progress_callback, [
								'stage' => 'images',
								'message' => "Image {$image_number} of {$total_images} completed successfully!",
								'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
								'current_image' => $image_number,
								'total_images' => $total_images,
								'token' => $token,
								'completed' => true
					] );
						}
				} else {
					$results['results'][] = [
						'success' => false,
						'token' => $token,
						'error' => $save_result['message'],
							'stage' => 'save',
							'index' => $index,
					];
					
					$results['summary']['failed']++;
					
						Logger::error( 'sequential_image_save_failed', "Failed to save image {$image_number}", [
						'token' => $token,
						'error' => $save_result['message'],
					] );
						
						// Update progress: save failed
						if ( $progress_callback ) {
							call_user_func( $progress_callback, [
								'stage' => 'images',
								'message' => "Failed to save image {$image_number} of {$total_images}: " . $save_result['message'],
								'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
								'current_image' => $image_number,
								'total_images' => $total_images,
								'token' => $token,
								'failed' => true
							] );
						}
				}
			} else {
				$results['results'][] = [
					'success' => false,
					'token' => $token,
					'error' => $image_result['message'],
						'stage' => 'generation',
						'index' => $index,
				];
				
				$results['summary']['failed']++;
				
					Logger::error( 'sequential_image_generation_failed', "Failed to generate image {$image_number}", [
					'token' => $token,
					'error' => $image_result['message'],
				] );
					
					// Update progress: generation failed
					if ( $progress_callback ) {
						call_user_func( $progress_callback, [
							'stage' => 'images',
							'message' => "Failed to generate image {$image_number} of {$total_images}: " . $image_result['message'],
							'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
							'current_image' => $image_number,
							'total_images' => $total_images,
							'token' => $token,
							'failed' => true
						] );
					}
				}

			} catch ( \Exception $e ) {
				// Check if this is a timeout error
				$is_timeout = ( time() - $individual_image_start ) > $individual_timeout || 
							  strpos( strtolower( $e->getMessage() ), 'timeout' ) !== false ||
							  strpos( strtolower( $e->getMessage() ), 'timed out' ) !== false;
				
				$error_message = $is_timeout ? 
					"Image generation timed out after " . ( time() - $individual_image_start ) . " seconds" : 
					'Exception: ' . $e->getMessage();
				
				$results['results'][] = [
					'success' => false,
					'token' => $token,
					'error' => $error_message,
					'stage' => $is_timeout ? 'timeout' : 'exception',
					'index' => $index,
				];
				
				$results['summary']['failed']++;
				
				Logger::warning( 'sequential_image_exception', "Exception during image {$image_number} generation - continuing with next image", [
					'token' => $token,
					'error' => $e->getMessage(),
					'is_timeout' => $is_timeout,
					'elapsed_time' => time() - $individual_image_start,
					'trace' => $e->getTraceAsString(),
				] );
				
				// Update progress: exception occurred but continuing
				if ( $progress_callback ) {
					$progress_message = $is_timeout ? 
						"Image {$image_number} timed out - continuing with next image..." : 
						"Image {$image_number} failed - continuing with next image...";
						
					call_user_func( $progress_callback, [
						'stage' => 'images',
						'message' => $progress_message,
						'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
						'current_image' => $image_number,
						'total_images' => $total_images,
						'token' => $token,
						'failed' => true,
						'is_timeout' => $is_timeout
					] );
				}
			} finally {
				// Always restore original time limit
				if ( isset( $original_time_limit ) && $original_time_limit ) {
					set_time_limit( $original_time_limit );
				}
			}

			// Add a small delay between requests to avoid rate limiting (but not after the last image)
			if ( $index < count( $image_requirements ) - 1 ) {
				sleep( 2 ); // 2-second delay between images
			}
			
			// Check for overall timeout (individual timeout per image: 2m 30s max)
			$total_elapsed = microtime( true ) - $generation_start_time;
			if ( $total_elapsed > 450 ) { // 7.5 minutes total maximum (allowing for 3 images)
				Logger::warning( 'sequential_image_timeout', 'Sequential image generation approaching timeout, stopping early', [
					'processed' => $index + 1,
					'total' => count( $image_requirements ),
					'elapsed_seconds' => $total_elapsed
				] );
				
				if ( $progress_callback ) {
					call_user_func( $progress_callback, [
						'stage' => 'images',
						'message' => "Image generation timeout after processing " . ($index + 1) . " of {$total_images} images",
						'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
						'timeout' => true
				] );
				}
				break;
			}
		}

		$total_duration = microtime( true ) - $generation_start_time;

		Logger::info( 'sequential_image_generation_complete', 'Sequential image generation completed', [
			'total' => $results['summary']['total'],
			'successful' => $results['summary']['successful'],
			'failed' => $results['summary']['failed'],
			'total_cost' => $results['summary']['total_cost'],
			'total_duration_seconds' => $total_duration,
			'used_seed_image' => $has_seed_images,
			'seed_image_url' => $seed_image_used,
		] );
		
		// Final progress update
		if ( $progress_callback ) {
			call_user_func( $progress_callback, [
				'stage' => 'images',
				'message' => "Image generation completed: {$results['summary']['successful']} successful, {$results['summary']['failed']} failed",
				'progress' => 100,
				'completed' => true,
				'summary' => $results['summary']
			] );
		}

		return $results;
	}

	/**
	 * Make API request to OpenAI.
	 *
	 * @param array $data Request data.
	 * @return array Response data.
	 * @throws \Exception On API errors.
	 */
	private function make_request( $data ) {
		$request_start = microtime( true );
		Logger::info( 'openai_make_request_start', 'Starting OpenAI API request', [
			'memory_usage' => memory_get_usage(),
			'request_start_time' => $request_start,
			'model' => $data['model'] ?? 'unknown',
			'prompt_length' => strlen( $data['prompt'] ?? '' ),
		] );
		
		$headers = [
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
		];

		$args = [
			'method' => 'POST',
			'headers' => $headers,
			'body' => wp_json_encode( $data ),
			'timeout' => 150, // 2m 30s timeout for individual image generation
		];

		// Check for global cancellation before making request
		if ( get_transient( 'ai_blog_global_cancel_flag' ) ) {
			Logger::warning( 'openai_request_cancelled', 'Request cancelled due to global cancellation flag' );
			
			// Log to debug file
			$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - OPENAI_SERVICE: Request cancelled due to global cancellation\n", FILE_APPEND );
			
			throw new \Exception( 'Generation cancelled by user' );
		}
		
		// Log request details
		Logger::info( 'openai_request_details', 'Sending request to OpenAI API', [
			'url' => $this->api_url,
			'headers' => array_merge( $headers, [ 'Authorization' => 'Bearer [REDACTED]' ] ), // Don't log the actual API key
			'request_body_length' => strlen( wp_json_encode( $data ) ),
			'request_data' => $data,
			'timeout' => $args['timeout'],
		] );

		Logger::info( 'openai_wp_remote_request_start', 'About to call wp_remote_request', [
			'memory_before_request' => memory_get_usage(),
		] );
		
		// Add a more aggressive timeout handler
		add_filter( 'http_request_timeout', function( $timeout ) {
			return 150; // Force 2m 30s timeout
		}, 999 );
		
		// Add connection timeout
		add_filter( 'http_request_args', function( $r ) {
			$r['connect_timeout'] = 30; // 30 second connection timeout
			$r['timeout'] = 150; // Total timeout
			$r['limit_response_size'] = 50 * 1024 * 1024; // 50MB max response
			$r['blocking'] = true; // Ensure blocking request
			$r['stream'] = false; // Don't use streams
			$r['decompress'] = true; // Handle compression
			return $r;
		}, 999 );
		
		// Add pre-request hook to track timeout
		$request_start_hook = time();
		add_action( 'http_api_curl', function( $handle ) use ( $request_start_hook ) {
			// Set CURL-specific timeout options
			curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 30 );
			curl_setopt( $handle, CURLOPT_TIMEOUT, 150 );
			curl_setopt( $handle, CURLOPT_NOSIGNAL, 1 ); // Required for timeout to work in some environments
		}, 999 );
		
		// Log right before the actual HTTP call
		Logger::info( 'openai_http_call_imminent', 'About to execute wp_remote_request', [
			'url' => $this->api_url,
			'timeout' => $args['timeout'],
			'time' => date( 'Y-m-d H:i:s' ),
		] );
		
		try {
			$response = wp_remote_request( $this->api_url, $args );
		} catch ( \Exception $e ) {
			// Remove filters on exception
			remove_all_filters( 'http_request_timeout', 999 );
			remove_all_filters( 'http_request_args', 999 );
			remove_all_actions( 'http_api_curl', 999 );
			
			Logger::error( 'openai_request_exception', 'Exception during HTTP request', [
				'error_message' => $e->getMessage(),
				'error_code' => $e->getCode()
			] );
			throw new \Exception( 'HTTP request exception: ' . $e->getMessage() );
		}
		
		// Remove filters after request
		remove_all_filters( 'http_request_timeout', 999 );
		remove_all_filters( 'http_request_args', 999 );
		remove_all_actions( 'http_api_curl', 999 );

		$request_duration = microtime( true ) - $request_start;
		Logger::info( 'openai_wp_remote_request_completed', 'wp_remote_request completed', [
			'request_duration_seconds' => $request_duration,
			'memory_after_request' => memory_get_usage(),
			'response_is_error' => is_wp_error( $response ),
		] );

		if ( is_wp_error( $response ) ) {
			Logger::error( 'openai_request_error', 'HTTP request failed', [
				'error_message' => $response->get_error_message(),
				'error_data' => $response->get_error_data()
			] );
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response );

		// Log the complete response received
		Logger::info( 'openai_response_details', 'Received response from OpenAI API', [
			'response_code' => $response_code,
			'response_headers' => $response_headers,
			'response_body_length' => strlen( $response_body ),
			// Don't log the full response body as it can be huge for images
			'response_preview' => substr( $response_body, 0, 200 ) . '...',
		] );

		if ( $response_code !== 200 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? 'Unknown API error';
			
			Logger::error( 'openai_api_error', 'API returned error response', [
				'response_code' => $response_code,
				'error_data' => $error_data,
				'error_message' => $error_message,
			] );
			
			throw new \Exception( "API error (HTTP {$response_code}): {$error_message}" );
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::error( 'openai_json_decode_error', 'Failed to decode JSON response', [
				'json_error' => json_last_error_msg(),
				'response_preview' => substr( $response_body, 0, 200 ) . '...',
			] );
			throw new \Exception( 'Invalid JSON response from API' );
		}

		// Log the decoded response structure without large data
		$log_response = $decoded_response;
		if ( isset( $log_response['data'] ) && is_array( $log_response['data'] ) ) {
			foreach ( $log_response['data'] as $idx => $item ) {
				if ( isset( $item['b64_json'] ) ) {
					$log_response['data'][$idx]['b64_json'] = 'BASE64_DATA_' . strlen( $item['b64_json'] ) . '_BYTES';
				}
			}
		}
		
		Logger::info( 'openai_response_decoded', 'Successfully decoded API response', [
			'response_structure' => $log_response,
			'response_keys' => array_keys( $decoded_response ),
		] );

		return $decoded_response;
	}

	/**
	 * Generate text using generic prompt.
	 *
	 * @param string $prompt The text prompt.
	 * @param array  $options Optional parameters.
	 * @return array Response with success/failure and content.
	 */
	public function generate_text( $prompt, $options = [] ) {
		try {
			if ( empty( $this->api_key ) ) {
				return [
					'success' => false,
					'message' => __( 'API key is required.', 'ai-blog-generator' ),
				];
			}

			Logger::info( 'openai_text_generation_start', 'Starting OpenAI text generation request', [
				'prompt_length' => strlen( $prompt ),
				'options' => $options
			] );

			// Use GPT-4 for text generation
			$text_api_url = 'https://api.openai.com/v1/chat/completions';

			$request_data = [
				'model' => 'gpt-4-turbo',
				'messages' => [
					[
						'role' => 'user',
						'content' => $prompt,
					],
				],
				'max_tokens' => $options['max_tokens'] ?? 4000,
				'temperature' => $options['temperature'] ?? 0.7,
			];

			// Add optional parameters
			if ( isset( $options['top_p'] ) ) {
				$request_data['top_p'] = (float) $options['top_p'];
			}

			$headers = [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			];

			$args = [
				'method' => 'POST',
				'headers' => $headers,
				'body' => wp_json_encode( $request_data ),
				'timeout' => 300,
			];

			Logger::info( 'openai_text_request', 'Sending text generation request to OpenAI', [
				'url' => $text_api_url,
				'model' => $request_data['model'],
				'max_tokens' => $request_data['max_tokens'],
				'temperature' => $request_data['temperature']
			] );

			$response = wp_remote_request( $text_api_url, $args );

			if ( is_wp_error( $response ) ) {
				Logger::error( 'openai_text_request_error', 'HTTP request failed for text generation', [
					'error_message' => $response->get_error_message()
				] );
				
				return [
					'success' => false,
					'message' => 'HTTP request failed: ' . $response->get_error_message(),
				];
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( $response_code !== 200 ) {
				$error_data = json_decode( $response_body, true );
				$error_message = $error_data['error']['message'] ?? 'Unknown API error';
				
				Logger::error( 'openai_text_api_error', 'OpenAI API returned error for text generation', [
					'response_code' => $response_code,
					'error_message' => $error_message
				] );
				
				return [
					'success' => false,
					'message' => "API error (HTTP {$response_code}): {$error_message}",
				];
			}

			$decoded_response = json_decode( $response_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				Logger::error( 'openai_text_json_error', 'Failed to decode JSON response for text generation', [
					'json_error' => json_last_error_msg()
				] );
				
				return [
					'success' => false,
					'message' => 'Invalid JSON response from API',
				];
			}

			if ( ! isset( $decoded_response['choices'][0]['message']['content'] ) ) {
				Logger::error( 'openai_text_invalid_response', 'Invalid response format for text generation', [
					'response_keys' => array_keys( $decoded_response ),
					'choices_available' => isset( $decoded_response['choices'] )
				] );
				
				return [
					'success' => false,
					'message' => 'Invalid response format from API',
				];
			}

			$content = $decoded_response['choices'][0]['message']['content'];
			$usage = $decoded_response['usage'] ?? [];
			
			// Calculate cost (approximate rates for GPT-4)
			$cost = 0;
			if ( isset( $usage['prompt_tokens'] ) && isset( $usage['completion_tokens'] ) ) {
				$input_cost = ( $usage['prompt_tokens'] / 1000 ) * 0.03; // $0.03 per 1K input tokens
				$output_cost = ( $usage['completion_tokens'] / 1000 ) * 0.06; // $0.06 per 1K output tokens
				$cost = $input_cost + $output_cost;
			}

			Logger::info( 'openai_text_generation_success', 'Text generation completed successfully', [
				'content_length' => strlen( $content ),
				'usage' => $usage,
				'cost' => $cost
			] );

			return [
				'success' => true,
				'content' => $content,
				'cost' => $cost,
				'tokens_used' => ( $usage['prompt_tokens'] ?? 0 ) + ( $usage['completion_tokens'] ?? 0 ),
				'usage' => $usage
			];

		} catch ( \Exception $e ) {
			Logger::error( 'openai_text_generation_exception', 'Exception during text generation', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Generate multiple images in batch (DEPRECATED - use generate_images_sequentially instead).
	 * Backward compatibility alias for the new sequential method.
	 *
	 * @deprecated Use generate_images_sequentially() instead for better progress tracking.
	 * @param array $image_requirements Array of image requirements with prompts and tokens.
	 * @return array Results with success/failure for each image.
	 */
	public function generate_batch_images( $image_requirements ) {
		Logger::warning( 'deprecated_method_used', 'generate_batch_images is deprecated, use generate_images_sequentially instead' );
		
		// Call the new sequential method without progress callback for backward compatibility
		return $this->generate_images_sequentially( $image_requirements );
	}
}
