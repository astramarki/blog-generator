<?php
/**
 * Uninstall AI Blog Generator
 *
 * This file is executed when the plugin is deleted via WordPress admin.
 * It removes all plugin data including database tables and options.
 *
 * @package AI_Blog_Generator
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin constants for table names.
global $wpdb;
$table_prefix = $wpdb->prefix . 'ai_blog_';

// Define table names.
// Note: Order matters due to foreign key constraints - dependent tables must be dropped first.
$tables = [
	// Drop tables with foreign keys first
	$wpdb->prefix . 'ai_blog_generator_product_images',
	$wpdb->prefix . 'ai_blog_generator_product_links',
	$wpdb->prefix . 'ai_blog_generator_product_seed_images',
	$table_prefix . 'idea_categories',
	$table_prefix . 'generated_posts',
	$table_prefix . 'seed_images',
	
	// Then drop the parent tables
	$wpdb->prefix . 'ai_blog_generator_products',
	$table_prefix . 'ideas',
	$table_prefix . 'contexts',
	$table_prefix . 'personas',
	$table_prefix . 'logs',
	$table_prefix . 'cost_analytics',
	$wpdb->prefix . 'ai_blog_brand_features',
];

// Check if user wants to keep data.
$keep_data = get_option( 'ai_blog_generator_keep_data_on_uninstall', false );

if ( ! $keep_data ) {
	// Drop all plugin tables.
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
	
	// Delete all plugin options.
	$options = [
		// Plugin metadata.
		'ai_blog_generator_version',
		'ai_blog_generator_db_version',
		'ai_blog_generator_activated',
		'ai_blog_generator_uninstall_instructions',
		
		// API settings.
		'ai_blog_generator_anthropic_api_key',
		'ai_blog_generator_openai_api_key',
		
		// Generation settings.
		'ai_blog_generator_ideas_per_day',
		'ai_blog_generator_posts_per_day',
		'ai_blog_generator_auto_publish',
		'ai_blog_generator_default_category',
		
		// Scheduling settings.
		'ai_blog_generator_publish_time_min',
		'ai_blog_generator_publish_time_max',
		'ai_blog_generator_weekend_publishing',
		
		// Cost settings.
		'ai_blog_generator_monthly_budget',
		'ai_blog_generator_budget_alert_threshold',
		
		// Logging settings.
		'ai_blog_generator_log_to_database',
		'ai_blog_generator_log_to_error_log',
		'ai_blog_generator_log_retention_days',
		
		// Feature flags.
		'ai_blog_generator_enable_idea_generation',
		'ai_blog_generator_enable_image_generation',
		'ai_blog_generator_enable_seo_optimization',
		
		// Advanced settings.
		'ai_blog_generator_max_retries',
		'ai_blog_generator_retry_delay',
		'ai_blog_generator_timeout',
		
		// Data retention.
		'ai_blog_generator_keep_data_on_uninstall',
		'ai_blog_generator_remove_data_on_deactivate',
	];
	
	// Delete each option.
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	
	// Delete any transients.
	$transients = [
		'ai_blog_generator_stats',
		'ai_blog_generator_costs',
		'ai_blog_generator_api_status',
		'ai_blog_generator_idea_cache',
		'ai_blog_generator_context_cache',
		'ai_blog_generator_downgrade_notice',
		'ai_blog_active_generations',
		'ai_blog_generation_queue',
	];
	
	foreach ( $transients as $transient ) {
		delete_transient( $transient );
	}
	
	// Also delete any transients that match our generation pattern
	$wpdb->query(
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE '_transient_ai_blog_generation_%' 
		OR option_name LIKE '_transient_timeout_ai_blog_generation_%'"
	);
	
	// Remove any scheduled cron jobs.
	$cron_hooks = [
		'ai_blog_daily_ideas',
		'ai_blog_process_queue',
		'ai_blog_publish_scheduled',
		'ai_blog_cleanup_logs',
		'ai_blog_process_single_generation',
	];
	
	foreach ( $cron_hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	
	// Optionally delete generated posts.
	$delete_posts = get_option( 'ai_blog_generator_delete_posts_on_uninstall', false );
	
	if ( $delete_posts ) {
		// Get all post IDs from generated_posts table before dropping it.
		$post_ids = $wpdb->get_col(
			"SELECT post_id FROM {$table_prefix}generated_posts WHERE post_id IS NOT NULL"
		);
		
		// Delete each post permanently.
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}
	
	// Delete any uploaded seed images.
	$upload_dir = wp_upload_dir();
	$seed_images_dir = $upload_dir['basedir'] . '/ai-blog-generator/seed-images';
	
	if ( is_dir( $seed_images_dir ) ) {
		// Recursively delete the directory.
		ai_blog_generator_delete_directory( $seed_images_dir );
	}
	
	// Delete generation log files.
	$logs_dir = $upload_dir['basedir'] . '/ai-blog-generator-logs';
	
	if ( is_dir( $logs_dir ) ) {
		// Recursively delete the logs directory.
		ai_blog_generator_delete_directory( $logs_dir );
	}
	
	// Delete the parent directory if empty.
	$parent_dir = $upload_dir['basedir'] . '/ai-blog-generator';
	if ( is_dir( $parent_dir ) && count( scandir( $parent_dir ) ) === 2 ) {
		rmdir( $parent_dir );
	}
	
	// Clean up any leftover data in wp_options with our prefix.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE 'ai_blog_generator_%'"
	);
	
	// Clear object cache.
	wp_cache_flush();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 * @return bool
 */
function ai_blog_generator_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}
	
	$files = array_diff( scandir( $dir ), [ '.', '..' ] );
	
	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			ai_blog_generator_delete_directory( $path );
		} else {
			unlink( $path );
		}
	}
	
	return rmdir( $dir );
} 
 
 