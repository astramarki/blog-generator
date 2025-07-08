<?php
/**
 * Context Model
 *
 * @package AI_Blog_Generator
 * @subpackage Models
 */

namespace AI_Blog_Generator\Models;

use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Model Class
 *
 * Handles context data operations.
 *
 * @since 1.0.0
 */
class Context_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'contexts';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name',
		'description',
		'type',
		'content',
		'priority',
		'usage_flags',
		'always_include_content',
		'always_include_images',
		'always_include_avada',
		'always_include_html',
		'active',
	];

	/**
	 * Log to debug file for comprehensive debugging
	 */
	private function log_debug( $method, $message, $data = [] ) {
		$debug_log = AI_BLOG_GENERATOR_DEBUG_LOG;
		$timestamp = date( 'Y-m-d H:i:s' );
		$log_entry = "$timestamp - CONTEXT_MODEL::$method - $message\n";
		if ( ! empty( $data ) ) {
			// Create safe representation of data to avoid binary corruption
			$safe_data = $this->make_data_safe_for_logging( $data );
			$log_entry .= "$timestamp - CONTEXT_MODEL::$method - DATA: " . json_encode( $safe_data, JSON_UNESCAPED_SLASHES ) . "\n";
		}
		file_put_contents( $debug_log, $log_entry, FILE_APPEND );
	}
	
	/**
	 * Create a safe representation of data for logging (avoid binary corruption)
	 */
	private function make_data_safe_for_logging( $data ) {
		if ( is_object( $data ) ) {
			if ( isset( $data->id ) && isset( $data->name ) ) {
				return "Object[id={$data->id}, name=" . substr( $data->name ?? 'null', 0, 50 ) . "]";
			}
			return "Object[" . get_class( $data ) . "]";
		}
		
		if ( is_array( $data ) ) {
			$safe_array = [];
			foreach ( $data as $key => $value ) {
				if ( is_string( $value ) && strlen( $value ) > 100 ) {
					$safe_array[$key] = substr( $value, 0, 100 ) . '...[truncated]';
				} elseif ( is_object( $value ) ) {
					$safe_array[$key] = $this->make_data_safe_for_logging( $value );
				} else {
					$safe_array[$key] = $value;
				}
			}
			return $safe_array;
		}
		
		return $data;
	}

	/**
	 * Get active contexts with enhanced filtering and ordering.
	 *
	 * @param string|null $type Context type filter.
	 * @param string|null $usage Usage filter (ideas, content, images).
	 * @param bool        $preserve_structure Whether to preserve individual context structure.
	 * @return array
	 */
	public function get_enhanced_active( $type = null, $usage = null, $preserve_structure = false ) {
		global $wpdb;

		$this->log_debug( 'get_enhanced_active', 'Method called', [
			'type' => $type,
			'usage' => $usage,
			'preserve_structure' => $preserve_structure,
			'memory_usage' => memory_get_usage()
		] );

		try {
			$this->log_debug( 'get_enhanced_active', 'Starting method execution' );
			
			Logger::info( 'context_get_enhanced_active_start', 'Starting enhanced active context query', [
				'type' => $type,
				'usage' => $usage,
				'preserve_structure' => $preserve_structure
			]);

			$this->log_debug( 'get_enhanced_active', 'Checking table existence' );
			// Check if contexts table exists
			$table_name = AI_BLOG_GENERATOR_TABLE_CONTEXTS;
			$this->log_debug( 'get_enhanced_active', 'Table name resolved', [ 'table' => $table_name ] );
			
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
			$this->log_debug( 'get_enhanced_active', 'Table existence check completed', [
				'exists' => ! empty( $table_exists ),
				'table_exists_result' => $table_exists
			] );
			
			if ( ! $table_exists ) {
				$this->log_debug( 'get_enhanced_active', 'Table does not exist, returning empty' );
				Logger::error( 'context_table_missing', 'Contexts table does not exist', [
					'table_name' => $table_name,
					'expected_table' => AI_BLOG_GENERATOR_TABLE_CONTEXTS
				]);
				return $preserve_structure ? [] : [];
			}

			$this->log_debug( 'get_enhanced_active', 'Building SQL query' );
			$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " WHERE active = 1";
			$params = [];

			if ( $type ) {
				$sql .= " AND type = %s";
				$params[] = $type;
				$this->log_debug( 'get_enhanced_active', 'Added type filter', [ 'type' => $type ] );
			}

			if ( $usage ) {
				$sql .= " AND (usage_flags LIKE %s OR usage_flags IS NULL OR usage_flags = '')";
				$params[] = '%' . $usage . '%';
				$this->log_debug( 'get_enhanced_active', 'Added usage filter', [ 'usage' => $usage ] );
			}

			$sql .= " ORDER BY type ASC, priority DESC, name ASC";
			$this->log_debug( 'get_enhanced_active', 'Final SQL built', [
				'sql' => $sql,
				'params' => $params
			] );

			Logger::info( 'context_executing_query', 'Executing context query', [
				'sql' => $sql,
				'params_count' => count( $params ),
				'usage' => $usage,
				'type' => $type
			]);

			$this->log_debug( 'get_enhanced_active', 'About to execute query' );
			if ( ! empty( $params ) ) {
				$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
			} else {
				$results = $wpdb->get_results( $sql, ARRAY_A );
			}
			$this->log_debug( 'get_enhanced_active', 'Query executed', [
				'results_count' => count( $results ?? [] ),
				'results_type' => gettype( $results ),
				'wpdb_last_error' => $wpdb->last_error
			] );

			// Check for database errors
			if ( $wpdb->last_error ) {
				$this->log_debug( 'get_enhanced_active', 'Database error detected', [
					'error' => $wpdb->last_error
				] );
				Logger::error( 'context_query_db_error', 'Database error in context query', [
					'error' => $wpdb->last_error,
					'sql' => $sql,
					'params' => $params
				]);
				return $preserve_structure ? [] : [];
			}

			Logger::info( 'context_query_success', 'Context query executed successfully', [
				'results_count' => count( $results ?? [] ),
				'usage' => $usage,
				'type' => $type
			]);

			$this->log_debug( 'get_enhanced_active', 'Checking preserve_structure flag', [
				'preserve_structure' => $preserve_structure
			] );

			if ( $preserve_structure ) {
				$this->log_debug( 'get_enhanced_active', 'Returning results with preserved structure', [
					'results_count' => count( $results ?? [] )
				] );
				return $results ?? [];
			}

			$this->log_debug( 'get_enhanced_active', 'Starting grouping by type' );
			// Group by type if not preserving structure
			$grouped = [];
			foreach ( $results ?? [] as $index => $context ) {
				$this->log_debug( 'get_enhanced_active', "Processing context $index", [
					'context_type' => $context['type'] ?? 'no_type'
				] );
				$context_type = $context['type'];
				if ( ! isset( $grouped[ $context_type ] ) ) {
					$grouped[ $context_type ] = [];
				}
				$grouped[ $context_type ][] = $context;
			}

			$this->log_debug( 'get_enhanced_active', 'Grouping completed', [
				'grouped_types' => array_keys( $grouped ),
				'total_groups' => count( $grouped )
			] );

			return $grouped;

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_enhanced_active', 'Exception caught', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			
			Logger::error( 'context_get_enhanced_active_failed', 'Failed to get enhanced active contexts', [
				'type' => $type,
				'usage' => $usage,
				'preserve_structure' => $preserve_structure,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			
			// Return empty result instead of throwing exception
			return $preserve_structure ? [] : [];
		}
	}

	/**
	 * Get compiled contexts for specific usage with enhanced organization.
	 *
	 * @param string $usage Usage type (ideas, content, images).
	 * @param array  $type_filters Specific types to include (optional).
	 * @return array Compiled contexts organized by type.
	 */
	public function get_compiled_for_usage( $usage, $type_filters = [] ) {
		$this->log_debug( 'get_compiled_for_usage', 'Method called', [
			'usage' => $usage,
			'type_filters' => $type_filters,
			'memory_usage' => memory_get_usage()
		] );

		try {
			$this->log_debug( 'get_compiled_for_usage', 'Starting method execution' );
			
			Logger::info( 'context_get_compiled_start', 'Starting context compilation for usage', [
				'usage' => $usage,
				'type_filters' => $type_filters
			]);

			$contexts = $this->get_enhanced_active( null, $usage, true );
			
			Logger::info( 'context_enhanced_active_received', 'Enhanced active contexts received', [
				'usage' => $usage,
				'contexts_count' => count( $contexts ),
				'contexts_is_array' => is_array( $contexts )
			]);
			
			$compiled = [
				'general' => [],
				'products' => [],
				'seo' => [],
				'keywords' => [],
				'image' => [],
				'layout' => [],
			];

			// Filter by type if specified
			if ( ! empty( $type_filters ) ) {
				$contexts = array_filter( $contexts, function( $context ) use ( $type_filters ) {
					return in_array( $context['type'], $type_filters, true );
				} );
			}

			// Group contexts by type
			foreach ( $contexts as $context ) {
				$type = $context['type'];
				if ( isset( $compiled[ $type ] ) ) {
					$compiled[ $type ][] = $context;
				}
			}

			// Convert to organized strings with metadata
			$result = [];
			foreach ( $compiled as $type => $type_contexts ) {
				if ( empty( $type_contexts ) ) {
					continue;
				}

				$result[ $type ] = [
					'contexts' => $type_contexts,
					'content' => $this->compile_type_content( $type_contexts ),
					'count' => count( $type_contexts ),
				];
			}

			Logger::info( 'contexts_compiled_for_usage', 'Compiled contexts for specific usage', [
				'usage' => $usage,
				'types_included' => array_keys( $result ),
				'total_contexts' => array_sum( array_column( $result, 'count' ) ),
				'type_filters' => $type_filters,
			] );

			$this->log_debug( 'get_compiled_for_usage', 'Method completed for usage: ' . $usage );

			return $result;

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_compiled_for_usage', 'Exception: ' . $e->getMessage() );
			
			Logger::error( 'context_get_compiled_failed', 'Failed to compile contexts for usage', [
				'usage' => $usage,
				'type_filters' => $type_filters,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			
			// Return empty result instead of throwing exception
			return [];
		}
	}

	/**
	 * Compile content for contexts of a specific type.
	 *
	 * @param array $contexts Array of contexts for a single type.
	 * @return string Compiled content.
	 */
	private function compile_type_content( $contexts ) {
		if ( empty( $contexts ) ) {
			return '';
		}

		// If only one context, return its content directly
		if ( count( $contexts ) === 1 ) {
			return $contexts[0]['content'];
		}

		// Multiple contexts - organize with headers
		$content_parts = [];
		foreach ( $contexts as $context ) {
			$header = "=== {$context['name']} ===";
			if ( ! empty( $context['description'] ) ) {
				$header .= "\n({$context['description']})";
			}
			$content_parts[] = $header . "\n" . $context['content'];
		}

		return implode( "\n\n", $content_parts );
	}

	/**
	 * Get context usage flags.
	 *
	 * @return array Available usage flags.
	 */
	public function get_usage_flags() {
		return [
			'ideas' => __( 'Idea Generation', 'ai-blog-generator' ),
			'content' => __( 'Content Generation', 'ai-blog-generator' ),
			'images' => __( 'Image Generation', 'ai-blog-generator' ),
		];
	}

	/**
	 * Parse usage flags from string.
	 *
	 * @param string $usage_flags Comma-separated usage flags.
	 * @return array Array of usage flags.
	 */
	public function parse_usage_flags( $usage_flags ) {
		if ( empty( $usage_flags ) ) {
			return array_keys( $this->get_usage_flags() ); // Default to all
		}

		$flags = array_map( 'trim', explode( ',', $usage_flags ) );
		$valid_flags = array_keys( $this->get_usage_flags() );

		// Filter to only valid flags
		return array_intersect( $flags, $valid_flags );
	}

	/**
	 * Get priority levels.
	 *
	 * @return array Priority levels with descriptions.
	 */
	public function get_priority_levels() {
		return [
			100 => __( 'Critical (Always First)', 'ai-blog-generator' ),
			75  => __( 'High Priority', 'ai-blog-generator' ),
			50  => __( 'Normal Priority', 'ai-blog-generator' ),
			25  => __( 'Low Priority', 'ai-blog-generator' ),
			0   => __( 'Lowest Priority', 'ai-blog-generator' ),
		];
	}

	/**
	 * Get contexts optimized for prompt building.
	 *
	 * @param string $usage Usage type.
	 * @param array  $options Additional options for compilation.
	 * @return array Optimized context structure for prompt building.
	 */
	public function get_for_prompt( $usage, $options = [] ) {
		$this->log_debug( 'get_for_prompt', 'Method called', [
			'usage' => $usage,
			'options' => $options,
			'memory_usage' => memory_get_usage()
		] );

		try {
			$this->log_debug( 'get_for_prompt', 'Starting method execution' );
			
			Logger::info( 'context_get_for_prompt_start', 'Starting context retrieval for prompt', [
				'usage' => $usage,
				'options' => $options
			]);

			$this->log_debug( 'get_for_prompt', 'Setting up defaults' );
			$defaults = [
				'max_contexts_per_type' => 5,
				'include_metadata' => true,
				'priority_threshold' => 0,
				'type_filters' => [],
			];

			$options = array_merge( $defaults, $options );
			$this->log_debug( 'get_for_prompt', 'Options merged', [ 'final_options' => $options ] );
			
			Logger::info( 'context_calling_get_compiled', 'Calling get_compiled_for_usage', [
				'usage' => $usage,
				'type_filters' => $options['type_filters']
			]);
			
			$this->log_debug( 'get_for_prompt', 'About to call get_compiled_for_usage' );
			$compiled = $this->get_compiled_for_usage( $usage, $options['type_filters'] );
			$this->log_debug( 'get_for_prompt', 'get_compiled_for_usage completed', [
				'compiled_types' => array_keys( $compiled ),
				'compiled_count' => count( $compiled )
			] );

			Logger::info( 'context_compiled_received', 'Compiled contexts received', [
				'usage' => $usage,
				'compiled_types' => array_keys( $compiled ),
				'compiled_count' => count( $compiled )
			]);

			$this->log_debug( 'get_for_prompt', 'Starting result processing loop' );
			$result = [];
			foreach ( $compiled as $type => $data ) {
				$this->log_debug( 'get_for_prompt', "Processing type: $type", [ 'data_keys' => array_keys( $data ) ] );
				
				// Filter by priority threshold
				$filtered_contexts = array_filter( $data['contexts'], function( $context ) use ( $options ) {
					return ( $context['priority'] ?? 50 ) >= $options['priority_threshold'];
				} );

				$this->log_debug( 'get_for_prompt', "Filtered contexts for $type", [ 'count' => count( $filtered_contexts ) ] );

				// Limit number of contexts per type
				if ( $options['max_contexts_per_type'] > 0 ) {
					$filtered_contexts = array_slice( $filtered_contexts, 0, $options['max_contexts_per_type'] );
					$this->log_debug( 'get_for_prompt', "Limited contexts for $type", [ 'final_count' => count( $filtered_contexts ) ] );
				}

				if ( empty( $filtered_contexts ) ) {
					$this->log_debug( 'get_for_prompt', "No contexts for $type, skipping" );
					continue;
				}

				$this->log_debug( 'get_for_prompt', "Compiling content for $type" );
				$result[ $type ] = [
					'content' => $this->compile_type_content( $filtered_contexts ),
					'context_count' => count( $filtered_contexts ),
				];

				if ( $options['include_metadata'] ) {
					$this->log_debug( 'get_for_prompt', "Adding metadata for $type" );
					$result[ $type ]['metadata'] = [
						'contexts' => array_map( function( $context ) {
							return [
								'name' => $context['name'],
								'priority' => $context['priority'] ?? 50,
								'description' => $context['description'] ?? '',
							];
						}, $filtered_contexts ),
					];
				}
				
				$this->log_debug( 'get_for_prompt', "Completed processing for $type" );
			}

			$this->log_debug( 'get_for_prompt', 'Processing loop completed', [
				'result_types' => array_keys( $result )
			] );

			Logger::info( 'context_get_for_prompt_success', 'Context retrieval for prompt completed', [
				'usage' => $usage,
				'result_types' => array_keys( $result ),
				'total_contexts' => array_sum( array_column( $result, 'context_count' ) )
			]);

			$this->log_debug( 'get_for_prompt', 'Method completing successfully', [
				'result_types' => array_keys( $result ),
				'total_types' => count( $result )
			] );

			return $result;

		} catch ( \Exception $e ) {
			$this->log_debug( 'get_for_prompt', 'Exception caught', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			
			Logger::error( 'context_get_for_prompt_failed', 'Failed to get contexts for prompt', [
				'usage' => $usage,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			
			// Return empty result instead of throwing exception to prevent 500 errors
			return [];
		}
	}

	/**
	 * Get contexts by type.
	 *
	 * @param string $type Context type.
	 * @return array
	 */
	public function get_by_type( $type ) {
		return $this->find_all( [ 'type' => $type ], 'name ASC' );
	}

	/**
	 * Toggle context active status.
	 *
	 * @param int $id Context ID.
	 * @return bool
	 */
	public function toggle_active( $id ) {
		$context = $this->find( $id );
		
		if ( ! $context ) {
			return false;
		}

		$new_status = ! (bool) $context->active;
		return $this->update( $id, [ 'active' => $new_status ] );
	}

	/**
	 * Activate context.
	 *
	 * @param int $id Context ID.
	 * @return bool
	 */
	public function activate( $id ) {
		return $this->update( $id, [ 'active' => 1 ] );
	}

	/**
	 * Deactivate context.
	 *
	 * @param int $id Context ID.
	 * @return bool
	 */
	public function deactivate( $id ) {
		return $this->update( $id, [ 'active' => 0 ] );
	}

	/**
	 * Get seed images associated with contexts.
	 *
	 * @return array
	 */
	public function get_with_seed_images() {
		global $wpdb;

		$sql = "SELECT c.*, 
				GROUP_CONCAT(
					CONCAT(si.id, ':', si.product_name, ':', si.image_url) 
					SEPARATOR '||'
				) as seed_images
			FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " c
			LEFT JOIN " . AI_BLOG_GENERATOR_TABLE_SEED_IMAGES . " si ON c.id = si.context_id
			WHERE c.type = 'products'
			GROUP BY c.id
			ORDER BY c.name";

		$results = $wpdb->get_results( $sql );

		// Parse seed images.
		foreach ( $results as &$context ) {
			$context->seed_images_array = [];
			if ( ! empty( $context->seed_images ) ) {
				$images = explode( '||', $context->seed_images );
				foreach ( $images as $image_data ) {
					$parts = explode( ':', $image_data, 3 );
					if ( count( $parts ) === 3 ) {
						$context->seed_images_array[] = [
							'id' => $parts[0],
							'product_name' => $parts[1],
							'image_url' => $parts[2],
						];
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Duplicate a context.
	 *
	 * @param int $id Context ID to duplicate.
	 * @return int|false New context ID or false on failure.
	 */
	public function duplicate( $id ) {
		$context = $this->find( $id );
		
		if ( ! $context ) {
			return false;
		}

		$new_data = [
			'name' => $context->name . ' (Copy)',
			'type' => $context->type,
			'content' => $context->content,
			'active' => 0, // Start as inactive.
		];

		return $this->create( $new_data );
	}

	/**
	 * Get context types.
	 *
	 * @return array
	 */
	public function get_types() {
		return [
			'general'  => __( 'General Context', 'ai-blog-generator' ),
			'products' => __( 'Products/Services', 'ai-blog-generator' ),
			'seo'      => __( 'SEO Guidelines', 'ai-blog-generator' ),
			'keywords' => __( 'Keywords', 'ai-blog-generator' ),
			'image'    => __( 'Image Guidelines', 'ai-blog-generator' ),
			'layout'   => __( 'Layout Guidelines', 'ai-blog-generator' ),
		];
	}

	/**
	 * Count active contexts by type.
	 *
	 * @return array
	 */
	public function count_active_by_type() {
		global $wpdb;

		$sql = "SELECT type, COUNT(*) as count
			FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . "
			WHERE active = 1
			GROUP BY type";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$counts = array_fill_keys( array_keys( $this->get_types() ), 0 );
		
		foreach ( $results as $row ) {
			$counts[ $row['type'] ] = (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Get type statistics.
	 *
	 * @return array
	 */
	public function get_type_statistics() {
		global $wpdb;

		$sql = "SELECT 
			type, 
			COUNT(*) as total_count,
			SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count,
			SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive_count
			FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . "
			GROUP BY type
			ORDER BY type";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$stats = [];
		$type_labels = $this->get_types();
		
		foreach ( $results as $row ) {
			$stats[ $row['type'] ] = [
				'type' => $row['type'],
				'label' => $type_labels[ $row['type'] ] ?? $row['type'],
				'total' => (int) $row['total_count'],
				'active' => (int) $row['active_count'],
				'inactive' => (int) $row['inactive_count'],
			];
		}

		return $stats;
	}

	/**
	 * Validate data.
	 *
	 * @param array    $data Data to validate.
	 * @param int|null $id   Record ID for updates.
	 * @return bool
	 */
	protected function validate( $data, $id = null ) {
		// Name is required.
		if ( isset( $data['name'] ) && empty( $data['name'] ) ) {
			return false;
		}

		// Validate type.
		if ( isset( $data['type'] ) ) {
			$valid_types = array_keys( $this->get_types() );
			if ( ! in_array( $data['type'], $valid_types, true ) ) {
				return false;
			}
		}

		// Content is required.
		if ( isset( $data['content'] ) && empty( trim( $data['content'] ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return mixed
	 */
	protected function sanitize_field( $field, $value ) {
		switch ( $field ) {
			case 'name':
				return sanitize_text_field( $value );
			
			case 'description':
				return sanitize_textarea_field( $value );
			
			case 'type':
				return sanitize_key( $value );
			
			case 'content':
				// For content, preserve everything exactly as entered
				// This allows JavaScript, HTML, Avada shortcodes, etc. to be stored without modification
				return wp_unslash( $value );
			
			case 'priority':
				return absint( $value );
			
			case 'usage_flags':
				// Ensure single usage category
				$valid_usage = ['ideas', 'content', 'images'];
				$value = sanitize_text_field( $value );
				return in_array( $value, $valid_usage, true ) ? $value : 'content';
			

			
			case 'active':
			case 'always_include_content':
			case 'always_include_images':
			case 'always_include_avada':
			case 'always_include_html':
				return (int) (bool) $value;
			
			default:
				return parent::sanitize_field( $field, $value );
		}
	}

	/**
	 * Format a record for output.
	 *
	 * @param object $record Raw record.
	 * @return array Formatted record.
	 */
	public function format( $record ) {
		$formatted = parent::format( $record );
		
		if ( $formatted ) {
			// Format boolean fields
			$boolean_fields = ['active', 'always_include_content', 'always_include_images', 'always_include_avada', 'always_include_html'];
			foreach ( $boolean_fields as $field ) {
				if ( isset( $formatted[ $field ] ) ) {
					$formatted[ $field ] = (bool) $formatted[ $field ];
				}
			}
		}

		return $formatted;
	}

	/**
	 * Get active contexts (backward compatibility).
	 *
	 * @param string|null $type Context type filter.
	 * @return array
	 */
	public function get_active( $type = null ) {
		// Use the enhanced method for backward compatibility
		$grouped = $this->get_enhanced_active( $type, null, false );
		
		// Convert to the old format for backward compatibility
		$results = [];
		foreach ( $grouped as $type_contexts ) {
			foreach ( $type_contexts as $context ) {
				$results[] = (object) $context;
			}
		}
		
		return $results;
	}

	/**
	 * Get contexts that should always be included for content generation.
	 *
	 * @return array
	 */
	public function get_always_include_content() {
		global $wpdb;
		
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " 
				WHERE active = 1 AND always_include_content = 1 
				ORDER BY type ASC, priority DESC, name ASC";
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		Logger::info( 'always_include_content_retrieved', 'Retrieved always-include content contexts', [
			'count' => count( $results ),
		] );
		
		return $results;
	}

	/**
	 * Get contexts that should always be included for image generation.
	 *
	 * @return array
	 */
	public function get_always_include_images() {
		global $wpdb;
		
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " 
				WHERE active = 1 AND always_include_images = 1 
				ORDER BY type ASC, priority DESC, name ASC";
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		Logger::info( 'always_include_images_retrieved', 'Retrieved always-include image contexts', [
			'count' => count( $results ),
		] );
		
		return $results;
	}

	/**
	 * Get contexts that should always be included for Avada layouts.
	 *
	 * @return array
	 */
	public function get_always_include_avada() {
		global $wpdb;
		
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " 
				WHERE active = 1 AND always_include_avada = 1 
				ORDER BY type ASC, priority DESC, name ASC";
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		Logger::info( 'always_include_avada_retrieved', 'Retrieved always-include Avada contexts', [
			'count' => count( $results ),
		] );
		
		return $results;
	}

	/**
	 * Get contexts that should always be included for HTML layouts.
	 *
	 * @return array
	 */
	public function get_always_include_html() {
		global $wpdb;
		
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " 
				WHERE active = 1 AND always_include_html = 1 
				ORDER BY type ASC, priority DESC, name ASC";
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		Logger::info( 'always_include_html_retrieved', 'Retrieved always-include HTML contexts', [
			'count' => count( $results ),
		] );
		
		return $results;
	}

	/**
	 * Get contexts for a specific persona including always-include contexts.
	 *
	 * @param int    $persona_id Persona ID.
	 * @param string $usage      Usage type ('content' or 'images').
	 * @return array
	 */
	public function get_contexts_for_persona( $persona_id, $usage = 'content' ) {
		global $wpdb;
		
		// Get persona-specific contexts
		$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
		$persona = $persona_model->find( $persona_id );
		
		$context_ids = [];
		if ( $persona && ! empty( $persona->include_contexts ) ) {
			$context_ids = array_map( 'intval', explode( ',', $persona->include_contexts ) );
		}
		
		// Build query to get all relevant contexts
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " WHERE active = 1 AND (";
		
		// Always include contexts based on usage
		if ( $usage === 'content' ) {
			$sql .= "always_include_content = 1";
		} else {
			$sql .= "always_include_images = 1";
		}
		
		// Add persona-specific contexts
		if ( ! empty( $context_ids ) ) {
			$sql .= " OR id IN (" . implode( ',', $context_ids ) . ")";
		}
		
		$sql .= ") ORDER BY type ASC, priority DESC, name ASC";
		
		$results = $wpdb->get_results( $sql, ARRAY_A );
		
		Logger::info( 'persona_contexts_retrieved', 'Retrieved contexts for persona', [
			'persona_id' => $persona_id,
			'usage' => $usage,
			'count' => count( $results ),
			'persona_specific_ids' => $context_ids,
		] );
		
		return $results;
	}

	/**
	 * Get compiled contexts for AI generation (backward compatibility).
	 *
	 * @return array Associative array with context types as keys.
	 */
	public function get_compiled_contexts() {
		$compiled_data = $this->get_compiled_for_usage( 'content' );
		
		// Convert to old format for backward compatibility
		$result = [];
		foreach ( $compiled_data as $type => $data ) {
			$result[ $type ] = $data['content'];
		}
		
		return $result;
	}

	/**
	 * Get active contexts for database manager compatibility.
	 *
	 * @param string|null $type Context type filter.
	 * @return array
	 */
	public function get_active_contexts( $type = null ) {
		return $this->get_active( $type );
	}

	/**
	 * Check if table exists
	 *
	 * @return bool
	 */
	private function table_exists() {
		$this->log_debug( 'table_exists', 'Method called', [
			'memory_usage' => memory_get_usage()
		] );

		try {
			global $wpdb;

			$table_name = $this->get_table_name();
			$this->log_debug( 'table_exists', 'Checking table existence', [ 'table' => $table_name ] );

			$sql = $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name );
			$this->log_debug( 'table_exists', 'SQL query prepared', [ 'sql' => $sql ] );

			$result = $wpdb->get_var( $sql );
			$exists = ! empty( $result );

			$this->log_debug( 'table_exists', 'Table existence check completed', [
				'exists' => $exists,
				'result' => $result,
				'wpdb_last_error' => $wpdb->last_error
			] );

			return $exists;

		} catch ( \Exception $e ) {
			$this->log_debug( 'table_exists', 'Exception in table_exists method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			return false;
		}
	}
} 