<?php
/**
 * Plugin Deactivator
 *
 * @package AI_Blog_Generator
 * @subpackage Includes
 */

namespace AI_Blog_Generator\Includes;

use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Deactivator Class
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 1.0.0
 */
class Plugin_Deactivator {

	/**
	 * Plugin deactivation routine.
	 *
	 * Cleans up scheduled events and performs necessary cleanup tasks.
	 */
	public static function deactivate() {
		// Log deactivation.
		Logger::info( 'plugin_deactivation', 'AI Blog Generator plugin deactivated' );
		
		// Unschedule cron jobs.
		self::unschedule_cron_jobs();
		
		// Clear plugin cache.
		self::clear_cache();
		
		// Optionally clean up data based on user preference.
		self::cleanup_data();
		
		// Remove activation flag.
		delete_option( 'ai_blog_generator_activated' );
	}

	/**
	 * Unschedule all plugin cron jobs.
	 */
	private static function unschedule_cron_jobs() {
		// List of all plugin cron hooks.
		$cron_hooks = [
			'ai_blog_daily_ideas',
			'ai_blog_process_queue',
			'ai_blog_publish_scheduled',
			'ai_blog_cleanup_logs',
		];
		
		// Unschedule each cron job.
		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			
			// Clear all scheduled events for this hook.
			wp_clear_scheduled_hook( $hook );
		}
		
		Logger::info( 'cron_cleanup', 'All scheduled cron jobs have been cleared' );
	}

	/**
	 * Clear plugin cache and transients.
	 */
	private static function clear_cache() {
		// Delete plugin transients.
		$transients = [
			'ai_blog_generator_stats',
			'ai_blog_generator_costs',
			'ai_blog_generator_api_status',
			'ai_blog_generator_idea_cache',
			'ai_blog_generator_context_cache',
		];
		
		foreach ( $transients as $transient ) {
			delete_transient( $transient );
		}
		
		// Clear object cache for plugin data.
		wp_cache_delete_group( 'ai_blog_generator' );
		
		// Trigger action for third-party cache plugins.
		do_action( 'ai_blog_generator_clear_cache' );
	}

	/**
	 * Cleanup plugin data based on user settings.
	 */
	private static function cleanup_data() {
		// Check if user wants to remove data on deactivation.
		$remove_data = get_option( 'ai_blog_generator_remove_data_on_deactivate', false );
		
		if ( ! $remove_data ) {
			return; // Keep all data.
		}
		
		// This is a destructive operation, so we'll only do minimal cleanup.
		// Full data removal should be done on uninstall.
		
		// Clear temporary data.
		self::clear_temporary_data();
		
		// Note: We don't remove database tables or options on deactivation.
		// That should only happen on uninstall.
	}

	/**
	 * Clear temporary plugin data.
	 */
	private static function clear_temporary_data() {
		global $wpdb;
		
		// Clear old logs (older than 7 days).
		$wpdb->query( 
			$wpdb->prepare(
				"DELETE FROM " . AI_BLOG_GENERATOR_TABLE_LOGS . " 
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				7
			)
		);
		
		// Clear orphaned draft posts that were never published.
		$orphaned_posts = $wpdb->get_col(
			"SELECT post_id FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " 
			WHERE status = 'draft' 
			AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);
		
		foreach ( $orphaned_posts as $post_id ) {
			// Only delete if the post is still in draft status.
			$post = get_post( $post_id );
			if ( $post && $post->post_status === 'draft' ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	/**
	 * Perform network-wide deactivation.
	 *
	 * @param bool $network_wide Whether the plugin is being network deactivated.
	 */
	public static function deactivate_network( $network_wide ) {
		if ( ! is_multisite() || ! $network_wide ) {
			self::deactivate();
			return;
		}
		
		// Get all blog ids.
		$blog_ids = get_sites( [ 'fields' => 'ids' ] );
		
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			self::deactivate();
			restore_current_blog();
		}
	}

	/**
	 * Create uninstall instructions file.
	 */
	public static function create_uninstall_instructions() {
		$instructions = "=== AI Blog Generator Uninstall Instructions ===\n\n";
		$instructions .= "The plugin has been deactivated but your data has been preserved.\n\n";
		$instructions .= "If you want to completely remove the plugin and all its data:\n";
		$instructions .= "1. Delete the plugin from the WordPress admin panel\n";
		$instructions .= "2. This will trigger the uninstall process which will:\n";
		$instructions .= "   - Remove all database tables\n";
		$instructions .= "   - Delete all plugin options\n";
		$instructions .= "   - Remove generated blog posts (if configured)\n\n";
		$instructions .= "To keep your data for future use:\n";
		$instructions .= "- Simply leave the plugin files in place\n";
		$instructions .= "- Your data will be available when you reactivate\n\n";
		$instructions .= "Generated on: " . current_time( 'mysql' ) . "\n";
		
		// Store instructions as an option for reference.
		update_option( 'ai_blog_generator_uninstall_instructions', $instructions );
	}

	/**
	 * Handle plugin downgrade.
	 *
	 * @param string $new_version The version being downgraded to.
	 * @param string $old_version The current version.
	 */
	public static function handle_downgrade( $new_version, $old_version ) {
		Logger::warning( 'plugin_downgrade', 'Plugin downgrade detected', [
			'from_version' => $old_version,
			'to_version'   => $new_version,
		] );
		
		// Backup current settings.
		$settings_backup = [];
		$options = $GLOBALS['wpdb']->get_results(
			"SELECT option_name, option_value 
			FROM {$GLOBALS['wpdb']->options} 
			WHERE option_name LIKE 'ai_blog_generator_%'"
		);
		
		foreach ( $options as $option ) {
			$settings_backup[ $option->option_name ] = $option->option_value;
		}
		
		update_option( 'ai_blog_generator_settings_backup_' . $old_version, $settings_backup );
		
		// Notify admin about the downgrade.
		set_transient( 'ai_blog_generator_downgrade_notice', [
			'from' => $old_version,
			'to'   => $new_version,
		], WEEK_IN_SECONDS );
	}
} 