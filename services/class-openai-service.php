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
	 */
	public function __construct() {
		$this->api_key = get_option( 'ai_blog_generator_openai_api_key', '' );
		$this->cost_model = new Cost_Model();
	}

	/**
	 * Check if running in local environment.
	 *
	 * @return bool True if local environment.
	 */
	private function is_local_environment() {
		$site_url = get_site_url();
		return (
			strpos( $site_url, 'localhost' ) !== false ||
			strpos( $site_url, '127.0.0.1' ) !== false ||
			strpos( $site_url, '.local' ) !== false ||
			( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV )
		);
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
			] );
			
			$api_call_start = microtime( true );
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
				throw new \Exception( 'Failed to download seed image' );
			}

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
			] );

			// Make multipart request to edits endpoint
			$response = $this->make_multipart_request( $form_data, $temp_file );

			// Clean up temporary file
			unlink( $temp_file );

			if ( ! isset( $response['data'][0] ) ) {
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
				'seed_image_url' => $seed_image_url,
				'prompt_length' => strlen( $prompt ),
			] );

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
		
		// Disable SSL verification for local environments
		if ( $this->is_local_environment() ) {
			$args['sslverify'] = false;
		}
		
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
			'timeout' => 300, // 5 minute timeout for image editing
		];
		
		// Disable SSL verification for local environments
		if ( $this->is_local_environment() ) {
			$args['sslverify'] = false;
		}

		Logger::info( 'openai_multipart_request', 'Sending multipart request to OpenAI edits endpoint', [
			'url' => $this->api_edit_url,
			'form_data' => $form_data,
			'body_size' => strlen( $body ),
		] );

		$response = wp_remote_request( $this->api_edit_url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error( 'openai_multipart_request_error', 'HTTP request failed', [
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
			
			Logger::error( 'openai_edits_api_error', 'API returned error response', [
				'response_code' => $response_code,
				'error_data' => $error_data,
				'error_message' => $error_message,
			] );
			
			throw new \Exception( "API error (HTTP {$response_code}): {$error_message}" );
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::error( 'openai_edits_json_decode_error', 'Failed to decode JSON response', [
				'json_error' => json_last_error_msg(),
				'response_preview' => substr( $response_body, 0, 200 ) . '...',
			] );
			throw new \Exception( 'Invalid JSON response from API' );
		}

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
		
		// Disable SSL verification for local environments
		if ( $this->is_local_environment() ) {
			$args['sslverify'] = false;
		}
		
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
	 * @return array Generated image result.
	 */
	public function generate_blog_image( $title, $description, $seed_images = [] ) {
		// Build prompt for blog image
		$prompt = $this->build_image_prompt( $title, $description, $seed_images );

		// Generate image with blog-appropriate settings
		$options = [
			'size' => '1024x1024',
			'quality' => 'standard',
		];

		$result = $this->generate_image( $prompt, $options );

		if ( $result['success'] ) {
			// Generate filename based on title
			$filename = $this->generate_filename( $title );
			
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
	 * @return string Generated filename.
	 */
	private function generate_filename( $title ) {
		// Clean title for filename
		$filename = sanitize_file_name( $title );
		$filename = preg_replace( '/[^a-zA-Z0-9\-_]/', '-', $filename );
		$filename = preg_replace( '/-+/', '-', $filename );
		$filename = trim( $filename, '-' );
		
		// Add timestamp to ensure uniqueness
		$filename .= '-' . time();
		
		return strtolower( $filename );
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
			
			// Calculate progress percentage for this image
			$progress_percentage = round( ( $index / $total_images ) * 100 );
			
			// Call progress callback if provided
			if ( $progress_callback ) {
				call_user_func( $progress_callback, [
					'stage' => 'images',
					'message' => "Generating image {$image_number} of {$total_images}: " . substr( $prompt, 0, 50 ) . '...',
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

			try {
				// Use seed image editing if we have a seed image
			if ( $has_seed_images && $seed_image_used ) {
					Logger::info( 'sequential_seed_image_edit', "Starting seed image edit for image {$image_number}", [
					'token' => $token,
					'seed_image_url' => $seed_image_used,
						'prompt_preview' => substr( $prompt, 0, 100 ),
				] );
				
				$image_result = $this->edit_image( $prompt, $seed_image_used );
				
					Logger::info( 'sequential_seed_edit_result', "Seed image edit completed for image {$image_number}", [
					'token' => $token,
					'success' => $image_result['success'] ?? false,
					'error_message' => $image_result['message'] ?? 'none',
				] );
			} else {
					Logger::info( 'sequential_standard_generation', "Starting standard generation for image {$image_number}", [
					'token' => $token,
						'prompt_preview' => substr( $prompt, 0, 100 ),
				] );
				
				$image_result = $this->generate_image( $prompt );
				
					Logger::info( 'sequential_standard_result', "Standard generation completed for image {$image_number}", [
					'token' => $token,
					'success' => $image_result['success'] ?? false,
					'error_message' => $image_result['message'] ?? 'none',
				] );
			}

			if ( $image_result['success'] ) {
				// Generate filename based on token and timestamp
					$filename = 'ai-blog-image-' . sanitize_file_name( str_replace( [ '{{', '}}' ], '', $token ) ) . '-' . time() . '-' . $index;
					
					// Update progress: saving image
					if ( $progress_callback ) {
						call_user_func( $progress_callback, [
							'stage' => 'images',
							'message' => "Saving image {$image_number} of {$total_images} to media library...",
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
				$results['results'][] = [
					'success' => false,
					'token' => $token,
					'error' => 'Exception: ' . $e->getMessage(),
					'stage' => 'exception',
					'index' => $index,
				];
				
				$results['summary']['failed']++;
				
				Logger::error( 'sequential_image_exception', "Exception during image {$image_number} generation", [
					'token' => $token,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				] );
				
				// Update progress: exception occurred
				if ( $progress_callback ) {
					call_user_func( $progress_callback, [
						'stage' => 'images',
						'message' => "Error generating image {$image_number} of {$total_images}: " . $e->getMessage(),
						'progress' => round( ( ( $index + 1 ) / $total_images ) * 100 ),
						'current_image' => $image_number,
						'total_images' => $total_images,
						'token' => $token,
						'failed' => true
					] );
				}
			}

			// Add a small delay between requests to avoid rate limiting (but not after the last image)
			if ( $index < count( $image_requirements ) - 1 ) {
				sleep( 2 ); // 2-second delay between images
			}
			
			// Check for overall timeout (individual timeout per image: 3 minutes max)
			$total_elapsed = microtime( true ) - $generation_start_time;
			if ( $total_elapsed > 540 ) { // 9 minutes total maximum
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
			'timeout' => 300, // 5 minute timeout for image generation
		];
		
		// Disable SSL verification for local environments
		if ( $this->is_local_environment() ) {
			$args['sslverify'] = false;
			Logger::info( 'openai_ssl_disabled', 'SSL verification DISABLED for local environment' );
		}

		// Check for global cancellation before making request
		if ( get_transient( 'ai_blog_global_cancel_flag' ) ) {
			Logger::warning( 'openai_request_cancelled', 'Request cancelled due to global cancellation flag' );
			
			// Log to debug file
			$debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
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
		
		$response = wp_remote_request( $this->api_url, $args );
		
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
			
			// Disable SSL verification for local environments
			if ( $this->is_local_environment() ) {
				$args['sslverify'] = false;
			}

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
