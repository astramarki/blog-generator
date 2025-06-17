<?php
/**
 * AI Blog Generator
 *
 * @package           AI_Blog_Generator
 * @author            Your Name
 * @copyright         2024 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AI Blog Generator
 * Plugin URI:        https://example.com/ai-blog-generator
 * Description:       Automatically generate SEO-optimized blog posts using Claude 4 models (Sonnet 4 & Opus 4) and GPT-Image-1. Features include idea generation, content creation with images, scheduling, and cost tracking.
 * Version:           1.6.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
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
	 * Constructor - Initialize the plugin.
	 */
	private function __construct() {
		$this->version = AI_BLOG_GENERATOR_VERSION;
		
		// Load dependencies.
		$this->load_dependencies();
		
		// Set locale for internationalization.
		$this->set_locale();
		
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
		
		// Load utility traits (must be loaded before classes that use them).
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'utilities/trait-loggable.php';
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'utilities/trait-ajax-handler.php';
		
		$this->loader = new AI_Blog_Generator\Includes\Plugin_Loader();
	}

	/**
	 * Set plugin locale for internationalization.
	 */
	private function set_locale() {
		$plugin_i18n = new AI_Blog_Generator\Includes\Plugin_I18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Define all hooks for the admin area.
	 */
	private function define_admin_hooks() {
		// Only load admin functionality in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Initialize admin manager.
		$admin_manager = new AI_Blog_Generator\Admin\Admin_Manager( $this->version );
		
		// Admin menu and pages.
		$this->loader->add_action( 'admin_menu', $admin_manager, 'add_menu_pages' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_manager, 'enqueue_scripts' );
		
		// Register AJAX handlers.
		$admin_manager->register_ajax_handlers();
		
		// Initialize controllers for AJAX handling.
		$this->init_controllers();
	}

	/**
	 * Define all hooks for the public-facing side.
	 */
	private function define_public_hooks() {
		// Enqueue frontend scripts and styles.
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_frontend_scripts' );
		
		// Enqueue ApexCharts globally for any page that might have AI-generated content
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_apexcharts' );
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
				$this->loader->add_action( 'ai_blog_process_queue', $scheduler, 'process_approved_ideas' );
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
		
		// Blog Ideas V2 controller.
		$blog_ideas_v2_controller = new AI_Blog_Generator\Controllers\Blog_Ideas_Controller_V2();
		
		// Log controller initialization for debugging
		\AI_Blog_Generator\Utilities\Logger::debug( 'controller_initialization', 'Initializing Blog Ideas V2 Controller', [], __CLASS__, __METHOD__ );
		
		$blog_ideas_v2_controller->register_ajax_handlers();
		
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

		// Enqueue Bootstrap CSS (required for accordion styles)
		// Commented out to avoid conflicts with theme - accordion styles are in blogs.css
		// wp_enqueue_style(
		// 	'bootstrap-css',
		// 	'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
		// 	[],
		// 	'5.3.0'
		// );

		// Enqueue the blog styling framework CSS
		wp_enqueue_style(
			'ai-blog-generator-blogs-css',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/css/blogs.css',
			[], // Remove Bootstrap dependency
			$this->version
		);

		// Enqueue Bootstrap JavaScript (required for accordions to work)
		wp_enqueue_script(
			'bootstrap-js',
			'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
			[],
			'5.3.0',
			true // Load in footer
		);

		// Add accordion initialization script
		wp_add_inline_script(
			'bootstrap-js',
			'
			// Initialize Bootstrap accordions when DOM is ready
			document.addEventListener("DOMContentLoaded", function() {
				// Find all accordions and ensure they\'re initialized
				const accordions = document.querySelectorAll(".accordion");
				accordions.forEach(function(accordion) {
					// Initialize collapse for each accordion item
					const collapseElements = accordion.querySelectorAll(".accordion-collapse");
					collapseElements.forEach(function(collapseEl) {
						if (typeof bootstrap !== "undefined" && bootstrap.Collapse) {
							// Initialize Bootstrap collapse
							new bootstrap.Collapse(collapseEl, {
								toggle: false
							});
						}
					});
				});
				
				// Debug logging
				console.log("AI Blog Generator: Initialized " + accordions.length + " accordions");
			});
			',
			'after'
		);

		// Enqueue frontend JavaScript for interactive elements (accordions, charts, animations)
		wp_enqueue_script(
			'ai-blog-generator-frontend',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/frontend-blog.js',
			[ 'jquery', 'bootstrap-js' ], // Add Bootstrap JS as dependency
			$this->version,
			true
		);

		// Always enqueue ApexCharts on singular posts/pages since AI-generated content uses charts
		$this->enqueue_apexcharts();
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
	 * Enqueue ApexCharts.js from CDN.
	 */
	public function enqueue_apexcharts() {
		// Only enqueue if not already enqueued.
		if ( wp_script_is( 'apexcharts', 'enqueued' ) ) {
			return;
		}

		// Enqueue ApexCharts.js from CDN.
		wp_enqueue_script(
			'apexcharts',
			'https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js',
			[],
			'3.44.0', // Latest stable version as of 2024
			true // Load in footer for better performance
		);

		// Add inline script to make ApexCharts available globally.
		wp_add_inline_script(
			'apexcharts',
			'window.ApexCharts = window.ApexCharts || ApexCharts;
			
			// Initialize ApexCharts when DOM is ready
			document.addEventListener("DOMContentLoaded", function() {
				console.log("ApexCharts DOM ready, checking for charts...");
				
				function initializeCharts() {
					if (typeof ApexCharts === "undefined") {
						console.log("ApexCharts not loaded yet, retrying...");
						setTimeout(initializeCharts, 500);
						return;
					}
					
					console.log("ApexCharts available, initializing charts");
					
					// Find all chart elements
					const chartElements = document.querySelectorAll("[id^=\'chart\'], .blog-chart");
					console.log("Found " + chartElements.length + " chart elements");
					
					chartElements.forEach(function(chartEl) {
						// Skip if already initialized
						if (chartEl.querySelector(".apexcharts-canvas")) {
							return;
						}
						
						// Look for chart configuration
						let chartConfig = null;
						
						// Check for data attributes
						if (chartEl.dataset.chartConfig) {
							try {
								chartConfig = JSON.parse(chartEl.dataset.chartConfig);
							} catch (e) {
								console.error("Invalid chart config in data attribute", e);
							}
						}
						
						// Check for configuration in script tags
						if (!chartConfig) {
							const scriptTag = document.querySelector("script[data-chart-id=\'" + chartEl.id + "\']");
							if (scriptTag) {
								try {
									chartConfig = JSON.parse(scriptTag.textContent);
								} catch (e) {
									console.error("Invalid chart config in script tag", e);
								}
							}
						}
						
						// Initialize chart if config found
						if (chartConfig && chartConfig.series && chartConfig.series.length > 0) {
							try {
								chartConfig.chart = chartConfig.chart || {};
								chartConfig.chart.height = chartConfig.chart.height || 400;
								
								const chart = new ApexCharts(chartEl, chartConfig);
								chart.render();
								
								chartEl.classList.remove("blog-chart-loading");
								console.log("Chart initialized successfully for", chartEl.id);
							} catch (e) {
								console.error("Failed to initialize chart", chartEl.id, e);
								chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart failed to load</div>";
							}
						} else {
							console.log("No valid chart data found for", chartEl.id);
							chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart data not available</div>";
						}
					});
				}
				
				// Start chart initialization
				initializeCharts();
			});',
			'after'
		);
	}

	/**
	 * Check if a post might need chart functionality.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool True if post might need charts.
	 */
	private function post_might_need_charts( $post ) {
		// Check for chart-related keywords in post content.
		$chart_keywords = [
			'chart',
			'graph',
			'data',
			'statistics',
			'analytics',
			'metrics',
			'dashboard',
			'visualization',
			'apexcharts',
		];

		$content_lower = strtolower( $post->post_content );
		
		foreach ( $chart_keywords as $keyword ) {
			if ( strpos( $content_lower, $keyword ) !== false ) {
				return true;
			}
		}

		// Check for specific chart-related HTML elements or classes.
		if ( preg_match( '/<div[^>]*class="[^"]*chart[^"]*"[^>]*>/i', $post->post_content ) ) {
			return true;
		}

		// Check if post has chart-related categories or tags.
		$categories = get_the_category( $post->ID );
		$tags = get_the_tags( $post->ID );
		
		$terms_to_check = array_merge(
			wp_list_pluck( $categories, 'name' ),
			wp_list_pluck( $tags, 'name' )
		);

		foreach ( $terms_to_check as $term ) {
			if ( in_array( strtolower( $term ), $chart_keywords, true ) ) {
				return true;
			}
		}

		return false;
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

// SSL Fix for Local Development
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	// Disable SSL verification for local development
	add_filter( 'https_ssl_verify', '__return_false' );
	add_filter( 'https_local_ssl_verify', '__return_false' );
	add_filter( 'http_request_args', function( $args ) {
		$args['sslverify'] = false;
		$args['timeout'] = 120; // Increase timeout to 2 minutes
		return $args;
	} );
} 