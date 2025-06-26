<?php
/**
 * Prompt Compiler Service
 *
 * Handles compilation of system and user prompts for AI content generation.
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Product_Model;
use AI_Blog_Generator\Models\Brand_Feature_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Loggable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prompt Compiler Service Class
 *
 * Compiles comprehensive system and user prompts for AI content generation.
 *
 * @since 1.0.0
 */
class Prompt_Compiler_Service {

	use Loggable;

	/**
	 * Blog Ideas model instance.
	 *
	 * @var Blog_Ideas_Model_V2
	 */
	private $idea_model;

	/**
	 * Persona model instance.
	 *
	 * @var Persona_Model
	 */
	private $persona_model;

	/**
	 * Context model instance.
	 *
	 * @var Context_Model
	 */
	private $context_model;

	/**
	 * Product model instance.
	 *
	 * @var Product_Model
	 */
	private $product_model;

	/**
	 * Brand Feature model instance.
	 *
	 * @var Brand_Feature_Model
	 */
	private $brand_feature_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_function_entry();
		
		// Initialize models
		$this->idea_model = new Blog_Ideas_Model_V2();
		$this->persona_model = new Persona_Model();
		$this->context_model = new Context_Model();
		$this->product_model = new Product_Model();
		$this->brand_feature_model = new Brand_Feature_Model();
		
		$this->log_info( 'prompt_compiler_init', 'Prompt Compiler Service initialized' );
		
