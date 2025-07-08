<?php
/**
 * AI Blog Generator
 *
 * @package           AI_Blog_Generator
 * @author            Red Circle Solutions
 * @copyright         2025 Red Circle Solutions
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AI Blog Generator
 * Plugin URI:        https://redcirclesolutions.com/ai-blog-generator
 * Description:       Automatically generate SEO-optimized blog posts using Claude 4 models (Sonnet 4 & Opus 4) and GPT-Image-1. Features include idea generation, content creation with images, scheduling, and cost tracking.
 * Version:           1.6.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Red Circle Solutions
 * Author URI:        https://redcirclesolutions.com
 * Text Domain:       ai-blog-generator
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Development error reporting settings.
 * IMPORTANT: Remove or comment out these lines in production!
 */
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', '1' );
	ini_set( 'display_startup_errors', '1' );
	ini_set( 'log_errors', '1' );
	ini_set( 'error_log', plugin_dir_path( __FILE__ ) . 'debug.log' );
}

/**
 * Current plugin version.
 */
define( 'AI_BLOG_GENERATOR_VERSION', '1.6.0' );

/**
 * Debug logging configuration.
 * Set to true to enable detailed debug logging.
 * Temporarily enabled by default to help debug seed image upload issues.
 */
define( 'AI_BLOG_GENERATOR_DEBUG', get_option( 'ai_blog_generator_debug_logging', true ) );

/**
 * Plugin constants.
 */
