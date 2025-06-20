<?php
/**
 * Admin Manager
 *
 * @package AI_Blog_Generator
 * @subpackage Admin
 */

namespace AI_Blog_Generator\Admin;

use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Persona_Model;
use AI_Blog_Generator\Models\Log_Model;
use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Services\Scheduler_Service;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Ajax_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Manager Class
 *
 * Registers and manages all admin pages.
 *
 * @since 1.0.0
 */
class Admin_Manager {

	use Ajax_Handler;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	private $menu_slug = 'ai-blog-generator';

	/**
	 * Minimum capability required.
	 *
	 * @var string
	 */
	private $capability = 'manage_options';

	/**
	 * Constructor.
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( $version ) {
		$this->version = $version;
	}

	/**
	 * Add menu pages to WordPress admin.
	 */
	public function add_menu_pages() {
		// Main menu.
		add_menu_page(
			__( 'AI Blog Generator', 'ai-blog-generator' ),
			__( 'AI Blog Generator', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug,
			[ $this, 'render_settings_page' ],
			'dashicons-edit-page',
			30
		);

		// Settings submenu (rename the main menu item).
		add_submenu_page(
			$this->menu_slug,
			__( 'Settings', 'ai-blog-generator' ),
			__( 'Settings', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug,
			[ $this, 'render_settings_page' ]
		);

		// Blog Ideas submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Blog Ideas', 'ai-blog-generator' ),
			__( 'Blog Ideas', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-ideas',
			[ $this, 'render_blog_ideas_page' ]
		);

		// Blog Ideas V2 submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Blog Ideas V2', 'ai-blog-generator' ),
			__( 'Blog Ideas V2', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-ideas-v2',
			[ $this, 'render_blog_ideas_v2_page' ]
		);

		// Approved Ideas V2 submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Approved Ideas V2', 'ai-blog-generator' ),
			__( 'Approved Ideas V2', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-approved-ideas-v2',
			[ $this, 'render_approved_ideas_v2_page' ]
		);

		// Approved Blogs submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Approved Blogs', 'ai-blog-generator' ),
			__( 'Approved Blogs', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-approved',
			[ $this, 'render_approved_blogs_page' ]
		);

		// Drafted Posts submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Drafted Posts', 'ai-blog-generator' ),
			__( 'Drafted Posts', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-drafts',
			[ $this, 'render_drafted_posts_page' ]
		);

		// Published Posts submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Published Posts', 'ai-blog-generator' ),
			__( 'Published Posts', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-published',
			[ $this, 'render_published_posts_page' ]
		);

		// Contexts submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Contexts', 'ai-blog-generator' ),
			__( 'Contexts', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-contexts',
			[ $this, 'render_contexts_page' ]
		);

		// Personas submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Personas', 'ai-blog-generator' ),
			__( 'Personas', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-personas',
			[ $this, 'render_personas_page' ]
		);

		// Logs submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Logs', 'ai-blog-generator' ),
			__( 'Logs', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-logs',
			[ $this, 'render_logs_page' ]
		);

		// Cost Dashboard submenu.
		add_submenu_page(
			$this->menu_slug,
			__( 'Cost Dashboard', 'ai-blog-generator' ),
			__( 'Cost Dashboard', 'ai-blog-generator' ),
			$this->capability,
			$this->menu_slug . '-costs',
			[ $this, 'render_costs_dashboard' ]
		);
	}

	/**
	 * Enqueue scripts and styles for admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Debug logging to identify hook names
		Logger::debug( 'admin_hook_debug', 'Admin page hook received', [
			'hook' => $hook,
			'menu_slug' => $this->menu_slug,
			'ideas_v2_found' => strpos( $hook, '-ideas-v2' ) !== false,
			'full_page_check' => $this->menu_slug . '-ideas-v2',
			'contains_menu_slug' => strpos( $hook, $this->menu_slug ) !== false
		], __CLASS__, __METHOD__ );
		
		// Only load on our plugin pages.
		if ( strpos( $hook, $this->menu_slug ) === false ) {
			return;
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'ai-blog-generator-admin',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/css/admin.css',
			[],
			$this->version
		);

		// Enqueue admin scripts.
		wp_enqueue_script(
			'ai-blog-generator-admin',
			AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/admin.js',
			[ 'jquery' ],
			$this->version,
			true
		);

		// Page-specific scripts.
		$this->enqueue_page_specific_scripts( $hook );

		// Localize script with data (after page-specific scripts are enqueued).
		$this->localize_scripts();
	}

	/**
	 * Localize scripts with necessary data and translations.
	 */
	private function localize_scripts() {
		// Check if we're on the Blog Ideas V2 page specifically
		$current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$is_ideas_v2_page = ( $current_page === $this->menu_slug . '-ideas-v2' );
		
		// Also check if we can detect it from the hook
		if ( ! $is_ideas_v2_page && isset( $GLOBALS['hook_suffix'] ) ) {
			$hook = $GLOBALS['hook_suffix'];
			$ideas_v2_patterns = [
				$this->menu_slug . '-ideas-v2',
				$this->menu_slug . '_page_' . $this->menu_slug . '-ideas-v2'
			];
			
			foreach ( $ideas_v2_patterns as $pattern ) {
				if ( strpos( $hook, $pattern ) !== false ) {
					$is_ideas_v2_page = true;
					break;
				}
			}
		}
		
		// Exclude Approved Ideas V2 page from Blog Ideas V2 detection
		if ( $is_ideas_v2_page && $current_page === $this->menu_slug . '-approved-ideas-v2' ) {
			$is_ideas_v2_page = false;
		}
		
		Logger::debug( 'admin_page_debug', 'Current admin page detected', [
			'current_page' => $current_page,
			'is_ideas_v2_page' => $is_ideas_v2_page,
			'hook_suffix' => $GLOBALS['hook_suffix'] ?? 'not_set',
			'expected_page' => $this->menu_slug . '-ideas-v2'
		], __CLASS__, __METHOD__ );
		// Get personas for JavaScript
		$personas = [];
		try {
			$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
			$all_personas = $persona_model->get_active_personas();
			
			// Debug logging
			Logger::debug( 'personas_localization', 'Loading personas for JavaScript localization', [
				'personas_type' => gettype( $all_personas ),
				'personas_count' => is_array( $all_personas ) ? count( $all_personas ) : 'not array',
				'first_persona' => ( is_array( $all_personas ) && !empty( $all_personas ) ) ? $all_personas[0] : null
			], __CLASS__, __METHOD__ );
			
			if ( is_array( $all_personas ) ) {
				foreach ( $all_personas as $persona ) {
					// Handle both object and array formats for backward compatibility
					$id = is_object( $persona ) ? $persona->id : $persona['id'];
					$name = is_object( $persona ) ? $persona->name : $persona['name'];
					$tone = is_object( $persona ) ? $persona->tone : $persona['tone'];
					
					$personas[ $id ] = [
						'id' => $id,
						'name' => $name,
						'tone' => $tone
					];
				}
			}
		} catch ( \Exception $e ) {
			Logger::error( 'personas_localization_failed', 'Failed to load personas for localization', [
				'error_message' => $e->getMessage(),
				'error_file' => $e->getFile(),
				'error_line' => $e->getLine()
			], __CLASS__, __METHOD__ );
		}

		// Get categories for JavaScript
		$categories = [];
		try {
			$wp_categories = get_categories( [ 'hide_empty' => false ] );
			foreach ( $wp_categories as $category ) {
				$categories[ $category->term_id ] = $category->name;
			}
		} catch ( \Exception $e ) {
			Logger::error( 'categories_localization_failed', 'Failed to load categories for localization', [
				'error_message' => $e->getMessage(),
				'error_file' => $e->getFile(),
				'error_line' => $e->getLine()
			], __CLASS__, __METHOD__ );
		}

		$localized_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ai_blog_admin_nonce' ),
			'adminUrl' => admin_url(),
			'personas' => $personas,
			'categories' => $categories,
			'strings' => [
				'confirm_approve' => __( 'Are you sure you want to approve this idea?', 'ai-blog-generator' ),
				'confirm_deny' => __( 'Are you sure you want to deny this idea?', 'ai-blog-generator' ),
				'confirm_bulk' => __( 'Apply this action to selected items?', 'ai-blog-generator' ),
				'confirm_delete' => __( 'Are you sure you want to delete this item? This action cannot be undone.', 'ai-blog-generator' ),
				'generating' => __( 'Generating...', 'ai-blog-generator' ),
				'saving' => __( 'Saving...', 'ai-blog-generator' ),
				'loading' => __( 'Loading...', 'ai-blog-generator' ),
				'success' => __( 'Success!', 'ai-blog-generator' ),
				'error' => __( 'Error!', 'ai-blog-generator' ),
				'error_generic' => __( 'An unexpected error occurred. Please try again.', 'ai-blog-generator' ),
			],
			'settings' => [
				'posts_per_day' => get_option( 'ai_blog_generator_posts_per_day', 999 ), // High default instead of hardcoded 2
				'auto_publish' => get_option( 'ai_blog_generator_auto_publish', false ),
				'generation_paused' => get_option( 'ai_blog_generator_generation_paused', false ),
			],
		];

		// Localize for main admin script
		wp_localize_script( 'ai-blog-generator-admin', 'aiBlogAjax', $localized_data );
		
		// Localize for page-specific scripts (wp_localize_script silently fails if script doesn't exist)
		wp_localize_script( 'ai-blog-generator-contexts', 'aiBlogAjax', $localized_data );
		wp_localize_script( 'ai-blog-generator-personas', 'aiBlogAjax', $localized_data );
		wp_localize_script( 'ai-blog-generator-logs', 'aiBlogAjax', $localized_data );
		
		// Debug Blog Ideas V2 script localization
		$script_registered = wp_script_is( 'ai-blog-ideas-v2', 'registered' );
		$script_enqueued = wp_script_is( 'ai-blog-ideas-v2', 'enqueued' );
		Logger::debug( 'script_localization_debug', 'Blog Ideas V2 script status check', [
			'script_registered' => $script_registered,
			'script_enqueued' => $script_enqueued,
			'nonce' => $localized_data['nonce']
		], __CLASS__, __METHOD__ );
		
		wp_localize_script( 'ai-blog-ideas-v2', 'ai_blog_admin', $localized_data );
		
		// Debug Approved Ideas V2 script localization
		$approved_script_registered = wp_script_is( 'ai-approved-ideas-v2', 'registered' );
		$approved_script_enqueued = wp_script_is( 'ai-approved-ideas-v2', 'enqueued' );
		Logger::debug( 'script_localization_debug', 'Approved Ideas V2 script status check', [
			'script_registered' => $approved_script_registered,
			'script_enqueued' => $approved_script_enqueued,
			'nonce' => $localized_data['nonce']
		], __CLASS__, __METHOD__ );
		
		wp_localize_script( 'ai-approved-ideas-v2', 'ai_blog_admin', $localized_data );
		
		// If we're on the Blog Ideas V2 page but the script wasn't enqueued, enqueue it now
		if ( $is_ideas_v2_page && ! wp_script_is( 'ai-blog-ideas-v2', 'enqueued' ) ) {
					Logger::debug( 'force_script_enqueue', 'Blog Ideas V2 script not enqueued, forcing enqueue', [
			'is_ideas_v2_page' => $is_ideas_v2_page,
			'script_enqueued_before' => wp_script_is( 'ai-blog-ideas-v2', 'enqueued' ),
			'script_registered_before' => wp_script_is( 'ai-blog-ideas-v2', 'registered' ),
			'current_page' => $current_page,
			'hook_suffix' => $GLOBALS['hook_suffix'] ?? 'not_set'
		], __CLASS__, __METHOD__ );
			
			// Enqueue Bootstrap first
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
				[],
				'5.3.0'
			);
			
			wp_enqueue_script(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
				['jquery'],
				'5.3.0',
				true
			);
			
			// Enqueue FontAwesome
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
				[],
				'6.4.0'
			);
			
			// Enqueue the Blog Ideas V2 script
			wp_enqueue_script(
				'ai-blog-ideas-v2',
				AI_BLOG_GENERATOR_PLUGIN_URL . 'assets/js/blog-ideas-v2.js',
				['jquery', 'bootstrap'],
				$this->version,
				true
			);
			
			// Localize the script again
			wp_localize_script( 'ai-blog-ideas-v2', 'ai_blog_admin', $localized_data );
			
					Logger::debug( 'force_script_enqueue_completed', 'Forced script enqueue completed successfully', [
			'script_now_enqueued' => wp_script_is( 'ai-blog-ideas-v2', 'enqueued' ),
			'script_now_registered' => wp_script_is( 'ai-blog-ideas-v2', 'registered' ),
			'bootstrap_enqueued' => wp_script_is( 'bootstrap', 'enqueued' ),
			'fontawesome_enqueued' => wp_style_is( 'font-awesome', 'enqueued' )
		], __CLASS__, __METHOD__ );
		}
	}

	/**
	 * Enqueue page-specific scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	private function enqueue_page_specific_scripts( $hook ) {
		// Cost Dashboard - Chart.js for visualizations.
		if ( strpos( $hook, $this->menu_slug . '-costs' ) !== false ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
				[],
				'3.9.1',
				true
			);

			// Also enqueue ApexCharts as an alternative charting library.
			wp_enqueue_script(
				'apexcharts-admin',
				'https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js',
				[],
				'3.44.0',
				true
			);

			// Add inline script to make both charting libraries available.
			wp_add_inline_script(
				'apexcharts-admin',
				'window.ApexCharts = window.ApexCharts || ApexCharts;',
				'after'
			);
		}

		// Contexts page - CodeMirror for better text editing and contexts.js for functionality.
		if ( strpos( $hook, $this->menu_slug . '-contexts' ) !== false ) {
			wp_enqueue_script( 'wp-codemirror' );
			wp_enqueue_style( 'wp-codemirror' );
			
			// Enqueue contexts-specific JavaScript
			wp_enqueue_script(
				'ai-blog-generator-contexts',
				AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/contexts.js',
				[ 'jquery', 'ai-blog-generator-admin' ],
				$this->version,
				true
			);
		}

		// Personas page - personas.js for functionality.
		if ( strpos( $hook, $this->menu_slug . '-personas' ) !== false ) {
			// Enqueue personas-specific JavaScript
			wp_enqueue_script(
				'ai-blog-generator-personas',
				AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/personas.js',
				[ 'jquery', 'ai-blog-generator-admin' ],
				$this->version,
				true
			);
		}

		// Logs page - Enhanced logs functionality
		if ( strpos( $hook, $this->menu_slug . '-logs' ) !== false ) {
			// Enqueue logs-specific JavaScript
			wp_enqueue_script(
				'ai-blog-generator-logs',
				AI_BLOG_GENERATOR_PLUGIN_URL . 'admin/assets/js/logs.js',
				[ 'jquery', 'ai-blog-generator-admin' ],
				$this->version,
				true
			);
		}

		// Blog Ideas V2 page - Bootstrap 5 and modern assets
		// WordPress admin hooks can be: ai-blog-generator_page_ai-blog-generator-ideas-v2
		$ideas_v2_patterns = [
			$this->menu_slug . '-ideas-v2',
			$this->menu_slug . '_page_' . $this->menu_slug . '-ideas-v2'
		];
		
		$is_ideas_v2_page = false;
		foreach ( $ideas_v2_patterns as $pattern ) {
			if ( strpos( $hook, $pattern ) !== false ) {
				$is_ideas_v2_page = true;
				break;
			}
		}
		
		// Also check current page parameter as fallback (exact match only)
		if ( ! $is_ideas_v2_page && isset( $_GET['page'] ) ) {
			$is_ideas_v2_page = ( $_GET['page'] === $this->menu_slug . '-ideas-v2' );
		}
		
		// Exclude Approved Ideas V2 page from Blog Ideas V2 detection
		if ( $is_ideas_v2_page && isset( $_GET['page'] ) && $_GET['page'] === $this->menu_slug . '-approved-ideas-v2' ) {
			$is_ideas_v2_page = false;
		}
		
		if ( $is_ideas_v2_page ) {
			// Debug logging for Blog Ideas V2 page detection
			Logger::debug( 'page_specific_scripts', 'Blog Ideas V2 page detected, enqueuing scripts', [
				'hook' => $hook,
				'menu_slug' => $this->menu_slug,
				'matched_pattern' => $pattern ?? 'page_parameter',
				'page_param' => $_GET['page'] ?? 'none'
			], __CLASS__, __METHOD__ );
			// Enqueue Bootstrap 5
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
				[],
				'5.3.0'
			);
			
			wp_enqueue_script(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
				['jquery'],
				'5.3.0',
				true
			);
			
			// Enqueue FontAwesome
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
				[],
				'6.4.0'
			);
			
			// Enqueue Blog Ideas V2 JavaScript
			wp_enqueue_script(
				'ai-blog-ideas-v2',
				AI_BLOG_GENERATOR_PLUGIN_URL . 'assets/js/blog-ideas-v2.js',
				['jquery', 'bootstrap'],
				$this->version,
				true
			);
		}

		// Approved Ideas V2 page - Bootstrap 5 and modern assets
		// WordPress admin hooks can be: ai-blog-generator_page_ai-blog-generator-approved-ideas-v2
		$approved_ideas_v2_patterns = [
			$this->menu_slug . '-approved-ideas-v2',
			$this->menu_slug . '_page_' . $this->menu_slug . '-approved-ideas-v2'
		];
		
		$is_approved_ideas_v2_page = false;
		foreach ( $approved_ideas_v2_patterns as $pattern ) {
			if ( strpos( $hook, $pattern ) !== false ) {
				$is_approved_ideas_v2_page = true;
				break;
			}
		}
		
		// Also check current page parameter as fallback (exact match only)
		if ( ! $is_approved_ideas_v2_page && isset( $_GET['page'] ) ) {
			$is_approved_ideas_v2_page = ( $_GET['page'] === $this->menu_slug . '-approved-ideas-v2' );
		}
		
		if ( $is_approved_ideas_v2_page ) {
			// Debug logging for Approved Ideas V2 page detection
			Logger::debug( 'page_specific_scripts', 'Approved Ideas V2 page detected, enqueuing scripts', [
				'hook' => $hook,
				'menu_slug' => $this->menu_slug,
				'matched_pattern' => $pattern ?? 'page_parameter',
				'page_param' => $_GET['page'] ?? 'none'
			], __CLASS__, __METHOD__ );
			
			// Enqueue Bootstrap 5
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
				[],
				'5.3.0'
			);
			
			wp_enqueue_script(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
				['jquery'],
				'5.3.0',
				true
			);
			
			// Enqueue FontAwesome
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
				[],
				'6.4.0'
			);
			
			// NOTE: Approved Ideas V2 JavaScript is now handled by the controller's enqueue_assets() method
			// to prevent duplicate script loading. The controller handles its own asset management.
		}

		// Blog Ideas page - ApexCharts for potential data visualization
		if ( strpos( $hook, $this->menu_slug . '-blog-ideas' ) !== false || 
			 strpos( $hook, $this->menu_slug . '-approved' ) !== false ||
			 strpos( $hook, $this->menu_slug . '-drafted-posts' ) !== false ||
			 strpos( $hook, $this->menu_slug . '-published-posts' ) !== false ) {
			
			// Enqueue ApexCharts for potential charts in blog management pages
			wp_enqueue_script(
				'apexcharts-admin',
				'https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js',
				[],
				'3.44.0',
				true
			);

			wp_add_inline_script(
				'apexcharts-admin',
				'window.ApexCharts = window.ApexCharts || ApexCharts;',
				'after'
			);
		}

		// Modal fix for approved blogs page
		if ( strpos( $hook, $this->menu_slug . '-approved' ) !== false ) {
			$modal_fix_script = "
			jQuery(document).ready(function($) {
				console.log('Modal fix script starting...');
				
				function initializeModalFix() {
					console.log('Checking for aiBlogGenerator object...');
					console.log('window.aiBlogGenerator exists:', !!window.aiBlogGenerator);
					console.log('aiBlogAjax object:', aiBlogAjax);
					console.log('aiBlogAjax.personas:', aiBlogAjax ? aiBlogAjax.personas : 'aiBlogAjax undefined');
					console.log('aiBlogAjax.categories:', aiBlogAjax ? aiBlogAjax.categories : 'aiBlogAjax undefined');
					
					if (window.aiBlogGenerator && typeof window.aiBlogGenerator.showIdeaEditModal === 'function') {
						console.log('Found aiBlogGenerator.showIdeaEditModal, overriding...');
						
						// Store original method
						window.aiBlogGenerator.originalShowIdeaEditModal = window.aiBlogGenerator.showIdeaEditModal;
						
						// Override with enhanced version
						window.aiBlogGenerator.showIdeaEditModal = function(ideaData) {
							console.log('Enhanced showIdeaEditModal called with:', ideaData);
							
							var categoryOptions = '<option value=\"0\">General</option>';
							if (aiBlogAjax && aiBlogAjax.categories) {
								console.log('Building category options from:', aiBlogAjax.categories);
								for (var categoryId in aiBlogAjax.categories) {
									var categoryName = aiBlogAjax.categories[categoryId];
									var selected = (ideaData.category_id == categoryId) ? ' selected' : '';
									categoryOptions += '<option value=\"' + categoryId + '\"' + selected + '>' + categoryName + '</option>';
								}
							} else {
								console.log('No categories found in aiBlogAjax');
							}
							
							var personaOptions = '<option value=\"\">None</option>';
							if (aiBlogAjax && aiBlogAjax.personas) {
								console.log('Building persona options from:', aiBlogAjax.personas);
								for (var personaId in aiBlogAjax.personas) {
									var persona = aiBlogAjax.personas[personaId];
									var selected = (ideaData.persona_id == personaId) ? ' selected' : '';
									personaOptions += '<option value=\"' + persona.id + '\"' + selected + '>' + persona.name + ' (' + persona.tone + ')</option>';
								}
							} else {
								console.log('No personas found in aiBlogAjax');
							}
							
							console.log('Final category options HTML:', categoryOptions);
							console.log('Final persona options HTML:', personaOptions);
							
							var modalHtml = '<div id=\"edit-idea-modal\" class=\"ai-blog-modal ai-blog-modal-active\" style=\"display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999;\">' +
								'<div class=\"ai-blog-modal-content\" style=\"position: relative; background: white; margin: 5% auto; padding: 0; width: 90%; max-width: 600px; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);\">' +
								'<div class=\"ai-blog-modal-header\" style=\"padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;\">' +
								'<h2 class=\"ai-blog-modal-title\" style=\"margin: 0; font-size: 1.3em;\">Edit Approved Idea</h2>' +
								'<button type=\"button\" class=\"ai-blog-modal-close\" style=\"background: none; border: none; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;\">&times;</button>' +
								'</div>' +
								'<form class=\"ai-blog-modal-body\" style=\"padding: 20px;\">' +
								'<input type=\"hidden\" id=\"edit-idea-id\" value=\"' + (ideaData.id || '') + '\">' +
								'<div class=\"ai-blog-form-group\" style=\"margin-bottom: 20px;\">' +
								'<label for=\"edit-idea-title\" style=\"display: block; margin-bottom: 5px; font-weight: 600;\">Title:</label>' +
								'<input type=\"text\" id=\"edit-idea-title\" class=\"ai-blog-form-control\" style=\"width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;\" value=\"' + (ideaData.title || '').replace(/\"/g, '&quot;') + '\" required>' +
								'</div>' +
								'<div class=\"ai-blog-form-group\" style=\"margin-bottom: 20px;\">' +
								'<label for=\"edit-idea-description\" style=\"display: block; margin-bottom: 5px; font-weight: 600;\">Description:</label>' +
								'<textarea id=\"edit-idea-description\" class=\"ai-blog-form-control\" style=\"width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-height: 100px; resize: vertical;\" rows=\"4\" required>' + (ideaData.description || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</textarea>' +
								'</div>' +
								'<div class=\"ai-blog-form-group\" style=\"margin-bottom: 20px;\">' +
								'<label for=\"edit-idea-category\" style=\"display: block; margin-bottom: 5px; font-weight: 600;\">Category:</label>' +
								'<select id=\"edit-idea-category\" class=\"ai-blog-form-control\" style=\"width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;\">' + categoryOptions + '</select>' +
								'</div>' +
								'<div class=\"ai-blog-form-group\" style=\"margin-bottom: 20px;\">' +
								'<label for=\"edit-idea-persona\" style=\"display: block; margin-bottom: 5px; font-weight: 600;\">Writing Persona:</label>' +
								'<select id=\"edit-idea-persona\" class=\"ai-blog-form-control\" style=\"width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;\">' + personaOptions + '</select>' +
								'</div>' +
								'<div class=\"ai-blog-form-group\" style=\"margin-bottom: 20px;\">' +
								'<label class=\"ai-blog-checkbox-label\" style=\"display: flex; align-items: center; cursor: pointer;\">' +
								'<input type=\"checkbox\" id=\"generate-after-edit\" style=\"margin-right: 8px;\"> Generate blog post after saving changes' +
								'</label>' +
								'</div>' +
								'<div class=\"ai-blog-modal-footer\" style=\"padding: 20px 0 0 0; border-top: 1px solid #ddd; text-align: right;\">' +
								'<button type=\"button\" class=\"button ai-blog-cancel-idea-edit\" style=\"margin-right: 10px;\">Cancel</button>' +
								'<button type=\"button\" class=\"button button-primary ai-blog-save-idea-edit\">Save Changes</button>' +
								'</div>' +
								'</form>' +
								'</div>' +
								'</div>';
							
							console.log('Modal HTML about to be added. ideaData.id:', ideaData.id);
							
							// Remove existing modal and add new one
							$('#edit-idea-modal').remove();
							$('body').append(modalHtml);
							
							console.log('Modal added to DOM');
							console.log('Hidden field value:', $('#edit-idea-id').val());
							
							// Focus the title field
							setTimeout(function() {
								$('#edit-idea-title').focus();
								console.log('Title field focused');
								console.log('Final hidden field check:', $('#edit-idea-id').val());
							}, 100);
						};
						
						console.log('showIdeaEditModal override complete');
						return true;
					} else {
						console.log('aiBlogGenerator object or showIdeaEditModal method not found, will retry...');
						return false;
					}
				}
				
				// Try to initialize immediately
				if (!initializeModalFix()) {
					// If not ready, try again after aiBlogGenerator is initialized
					var attempts = 0;
					var maxAttempts = 20;
					var checkInterval = setInterval(function() {
						attempts++;
						console.log('Retry attempt', attempts, 'of', maxAttempts);
						
						if (initializeModalFix() || attempts >= maxAttempts) {
							clearInterval(checkInterval);
							if (attempts >= maxAttempts) {
								console.log('Failed to initialize modal fix after', maxAttempts, 'attempts');
							}
						}
					}, 500);
				}
			});
			";
			wp_add_inline_script( 'ai-blog-generator-admin', $modal_fix_script );
		}

		// Media uploader for seed images.
		if ( in_array( $hook, [
			'toplevel_page_' . $this->menu_slug,
			$this->menu_slug . '_page_' . $this->menu_slug . '-contexts',
		], true ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get current settings.
		$settings = [
			'anthropic_api_key' => get_option( 'ai_blog_generator_anthropic_api_key', '' ),
			'openai_api_key' => get_option( 'ai_blog_generator_openai_api_key', '' ),
			'ideas_per_day' => get_option( 'ai_blog_generator_ideas_per_day', 5 ),
			'posts_per_day' => get_option( 'ai_blog_generator_posts_per_day', 999 ), // High default instead of hardcoded 2
			'auto_publish' => get_option( 'ai_blog_generator_auto_publish', false ),
			'default_category' => get_option( 'ai_blog_generator_default_category', get_option( 'default_category' ) ),
			'publish_time_min' => get_option( 'ai_blog_generator_publish_time_min', '08:00' ),
			'publish_time_max' => get_option( 'ai_blog_generator_publish_time_max', '20:00' ),
			'weekend_publishing' => get_option( 'ai_blog_generator_weekend_publishing', true ),
			'monthly_budget' => get_option( 'ai_blog_generator_monthly_budget', 100.00 ),
			'budget_alert_threshold' => get_option( 'ai_blog_generator_budget_alert_threshold', 80 ),
			'enable_idea_generation' => get_option( 'ai_blog_generator_enable_idea_generation', true ),
			'enable_image_generation' => get_option( 'ai_blog_generator_enable_image_generation', true ),
			'enable_seo_optimization' => get_option( 'ai_blog_generator_enable_seo_optimization', true ),
		];

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the blog ideas page.
	 */
	public function render_blog_ideas_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get ideas.
		$idea_model = new Idea_Model();
		$ideas = $idea_model->get_with_category( [ 'status' => 'pending' ], 'created_at DESC' );
		
		// Get statistics.
		$statistics = $idea_model->get_statistics();
		
		// Get categories for dropdown.
		$categories = get_categories( [ 'hide_empty' => false ] );

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/blog-ideas.php';
	}

	/**
	 * Render the Blog Ideas V2 page.
	 */
	public function render_blog_ideas_v2_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/blog-ideas-view-v2.php';
	}

	/**
	 * Render the Approved Ideas V2 page.
	 */
	public function render_approved_ideas_v2_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Use the dedicated controller instead of loading the view directly
		$controller = new \AI_Blog_Generator\Controllers\Approved_Ideas_Controller_V2();
		$controller->render_page();
	}

	/**
	 * Render the approved blogs page.
	 */
	public function render_approved_blogs_page() {
		try {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get approved ideas.
		$idea_model = new Idea_Model();
		$approved_ideas = $idea_model->get_with_category( [ 'status' => 'approved' ], 'created_at ASC' );
		
		// Get generation settings.
		$posts_per_day = intval( get_option( 'ai_blog_generator_posts_per_day', 999 ) ); // High default instead of hardcoded 2
		$blog_model = new Blog_Model();
		$today_generated = $blog_model->count_generated_today();
		
		// Check if generation is paused.
		$generation_paused = get_option( 'ai_blog_generator_generation_paused', false );

			// Get personas for dropdown.
			$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
			$personas = $persona_model->get_active_personas();
			
			// Get categories for dropdown.
			$categories = get_categories( [ 'hide_empty' => false ] );

			// Debug logging
			if ( defined( 'AI_BLOG_GENERATOR_DEBUG' ) && AI_BLOG_GENERATOR_DEBUG ) {
				error_log( 'Approved blogs page: personas count = ' . ( is_array( $personas ) ? count( $personas ) : 'not array' ) );
				error_log( 'Approved blogs page: categories count = ' . ( is_array( $categories ) ? count( $categories ) : 'not array' ) );
			}

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/approved-blogs.php';
			
		} catch ( Exception $e ) {
			error_log( 'Error in render_approved_blogs_page: ' . $e->getMessage() );
			wp_die( 'An error occurred while loading the approved blogs page. Please check the error logs.' );
		}
	}

	/**
	 * Render the drafted posts page.
	 */
	public function render_drafted_posts_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get drafted posts.
		$blog_model = new Blog_Model();
		$drafted_posts = $blog_model->get_all_with_details( [ 'status' => 'draft' ], 'gp.created_at DESC' );
		
		// Get scheduler service.
		$scheduler = new Scheduler_Service();

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/drafted-posts.php';
	}

	/**
	 * Render the published posts page.
	 */
	public function render_published_posts_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get published posts.
		$blog_model = new Blog_Model();
		$published_posts = $blog_model->get_all_with_details( [ 'status' => 'published' ], 'gp.created_at DESC', 50 );
		
		// Get statistics.
		$statistics = $blog_model->get_statistics( 'month' );

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/published-posts.php';
	}

	/**
	 * Render the contexts page.
	 */
	public function render_contexts_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get contexts.
		$context_model = new Context_Model();
		$contexts = $context_model->find_all( [], 'type ASC, name ASC' );
		
		// Get context types.
		$context_types = [
			'general' => __( 'General Business Information', 'ai-blog-generator' ),
			'products' => __( 'Products and Services', 'ai-blog-generator' ),
			'seo' => __( 'SEO Guidelines', 'ai-blog-generator' ),
			'keywords' => __( 'Target Keywords', 'ai-blog-generator' ),
			'image' => __( 'Image Generation Guidelines', 'ai-blog-generator' ),
			'layout' => __( 'Layout Guidelines', 'ai-blog-generator' ),
		];

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/contexts.php';
	}

	/**
	 * Render the personas page.
	 */
	public function render_personas_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get personas model.
		$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
		
		// Get all personas.
		$personas = $persona_model->get_all( [], 'name ASC' );
		
		// Format personas for view
		$formatted_personas = [];
		foreach ( $personas as $persona ) {
			$formatted_personas[] = $persona_model->format( $persona );
		}
		
		// Get tone options.
		$tone_options = [
			'professional' => __( 'Professional', 'ai-blog-generator' ),
			'friendly' => __( 'Friendly', 'ai-blog-generator' ),
			'analytical' => __( 'Analytical', 'ai-blog-generator' ),
			'inspirational' => __( 'Inspirational', 'ai-blog-generator' ),
			'casual' => __( 'Casual', 'ai-blog-generator' ),
			'academic' => __( 'Academic', 'ai-blog-generator' ),
		];
		
		// Pass formatted personas to view
		$personas = $formatted_personas;

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/personas.php';
	}

	/**
	 * Render the logs page.
	 */
	public function render_logs_page() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get filter parameters.
		$filters = [
			'level' => isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : '',
			'action' => isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
			'date_to' => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
			'search' => isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '',
		];

		// Get per_page parameter
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 50;
		$per_page = max( 1, min( 500, $per_page ) ); // Limit between 1 and 500
		
		// Get current page
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;

		// Get logs.
		$log_model = new Log_Model();
		$logs = $log_model->get_filtered( $filters, $per_page, $offset );
		
		// Get available actions for filter.
		$available_actions = $log_model->get_distinct_actions();

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * Render the costs dashboard.
	 */
	public function render_costs_dashboard() {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-blog-generator' ) );
		}

		// Get cost model.
		$cost_model = new Cost_Model();
		
		// Get current month costs.
		$current_month_start = date( 'Y-m-01' );
		$current_month_end = date( 'Y-m-t' );
		$current_month_costs = $cost_model->get_total_cost( $current_month_start, $current_month_end );
		
		// Get budget info.
		$monthly_budget = floatval( get_option( 'ai_blog_generator_monthly_budget', 100.00 ) );
		$budget_used_percentage = $monthly_budget > 0 ? ( $current_month_costs / $monthly_budget ) * 100 : 0;
		
		// Get cost breakdown.
		$cost_breakdown = $cost_model->get_cost_breakdown( 'current_month' );
		
		// Get daily costs for chart.
		$daily_costs = $cost_model->get_daily_costs( 30 );
		
		// Get cost by service.
		$cost_by_service = $cost_model->get_cost_by_service( $current_month_start, $current_month_end );
		
		// Get cost projections.
		$projections = $cost_model->project_monthly_cost();
		
		// Check if generation is paused due to budget.
		$generation_paused = get_option( 'ai_blog_generator_generation_paused', false );

		// Load the view.
		require_once AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/costs-dashboard.php';
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register_ajax_handlers() {
		// Settings page handlers.
		add_action( 'wp_ajax_ai_blog_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_ai_blog_test_api_connection', [ $this, 'ajax_test_api_connection' ] );
		
		// Blog ideas handlers.
		add_action( 'wp_ajax_ai_blog_approve_idea', [ $this, 'ajax_approve_idea' ] );
		add_action( 'wp_ajax_ai_blog_deny_idea', [ $this, 'ajax_deny_idea' ] );
		add_action( 'wp_ajax_ai_blog_bulk_approve_ideas', [ $this, 'ajax_bulk_approve_ideas' ] );
		add_action( 'wp_ajax_ai_blog_bulk_deny_ideas', [ $this, 'ajax_bulk_deny_ideas' ] );
		
		// Blog generation handlers.
		add_action( 'wp_ajax_ai_blog_generate_post', [ $this, 'ajax_generate_post' ] );
		add_action( 'wp_ajax_ai_blog_schedule_post', [ $this, 'ajax_schedule_post' ] );
		add_action( 'wp_ajax_ai_blog_publish_post', [ $this, 'ajax_publish_post' ] );
		
		// Approved blogs handlers.
		add_action( 'wp_ajax_ai_blog_get_approved_ideas', [ $this, 'ajax_get_approved_ideas' ] );
		add_action( 'wp_ajax_ai_blog_get_idea_for_edit', [ $this, 'ajax_get_idea_for_edit' ] );
		add_action( 'wp_ajax_ai_blog_update_approved_idea', [ $this, 'ajax_update_approved_idea' ] );
		add_action( 'wp_ajax_ai_blog_generate_from_approved_idea', [ $this, 'ajax_generate_from_approved_idea' ] );
		// Removed duplicate handler: add_action( 'wp_ajax_ai_blog_get_generation_status', [ $this, 'ajax_get_generation_status' ] );
		
		// Context handlers.
		add_action( 'wp_ajax_ai_blog_save_context', [ $this, 'ajax_save_context' ] );
		add_action( 'wp_ajax_ai_blog_delete_context', [ $this, 'ajax_delete_context' ] );
		add_action( 'wp_ajax_ai_blog_toggle_context', [ $this, 'ajax_toggle_context' ] );
		
		// Note: Seed image handlers are registered in Context_Controller
		
		// Log handlers.
		add_action( 'wp_ajax_ai_blog_export_logs', [ $this, 'ajax_export_logs' ] );
		add_action( 'wp_ajax_ai_blog_clear_logs', [ $this, 'ajax_clear_logs' ] );
		
		// Enhanced log handlers for new logs page
		add_action( 'wp_ajax_ai_blog_get_logs', [ $this, 'ajax_get_logs' ] );
		add_action( 'wp_ajax_ai_blog_get_log_stats', [ $this, 'ajax_get_log_stats' ] );
		add_action( 'wp_ajax_ai_blog_check_new_logs', [ $this, 'ajax_check_new_logs' ] );
		add_action( 'wp_ajax_ai_blog_clear_old_logs', [ $this, 'ajax_clear_old_logs' ] );
		
		// Cost handlers.
		add_action( 'wp_ajax_ai_blog_get_cost_data', [ $this, 'ajax_get_cost_data' ] );
		add_action( 'wp_ajax_ai_blog_reset_budget', [ $this, 'ajax_reset_budget' ] );
		
		// Cron handlers.
		add_action( 'wp_ajax_ai_blog_trigger_cron', [ $this, 'ajax_trigger_cron' ] );
		add_action( 'wp_ajax_ai_blog_get_cron_status', [ $this, 'ajax_get_cron_status' ] );
	}

	/**
	 * Save plugin settings via AJAX.
	 */
	public function ajax_save_settings() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return; // verify_ajax_security already sends the error response
			}

			$this->log_info( 'settings_save_start', 'Starting plugin settings save process', [
				'user_id' => get_current_user_id(),
				'settings_keys' => array_keys( $_POST )
			] );

			// Sanitize settings data
			$sanitized_settings = $this->sanitize_ajax_data( $_POST, [
				'anthropic_api_key' => 'sanitize_text_field',
				'anthropic_model' => 'sanitize_text_field',
				'openai_api_key' => 'sanitize_text_field',
				'ideas_per_day' => 'absint',
				'posts_per_day' => 'absint',
				'auto_publish' => function( $value ) { return (bool) $value; },
				'default_category' => 'absint',
				'publish_time_min' => 'sanitize_text_field',
				'publish_time_max' => 'sanitize_text_field',
				'weekend_publishing' => function( $value ) { return (bool) $value; },
				'monthly_budget' => function( $value ) { return floatval( $value ); },
				'budget_alert_threshold' => 'absint',
				'debug_logging' => function( $value ) { return (bool) $value; },
				'enable_idea_generation' => function( $value ) { return (bool) $value; },
				'enable_image_generation' => function( $value ) { return (bool) $value; },
				'enable_seo_optimization' => function( $value ) { return (bool) $value; }
			] );

			$settings_updated = [];
			$settings_errors = [];

			// Save each setting with validation
			foreach ( $sanitized_settings as $key => $value ) {
				if ( $key === 'nonce' ) {
					continue; // Skip nonce field
				}

				$option_name = 'ai_blog_generator_' . $key;
				
				// Validate specific settings
				$validation_result = $this->validate_setting( $key, $value );
				if ( ! $validation_result['valid'] ) {
					$settings_errors[ $key ] = $validation_result['message'];
					$this->log_warning( 'setting_validation_failed', 'Setting validation failed', [
						'setting_key' => $key,
						'provided_value' => $value,
						'error_message' => $validation_result['message']
					] );
					continue;
				}

				// Log sensitive data handling
				if ( in_array( $key, [ 'anthropic_api_key', 'openai_api_key' ], true ) ) {
					$this->log_info( 'api_key_updated', 'API key updated', [
						'key_type' => $key,
						'key_length' => strlen( $value ),
						'key_provided' => ! empty( $value ),
						'updated_by' => get_current_user_id()
					] );
				}

				// Update option
				$updated = update_option( $option_name, $value );
				if ( $updated ) {
					$settings_updated[ $key ] = $value;
					$this->log_debug( 'setting_updated', 'Setting updated successfully', [
						'setting_key' => $key,
						'option_name' => $option_name,
						'value_type' => gettype( $value )
					] );
				} else {
					// Check if value is the same (update_option returns false for same values)
					$current_value = get_option( $option_name );
					if ( $current_value !== $value ) {
						$settings_errors[ $key ] = 'Failed to update setting';
						$this->log_error( 'setting_update_failed', 'Failed to update setting', [
							'setting_key' => $key,
							'option_name' => $option_name,
							'provided_value' => $value,
							'current_value' => $current_value
						] );
					} else {
						$settings_updated[ $key ] = $value;
						$this->log_debug( 'setting_unchanged', 'Setting value unchanged', [
							'setting_key' => $key
						] );
					}
				}
			}

			// Handle debug logging setting change
			if ( isset( $settings_updated['debug_logging'] ) ) {
				$this->log_info( 'debug_logging_changed', 'Debug logging setting changed', [
					'new_value' => $settings_updated['debug_logging'],
					'changed_by' => get_current_user_id()
				] );
			}

			// Prepare response
			if ( ! empty( $settings_errors ) ) {
				$this->log_warning( 'settings_save_partial', 'Settings saved with some errors', [
					'updated_count' => count( $settings_updated ),
					'error_count' => count( $settings_errors ),
					'errors' => $settings_errors
				] );

				$this->send_ajax_success( [
					'updated_settings' => array_keys( $settings_updated ),
					'errors' => $settings_errors,
					'partial_success' => true
				], sprintf(
					__( 'Settings partially saved. %d updated, %d errors.', 'ai-blog-generator' ),
					count( $settings_updated ),
					count( $settings_errors )
				), 'save_settings' );
			} else {
				$this->log_info( 'settings_save_complete', 'All settings saved successfully', [
					'updated_count' => count( $settings_updated ),
					'updated_settings' => array_keys( $settings_updated ),
					'saved_by' => get_current_user_id()
				] );

				$this->send_ajax_success( [
					'updated_settings' => array_keys( $settings_updated ),
					'total_updated' => count( $settings_updated )
				], __( 'Settings saved successfully.', 'ai-blog-generator' ), 'save_settings' );
			}

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'save_settings', [
				'settings_attempted' => array_keys( $_POST ),
				'user_id' => get_current_user_id()
			] );
		}

		$this->end_timer( $start_time, 'save_settings' );
	}

	/**
	 * Validate individual setting values.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return array Validation result with 'valid' and 'message' keys.
	 */
	private function validate_setting( $key, $value ) {
		switch ( $key ) {
			case 'anthropic_model':
				$anthropic_service = new \AI_Blog_Generator\Services\Anthropic_Service();
				$available_models = $anthropic_service->get_available_models();
				if ( ! array_key_exists( $value, $available_models ) ) {
					return [
						'valid' => false,
						'message' => __( 'Invalid Claude model selected.', 'ai-blog-generator' )
					];
				}
				break;

			case 'ideas_per_day':
				if ( $value < 1 || $value > 20 ) {
					return [
						'valid' => false,
						'message' => __( 'Ideas per day must be between 1 and 20.', 'ai-blog-generator' )
					];
				}
				break;

			case 'posts_per_day':
				if ( $value < 1 || $value > 50 ) {
					return [
						'valid' => false,
						'message' => __( 'Posts per day must be between 1 and 50.', 'ai-blog-generator' )
					];
				}
				break;

			case 'monthly_budget':
				if ( $value < 0 ) {
					return [
						'valid' => false,
						'message' => __( 'Monthly budget cannot be negative.', 'ai-blog-generator' )
					];
				}
				break;

			case 'budget_alert_threshold':
				if ( $value < 0 || $value > 100 ) {
					return [
						'valid' => false,
						'message' => __( 'Budget alert threshold must be between 0 and 100.', 'ai-blog-generator' )
					];
				}
				break;

			case 'publish_time_min':
			case 'publish_time_max':
				if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
					return [
						'valid' => false,
						'message' => __( 'Invalid time format. Use HH:MM format.', 'ai-blog-generator' )
					];
				}
				break;
		}

		return [ 'valid' => true, 'message' => '' ];
	}

	/**
	 * AJAX handler for testing API connection.
	 */
	public function ajax_test_api_connection() {
		// Verify nonce.
		check_ajax_referer( 'ai_blog_admin_nonce', 'nonce' );
		
		// Check capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_send_json_error( __( 'Unauthorized access.', 'ai-blog-generator' ) );
		}

		$service = sanitize_text_field( $_POST['service'] ?? '' );
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

		// Debug logging
		Logger::info( 'api_test_request', 'API connection test requested', [
			'service' => $service,
			'has_api_key' => ! empty( $api_key ),
			'api_key_length' => strlen( $api_key ),
		] );

		if ( empty( $service ) ) {
			Logger::error( 'api_test_error', 'Service parameter missing', [ 'POST' => $_POST ] );
			wp_send_json_error( __( 'Service parameter is required.', 'ai-blog-generator' ) );
		}

		if ( empty( $api_key ) ) {
			Logger::error( 'api_test_error', 'API key parameter missing', [ 'service' => $service ] );
			wp_send_json_error( __( 'API key is required.', 'ai-blog-generator' ) );
		}

		try {
			if ( 'anthropic' === $service ) {
				$anthropic = new \AI_Blog_Generator\Services\Anthropic_Service( $api_key );
				$result = $anthropic->test_connection();
				Logger::info( 'anthropic_test_result', 'Anthropic test completed', $result );
			} elseif ( 'openai' === $service ) {
				$openai = new \AI_Blog_Generator\Services\OpenAI_Service( $api_key );
				$result = $openai->test_connection();
				Logger::info( 'openai_test_result', 'OpenAI test completed', $result );
			} else {
				Logger::error( 'api_test_error', 'Invalid service', [ 'service' => $service ] );
				wp_send_json_error( sprintf( __( 'Invalid service: %s', 'ai-blog-generator' ), $service ) );
			}

			if ( $result['success'] ) {
				wp_send_json_success( [
					'message' => $result['message'],
					'service' => $service,
				] );
			} else {
				wp_send_json_error( [
					'message' => $result['message'],
					'service' => $service,
				] );
			}
		} catch ( \Exception $e ) {
			Logger::error( 'api_test_exception', 'Exception during API test', [
				'service' => $service,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			] );
			wp_send_json_error( sprintf( __( 'Connection test failed: %s', 'ai-blog-generator' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for getting logs with filtering and pagination.
	 */
	public function ajax_get_logs() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$this->log_info( 'get_logs_request', 'Processing logs request', [
				'user_id' => get_current_user_id(),
				'request_data' => array_keys( $_POST )
			] );

			// Get and sanitize parameters
			$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
			$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
			$filters = isset( $_POST['filters'] ) ? $_POST['filters'] : [];

			// Sanitize filters
			$sanitized_filters = [
				'search' => isset( $filters['search'] ) ? sanitize_text_field( $filters['search'] ) : '',
				'level' => isset( $filters['level'] ) ? sanitize_text_field( $filters['level'] ) : '',
				'action' => isset( $filters['action'] ) ? sanitize_text_field( $filters['action'] ) : '',
				'date_from' => isset( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '',
				'date_to' => isset( $filters['date_to'] ) ? sanitize_text_field( $filters['date_to'] ) : '',
			];

			// Validate per_page
			$per_page = max( 1, min( 100, $per_page ) );

			$this->log_debug( 'get_logs_params', 'Parameters validated', [
				'page' => $page,
				'per_page' => $per_page,
				'filters' => $sanitized_filters
			] );

			// Get logs
			$log_model = new \AI_Blog_Generator\Models\Log_Model();
			
			// Calculate offset
			$offset = ( $page - 1 ) * $per_page;
			
			// Get filtered logs
			$logs = $log_model->get_filtered( $sanitized_filters, $per_page, $offset );
			$total_logs = $log_model->count_filtered( $sanitized_filters );
			$total_pages = ceil( $total_logs / $per_page );

			// Get statistics
			$stats = $log_model->get_level_statistics( $sanitized_filters );

			// Prepare pagination info
			$pagination = [
				'current_page' => $page,
				'total_pages' => $total_pages,
				'total_logs' => $total_logs,
				'per_page' => $per_page,
				'has_previous' => $page > 1,
				'has_next' => $page < $total_pages
			];

			$this->log_info( 'get_logs_success', 'Logs retrieved successfully', [
				'logs_count' => count( $logs ),
				'total_logs' => $total_logs,
				'current_page' => $page,
				'filters_applied' => ! empty( array_filter( $sanitized_filters ) )
			] );

			$this->send_ajax_success( [
				'logs' => $logs,
				'pagination' => $pagination,
				'stats' => $stats
			], 'Logs retrieved successfully', 'get_logs' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_logs', [
				'page' => $page ?? 1,
				'per_page' => $per_page ?? 20,
				'filters' => $sanitized_filters ?? []
			] );
		}

		$this->end_timer( $start_time, 'get_logs' );
	}

	/**
	 * AJAX handler for getting log statistics.
	 */
	public function ajax_get_log_stats() {
		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Get filters
			$filters = isset( $_POST['filters'] ) ? $_POST['filters'] : [];
			$sanitized_filters = [
				'search' => isset( $filters['search'] ) ? sanitize_text_field( $filters['search'] ) : '',
				'level' => isset( $filters['level'] ) ? sanitize_text_field( $filters['level'] ) : '',
				'action' => isset( $filters['action'] ) ? sanitize_text_field( $filters['action'] ) : '',
				'date_from' => isset( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '',
				'date_to' => isset( $filters['date_to'] ) ? sanitize_text_field( $filters['date_to'] ) : '',
			];

			$log_model = new \AI_Blog_Generator\Models\Log_Model();
			$stats = $log_model->get_level_statistics( $sanitized_filters );

			$this->send_ajax_success( $stats, 'Statistics retrieved successfully', 'get_log_stats' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_log_stats', [
				'filters' => $sanitized_filters ?? []
			] );
		}
	}

	/**
	 * AJAX handler for checking for new logs (live updates).
	 */
	public function ajax_check_new_logs() {
		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$last_log_id = isset( $_POST['last_log_id'] ) ? absint( $_POST['last_log_id'] ) : 0;
			$filters = isset( $_POST['filters'] ) ? $_POST['filters'] : [];
			
			$sanitized_filters = [
				'search' => isset( $filters['search'] ) ? sanitize_text_field( $filters['search'] ) : '',
				'level' => isset( $filters['level'] ) ? sanitize_text_field( $filters['level'] ) : '',
				'action' => isset( $filters['action'] ) ? sanitize_text_field( $filters['action'] ) : '',
				'date_from' => isset( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '',
				'date_to' => isset( $filters['date_to'] ) ? sanitize_text_field( $filters['date_to'] ) : '',
			];

			$log_model = new \AI_Blog_Generator\Models\Log_Model();
			$has_new_logs = $log_model->has_new_logs_since( $last_log_id, $sanitized_filters );

			$this->send_ajax_success( [
				'has_new_logs' => $has_new_logs,
				'last_checked' => current_time( 'mysql' )
			], '', 'check_new_logs' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'check_new_logs', [
				'last_log_id' => $last_log_id ?? 0
			] );
		}
	}

	/**
	 * AJAX handler for exporting logs.
	 */
	public function ajax_export_logs() {
		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$this->log_info( 'export_logs_request', 'Log export requested', [
				'user_id' => get_current_user_id()
			] );

			// Get filters
			$filters_json = isset( $_GET['filters'] ) ? $_GET['filters'] : '{}';
			$filters = json_decode( $filters_json, true ) ?: [];
			
			$sanitized_filters = [
				'search' => isset( $filters['search'] ) ? sanitize_text_field( $filters['search'] ) : '',
				'level' => isset( $filters['level'] ) ? sanitize_text_field( $filters['level'] ) : '',
				'action' => isset( $filters['action'] ) ? sanitize_text_field( $filters['action'] ) : '',
				'date_from' => isset( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '',
				'date_to' => isset( $filters['date_to'] ) ? sanitize_text_field( $filters['date_to'] ) : '',
			];

			// Get all logs matching filters (no limit for export)
			$log_model = new \AI_Blog_Generator\Models\Log_Model();
			$logs = $log_model->get_filtered( $sanitized_filters, 0 ); // 0 = no limit

			// Generate CSV content
			$csv_content = "ID,Level,Action,Message,Class,Method,Memory Usage,Created At\n";
			
			foreach ( $logs as $log ) {
				$csv_content .= sprintf(
					"%d,%s,%s,%s,%s,%s,%s,%s\n",
					$log->id,
					'"' . str_replace( '"', '""', $log->level ) . '"',
					'"' . str_replace( '"', '""', $log->action ) . '"',
					'"' . str_replace( '"', '""', $log->message ) . '"',
					'"' . str_replace( '"', '""', $log->class_name ?? '' ) . '"',
					'"' . str_replace( '"', '""', $log->method_name ?? '' ) . '"',
					'"' . str_replace( '"', '""', $log->memory_usage ?? '' ) . '"',
					'"' . str_replace( '"', '""', $log->created_at ) . '"'
				);
			}

			// Set headers for download
			$filename = 'ai-blog-logs-' . date( 'Y-m-d-H-i-s' ) . '.csv';
			
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . strlen( $csv_content ) );
			
			// Clean any output buffer
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			
			echo $csv_content;
			
			$this->log_info( 'export_logs_success', 'Logs exported successfully', [
				'filename' => $filename,
				'log_count' => count( $logs ),
				'user_id' => get_current_user_id()
			] );
			
			exit;

		} catch ( \Exception $e ) {
			$this->log_error( 'export_logs_failed', 'Failed to export logs', [
				'error' => $e->getMessage(),
				'user_id' => get_current_user_id()
			] );
			
			// Clean any output buffer
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			
			wp_send_json_error( [ 'message' => __( 'Failed to export logs.', 'ai-blog-generator' ) ] );
		}
	}

	/**
	 * AJAX handler for clearing old logs.
	 */
	public function ajax_clear_old_logs() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$this->log_info( 'clear_old_logs_request', 'Clear old logs requested', [
				'user_id' => get_current_user_id()
			] );

			// Get retention days from settings
			$retention_days = intval( get_option( 'ai_blog_generator_log_retention_days', 30 ) );
			
			// Clear old logs
			$log_model = new \AI_Blog_Generator\Models\Log_Model();
			$deleted_count = $log_model->clean_old_logs( $retention_days );

			$this->log_info( 'clear_old_logs_success', 'Old logs cleared successfully', [
				'deleted_count' => $deleted_count,
				'retention_days' => $retention_days,
				'user_id' => get_current_user_id()
			] );

			$this->send_ajax_success( [
				'deleted_count' => $deleted_count,
				'retention_days' => $retention_days
			], sprintf(
				__( 'Successfully deleted %d old logs older than %d days.', 'ai-blog-generator' ),
				$deleted_count,
				$retention_days
			), 'clear_old_logs' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'clear_old_logs', [
				'retention_days' => $retention_days ?? 30,
				'user_id' => get_current_user_id()
			] );
		}

		$this->end_timer( $start_time, 'clear_old_logs' );
	}

	/**
	 * AJAX handler for getting approved ideas.
	 */
	public function ajax_get_approved_ideas() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			$this->log_info( 'get_approved_ideas_start', 'Getting approved ideas for admin page', [
				'user_id' => get_current_user_id()
			] );

			// Get approved ideas
			$idea_model = new \AI_Blog_Generator\Models\Idea_Model();
			$approved_ideas = $idea_model->get_with_category( [ 'status' => 'approved' ], 'created_at ASC', 100 );

			$this->log_info( 'model_loaded', 'Model Loaded', [
				'user_id' => get_current_user_id(),
				'ideas_count' => count( $approved_ideas )
			] );

			// Get personas for display
			$persona_model = new \AI_Blog_Generator\Models\Persona_Model();
			$personas_raw = $persona_model->get_all( [], 'name ASC' );
			$personas = [];
			foreach ( $personas_raw as $persona ) {
				$personas[ $persona->id ] = $persona;
			}

			// Get categories for display
			$categories = get_categories();
			$categories_indexed = [];
			foreach ( $categories as $category ) {
				$categories_indexed[ $category->term_id ] = $category->name;
			}

			// Format ideas for JSON response
			$formatted_ideas = [];
			foreach ( $approved_ideas as $idea ) {
				$id = is_array( $idea ) ? $idea['id'] : $idea->id;
				$title = is_array( $idea ) ? $idea['title'] : $idea->title;
				$description = is_array( $idea ) ? $idea['description'] : $idea->description;
				$category_id = is_array( $idea ) ? $idea['category_id'] : $idea->category_id;
				$persona_id = is_array( $idea ) ? $idea['persona_id'] : $idea->persona_id;
				$created_at = is_array( $idea ) ? $idea['created_at'] : $idea->created_at;
				
				$category_name = isset( $categories_indexed[ $category_id ] ) ? $categories_indexed[ $category_id ] : __( 'Uncategorized', 'ai-blog-generator' );
				$persona_name = '';
				
				if ( $persona_id && isset( $personas[ $persona_id ] ) ) {
					$persona = $personas[ $persona_id ];
					$persona_name = is_array( $persona ) ? $persona['name'] : $persona->name;
				}

				$formatted_ideas[] = [
					'id' => $id,
					'title' => $title,
					'description' => $description,
					'category' => $category_name,
					'category_id' => $category_id,
					'persona_name' => $persona_name,
					'persona_id' => $persona_id,
					'created_at' => $created_at,
					'date_formatted' => wp_date( get_option( 'date_format' ), strtotime( $created_at ) )
				];
			}

			// Get generation statuses (placeholder for now)
			$generation_statuses = [];

			$this->log_info( 'get_approved_ideas_success', 'Approved ideas retrieved successfully', [
				'ideas_count' => count( $formatted_ideas )
			] );

			$this->send_ajax_success( [
				'ideas' => $formatted_ideas,
				'generation_statuses' => $generation_statuses,
				'total_count' => count( $formatted_ideas )
			], __( 'Approved ideas loaded successfully.', 'ai-blog-generator' ), 'get_approved_ideas' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_approved_ideas', [] );
		}

		$this->end_timer( $start_time, 'get_approved_ideas' );
	}

	/**
	 * AJAX handler for getting idea data for editing.
	 */
	public function ajax_get_idea_for_edit() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Get idea ID
			$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
			if ( ! $idea_id ) {
				$this->send_ajax_error( __( 'Invalid idea ID.', 'ai-blog-generator' ), [], 'get_idea_for_edit', 'invalid_id' );
				return;
			}

			$this->log_info( 'get_idea_for_edit_start', 'Getting idea for editing', [
				'idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );

			// Get the idea
			$idea_model = new \AI_Blog_Generator\Models\Idea_Model();
			$idea = $idea_model->get( $idea_id );

			if ( ! $idea ) {
				$this->send_ajax_error( __( 'Idea not found.', 'ai-blog-generator' ), [ 'idea_id' => $idea_id ], 'get_idea_for_edit', 'idea_not_found' );
				return;
			}

			if ( $idea['status'] !== 'approved' ) {
				$this->send_ajax_error( __( 'Only approved ideas can be edited.', 'ai-blog-generator' ), [ 'idea_id' => $idea_id, 'status' => $idea['status'] ], 'get_idea_for_edit', 'not_approved' );
				return;
			}

			$this->log_info( 'get_idea_for_edit_success', 'Idea retrieved for editing', [
				'idea_id' => $idea_id,
				'idea_title' => $idea['title']
			] );

			$this->send_ajax_success( [
				'id' => $idea['id'],
				'title' => $idea['title'],
				'description' => $idea['description'],
				'category_id' => $idea['category_id'],
				'persona_id' => $idea['persona_id'],
				'primary_keyword' => isset( $idea['primary_keyword'] ) ? $idea['primary_keyword'] : ''
			], __( 'Idea data loaded successfully.', 'ai-blog-generator' ), 'get_idea_for_edit' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_idea_for_edit', [
				'idea_id' => $idea_id ?? 0
			] );
		}

		$this->end_timer( $start_time, 'get_idea_for_edit' );
	}

	/**
	 * AJAX handler for updating approved idea.
	 */
	public function ajax_update_approved_idea() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Validate required parameters
			$params = $this->validate_ajax_params( [ 'idea_id', 'title', 'description' ] );
			if ( false === $params ) {
				return;
			}

			$idea_id = absint( $params['idea_id'] );
			$generate_after = isset( $_POST['generate_after'] ) && $_POST['generate_after'] === '1';

			// DEBUG: Log the generate_after parameter value
			$this->log_info( 'debug_generate_after', 'Generate after parameter check', [
				'generate_after_raw' => $_POST['generate_after'] ?? 'NOT_SET',
				'generate_after_boolean' => $generate_after,
				'all_post_data' => $_POST
			] );

			$this->log_info( 'update_approved_idea_start', 'Starting approved idea update', [
				'idea_id' => $idea_id,
				'generate_after' => $generate_after,
				'user_id' => get_current_user_id()
			] );

			// Get the idea
			$idea_model = new \AI_Blog_Generator\Models\Idea_Model();
			$idea = $idea_model->get( $idea_id );

			if ( ! $idea || $idea['status'] !== 'approved' ) {
				$this->send_ajax_error( __( 'Idea not found or not approved.', 'ai-blog-generator' ), [ 'idea_id' => $idea_id ], 'update_approved_idea', 'idea_not_found' );
				return;
			}

			// Sanitize update data
			$update_data = [
				'title' => sanitize_text_field( $params['title'] ),
				'description' => sanitize_textarea_field( $params['description'] ),
				'category_id' => isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : null,
				'persona_id' => isset( $_POST['persona_id'] ) ? absint( $_POST['persona_id'] ) : null,
				'updated_at' => current_time( 'mysql' )
			];

			// Remove null values
			$update_data = array_filter( $update_data, function( $value ) {
				return $value !== null && $value !== '';
			} );

			// Update the idea
			$updated = $idea_model->update( $idea_id, $update_data );

			if ( ! $updated ) {
				$this->send_ajax_error( __( 'Failed to update idea.', 'ai-blog-generator' ), [ 'idea_id' => $idea_id ], 'update_approved_idea', 'update_failed' );
				return;
			}

			$this->log_info( 'approved_idea_updated', 'Approved idea updated successfully', [
				'idea_id' => $idea_id,
				'updated_fields' => array_keys( $update_data ),
				'updated_by' => get_current_user_id()
			] );

			// Generate blog post if requested
			if ( $generate_after ) {
				$this->log_info( 'generate_after_update', 'Starting generation after idea update', [
					'idea_id' => $idea_id
				] );

				$blog_controller = new \AI_Blog_Generator\Controllers\Blog_Controller();
				$generation_result = $blog_controller->generate_from_approved_idea( $idea_id );

				if ( is_wp_error( $generation_result ) ) {
					$this->log_error( 'generate_after_update_failed', 'Generation failed after idea update', [
						'idea_id' => $idea_id,
						'error' => $generation_result->get_error_message()
					] );
					
					// Still return success for the update, but mention generation failure
					$this->send_ajax_success( [
						'updated' => true,
						'generated' => false,
						'generation_error' => $generation_result->get_error_message()
					], __( 'Idea updated successfully, but generation failed.', 'ai-blog-generator' ), 'update_approved_idea' );
				} else {
					$this->log_info( 'generate_after_update_success', 'Generation started after idea update', [
						'idea_id' => $idea_id,
						'blog_id' => $generation_result
					] );

					$this->send_ajax_success( [
						'updated' => true,
						'generated' => true,
						'blog_id' => $generation_result
					], __( 'Idea updated and blog generation started successfully.', 'ai-blog-generator' ), 'update_approved_idea' );
				}
			} else {
				$this->send_ajax_success( [
					'updated' => true,
					'generated' => false
				], __( 'Idea updated successfully.', 'ai-blog-generator' ), 'update_approved_idea' );
			}

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'update_approved_idea', [
				'idea_id' => $idea_id ?? 0
			] );
		}

		$this->end_timer( $start_time, 'update_approved_idea' );
	}

	/**
	 * AJAX handler for generating blog from approved idea.
	 */
	public function ajax_generate_from_approved_idea() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Get idea ID
			$idea_id = isset( $_POST['idea_id'] ) ? absint( $_POST['idea_id'] ) : 0;
			
			// DEBUG: Log all POST data to see what's being received
			$this->log_info( 'debug_ajax_post_data', 'Received POST data for generate_from_approved_idea', [
				'all_post_data' => $_POST,
				'extracted_idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );
			
			if ( ! $idea_id ) {
				$this->send_ajax_error( __( 'Invalid idea ID.', 'ai-blog-generator' ), [], 'generate_from_approved_idea', 'invalid_id' );
				return;
			}

			$this->log_info( 'generate_from_approved_idea_start', 'Starting blog generation from approved idea', [
				'idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );

			// Use the blog controller to handle the generation
			$blog_controller = new \AI_Blog_Generator\Controllers\Blog_Controller();
			$this->log_info( 'blog_controller_initialized', 'Blog Controller Initialized', [
				'idea_id' => $idea_id,
				'user_id' => get_current_user_id()
			] );
			$result = $blog_controller->generate_from_approved_idea( $idea_id );

			if ( is_wp_error( $result ) ) {
				$this->log_error( 'generate_from_approved_idea_failed', 'Blog generation failed', [
					'idea_id' => $idea_id,
					'error' => $result->get_error_message()
				] );

				$this->send_ajax_error( $result->get_error_message(), [ 'idea_id' => $idea_id ], 'generate_from_approved_idea', 'generation_failed' );
				return;
			}

			$this->log_info( 'generate_from_approved_idea_success', 'Blog generation started successfully', [
				'idea_id' => $idea_id,
				'blog_id' => $result
			] );

			$this->send_ajax_success( [
				'blog_id' => $result,
				'idea_id' => $idea_id,
				'debug_received_idea_id' => $idea_id,
				'debug_post_data' => $_POST
			], __( 'Blog generation started successfully. The idea will be removed from this queue once generation is complete.', 'ai-blog-generator' ), 'generate_from_approved_idea' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'generate_from_approved_idea', [
				'idea_id' => $idea_id ?? 0
			] );
		}

		$this->end_timer( $start_time, 'generate_from_approved_idea' );
	}

	/**
	 * AJAX handler for getting real-time generation status.
	 */
	public function ajax_get_generation_status() {
		$start_time = $this->start_timer();

		try {
			// Verify security
			if ( ! $this->verify_ajax_security() ) {
				return;
			}

			// Get idea IDs (can be single or multiple)
			$idea_ids = isset( $_POST['idea_ids'] ) ? $_POST['idea_ids'] : [];
			if ( isset( $_POST['idea_id'] ) ) {
				$idea_ids = [ absint( $_POST['idea_id'] ) ];
			}

			if ( empty( $idea_ids ) ) {
				$this->send_ajax_error( __( 'No idea IDs provided.', 'ai-blog-generator' ), [], 'get_generation_status', 'missing_ids' );
				return;
			}

			// Sanitize idea IDs
			$idea_ids = array_map( 'absint', $idea_ids );
			$idea_ids = array_filter( $idea_ids );

			$this->log_debug( 'get_generation_status_request', 'Generation status requested', [
				'idea_ids' => $idea_ids,
				'user_id' => get_current_user_id()
			] );

			// Get generation status for each idea
			$content_generator = new \AI_Blog_Generator\Services\Content_Generator();
			$status_data = [];
			$completed_ideas = [];

			foreach ( $idea_ids as $idea_id ) {
				$status = $content_generator->get_generation_status( $idea_id );
				
				if ( $status ) {
					$status_data[ $idea_id ] = $status;
					
					// Check if generation is complete
					if ( $status['stage'] === 'complete' ) {
						$completed_ideas[] = $idea_id;
						// Clear the status since it's complete
						$content_generator->clear_generation_status( $idea_id );
					}
				} else {
					// Check if idea status changed to 'generated' (completed)
					$idea_model = new \AI_Blog_Generator\Models\Idea_Model();
					$idea = $idea_model->get( $idea_id );
					
					if ( $idea && $idea['status'] === 'generated' ) {
						$completed_ideas[] = $idea_id;
						$status_data[ $idea_id ] = [
							'idea_id' => $idea_id,
							'stage' => 'complete',
							'message' => 'Blog post generated successfully!',
							'progress' => 100,
							'timestamp' => current_time( 'mysql' )
						];
					}
				}
			}

			$this->log_debug( 'get_generation_status_response', 'Generation status response prepared', [
				'statuses_found' => count( $status_data ),
				'completed_ideas' => $completed_ideas
			] );

			$this->send_ajax_success( [
				'statuses' => $status_data,
				'completed_ideas' => $completed_ideas
			], 'Generation status retrieved', 'get_generation_status' );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, 'get_generation_status', [
				'idea_ids' => $idea_ids ?? []
			] );
		}

		$this->end_timer( $start_time, 'get_generation_status' );
	}
} 



