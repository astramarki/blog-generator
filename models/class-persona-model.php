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
		'active',
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
	 * @param string $tone Persona tone.
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

		if ( ! isset( $tone_mappings[ $tone ] ) ) {
			return true; // Default to appropriate if tone not mapped
		}

		foreach ( $tone_mappings[ $tone ] as $keyword ) {
			if ( strpos( $idea_text, $keyword ) !== false ) {
				return true;
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
		if ( isset( $data['tone'] ) ) {
			$valid_tones = array_keys( $this->get_tone_options() );
			if ( ! in_array( $data['tone'], $valid_tones, true ) ) {
				Logger::error( 'persona_validation_failed', 'Invalid tone value', [
					'provided_tone' => $data['tone'],
					'valid_tones' => $valid_tones,
				] );
				return false;
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
				return sanitize_text_field( $value );
				
			case 'bio':
			case 'expertise':
			case 'writing_style':
				return sanitize_textarea_field( $value );
				
			case 'tone':
				return sanitize_key( $value );
				
			case 'active':
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
		if ( isset( $formatted['active'] ) ) {
			$formatted['active'] = (bool) $formatted['active'];
		}

		return $formatted;
	}
} 