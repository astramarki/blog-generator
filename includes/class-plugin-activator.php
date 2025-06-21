<?php
/**
 * Plugin Activator
 *
 * @package AI_Blog_Generator
 * @subpackage Includes
 */

namespace AI_Blog_Generator\Includes;

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Activator Class
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 */
class Plugin_Activator {

	/**
	 * Plugin activation routine.
	 *
	 * Creates database tables, sets default options, and schedules cron jobs.
	 */
	public static function activate() {
		// Set activation flag.
		update_option( 'ai_blog_generator_activated', true );
		
		// Create database tables.
		self::create_database_tables();
		
		// Set default options.
		self::set_default_options();
		
		// Schedule cron jobs.
		self::schedule_cron_jobs();
		
		// Create default contexts.
		self::create_default_contexts();
		
		// Create default personas.
		self::create_default_personas();
		
		// Create product seed images table
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_blog_generator_product_seed_images (
			id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			image_url VARCHAR(500) NOT NULL,
			display_order INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY idx_product (product_id),
			KEY idx_order (display_order)
		) $charset_collate;";

		$wpdb->query( $sql );
		
		// Clear any cached data.
		self::clear_cache();
		
		// Set plugin version.
		update_option( 'ai_blog_generator_version', AI_BLOG_GENERATOR_VERSION );
		
		// Log activation.
		Logger::info( 'plugin_activation', 'AI Blog Generator plugin activated', [
			'version' => AI_BLOG_GENERATOR_VERSION,
		] );
	}

