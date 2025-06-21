<?php
/**
 * Persona Model
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
 * Persona Model Class
 *
 * Handles persona data operations.
 *
 * @since 1.5.0
 */
class Persona_Model extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'personas';

	/**
	 * Fillable fields.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name',
		'bio',
		'expertise',
		'writing_style',
		'tone',
		'layout_style',
		'layout_rules',
		'wordpress_user_id',
		'active',
		'uses_seed_mages',
		'number_of_images',
		'uses_charts',
		'uses_avada_layouts',
		'uses_plain_html',
		'include_contexts',
	];

	/**
	 * Get active personas.
	 *
	 * @return array
	 */
	public function get_active_personas() {
		$personas = $this->find_all( [ 'active' => 1 ], 'name ASC' );
		
		Logger::info( 'active_personas_retrieved', 'Retrieved active personas', [
			'count' => count( $personas ),
		] );
		
		return $personas;
	}

	/**
	 * Get personas by expertise keywords.
	 *
	 * @param array|string $keywords Keywords to search for.
	 * @return array Matching personas.
	 */
	public function get_by_expertise( $keywords ) {
		global $wpdb;
		
		if ( is_string( $keywords ) ) {
			$keywords = [ $keywords ];
		}

		$where_clauses = [];
		$values = [];
		
		foreach ( $keywords as $keyword ) {
			$where_clauses[] = 'expertise LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $keyword ) . '%';
		}

		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_PERSONAS . "
				WHERE active = 1 
				AND (" . implode( ' OR ', $where_clauses ) . ")
				ORDER BY name ASC";

		if ( ! empty( $values ) ) {
			$prepared = $wpdb->prepare( $sql, $values );
			$results = $wpdb->get_results( $prepared, ARRAY_A );
		} else {
			$results = [];
		}

		return $results;
	}

	/**
	 * Find the best matching persona for an idea.
	 *
	 * @param array $idea The idea data with title, description, etc.
	 * @return int|null Persona ID or null if no match.
	 */
	public function find_best_match_for_idea( $idea ) {
		$personas = $this->get_active_personas();
		
		if ( empty( $personas ) ) {
			Logger::warning( 'no_active_personas', 'No active personas available for matching' );
			return null;
		}

		$scores = [];
		$idea_text = strtolower( $idea['title'] . ' ' . $idea['description'] . ' ' . ( $idea['keywords'] ?? '' ) );

		foreach ( $personas as $persona ) {
			$score = 0;
			
			// Score based on expertise matching
			if ( ! empty( $persona->expertise ) ) {
				$expertise_keywords = array_map( 'trim', explode( ',', strtolower( $persona->expertise ) ) );
				foreach ( $expertise_keywords as $keyword ) {
					if ( ! empty( $keyword ) && strpos( $idea_text, $keyword ) !== false ) {
						$score += 10;
					}
				}
			}

			// Additional scoring based on category if available
			if ( ! empty( $idea['category_name'] ) && ! empty( $persona->expertise ) ) {
				if ( stripos( $persona->expertise, $idea['category_name'] ) !== false ) {
					$score += 5;
				}
			}

			// Score based on tone appropriateness
			if ( $this->is_tone_appropriate( $idea, $persona->tone ) ) {
				$score += 3;
			}

			$scores[ $persona->id ] = $score;
		}

		// Get the persona with the highest score
		if ( empty( $scores ) || max( $scores ) === 0 ) {
			// If no matches, return the first active persona as fallback
			Logger::info( 'persona_fallback_selection', 'No matching persona found, using fallback', [
				'idea_title' => $idea['title'],
			] );
			return $personas[0]->id;
		}

		arsort( $scores );
		$best_persona_id = key( $scores );

		Logger::info( 'persona_selected', 'Best matching persona selected', [
			'idea_title' => $idea['title'],
			'persona_id' => $best_persona_id,
			'score' => $scores[ $best_persona_id ],
			'all_scores' => $scores,
		] );

		return $best_persona_id;
	}

	/**
	 * Check if tone is appropriate for the idea topic.
	 *
	 * @param array  $idea Idea data.
	 * @param string $tone Persona tone (can be comma-separated for multiple tones).
	 * @return bool
	 */
	private function is_tone_appropriate( $idea, $tone ) {
		$idea_text = strtolower( $idea['title'] . ' ' . $idea['description'] );
		
		// Map tones to appropriate topic keywords
		$tone_mappings = [
			'professional' => [ 'business', 'corporate', 'professional', 'industry', 'enterprise' ],
			'friendly' => [ 'community', 'family', 'fun', 'social', 'personal' ],
			'analytical' => [ 'data', 'analysis', 'research', 'study', 'metrics' ],
			'inspirational' => [ 'motivate', 'inspire', 'success', 'achieve', 'dream' ],
			'casual' => [ 'lifestyle', 'everyday', 'simple', 'easy', 'relax' ],
			'academic' => [ 'education', 'learning', 'study', 'research', 'academic' ],
		];

		// Handle multiple tones
		$tones = strpos( $tone, ',' ) !== false ? explode( ',', $tone ) : [ $tone ];
		
		foreach ( $tones as $single_tone ) {
			$single_tone = trim( $single_tone );
			
			if ( ! isset( $tone_mappings[ $single_tone ] ) ) {
				continue; // Skip unmapped tones
			}

			foreach ( $tone_mappings[ $single_tone ] as $keyword ) {
				if ( strpos( $idea_text, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Toggle persona active status.
	 *
	 * @param int $id Persona ID.
	 * @return bool
	 */
	public function toggle_active( $id ) {
		$persona = $this->find( $id );
		
		if ( ! $persona ) {
			return false;
		}

		$new_status = ! (bool) $persona->active;
		$result = $this->update( $id, [ 'active' => $new_status ] );
		
		if ( $result ) {
			Logger::info( 'persona_status_toggled', 'Persona active status toggled', [
				'persona_id' => $id,
				'new_status' => $new_status,
			] );
		}
		
		return $result;
	}

	/**
	 * Get available tone options.
	 *
	 * @return array
	 */
	public function get_tone_options() {
		return [
			'professional' => __( 'Professional', 'ai-blog-generator' ),
			'friendly' => __( 'Friendly', 'ai-blog-generator' ),
			'analytical' => __( 'Analytical', 'ai-blog-generator' ),
			'inspirational' => __( 'Inspirational', 'ai-blog-generator' ),
			'casual' => __( 'Casual', 'ai-blog-generator' ),
			'academic' => __( 'Academic', 'ai-blog-generator' ),
		];
	}

	/**
	 * Get persona statistics.
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;

		$stats = [
			'total' => $this->count(),
			'active' => $this->count( [ 'active' => 1 ] ),
			'inactive' => $this->count( [ 'active' => 0 ] ),
		];

		// Get tone distribution
		$sql = "SELECT tone, COUNT(*) as count 
				FROM " . AI_BLOG_GENERATOR_TABLE_PERSONAS . "
				GROUP BY tone";
		
		$tone_results = $wpdb->get_results( $sql, ARRAY_A );
		$stats['by_tone'] = [];
		
		foreach ( $tone_results as $row ) {
			$stats['by_tone'][ $row['tone'] ] = (int) $row['count'];
		}

		// Get post count by persona
		$sql = "SELECT p.id, p.name, COUNT(gp.id) as post_count
				FROM " . AI_BLOG_GENERATOR_TABLE_PERSONAS . " p
				LEFT JOIN " . AI_BLOG_GENERATOR_TABLE_POSTS . " gp ON p.id = gp.persona_id
				GROUP BY p.id
				ORDER BY post_count DESC";
		
		$post_results = $wpdb->get_results( $sql, ARRAY_A );
		$stats['post_counts'] = $post_results;

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
		// Name is required
		if ( isset( $data['name'] ) && empty( trim( $data['name'] ) ) ) {
			Logger::error( 'persona_validation_failed', 'Persona name is required' );
			return false;
		}

		// Bio is required
		if ( isset( $data['bio'] ) && empty( trim( $data['bio'] ) ) ) {
			Logger::error( 'persona_validation_failed', 'Persona bio is required' );
			return false;
		}

		// Validate tone if provided
		if ( isset( $data['tone'] ) && ! empty( $data['tone'] ) ) {
			$valid_tones = array_keys( $this->get_tone_options() );
			
			// Handle multiple tones separated by commas
			if ( strpos( $data['tone'], ',' ) !== false ) {
				$tones = explode( ',', $data['tone'] );
				foreach ( $tones as $tone ) {
					$tone = trim( $tone );
					if ( ! empty( $tone ) && ! in_array( $tone, $valid_tones, true ) ) {
						Logger::error( 'persona_validation_failed', 'Invalid tone value', [
							'invalid_tone' => $tone,
							'provided_tone' => $data['tone'],
							'valid_tones' => $valid_tones,
						] );
						return false;
					}
				}
			} else {
				// Single tone validation
				if ( ! in_array( $data['tone'], $valid_tones, true ) ) {
					Logger::error( 'persona_validation_failed', 'Invalid tone value', [
						'provided_tone' => $data['tone'],
						'valid_tones' => $valid_tones,
					] );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return mixed Sanitized value.
	 */
	protected function sanitize_field( $field, $value ) {
		switch ( $field ) {
			case 'name':
			case 'layout_rules':
			case 'include_contexts':
				return sanitize_text_field( $value );
				
			case 'bio':
			case 'expertise':
			case 'writing_style':
			case 'layout_style':
				return sanitize_textarea_field( $value );
				
			case 'tone':
				// Handle multiple tones separated by commas
				if ( strpos( $value, ',' ) !== false ) {
					$tones = explode( ',', $value );
					$sanitized_tones = array_map( 'sanitize_key', $tones );
					return implode( ',', array_filter( $sanitized_tones ) );
				}
				return sanitize_key( $value );
				
			case 'wordpress_user_id':
			case 'number_of_images':
				return $value ? absint( $value ) : null;
				
			case 'active':
			case 'uses_seed_mages':
			case 'uses_charts':
			case 'uses_avada_layouts':
			case 'uses_plain_html':
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
		if ( ! $record ) {
			return null;
		}

		$formatted = parent::format( $record );
		
		// Ensure boolean fields are properly formatted
		$boolean_fields = ['active', 'uses_seed_mages', 'uses_charts', 'uses_avada_layouts', 'uses_plain_html'];
		foreach ( $boolean_fields as $field ) {
			if ( isset( $formatted[ $field ] ) ) {
				$formatted[ $field ] = (bool) $formatted[ $field ];
			}
		}

		return $formatted;
	}

	/**
	 * Create WordPress user for persona if needed.
	 *
	 * @param array $persona_data Persona data.
	 * @return int|null WordPress user ID or null on failure.
	 */
	public function create_wordpress_user_for_persona( $persona_data ) {
		// Generate username from persona name
		$base_username = sanitize_user( strtolower( str_replace( ' ', '_', $persona_data['name'] ) ), true );
		$username = $base_username;
		$counter = 1;
		
		// Ensure unique username
		while ( username_exists( $username ) ) {
			$username = $base_username . '_' . $counter;
			$counter++;
		}
		
		// Generate email
		$domain = parse_url( home_url(), PHP_URL_HOST );
		$email = $username . '@' . $domain;
		
		// Create user data
		$user_data = [
			'user_login' => $username,
			'user_email' => $email,
			'user_pass' => wp_generate_password( 12, true ),
			'display_name' => $persona_data['name'],
			'nickname' => $persona_data['name'],
			'first_name' => explode( ' ', $persona_data['name'] )[0],
			'last_name' => count( explode( ' ', $persona_data['name'] ) ) > 1 ? explode( ' ', $persona_data['name'] )[1] : '',
			'description' => $persona_data['bio'],
			'role' => 'author',
		];
		
		// Create the user
		$user_id = wp_insert_user( $user_data );
		
		if ( is_wp_error( $user_id ) ) {
			Logger::error( 'wordpress_user_creation_failed', 'Failed to create WordPress user for persona', [
				'persona_name' => $persona_data['name'],
				'error' => $user_id->get_error_message(),
			] );
			return null;
		}
		
		// Add user meta for AI persona identification
		update_user_meta( $user_id, 'ai_blog_generator_persona', true );
		update_user_meta( $user_id, 'ai_blog_generator_persona_name', $persona_data['name'] );
		
		Logger::info( 'wordpress_user_created', 'WordPress user created for persona', [
			'persona_name' => $persona_data['name'],
			'user_id' => $user_id,
			'username' => $username,
		] );
		
		return $user_id;
	}

	/**
	 * Override create method to handle WordPress user creation.
	 *
	 * @param array $data Data to create.
	 * @return int|false Insert ID or false on failure.
	 */
	public function create( $data ) {
		// Check if WordPress user ID is provided
		if ( empty( $data['wordpress_user_id'] ) ) {
			// Create WordPress user
			$user_id = $this->create_wordpress_user_for_persona( $data );
			if ( $user_id ) {
				$data['wordpress_user_id'] = $user_id;
			}
		}
		
		// Call parent create method
		return parent::create( $data );
	}

	/**
	 * Override update method to handle WordPress user creation.
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Data to update.
	 * @return bool Success status.
	 */
	public function update( $id, $data ) {
		// Get existing persona
		$existing = $this->find( $id );
		
		// Check if we need to create a WordPress user
		if ( $existing && empty( $existing->wordpress_user_id ) && empty( $data['wordpress_user_id'] ) ) {
			// Get full persona data for user creation
			$persona_data = array_merge( (array) $existing, $data );
			
			// Create WordPress user
			$user_id = $this->create_wordpress_user_for_persona( $persona_data );
			if ( $user_id ) {
				$data['wordpress_user_id'] = $user_id;
			}
		}
		
		// Call parent update method
		return parent::update( $id, $data );
	}
} 