define( 'AI_BLOG_GENERATOR_PLUGIN_FILE', __FILE__ );
define( 'AI_BLOG_GENERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_BLOG_GENERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_BLOG_GENERATOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Logs directory path
$upload_dir = wp_upload_dir();
define( 'AI_BLOG_GENERATOR_LOGS_DIR', $upload_dir['basedir'] . '/ai-blog-generator-logs' );

// Ensure logs directory exists
if ( ! file_exists( AI_BLOG_GENERATOR_LOGS_DIR ) ) {
	wp_mkdir_p( AI_BLOG_GENERATOR_LOGS_DIR );
}

define( 'AI_BLOG_GENERATOR_DEBUG_LOG', AI_BLOG_GENERATOR_LOGS_DIR . '/debug-transaction.log' );

// Database table names
global $wpdb;
define( 'AI_BLOG_GENERATOR_TABLE_IDEAS', $wpdb->prefix . 'ai_blog_ideas' );
define( 'AI_BLOG_GENERATOR_TABLE_POSTS', $wpdb->prefix . 'ai_blog_generated_posts' );
define( 'AI_BLOG_GENERATOR_TABLE_CONTEXTS', $wpdb->prefix . 'ai_blog_contexts' );
define( 'AI_BLOG_GENERATOR_TABLE_PERSONAS', $wpdb->prefix . 'ai_blog_personas' );
define( 'AI_BLOG_GENERATOR_TABLE_LOGS', $wpdb->prefix . 'ai_blog_logs' );
define( 'AI_BLOG_GENERATOR_TABLE_COSTS', $wpdb->prefix . 'ai_blog_cost_analytics' );
define( 'AI_BLOG_GENERATOR_TABLE_SEED_IMAGES', $wpdb->prefix . 'ai_blog_seed_images' );
define( 'AI_BLOG_GENERATOR_TABLE_IDEA_CATEGORIES', $wpdb->prefix . 'ai_blog_idea_categories' );
define( 'AI_BLOG_GENERATOR_TABLE_PRODUCTS', $wpdb->prefix . 'ai_blog_generator_products' );
define( 'AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES', $wpdb->prefix . 'ai_blog_generator_product_images' );
define( 'AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS', $wpdb->prefix . 'ai_blog_generator_product_links' );
define( 'AI_BLOG_GENERATOR_TABLE_PRODUCT_SEED_IMAGES', $wpdb->prefix . 'ai_blog_generator_product_seed_images' );
define( 'AI_BLOG_GENERATOR_TABLE_BRAND_FEATURES', $wpdb->prefix . 'ai_blog_brand_features' );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function ( $class ) {
	// Project-specific namespace prefix.
	$prefix = 'AI_Blog_Generator\\';

	// Base directory for the namespace prefix.
	$base_dir = AI_BLOG_GENERATOR_PLUGIN_DIR;

	// Does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// No, move to the next registered autoloader.
		return;
	}

	// Get the relative class name.
	$relative_class = substr( $class, $len );

	// Replace the namespace prefix with the base directory, replace namespace
	// separators with directory separators in the relative class name, make it lowercase,
	// and append with .php
	$file_parts = explode( '\\', $relative_class );
	$file_name = 'class-' . strtolower( str_replace( '_', '-', array_pop( $file_parts ) ) ) . '.php';
	
	// Build the file path.
	$file_path = $base_dir;
	if ( ! empty( $file_parts ) ) {
		$file_path .= strtolower( implode( DIRECTORY_SEPARATOR, $file_parts ) ) . DIRECTORY_SEPARATOR;
	}
	$file = $file_path . $file_name;

	// If the file exists, require it.
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Load critical traits early to ensure they're available during activation.
 * These must be loaded before any classes that use them.
 */
require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'utilities/trait-loggable.php';
require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'utilities/trait-ajax-handler.php';

/**
 * Main plugin class - Singleton pattern.
 */
class AI_Blog_Generator {

	/**
	 * Single instance of the class.
	 *
	 * @var AI_Blog_Generator
	 */
	private static $instance = null;

	/**
	 * Plugin loader instance.
	 *
	 * @var AI_Blog_Generator\Includes\Plugin_Loader
	 */
	private $loader;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Main plugin instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @return AI_Blog_Generator
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Set version.
		$this->version = AI_BLOG_GENERATOR_VERSION;
		
		// Load dependencies.
		$this->load_dependencies();
		
		// Set locale for internationalization.
		$this->set_locale();
		
		// Register ApexCharts cleaning filters globally
		$this->register_apexcharts_filters();
		
		// Define admin hooks.
		$this->define_admin_hooks();
		
		// Define public hooks.
		$this->define_public_hooks();
		
		// Initialize core components.
		$this->init_core_components();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		// Load core classes.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'includes/class-plugin-loader.php';
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'includes/class-plugin-activator.php';
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'includes/class-plugin-deactivator.php';
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'includes/class-plugin-i18n.php';
		
		$this->loader = new AI_Blog_Generator\Includes\Plugin_Loader();
	}

	/**
	 * Set plugin locale for internationalization.
	 */
	private function set_locale() {
		$this->i18n = new AI_Blog_Generator\Includes\Plugin_I18n();
		$this->loader->add_action( 'plugins_loaded', $this->i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Define all hooks for the admin area.
	 */
	private function define_admin_hooks() {
		// Initialize admin manager.
		$admin_manager = new AI_Blog_Generator\Admin\Admin_Manager( $this->version );
		
		// Only load admin UI functionality in admin area.
		if ( is_admin() ) {
		// Admin menu and pages.
		$this->loader->add_action( 'admin_menu', $admin_manager, 'add_menu_pages' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_manager, 'enqueue_scripts' );
		}
		
		// Always register AJAX handlers (needed for both admin and frontend AJAX requests).
		$admin_manager->register_ajax_handlers();
		
		// Initialize controllers for AJAX handling (always needed for AJAX).
		$this->init_controllers();
		
		// Register post-save cleanup handler
		$this->loader->add_action( 'save_post', $this, 'cleanup_fusion_code_after_save', 99, 3 );
		$this->loader->add_action( 'wp_insert_post', $this, 'cleanup_fusion_code_after_save', 99, 3 );
	}

	/**
	 * Define all hooks for the public-facing side.
	 */
	private function define_public_hooks() {
		// Enqueue frontend scripts and styles.
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_frontend_scripts' );
	}

	/**
	 * Initialize core plugin components.
	 */
	private function init_core_components() {
		// Register custom cron schedules.
		$this->loader->add_filter( 'cron_schedules', $this, 'add_custom_cron_schedules' );
		
		// Initialize database manager (singleton).
		$db_manager = AI_Blog_Generator\Models\Database_Manager::get_instance();
		
		// Initialize logger (singleton).
		$logger = AI_Blog_Generator\Utilities\Logger::get_instance();
		
		// Initialize scheduler service with error protection.
		try {
			$scheduler = new AI_Blog_Generator\Services\Scheduler_Service();
			$this->loader->add_action( 'init', $scheduler, 'init_cron_jobs' );
		} catch ( \Exception $e ) {
			// Log scheduler initialization error but don't let it break the main plugin
			if ( class_exists( 'AI_Blog_Generator\Utilities\Logger' ) ) {
				AI_Blog_Generator\Utilities\Logger::error( 'scheduler_init_failed', 'Scheduler service failed to initialize', [
					'error' => $e->getMessage()
				] );
			}
		}
		
		// Register cron hooks only if scheduler is available.
		if ( isset( $scheduler ) ) {
			try {
				$this->loader->add_action( 'ai_blog_daily_ideas', $scheduler, 'daily_idea_generation' );
				$this->loader->add_action( 'ai_blog_process_queue', $scheduler, 'process_approved_ideas_queue' );
				$this->loader->add_action( 'ai_blog_publish_scheduled', $scheduler, 'publish_scheduled_posts' );
			} catch ( \Exception $e ) {
				// Log cron registration error
				if ( class_exists( 'AI_Blog_Generator\Utilities\Logger' ) ) {
					AI_Blog_Generator\Utilities\Logger::error( 'cron_registration_failed', 'Cron hook registration failed', [
						'error' => $e->getMessage()
					] );
				}
			}
		}
		
		// Register handler for single generation processing (used by Generation_Queue)
		$this->loader->add_action( 'ai_blog_process_single_generation', $this, 'process_single_generation' );
		
		// Clean up old logs daily.
		$this->loader->add_action( 'ai_blog_cleanup_logs', $logger, 'clean_old_logs' );
	}
	
	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		// Add 5-minute schedule.
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes', 'ai-blog-generator' ),
			];
		}
		
		// Add 15-minute schedule.
		if ( ! isset( $schedules['fifteen_minutes'] ) ) {
			$schedules['fifteen_minutes'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', 'ai-blog-generator' ),
			];
		}
		
		return $schedules;
	}

	/**
	 * Initialize controllers for handling requests.
	 */
	private function init_controllers() {
		// Blog controller.
		$blog_controller = new AI_Blog_Generator\Controllers\Blog_Controller();
		$blog_controller->register_ajax_handlers();
		
		// Idea controller.
		$idea_controller = new AI_Blog_Generator\Controllers\Idea_Controller();
		$idea_controller->register_ajax_handlers();
		
		// Idea Generator controller.
		if ( class_exists( '\AI_Blog_Generator\Controllers\Blog_Ideas_Controller_V2' ) ) {
			$blog_ideas_v2_controller = new \AI_Blog_Generator\Controllers\Blog_Ideas_Controller_V2();
			$blog_ideas_v2_controller->register_ajax_handlers();
			\AI_Blog_Generator\Utilities\Logger::debug( 'controller_initialization', 'Initializing Idea Generator Controller', [], __CLASS__, __METHOD__ );
		}
		
		// Approved Ideas V2 controller.
		$approved_ideas_v2_controller = new AI_Blog_Generator\Controllers\Approved_Ideas_Controller_V2();
		
		// Log controller initialization for debugging
		\AI_Blog_Generator\Utilities\Logger::debug( 'controller_initialization', 'Initializing Approved Ideas V2 Controller', [], __CLASS__, __METHOD__ );
		
		$approved_ideas_v2_controller->register_ajax_handlers();
		
		// Context controller.
		$context_controller = new AI_Blog_Generator\Controllers\Context_Controller();
		$context_controller->register_ajax_handlers();
		
		// Persona controller.
		$persona_controller = new AI_Blog_Generator\Controllers\Persona_Controller();
		$persona_controller->register_ajax_handlers();
		
		// Product controller.
		$product_controller = new AI_Blog_Generator\Controllers\Product_Controller();
		$product_controller->register_ajax_handlers();
		
		// Brand Feature controller.
		$brand_feature_controller = new AI_Blog_Generator\Controllers\Brand_Feature_Controller();
		$brand_feature_controller->register_ajax_handlers();
		
		// Image controller.
		$image_controller = new AI_Blog_Generator\Controllers\Image_Controller();
		$image_controller->register_ajax_handlers();
		
		// Analytics controller.
		$analytics_controller = new AI_Blog_Generator\Controllers\Analytics_Controller();
		$analytics_controller->register_ajax_handlers();
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the plugin loader.
	 *
	 * @return AI_Blog_Generator\Includes\Plugin_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_frontend_scripts() {
		// Only enqueue on posts and pages where blog content might be displayed.
		if ( ! is_singular() ) {
			return;
		}

		// Check if this is an AI-generated post or contains AI-generated content
		global $post;
		$is_ai_generated = get_post_meta( $post->ID, 'ai_generated', true ) === '1';
		$has_accordion = strpos( $post->post_content, 'accordion' ) !== false || strpos( $post->post_content, 'ai-blog-accordion' ) !== false;
		
		// Only load scripts if this is AI-generated content with accordions
		if ( ! $is_ai_generated && ! $has_accordion ) {
			return;
		}

		// Enqueue the blog styling framework CSS
		wp_enqueue_style(
			'ai-blog-generator-blogs-css',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/css/blogs.css',
			[],
			$this->version
		);

		// Only enqueue Bootstrap JS for AI-generated content
		if ( $is_ai_generated && $has_accordion ) {
			// Check if Bootstrap is already loaded by theme/other plugins
			if ( ! wp_script_is( 'bootstrap', 'enqueued' ) && ! wp_script_is( 'bootstrap-js', 'enqueued' ) ) {
				// Enqueue Bootstrap JavaScript with a unique handle to avoid conflicts
				wp_enqueue_script(
					'ai-blog-bootstrap-js',
					'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
					[],
					'5.3.0',
					true // Load in footer
				);

				// Add accordion initialization script with namespace
				wp_add_inline_script(
					'ai-blog-bootstrap-js',
					'
					// Initialize AI Blog Generator accordions only
					(function() {
						// Create isolated Bootstrap instance for AI Blog accordions
						document.addEventListener("DOMContentLoaded", function() {
							// Only target AI Blog accordions, not all accordions
							const aiAccordions = document.querySelectorAll(".ai-blog-accordion, .ai-generated-accordion");
							
							if (aiAccordions.length === 0) {
								return;
							}
							
							aiAccordions.forEach(function(accordion) {
								// Initialize collapse for each accordion item
								const collapseElements = accordion.querySelectorAll(".accordion-collapse");
								collapseElements.forEach(function(collapseEl) {
									if (typeof bootstrap !== "undefined" && bootstrap.Collapse) {
										// Check if already initialized
										if (!collapseEl.classList.contains("ai-blog-initialized")) {
											// Initialize Bootstrap collapse
											new bootstrap.Collapse(collapseEl, {
												toggle: false
											});
											collapseEl.classList.add("ai-blog-initialized");
										}
									}
								});
							});
							
							// Debug logging
							console.log("AI Blog Generator: Initialized " + aiAccordions.length + " AI accordions");
						});
					})();
					',
					'after'
				);
			}
		}

		// Enqueue frontend JavaScript for interactive elements
		wp_enqueue_script(
			'ai-blog-generator-frontend',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/frontend-blog.js',
			[ 'jquery' ], // Remove Bootstrap JS as hard dependency
			$this->version,
			true
		);

		// Conditionally add Bootstrap as dependency if loaded
		if ( wp_script_is( 'ai-blog-bootstrap-js', 'enqueued' ) ) {
			wp_script_add_data( 'ai-blog-generator-frontend', 'deps', [ 'jquery', 'ai-blog-bootstrap-js' ] );
		}

		// ApexCharts removed - will be loaded by script tags in posts when needed
	}

	/**
	 * Process a single generation from the queue.
	 * This is called by WordPress cron when Generation_Queue schedules a generation.
	 *
	 * @param int $idea_id The idea ID to process.
	 */
	public function process_single_generation( $idea_id ) {
		// Log the processing start
		\AI_Blog_Generator\Utilities\Logger::info( 'process_single_generation', 'Processing single generation from queue', [
			'idea_id' => $idea_id
		] );
		
		try {
			// Initialize background processor and process the generation
			$background_processor = new \AI_Blog_Generator\Services\Background_Processor();
			$background_processor->process_generation( $idea_id );
		} catch ( \Exception $e ) {
			\AI_Blog_Generator\Utilities\Logger::error( 'process_single_generation_error', 'Error processing generation', [
				'idea_id' => $idea_id,
				'error' => $e->getMessage()
			] );
			
			// Mark as failed in queue
			try {
				$queue = new \AI_Blog_Generator\Services\Generation_Queue();
				$queue->mark_failed( $idea_id, $e->getMessage() );
			} catch ( \Exception $queue_error ) {
				\AI_Blog_Generator\Utilities\Logger::error( 'queue_mark_failed_error', 'Error marking generation as failed', [
					'idea_id' => $idea_id,
					'error' => $queue_error->getMessage()
				] );
			}
		}
	}

	/**
	 * Register ApexCharts cleaning filters globally.
	 * These filters ensure ApexCharts code is properly formatted without p/br tags.
	 */
	private function register_apexcharts_filters() {
		// Clean content before saving
		add_filter( 'content_save_pre', [ $this, 'clean_apexcharts_content' ], 99, 1 );
		add_filter( 'content_filtered_save_pre', [ $this, 'clean_apexcharts_content' ], 99, 1 );
		
		// Clean content when displaying - run AFTER wpautop (priority 10)
		add_filter( 'the_content', [ $this, 'clean_apexcharts_display' ], 11, 1 );
		
		// Also add a very late filter to catch any remaining issues
		add_filter( 'the_content', [ $this, 'clean_apexcharts_final' ], 9999, 1 );
		
		// Clean when editing
		add_filter( 'content_edit_pre', [ $this, 'clean_apexcharts_content' ], 99, 1 );
		
		// For Avada theme - run after their processing
		add_filter( 'avada_blog_post_content', [ $this, 'clean_apexcharts_display' ], 9999, 1 );
		
		// Disable wpautop for posts that contain fusion_code
		add_filter( 'the_content', [ $this, 'conditionally_remove_wpautop' ], 1 );
	}
	
	/**
	 * Clean ApexCharts content before saving.
	 *
	 * @param string $content The content to clean.
	 * @return string The cleaned content.
	 */
	public function clean_apexcharts_content( $content ) {
		// Only process if content might contain ApexCharts
		if ( stripos( $content, 'ApexCharts' ) === false && 
		     stripos( $content, '[fusion_code]' ) === false ) {
			return $content;
		}
		
		// Clean fusion_code blocks
		$content = preg_replace_callback( 
			'/\[fusion_code\](.*?)\[\/fusion_code\]/s',
			[ $this, 'clean_fusion_code_block' ],
			$content
		);
		
		return $content;
	}
	
	/**
	 * Clean a single fusion_code block.
	 *
	 * @param array $matches The regex matches.
	 * @return string The cleaned fusion_code block.
	 */
	private function clean_fusion_code_block( $matches ) {
		// Use the deep clean method for consistency
		$full_block = $matches[0];
		return $this->deep_clean_fusion_code( $full_block );
	}
	
	/**
	 * Clean ApexCharts content when displaying.
	 *
	 * @param string $content The content to clean.
	 * @return string The cleaned content.
	 */
	public function clean_apexcharts_display( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}
		
		// Use deep clean method for fusion_code blocks
		if ( strpos( $content, '[fusion_code]' ) !== false ) {
			$content = $this->deep_clean_fusion_code( $content );
		}
		
		// Clean ApexCharts code outside of fusion_code blocks
		$content = preg_replace_callback(
			'/<script[^>]*>.*?new\s+ApexCharts.*?<\/script>/s',
			function( $matches ) {
				$code = $matches[0];
				// Remove all p and br tags
				$code = str_replace( array( '<p>', '</p>', '<br>', '<br />', '<br/>' ), '', $code );
				return $code;
			},
			$content
		);
		
		return $content;
	}
	
	/**
	 * Conditionally remove wpautop for posts containing fusion_code.
	 * 
	 * @param string $content The content to check.
	 * @return string The content, potentially without wpautop applied.
	 */
	public function conditionally_remove_wpautop( $content ) {
		if ( strpos( $content, '[fusion_code]' ) !== false ) {
			// Remove wpautop filter for this content
			remove_filter( 'the_content', 'wpautop' );
			
			// Add it back after our other filters run
			add_filter( 'the_content', function( $content ) {
				add_filter( 'the_content', 'wpautop' );
				return $content;
			}, 12 );
		}
		return $content;
	}
	
	/**
	 * Final cleanup pass for ApexCharts code.
	 * This runs at very high priority to catch any tags added by other plugins/themes.
	 * 
	 * @param string $content The content to clean.
	 * @return string The cleaned content.
	 */
	public function clean_apexcharts_final( $content ) {
		if ( empty( $content ) || ( strpos( $content, 'fusion_code' ) === false && strpos( $content, 'ApexCharts' ) === false ) ) {
			return $content;
		}
		
		// Use deep clean method which handles all encoding and formatting issues
		return $this->deep_clean_fusion_code( $content );
	}

	/**
	 * Clean fusion code after post save.
	 * This runs after WordPress saves a post to ensure fusion_code blocks are properly formatted.
	 *
	 * @param int $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool $update Whether this is an existing post being updated or not.
	 */
	public function cleanup_fusion_code_after_save( $post_id, $post, $update ) {
		// Skip if this is an autosave, revision, or if we're already cleaning
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		
		// Prevent infinite loops - check if we're already cleaning this post
		static $cleaning_posts = [];
		if ( isset( $cleaning_posts[$post_id] ) ) {
			return;
		}
		
		// Check if post content contains fusion_code blocks
		if ( strpos( $post->post_content, '[fusion_code]' ) === false ) {
			return;
		}
		
		// Mark this post as being cleaned
		$cleaning_posts[$post_id] = true;
		
		// Get the current content
		$content = $post->post_content;
		$original_content = $content;
		
		// Perform comprehensive cleaning
		$content = $this->deep_clean_fusion_code( $content );
		
		// Only update if content actually changed
		if ( $content !== $original_content ) {
			// Remove save_post action temporarily to prevent recursion
			remove_action( 'save_post', [ $this, 'cleanup_fusion_code_after_save' ], 99 );
			remove_action( 'wp_insert_post', [ $this, 'cleanup_fusion_code_after_save' ], 99 );
			
			// Update the post
			wp_update_post( [
				'ID' => $post_id,
				'post_content' => $content
			] );
			
			// Re-add the actions
			add_action( 'save_post', [ $this, 'cleanup_fusion_code_after_save' ], 99, 3 );
			add_action( 'wp_insert_post', [ $this, 'cleanup_fusion_code_after_save' ], 99, 3 );
			
			// Log the cleanup
			if ( class_exists( 'AI_Blog_Generator\Utilities\Logger' ) ) {
				\AI_Blog_Generator\Utilities\Logger::info( 'fusion_code_cleaned', 'Fusion code blocks cleaned after save', [
					'post_id' => $post_id,
					'post_title' => $post->post_title
				] );
			}
		}
		
		// Clear the cleaning flag
		unset( $cleaning_posts[$post_id] );
	}
	
	/**
	 * Perform deep cleaning of fusion_code blocks.
	 * This method handles all types of encoding issues and formatting problems.
	 *
	 * @param string $content The content to clean.
	 * @return string The cleaned content.
	 */
	private function deep_clean_fusion_code( $content ) {
		// Pattern to match fusion_code blocks
		$pattern = '/(\[fusion_code\])([\s\S]*?)(\[\/fusion_code\])/';
		
		$content = preg_replace_callback( $pattern, function( $matches ) {
			$code = $matches[2];
			
			// Step 1: Decode HTML entities (may need multiple passes)
			$max_decode_attempts = 3;
			for ( $i = 0; $i < $max_decode_attempts; $i++ ) {
				$decoded = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( $decoded === $code ) {
					break; // No more entities to decode
				}
				$code = $decoded;
			}
			
			// Step 2: Remove all HTML tags that WordPress adds
			$tags_to_remove = [
				// Direct tags
				'<p>', '</p>', '<p/>', '<p />',
				'<br>', '<br/>', '<br />', '</br>',
				'<div>', '</div>',
				'<span>', '</span>',
				// Encoded versions (in case they appear after decoding)
				'&lt;p&gt;', '&lt;/p&gt;', '&lt;p/&gt;', '&lt;p /&gt;',
				'&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;', '&lt;/br&gt;',
			];
			
			// Remove tags
			$code = str_replace( $tags_to_remove, '', $code );
			
			// Also use regex to catch any p or br tags with attributes
			$code = preg_replace( '/<p[^>]*>/', '', $code );
			$code = preg_replace( '/<\/p>/', '', $code );
			$code = preg_replace( '/<br[^>]*>/', '', $code );
			$code = preg_replace( '/<div[^>]*>/', '', $code );
			$code = preg_replace( '/<\/div>/', '', $code );
			$code = preg_replace( '/<span[^>]*>/', '', $code );
			$code = preg_replace( '/<\/span>/', '', $code );
			
			// Step 3: Fix common encoding issues
			$replacements = [
				'&amp;' => '&',
				'&nbsp;' => ' ',
				'&#039;' => "'",
				'&quot;' => '"',
				'&apos;' => "'",
			];
			$code = str_replace( array_keys( $replacements ), array_values( $replacements ), $code );
			
			// Step 4: Clean up whitespace
			$code = trim( $code );
			
			// Step 5: Check if this is JavaScript code that needs script tags
			$is_javascript = false;
			
			// Check for JavaScript indicators
			$js_indicators = [
				'ApexCharts',
				'function',
				'var ',
				'const ',
				'let ',
				'document.',
				'window.',
				'getElementById',
				'querySelector',
				'addEventListener',
				'=>', // Arrow functions
				'chart',
				'Chart',
				'options',
				'series',
				'new ',
				'return ',
				'if (',
				'for (',
				'while (',
			];
			
			foreach ( $js_indicators as $indicator ) {
				if ( stripos( $code, $indicator ) !== false ) {
					$is_javascript = true;
					break;
				}
			}
			
			// Also check for common JavaScript patterns
			if ( ! $is_javascript && preg_match( '/\b(chart|Chart|options|series)\s*[=:{]/', $code ) ) {
				$is_javascript = true;
			}
			
			// Wrap in script tags if needed
			if ( $is_javascript && stripos( $code, '<script' ) === false ) {
				$code = '<script>' . "\n" . $code . "\n" . '</script>';
			}
			
			return $matches[1] . $code . $matches[3];
		}, $content );
		
		return $content;
	}
}

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, function() {
	require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'includes/class-plugin-activator.php';
	AI_Blog_Generator\Includes\Plugin_Activator::activate();
} );

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, function() {
	require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'includes/class-plugin-deactivator.php';
	AI_Blog_Generator\Includes\Plugin_Deactivator::deactivate();
} );

/**
 * Initialize and run the plugin.
 */
function ai_blog_generator() {
	return AI_Blog_Generator::instance();
}

// Initialize the plugin.
add_action( 'plugins_loaded', function() {
	ai_blog_generator()->run();
} ); 