		$this->log_function_exit();
	}

	/**
	 * Generate content prompts for a given idea.
	 *
	 * @param int $idea_id The idea ID to generate prompts for.
	 * @return array Array containing system_prompts and user_prompt.
	 * @throws \Exception If idea or persona not found.
	 */
	public function generate_content_prompts( $idea_id ) {
		$this->log_function_entry( [ 'idea_id' => $idea_id ] );
		
		try {
					// Load the idea
		$idea = $this->idea_model->get( $idea_id );
		if ( ! $idea ) {
			throw new \Exception( sprintf( __( 'Idea with ID %d not found', 'ai-blog-generator' ), $idea_id ) );
		}
		
		// Convert stdClass object to array for consistent access
		$idea = (array) $idea;
			
			$this->log_info( 'idea_loaded', 'Idea loaded successfully', [
				'idea_id' => $idea_id,
				'title' => $idea['title'] ?? 'unknown',
				'persona_id' => $idea['persona_id'] ?? null
			] );
			
					// Load associated persona
		$persona = null;
		if ( ! empty( $idea['persona_id'] ) ) {
			$persona = $this->persona_model->get( $idea['persona_id'] );
			if ( $persona ) {
				// Convert stdClass object to array for consistent access
				$persona = (array) $persona;
			} else {
				$this->log_warning( 'persona_not_found', 'Persona not found, using defaults', [
					'persona_id' => $idea['persona_id']
				] );
			}
		}
			
			// Build system prompts
			$system_prompts = $this->build_system_prompts( $idea, $persona );
			
			// Build user prompt
			$user_prompt = $this->build_user_prompt( $idea, $persona );
			
			// Log prompts to file
			$this->log_prompts_to_file( $idea_id, $system_prompts, $user_prompt );
			
			$result = [
				'system_prompts' => $system_prompts,
				'user_prompt' => $user_prompt,
				'idea' => $idea,
				'persona' => $persona
			];
			
			$this->log_info( 'prompts_generated', 'Prompts generated successfully', [
				'idea_id' => $idea_id,
				'system_prompts_count' => count( $system_prompts ),
				'user_prompt_length' => strlen( $user_prompt )
			] );
			
			$this->log_function_exit();
			return $result;
			
		} catch ( \Exception $e ) {
			$this->log_exception( 'prompt_generation_failed', $e, [
				'idea_id' => $idea_id
			] );
			throw $e;
		}
	}

	/**
	 * Build system prompts based on idea and persona.
	 *
	 * @param array      $idea    The idea data.
	 * @param array|null $persona The persona data.
	 * @return array Array of system prompts.
	 */
	private function build_system_prompts( $idea, $persona ) {
		$this->log_function_entry();
		
		$system_prompts = [];
		
		// 1. Persona bio and characteristics
		if ( $persona ) {
			$persona_prompt = $this->build_persona_prompt( $persona );
			if ( $persona_prompt ) {
				$system_prompts[] = [
					'type' => 'persona',
					'content' => $persona_prompt
				];
			}
			
			// 2. Layout styles
			$layout_prompt = $this->build_layout_prompt( $persona );
			if ( $layout_prompt ) {
				$system_prompts[] = [
					'type' => 'layout',
					'content' => $layout_prompt
				];
			}
			
			// 3. Chart usage
			$chart_prompt = $this->build_chart_usage_prompt( $persona );
			$system_prompts[] = [
				'type' => 'chart_usage',
				'content' => $chart_prompt
			];
		}
		
		// 4. Always-include contexts
		$always_include_contexts = $this->get_always_include_contexts( $persona );
		foreach ( $always_include_contexts as $context ) {
			$system_prompts[] = [
				'type' => 'context_' . $context['type'],
				'content' => $context['content'],
				'name' => $context['name']
			];
		}
		
		// 5. Persona-specific contexts (if not already included)
		if ( $persona && ! empty( $persona['include_contexts'] ) ) {
			$persona_contexts = $this->get_persona_contexts( $persona );
			foreach ( $persona_contexts as $context ) {
				// Check if already included
				$already_included = false;
				foreach ( $system_prompts as $prompt ) {
					if ( isset( $prompt['name'] ) && $prompt['name'] === $context['name'] ) {
						$already_included = true;
						break;
					}
				}
				
				if ( ! $already_included ) {
					$system_prompts[] = [
						'type' => 'context_' . $context['type'],
						'content' => $context['content'],
						'name' => $context['name']
					];
				}
			}
		}
		
		// 6. Format-specific contexts (HTML or Avada)
		$format_contexts = $this->get_format_specific_contexts( $persona );
		foreach ( $format_contexts as $context ) {
			// Check if already included
			$already_included = false;
			foreach ( $system_prompts as $prompt ) {
				if ( isset( $prompt['name'] ) && $prompt['name'] === $context['name'] ) {
					$already_included = true;
					break;
				}
			}
			
			if ( ! $already_included ) {
				$system_prompts[] = [
					'type' => 'context_format',
					'content' => $context['content'],
					'name' => $context['name']
				];
			}
		}
		
		// 7. Product promotion
		$product_prompt = $this->build_product_promotion_prompt();
		if ( $product_prompt ) {
			$system_prompts[] = [
				'type' => 'products',
				'content' => $product_prompt
			];
		}
		
		// 8. Used keyphrases
		$keyphrase_prompt = $this->build_used_keyphrases_prompt();
		if ( $keyphrase_prompt ) {
			$system_prompts[] = [
				'type' => 'keyphrases',
				'content' => $keyphrase_prompt
			];
		}
		
		// 9. Brand features
		$brand_features_prompt = $this->build_brand_features_prompt();
		if ( $brand_features_prompt ) {
			$system_prompts[] = [
				'type' => 'brand_features',
				'content' => $brand_features_prompt
			];
		}
		
		$this->log_info( 'system_prompts_built', 'System prompts built successfully', [
			'prompt_count' => count( $system_prompts ),
			'types' => array_column( $system_prompts, 'type' )
		] );
		
		$this->log_function_exit();
		return $system_prompts;
	}

	/**
	 * Build user prompt for content generation.
	 *
	 * @param array      $idea    The idea data.
	 * @param array|null $persona The persona data.
	 * @return string The user prompt.
	 */
	private function build_user_prompt( $idea, $persona ) {
		$this->log_function_entry();
		
		// Gather target keywords
		$target_keywords = $this->get_random_target_keywords( 2 );
		
		// Get persona settings
		$num_images = $persona['number_of_images'] ?? 2;
		$uses_seed_images = $persona['uses_seed_images'] ?? false;
		$content_format = $this->determine_content_format( $persona );
		
		// Build the main prompt
		$prompt = sprintf(
			"Write a comprehensive blog post about '%s' following these requirements:\n\n",
			$idea['title']
		);
		
		// Add description if available
		if ( ! empty( $idea['description'] ) ) {
			$prompt .= "Blog Idea Description: " . $idea['description'] . "\n\n";
		}
		
		$prompt .= "CONTENT REQUIREMENTS:\n";
		$prompt .= "- Write as the persona described in the system prompts\n";
		$prompt .= "- Create a well-formatted, SEO-friendly blog post between 1500-3000 words\n";
		$prompt .= "- Format: " . strtoupper( $content_format ) . "\n";
		
		// Only mention content images if num_images > 0
		if ( $num_images > 0 ) {
			$prompt .= "- Include placeholder spaces for {$num_images} images (use {{image1}}, {{image2}}, etc.)\n";
		}
		
		$prompt .= "\n";
		
		$prompt .= "SEO REQUIREMENTS:\n";
		$prompt .= "- Focus on these 2 target keywords: " . implode( ', ', $target_keywords ) . "\n";
		$prompt .= "- Use the keywords naturally throughout the content (no more than 7 times each)\n";
		$prompt .= "- Create a unique focus keyphrase that combines one target keyword with the post topic\n";
		$prompt .= "  Example: If target keyword is 'Poster Machines for Schools' and topic is 'Phonics in 1st grade', \n";
		$prompt .= "  the focus keyphrase could be 'Poster machines for 1st grade phonics'\n";
		$prompt .= "- The focus keyphrase MUST appear in the introduction\n";
		$prompt .= "- Use the focus keyphrase in at least 1 H2 and 1 H3 heading\n";
		$prompt .= "- Include the focus keyphrase in the meta description\n";
		$prompt .= "\n";
		
		$prompt .= "REQUIRED OUTPUT (format your response with these exact labels):\n";
		$prompt .= "\n";
		$prompt .= "TITLE: [SEO-optimized title under 60 characters]\n";
		$prompt .= "\n";
		$prompt .= "FOCUS_KEYPHRASE: [Unique keyphrase combining target keyword with topic]\n";
		$prompt .= "\n";
		$prompt .= "META_DESCRIPTION: [120-140 character description including the focus keyphrase]\n";
		$prompt .= "\n";
		$prompt .= "TAGS: [20-30 relevant tags separated by commas]\n";
		$prompt .= "\n";
		if ( $content_format === 'avada' ) {
			$prompt .= "AVADA_CONTENT: [Full blog post using Avada Fusion Builder shortcodes]\n";
		} else {
			$prompt .= "HTML_CONTENT: [Full blog post in clean HTML format]\n";
		}
		$prompt .= "\n";
		$prompt .= "IMAGES:\n";
		$prompt .= "{{featured}}: [Detailed AI prompt for featured image]\n";
		
		// Only include content image prompts if num_images > 0
		if ( $num_images > 0 ) {
			for ( $i = 1; $i <= $num_images; $i++ ) {
				$prompt .= "{{image{$i}}}: [Detailed AI prompt for content image {$i}";
				if ( $uses_seed_images ) {
					$prompt .= " - include instructions for seed image usage if applicable";
				}
				$prompt .= "]\n";
			}
		}
		
		$prompt .= "\n";
		
		// Add CHARTS section if persona uses charts
		$uses_charts = ! empty( $persona['uses_charts'] ) && $persona['uses_charts'] == 1;
		if ( $uses_charts ) {
			$prompt .= "CHARTS:\n";
			$prompt .= "[If you included any chart containers (e.g., <div id=\"chart1\">) in the HTML, provide the JavaScript code here. ";
			$prompt .= "If no charts were needed, leave this section completely empty (no text at all).]\n";
			$prompt .= "\n";
		}
		
		$prompt .= "IMPORTANT NOTES:\n";
		$prompt .= "- Follow all system prompts regarding persona, layout, products, and brand features\n";
		$prompt .= "- Ensure all product mentions include links that open in new tabs\n";
		$prompt .= "- Meta description MUST be under 140 characters (this is critical)\n";
		$prompt .= "- The focus keyphrase should be unique and not in the list of previously used keyphrases\n";
		
		$this->log_info( 'user_prompt_built', 'User prompt built successfully', [
			'prompt_length' => strlen( $prompt ),
			'target_keywords' => $target_keywords,
			'num_images' => $num_images,
			'content_format' => $content_format
		] );
		
		$this->log_function_exit();
		return $prompt;
	}

	/**
	 * Build persona characteristics prompt.
	 *
	 * @param array $persona The persona data.
	 * @return string|null The persona prompt.
	 */
	private function build_persona_prompt( $persona ) {
		if ( empty( $persona['bio'] ) ) {
			return null;
		}
		
		$prompt = "This post is written by:\n\n";
		$prompt .= "Biography: " . $persona['bio'] . "\n";
		
		if ( ! empty( $persona['expertise'] ) ) {
			$prompt .= "Expertise: " . $persona['expertise'] . "\n";
		}
		
		if ( ! empty( $persona['writing_style'] ) ) {
			$prompt .= "Writing Style: " . $persona['writing_style'] . "\n";
		}
		
		if ( ! empty( $persona['tone'] ) ) {
			$prompt .= "Tone: " . $persona['tone'] . "\n";
		}
		
		$prompt .= "\nWrite in the voice and style of this persona, reflecting their expertise and personality.";
		
		return $prompt;
	}

	/**
	 * Build layout style prompt.
	 *
	 * @param array $persona The persona data.
	 * @return string|null The layout prompt.
	 */
	private function build_layout_prompt( $persona ) {
		$prompt = '';
		
		if ( ! empty( $persona['layout_style'] ) ) {
			$prompt .= "Layout Style: " . $persona['layout_style'] . "\n";
		}
		
		if ( ! empty( $persona['layout_rules'] ) ) {
			$prompt .= "Layout Rules: " . $persona['layout_rules'] . "\n";
		}
		
		if ( empty( $prompt ) ) {
			return null;
		}
		
		return "Follow these layout guidelines:\n" . $prompt;
	}

	/**
	 * Build chart usage prompt.
	 *
	 * @param array $persona The persona data.
	 * @return string The chart usage prompt.
	 */
	private function build_chart_usage_prompt( $persona ) {
		$uses_charts = ! empty( $persona['uses_charts'] ) && $persona['uses_charts'] == 1;
		
		if ( $uses_charts ) {
			return "Uses ApexCharts when appropriate to provide visual reinforcement for numerical data. Include chart implementations where data visualization would enhance understanding.\n\n" .
				   "CRITICAL CHART IMPLEMENTATION:\n" .
				   "1. Place ApexCharts in a [fusion_builder_column][fusion_code]<script>...apexchart code...</script>[/fusion_code][fusion_builder_column]";
		} else {
			return "Do not use graphs or charts from ApexCharts.";
		}
	}

	/**
	 * Get always-include contexts.
	 *
	 * @param array|null $persona The persona data.
	 * @return array Array of contexts.
	 */
	private function get_always_include_contexts( $persona ) {
		$contexts = [];
		
		// Get contexts with always_include_content = 1
		$always_include = $this->context_model->get_always_include_content();
		
		foreach ( $always_include as $context ) {
			// Convert stdClass object to array for consistent access
			$context = is_object( $context ) ? (array) $context : $context;
			
			$contexts[] = [
				'id' => $context['id'],
				'name' => $context['name'],
				'type' => $context['type'],
				'content' => $context['content']
			];
		}
		
		$this->log_info( 'always_include_contexts_loaded', 'Always-include contexts loaded', [
			'count' => count( $contexts )
		] );
		
		return $contexts;
	}

	/**
	 * Get persona-specific contexts.
	 *
	 * @param array $persona The persona data.
	 * @return array Array of contexts.
	 */
	private function get_persona_contexts( $persona ) {
		if ( empty( $persona['include_contexts'] ) ) {
			return [];
		}
		
		$context_ids = explode( ',', $persona['include_contexts'] );
		$context_ids = array_map( 'trim', $context_ids );
		$context_ids = array_filter( $context_ids, 'is_numeric' );
		
		$contexts = [];
		foreach ( $context_ids as $context_id ) {
			$context = $this->context_model->get( $context_id );
			if ( $context ) {
				// Convert stdClass object to array for consistent access
				$context = is_object( $context ) ? (array) $context : $context;
				
				if ( $context['active'] ) {
					$contexts[] = [
						'id' => $context['id'],
						'name' => $context['name'],
						'type' => $context['type'],
						'content' => $context['content']
					];
				}
			}
		}
		
		$this->log_info( 'persona_contexts_loaded', 'Persona-specific contexts loaded', [
			'persona_id' => $persona['id'],
			'count' => count( $contexts )
		] );
		
		return $contexts;
	}

	/**
	 * Get format-specific contexts.
	 *
	 * @param array|null $persona The persona data.
	 * @return array Array of contexts.
	 */
	private function get_format_specific_contexts( $persona ) {
		$format = $this->determine_content_format( $persona );
		$contexts = [];
		
		if ( $format === 'html' ) {
			$html_contexts = $this->context_model->get_always_include_html();
			foreach ( $html_contexts as $context ) {
				// Convert stdClass object to array for consistent access
				$context = is_object( $context ) ? (array) $context : $context;
				
				$contexts[] = [
					'id' => $context['id'],
					'name' => $context['name'],
					'type' => $context['type'],
					'content' => $context['content']
				];
			}
		} elseif ( $format === 'avada' ) {
			$avada_contexts = $this->context_model->get_always_include_avada();
			foreach ( $avada_contexts as $context ) {
				// Convert stdClass object to array for consistent access
				$context = is_object( $context ) ? (array) $context : $context;
				
				$contexts[] = [
					'id' => $context['id'],
					'name' => $context['name'],
					'type' => $context['type'],
					'content' => $context['content']
				];
			}
		}
		
		$this->log_info( 'format_contexts_loaded', 'Format-specific contexts loaded', [
			'format' => $format,
			'count' => count( $contexts )
		] );
		
		return $contexts;
	}

	/**
	 * Build product promotion prompt.
	 *
	 * @return string|null The product promotion prompt.
	 */
	private function build_product_promotion_prompt() {
		// Get products with all their relations (images and links)
		$products = $this->product_model->get_all_with_relations();
		
		if ( empty( $products ) ) {
			return null;
		}
		
		$prompt = "PRODUCT PROMOTION REQUIREMENTS:\n\n";
		$prompt .= "You MUST promote at least 2 products in the content. When promoting products, use images of the products. Here are the available products:\n\n";
		
		$products_data = [];
		foreach ( $products as $product ) {
			// Log the raw product data for debugging
			$this->log_info( 'product_data_raw', 'Raw product data before processing', [
				'product_id' => $product->id ?? 'unknown',
				'is_object' => is_object( $product ),
				'keys' => is_object( $product ) ? array_keys( get_object_vars( $product ) ) : array_keys( (array) $product )
			] );
			
			// Convert stdClass object to array for consistent access
			$product = is_object( $product ) ? (array) $product : $product;
			
			$product_info = [
				'name' => $product['product_name'] ?? '',
				'description' => $product['product_description'] ?? '',
				'ideal_uses' => $product['ideal_uses'] ?? '',
				'url' => '',
				'images' => []
			];
			
			// Get primary URL from links
			if ( ! empty( $product['links'] ) && is_array( $product['links'] ) ) {
				foreach ( $product['links'] as $link ) {
					$link = is_object( $link ) ? (array) $link : $link;
					if ( ( $link['link_type'] ?? '' ) === 'product_page' || empty( $product_info['url'] ) ) {
						$product_info['url'] = $link['link_url'] ?? '';
						if ( ( $link['link_type'] ?? '' ) === 'product_page' ) {
							break; // Prefer product_page links
						}
					}
				}
			}
			
			// Add image URLs from the images relation
			if ( ! empty( $product['images'] ) && is_array( $product['images'] ) ) {
				foreach ( $product['images'] as $image ) {
					$image = is_object( $image ) ? (array) $image : $image;
					// Use full_url if available, otherwise image_url
					$image_url = $image['full_url'] ?? $image['image_url'] ?? '';
					if ( ! empty( $image_url ) ) {
						$product_info['images'][] = $image_url;
					}
				}
			}
			
			$products_data[] = $product_info;
		}
		
		$prompt .= json_encode( $products_data, JSON_PRETTY_PRINT ) . "\n\n";
		$prompt .= "REQUIREMENTS:\n";
		$prompt .= "- Create links to each promoted product in all text that mentions the product\n";
		$prompt .= "- All product links should open in a new tab (target='_blank')\n";
		$prompt .= "- Use product images in promotions where appropriate\n";
		$prompt .= "- If a product needs to be shown in generated images, include the seed image URL in the image prompt\n";
		
		return $prompt;
	}

	/**
	 * Build used keyphrases prompt.
	 *
	 * @return string|null The used keyphrases prompt.
	 */
	private function build_used_keyphrases_prompt() {
		global $wpdb;
		
		// Get all focus keyphrases from published posts
		$keyphrases = $wpdb->get_col( "
			SELECT DISTINCT meta_value 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = '_yoast_wpseo_focuskw' 
			AND meta_value != ''
		" );
		
		if ( empty( $keyphrases ) ) {
			return null;
		}
		
		$prompt = "PREVIOUSLY USED KEYPHRASES (DO NOT REUSE):\n\n";
		$prompt .= "The following focus keyphrases have already been used in existing posts. ";
		$prompt .= "You MUST create a unique focus keyphrase that is NOT in this list:\n\n";
		
		foreach ( $keyphrases as $keyphrase ) {
			$prompt .= "- " . $keyphrase . "\n";
		}
		
		$prompt .= "\nEnsure your focus keyphrase is completely unique and not a variation of any listed above.";
		
		$this->log_info( 'used_keyphrases_loaded', 'Used keyphrases loaded', [
			'count' => count( $keyphrases )
		] );
		
		return $prompt;
	}

	/**
	 * Build brand features prompt.
	 *
	 * @return string|null The brand features prompt.
	 */
	private function build_brand_features_prompt() {
		$brand_features = $this->brand_feature_model->get_active_features();
		
		if ( empty( $brand_features ) ) {
			return null;
		}
		
		$prompt = "BRAND FEATURES:\n\n";
		$prompt .= "Use links to these brand features where appropriate in the content:\n\n";
		
		foreach ( $brand_features as $feature ) {
			// Convert stdClass object to array for consistent access
			$feature = is_object( $feature ) ? (array) $feature : $feature;
			
			$prompt .= sprintf(
				"- %s (%s): %s\n  URL: %s\n\n",
				$feature['name'],
				$feature['category'],
				$feature['description'] ?? 'No description',
				$feature['url']
			);
		}
		
		$prompt .= "All brand feature links should open in a new window (target='_blank').";
		
		return $prompt;
	}

	/**
	 * Get random target keywords.
	 *
	 * @param int $count Number of keywords to select.
	 * @return array Selected keywords.
	 */
	private function get_random_target_keywords( $count = 2 ) {
		// Get all keyword contexts
		$keyword_contexts = $this->context_model->get_by_type( 'keywords' );
		$all_keywords = [];
		
		foreach ( $keyword_contexts as $context ) {
			// Convert stdClass object to array for consistent access
			$context = is_object( $context ) ? (array) $context : $context;
			
			if ( ! empty( $context['content'] ) ) {
				// Split by newlines and commas
				$lines = preg_split( '/[\n,]+/', $context['content'] );
				foreach ( $lines as $line ) {
					$keyword = trim( $line );
					if ( ! empty( $keyword ) ) {
						$all_keywords[] = $keyword;
					}
				}
			}
		}
		
		// Remove duplicates
		$all_keywords = array_unique( $all_keywords );
		
		// Select random keywords
		if ( count( $all_keywords ) <= $count ) {
			return $all_keywords;
		}
		
		$selected = array_rand( array_flip( $all_keywords ), $count );
		if ( ! is_array( $selected ) ) {
			$selected = [ $selected ];
		}
		
		$this->log_info( 'target_keywords_selected', 'Target keywords selected', [
			'total_available' => count( $all_keywords ),
			'selected' => $selected
		] );
		
		return $selected;
	}

	/**
	 * Determine content format based on persona settings.
	 *
	 * @param array|null $persona The persona data.
	 * @return string 'html' or 'avada'
	 */
	private function determine_content_format( $persona ) {
		if ( ! $persona ) {
			// Default to HTML if no persona
			return 'html';
		}
		
		// Check persona settings
		if ( ! empty( $persona['uses_avada_layouts'] ) && $persona['uses_avada_layouts'] == 1 ) {
			return 'avada';
		}
		
		return 'html';
	}

	/**
	 * Log prompts to file for debugging.
	 *
	 * @param int    $idea_id        The idea ID.
	 * @param array  $system_prompts The system prompts.
	 * @param string $user_prompt    The user prompt.
	 */
	private function log_prompts_to_file( $idea_id, $system_prompts, $user_prompt ) {
		try {
			// Create log directory if it doesn't exist
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/ai-blog-generator-logs/generations';
			
			if ( ! file_exists( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
			}
			
			// Create log file
			$filename = $log_dir . '/' . $idea_id . '_prompts.txt';
			$content = "PROMPT COMPILATION LOG\n";
			$content .= "=====================\n";
			$content .= "Generated: " . current_time( 'mysql' ) . "\n";
			$content .= "Idea ID: " . $idea_id . "\n\n";
			
			$content .= "SYSTEM PROMPTS\n";
			$content .= "==============\n\n";
			
			foreach ( $system_prompts as $index => $prompt ) {
				$content .= "System Prompt " . ( $index + 1 ) . " (Type: " . $prompt['type'] . "):\n";
				$content .= "-" . str_repeat( '-', 50 ) . "\n";
				$content .= $prompt['content'] . "\n\n";
			}
			
			$content .= "\nUSER PROMPT\n";
			$content .= "===========\n\n";
			$content .= $user_prompt . "\n";
			
			file_put_contents( $filename, $content );
			
			$this->log_info( 'prompts_logged_to_file', 'Prompts logged to file', [
				'filename' => $filename,
				'file_size' => strlen( $content )
			] );
			
		} catch ( \Exception $e ) {
			$this->log_error( 'prompt_logging_failed', 'Failed to log prompts to file', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );
		}
	}

	/**
	 * Generate image prompts based on idea and persona.
	 *
	 * @param array      $idea        The idea data.
	 * @param array|null $persona     The persona data.
	 * @param array      $image_specs Array of image specifications from content.
	 * @return array Array of compiled image prompts.
	 */
	public function generate_image_prompts( $idea, $persona, $image_specs ) {
		// Convert stdClass objects to arrays for consistent access
		$idea = is_object( $idea ) ? (array) $idea : $idea;
		$persona = is_object( $persona ) ? (array) $persona : $persona;
		
		$this->log_function_entry( [
			'idea_id' => $idea['id'] ?? 'unknown',
			'has_persona' => ! is_null( $persona ),
			'image_count' => count( $image_specs )
		] );
		
		$compiled_prompts = [];
		$uses_seed_images = $persona && ! empty( $persona['uses_seed_images'] );
		
		// Get seed images if needed
		$seed_images = [];
		if ( $uses_seed_images ) {
			$seed_images = $this->get_available_seed_images();
		}
		
		foreach ( $image_specs as $index => $spec ) {
			$prompt = $spec['prompt'] ?? '';
			
			// Add seed image instructions if applicable
			if ( $uses_seed_images && ! empty( $seed_images ) ) {
				$seed_index = $index % count( $seed_images );
				$seed_image = $seed_images[ $seed_index ];
				
				$prompt .= "\n\nSeed Image Instructions: Include the product from the seed image naturally in the scene. ";
				$prompt .= "The product should be recognizable but integrated into the overall composition.";
				
				$spec['seed_image_url'] = $seed_image['url'] ?? null;
			}
			
			// Add style consistency instructions
			$prompt .= "\n\nStyle: Professional, clean, and suitable for a blog post about " . $idea['title'];
			
			$compiled_prompts[] = [
				'token' => $spec['token'] ?? '{{image' . ($index + 1) . '}}',
				'prompt' => $prompt,
				'alt_text' => $spec['alt_text'] ?? 'Blog image',
				'seed_image_url' => $spec['seed_image_url'] ?? null
			];
		}
		
		$this->log_info( 'image_prompts_generated', 'Image prompts generated', [
			'count' => count( $compiled_prompts ),
			'uses_seed_images' => $uses_seed_images
		] );
		
		$this->log_function_exit();
		return $compiled_prompts;
	}

	/**
	 * Get available seed images.
	 *
	 * @return array Array of seed images.
	 */
	private function get_available_seed_images() {
		// First try to get from contexts with seed images
		$seed_images = $this->context_model->get_seed_images();
		
		// If no context seed images, try product images
		if ( empty( $seed_images ) ) {
			$products = $this->product_model->get_active_products();
			foreach ( $products as $product ) {
				// Convert stdClass object to array for consistent access
				$product = is_object( $product ) ? (array) $product : $product;
				
				if ( ! empty( $product['image_url'] ) ) {
					$seed_images[] = [
						'url' => $product['image_url'],
						'name' => $product['product_name'] ?? '',
						'type' => 'product'
					];
				}
			}
		}
		
		return $seed_images;
	}
} 