	/**
	 * Create database tables.
	 */
	private static function create_database_tables() {
		$db_manager = Database_Manager::get_instance();
		
		// Create all plugin tables.
		$success = $db_manager->create_tables();
		
		if ( ! $success ) {
			// Log error but don't prevent activation.
			error_log( 'AI Blog Generator: Failed to create some database tables during activation.' );
		}
		
		// Update database version.
		update_option( 'ai_blog_generator_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		// API Settings.
		add_option( 'ai_blog_generator_anthropic_api_key', '' );
		add_option( 'ai_blog_generator_openai_api_key', '' );
		
		// Generation Settings.
		add_option( 'ai_blog_generator_ideas_per_day', 5 );
		add_option( 'ai_blog_generator_posts_per_day', 10 );
		add_option( 'ai_blog_generator_auto_publish', false );
		add_option( 'ai_blog_generator_default_category', get_option( 'default_category' ) );
		
		// Scheduling Settings.
		add_option( 'ai_blog_generator_publish_time_min', '08:00' );
		add_option( 'ai_blog_generator_publish_time_max', '20:00' );
		add_option( 'ai_blog_generator_weekend_publishing', true );
		
		// Cost Settings.
		add_option( 'ai_blog_generator_monthly_budget', 100.00 );
		add_option( 'ai_blog_generator_budget_alert_threshold', 80 );
		
		// Logging Settings.
		add_option( 'ai_blog_generator_log_to_database', true );
		add_option( 'ai_blog_generator_log_to_error_log', true );
		add_option( 'ai_blog_generator_log_retention_days', 30 );
		
		// Feature Flags.
		add_option( 'ai_blog_generator_enable_idea_generation', true );
		add_option( 'ai_blog_generator_enable_image_generation', true );
		add_option( 'ai_blog_generator_enable_seo_optimization', true );
		
		// Advanced Settings.
		add_option( 'ai_blog_generator_max_retries', 3 );
		add_option( 'ai_blog_generator_retry_delay', 5 );
		add_option( 'ai_blog_generator_timeout', 300 );
	}

	/**
	 * Schedule cron jobs.
	 */
	private static function schedule_cron_jobs() {
		// Schedule daily idea generation.
		if ( ! wp_next_scheduled( 'ai_blog_daily_ideas' ) ) {
			wp_schedule_event( 
				strtotime( 'tomorrow 6:00am' ), 
				'daily', 
				'ai_blog_daily_ideas' 
			);
		}
		
		// Schedule hourly processing of approved ideas.
		if ( ! wp_next_scheduled( 'ai_blog_process_queue' ) ) {
			wp_schedule_event( 
				time() + HOUR_IN_SECONDS, 
				'hourly', 
				'ai_blog_process_queue' 
			);
		}
		
		// Schedule publishing check every 15 minutes.
		if ( ! wp_next_scheduled( 'ai_blog_publish_scheduled' ) ) {
			wp_schedule_event( 
				time() + 15 * MINUTE_IN_SECONDS, 
				'fifteen_minutes', 
				'ai_blog_publish_scheduled' 
			);
		}
		
		// Schedule daily log cleanup.
		if ( ! wp_next_scheduled( 'ai_blog_cleanup_logs' ) ) {
			wp_schedule_event( 
				strtotime( 'tomorrow 3:00am' ), 
				'daily', 
				'ai_blog_cleanup_logs' 
			);
		}
		
		// Register custom cron schedules if not already registered.
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public static function add_cron_schedules( $schedules ) {
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
	 * Create default contexts.
	 */
	private static function create_default_contexts() {
		$db_manager = Database_Manager::get_instance();
		
		// Check if contexts already exist.
		$existing_contexts = $db_manager->get_all( 'contexts' );
		if ( ! empty( $existing_contexts ) ) {
			return; // Don't create defaults if contexts exist.
		}
		
		// Default contexts to create.
		$default_contexts = [
			[
				'name'        => 'General Business Information',
				'description' => 'Core business information and values',
				'type'        => 'general',
				'content'     => 'Replace this with your business description, mission, values, and general information that should be considered when generating blog posts.',
				'priority'    => 75,
				'usage_flags' => 'ideas,content,images',
				'active'      => 1,
			],
			[
				'name'        => 'Products and Services',
				'description' => 'Product catalog and service offerings',
				'type'        => 'products',
				'content'     => 'List your products and services here. Include features, benefits, and unique selling points.',
				'priority'    => 100,
				'usage_flags' => 'ideas,content,images',
				'active'      => 1,
			],
			[
				'name'        => 'SEO Guidelines',
				'description' => 'SEO best practices and requirements',
				'type'        => 'seo',
				'content'     => 'Include your SEO guidelines here: keyword density, meta description length, title format preferences, etc.',
				'priority'    => 50,
				'usage_flags' => 'content',
				'active'      => 1,
			],
			[
				'name'        => 'Target Keywords',
				'description' => 'Primary and secondary keywords for content',
				'type'        => 'keywords',
				'content'     => 'List your target keywords and phrases, one per line.',
				'priority'    => 75,
				'usage_flags' => 'ideas,content',
				'active'      => 1,
			],
			[
				'name'        => 'Image Generation Guidelines',
				'description' => 'Visual style and image requirements',
				'type'        => 'image',
				'content'     => 'Describe your preferred image style, colors, themes, and any specific requirements for generated images.',
				'priority'    => 50,
				'usage_flags' => 'images',
				'active'      => 1,
			],
		];
		
		// Insert default contexts.
		foreach ( $default_contexts as $context ) {
			$db_manager->insert( 'contexts', $context );
		}
	}

	/**
	 * Create default personas.
	 */
	private static function create_default_personas() {
		$db_manager = Database_Manager::get_instance();
		
		// Check if personas already exist.
		$existing_personas = $db_manager->get_all( 'personas' );
		if ( ! empty( $existing_personas ) ) {
			return; // Don't create defaults if personas exist.
		}
		
		// Default personas to create.
		$default_personas = [
			[
				'name' => 'John',
				'bio' => 'John is a professional blog writer who has a PhD in education. He is a copywriting expert with over 15 years of experience in creating engaging, educational content.',
				'expertise' => 'Education, Copywriting, Academic Writing, SEO Optimization, Content Strategy',
				'writing_style' => 'Academic yet accessible, uses data and research to support points, includes practical examples, structured and logical flow',
				'tone' => 'professional',
				'active' => 1,
			],
			[
				'name' => 'Ginny',
				'bio' => 'Ginny is a PTA president and community organizer looking for creative ways to fund school projects like their poster maker. She brings a parent\'s perspective and grassroots fundraising experience.',
				'expertise' => 'Community Organizing, Fundraising, Parent Engagement, School Activities, Event Planning',
				'writing_style' => 'Conversational and relatable, uses personal anecdotes, focuses on community and collaboration, practical tips and real-world examples',
				'tone' => 'friendly',
				'active' => 1,
			],
			[
				'name' => 'Marcus',
				'bio' => 'Marcus is a digital marketing specialist with expertise in e-commerce and conversion optimization. He focuses on data-driven strategies and ROI.',
				'expertise' => 'Digital Marketing, E-commerce, Analytics, Conversion Optimization, Social Media Marketing',
				'writing_style' => 'Data-focused, uses statistics and case studies, action-oriented, includes metrics and KPIs',
				'tone' => 'analytical',
				'active' => 1,
			],
			[
				'name' => 'Sarah',
				'bio' => 'Sarah is a wellness coach and lifestyle blogger who specializes in holistic health and work-life balance. She has certifications in nutrition and mindfulness.',
				'expertise' => 'Wellness, Nutrition, Mindfulness, Work-Life Balance, Holistic Health',
				'writing_style' => 'Empathetic and encouraging, uses inclusive language, focuses on practical wellness tips, incorporates mindfulness concepts',
				'tone' => 'inspirational',
				'active' => 1,
			],
		];
		
		// Insert default personas.
		foreach ( $default_personas as $persona ) {
			$db_manager->insert( 'personas', $persona );
		}
	}

	/**
	 * Clear cache.
	 */
	private static function clear_cache() {
		// Clear WordPress cache.
		wp_cache_flush();
		
		// Clear any transients.
		delete_transient( 'ai_blog_generator_stats' );
		delete_transient( 'ai_blog_generator_costs' );
		
		// Trigger action for third-party cache plugins.
		do_action( 'ai_blog_generator_clear_cache' );
	}

	/**
	 * Check plugin requirements.
	 *
	 * @return bool True if requirements are met, false otherwise.
	 */
	public static function check_requirements() {
		$errors = [];
		
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			$errors[] = sprintf(
				__( 'AI Blog Generator requires PHP %s or higher. You are running PHP %s.', 'ai-blog-generator' ),
				'7.4',
				PHP_VERSION
			);
		}
		
		// Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			$errors[] = sprintf(
				__( 'AI Blog Generator requires WordPress %s or higher. You are running WordPress %s.', 'ai-blog-generator' ),
				'5.8',
				$wp_version
			);
		}
		
		// Check for required PHP extensions.
		$required_extensions = [ 'curl', 'json', 'mbstring' ];
		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$errors[] = sprintf(
					__( 'AI Blog Generator requires the PHP %s extension to be installed.', 'ai-blog-generator' ),
					$extension
				);
			}
		}
		
		// Check database permissions.
		global $wpdb;
		$can_create_tables = current_user_can( 'activate_plugins' );
		if ( ! $can_create_tables ) {
			$errors[] = __( 'AI Blog Generator requires permission to create database tables.', 'ai-blog-generator' );
		}
		
		// Display errors if any.
		if ( ! empty( $errors ) ) {
			deactivate_plugins( plugin_basename( AI_BLOG_GENERATOR_PLUGIN_FILE ) );
			wp_die( 
				'<h1>' . __( 'Plugin Activation Failed', 'ai-blog-generator' ) . '</h1>' .
				'<p>' . implode( '</p><p>', $errors ) . '</p>' .
				'<p><a href="' . admin_url( 'plugins.php' ) . '">' . __( 'Return to Plugins page', 'ai-blog-generator' ) . '</a></p>'
			);
		}
		
		return true;
	}
} 