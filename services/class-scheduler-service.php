<?php
/**
 * Scheduler Service
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Utilities\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduler Service Class
 *
 * Manages cron jobs and post scheduling.
 *
 * @since 1.0.0
 */
class Scheduler_Service {

	/**
	 * Content generator instance.
	 *
	 * @var Content_Generator
	 */
	private $content_generator;

	/**
	 * Database manager instance.
	 *
	 * @var Database_Manager
	 */
	private $database_manager;

	/**
	 * Idea model instance.
	 *
	 * @var Idea_Model
	 */
	private $idea_model;

	/**
	 * Blog model instance.
	 *
	 * @var Blog_Model
	 */
	private $blog_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->database_manager = Database_Manager::get_instance();
		$this->idea_model = new Idea_Model();
		$this->blog_model = new Blog_Model();
		$this->content_generator = new Content_Generator();
	}

	/**
	 * Initialize cron jobs.
	 */
	public function init_cron_jobs() {
		// Register cron hooks with WordPress.
		$this->register_cron_hooks();
		
		// Schedule jobs if not already scheduled.
		$this->schedule_jobs();
		
		// Only log initialization once per session to prevent log spam
		if ( ! get_transient( 'ai_blog_scheduler_service_init_logged' ) ) {
			Logger::info( 'scheduler_init', 'Scheduler service initialized and cron jobs registered' );
			set_transient( 'ai_blog_scheduler_service_init_logged', true, 300 ); // 5 minutes
		}
	}

	/**
	 * Daily idea generation cron job.
	 */
	public function daily_idea_generation() {
		Logger::info( 'cron_daily_ideas_start', 'Starting daily idea generation' );
		
		try {
			// Check if generation is enabled.
			if ( ! get_option( 'ai_blog_generator_enable_idea_generation', true ) ) {
				Logger::info( 'cron_daily_ideas_disabled', 'Daily idea generation is disabled' );
				return;
			}
			
			// Check if generation is paused due to budget.
			if ( get_option( 'ai_blog_generator_generation_paused', false ) ) {
				Logger::warning( 'cron_daily_ideas_paused', 'Daily idea generation paused due to budget limits' );
				return;
			}
			
			// Check if we already have enough pending ideas.
			$pending_count = $this->idea_model->count_by_status( 'pending' );
			$max_pending = intval( get_option( 'ai_blog_generator_max_pending_ideas', 25 ) );
			
			if ( $pending_count >= $max_pending ) {
				Logger::info( 'cron_daily_ideas_sufficient', 'Sufficient pending ideas exist', [
					'pending_count' => $pending_count,
					'max_pending' => $max_pending,
				] );
				return;
			}
			
			// Generate ideas.
			$result = $this->content_generator->generate_ideas();
			
			if ( $result['success'] ) {
				Logger::info( 'cron_daily_ideas_success', 'Daily idea generation completed successfully', [
					'ideas_generated' => count( $result['ideas'] ),
				] );
				
				// Update last generation time.
				update_option( 'ai_blog_generator_last_idea_generation', current_time( 'mysql' ) );
			} else {
				Logger::error( 'cron_daily_ideas_failed', 'Daily idea generation failed', [
					'error' => $result['message'],
				] );
			}
			
		} catch ( \Exception $e ) {
			Logger::error( 'cron_daily_ideas_exception', 'Exception during daily idea generation', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			] );
		}
	}

	/**
	 * Process approved ideas queue cron job.
	 */
	public function process_approved_ideas_queue() {
		Logger::info( 'cron_process_queue_disabled', 'Auto processing is DISABLED to prevent automatic retries' );
		
		// DISABLED: Auto processing completely disabled to prevent automatic generation retries
		// Users must manually start generations from the GUI
		return;
		
		// OLD CODE BELOW - DISABLED
		/*
		Logger::info( 'cron_process_queue_start', 'Starting approved ideas processing' );
		
		try {
			// Check if processing is enabled.
			if ( ! get_option( 'ai_blog_generator_enable_auto_processing', true ) ) {
				Logger::info( 'cron_process_queue_disabled', 'Auto processing is disabled' );
				return;
			}
			
			// Check if generation is paused due to budget.
			if ( get_option( 'ai_blog_generator_generation_paused', false ) ) {
				Logger::warning( 'cron_process_queue_paused', 'Processing paused due to budget limits' );
				return;
			}
			
			// Get daily limit settings.
			$posts_per_day = intval( get_option( 'ai_blog_generator_posts_per_day', 2 ) );
			$today_generated = $this->blog_model->count_generated_today();
			
			if ( $today_generated >= $posts_per_day ) {
				Logger::info( 'cron_process_queue_limit_reached', 'Daily post generation limit reached', [
					'today_generated' => $today_generated,
					'daily_limit' => $posts_per_day,
				] );
				return;
			}
			
			// Get approved ideas to process.
			$ideas_to_process = min( $posts_per_day - $today_generated, 3 ); // Process max 3 per run.
			$all_approved_ideas = $this->idea_model->get_by_status( 'approved' );
			$approved_ideas = array_slice( $all_approved_ideas, 0, $ideas_to_process );
			
			if ( empty( $approved_ideas ) ) {
				Logger::info( 'cron_process_queue_no_ideas', 'No approved ideas to process' );
				return;
			}
			
			$processed = 0;
			$failed = 0;
			
			foreach ( $approved_ideas as $idea ) {
				// Convert object to array for consistent access
				$idea_id = is_object( $idea ) ? $idea->id : $idea['id'];
				
				// Generate blog post.
				$result = $this->content_generator->generate_blog_post( $idea_id );
				
				if ( $result['success'] ) {
					$processed++;
					
					// Schedule for publishing if auto-publish is enabled.
					if ( get_option( 'ai_blog_generator_auto_publish', false ) ) {
						$this->schedule_post_for_publishing( $result['post_id'] );
					}
					
					Logger::info( 'cron_idea_processed', 'Successfully processed approved idea', [
						'idea_id' => $idea_id,
						'post_id' => $result['post_id'],
						'cost' => $result['cost'],
					] );
				} else {
					$failed++;
					Logger::error( 'cron_idea_failed', 'Failed to process approved idea', [
						'idea_id' => $idea_id,
						'error' => $result['message'],
					] );
				}
				
				// Small delay between processing to avoid rate limits.
				sleep( 2 );
			}
			
			Logger::info( 'cron_process_queue_complete', 'Approved ideas processing completed', [
				'processed' => $processed,
				'failed' => $failed,
				'total_today' => $today_generated + $processed,
			] );
			
		} catch ( \Exception $e ) {
			Logger::error( 'cron_process_queue_exception', 'Exception during queue processing', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			] );
		}
		*/
	}

	/**
	 * Publish scheduled posts cron job.
	 */
	public function publish_scheduled_posts() {
		Logger::info( 'cron_publish_start', 'Starting scheduled posts publishing' );
		
		try {
			// Get posts scheduled for publishing.
			$scheduled_posts = $this->blog_model->get_scheduled_for_publishing();
			
			if ( empty( $scheduled_posts ) ) {
				return; // No posts to publish.
			}
			
			$published = 0;
			$failed = 0;
			
			foreach ( $scheduled_posts as $blog_post ) {
				$post_id = $blog_post['post_id'];
				
				// Check if WordPress post exists and is still a draft.
				$wp_post = get_post( $post_id );
				if ( ! $wp_post || $wp_post->post_status !== 'draft' ) {
					// Update our record to reflect actual status.
					$this->blog_model->update( $blog_post['id'], [
						'status' => $wp_post ? $wp_post->post_status : 'deleted',
					] );
					continue;
				}
				
				// Publish the post.
				$result = wp_update_post([
					'ID' => $post_id,
					'post_status' => 'publish',
				], true );
				
				if ( ! is_wp_error( $result ) ) {
					// Update blog record.
					$this->blog_model->update( $blog_post['id'], [
						'status' => 'published',
						'published_at' => current_time( 'mysql' ),
					] );
					
					$published++;
					
					Logger::info( 'cron_post_published', 'Successfully published scheduled post', [
						'blog_id' => $blog_post['id'],
						'post_id' => $post_id,
						'title' => $blog_post['title'],
					] );
					
					// Trigger post-publish actions.
					do_action( 'ai_blog_post_published', $post_id, $blog_post );
				} else {
					$failed++;
					Logger::error( 'cron_publish_failed', 'Failed to publish scheduled post', [
						'blog_id' => $blog_post['id'],
						'post_id' => $post_id,
						'error' => $result->get_error_message(),
					] );
				}
			}
			
			if ( $published > 0 || $failed > 0 ) {
				Logger::info( 'cron_publish_complete', 'Scheduled publishing completed', [
					'published' => $published,
					'failed' => $failed,
				] );
			}
			
		} catch ( \Exception $e ) {
			Logger::error( 'cron_publish_exception', 'Exception during scheduled publishing', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			] );
		}
	}

	/**
	 * Get random publish time within configured hours.
	 *
	 * @param int $min_hour Minimum hour (0-23).
	 * @param int $max_hour Maximum hour (0-23).
	 * @return string Time in H:i format.
	 */
	public function get_random_publish_time( $min_hour = 8, $max_hour = 20 ) {
		// Get configured time range.
		$min_time = get_option( 'ai_blog_generator_publish_time_min', '08:00' );
		$max_time = get_option( 'ai_blog_generator_publish_time_max', '20:00' );
		
		// Parse times.
		$min_parts = explode( ':', $min_time );
		$max_parts = explode( ':', $max_time );
		
		$min_hour = intval( $min_parts[0] );
		$max_hour = intval( $max_parts[0] );
		
		// Generate random time.
		$hour = rand( $min_hour, $max_hour );
		$minute = rand( 0, 59 );
		
		return sprintf( '%02d:%02d', $hour, $minute );
	}

	/**
	 * Distribute posts evenly throughout the day.
	 *
	 * @param int $post_count    Number of posts to distribute.
	 * @param int $posts_per_day Maximum posts per day.
	 * @return array Array of scheduled times.
	 */
	public function distribute_posts_daily( $post_count, $posts_per_day ) {
		$times = [];
		
		if ( $post_count <= 0 ) {
			return $times;
		}
		
		// Get time range.
		$min_time = get_option( 'ai_blog_generator_publish_time_min', '08:00' );
		$max_time = get_option( 'ai_blog_generator_publish_time_max', '20:00' );
		
		// Convert to minutes.
		$min_parts = explode( ':', $min_time );
		$max_parts = explode( ':', $max_time );
		
		$min_minutes = ( intval( $min_parts[0] ) * 60 ) + intval( $min_parts[1] );
		$max_minutes = ( intval( $max_parts[0] ) * 60 ) + intval( $max_parts[1] );
		
		$total_minutes = $max_minutes - $min_minutes;
		
		if ( $total_minutes <= 0 ) {
			return $times;
		}
		
		// Calculate interval between posts.
		$interval = $total_minutes / $post_count;
		
		// Generate distributed times.
		for ( $i = 0; $i < $post_count; $i++ ) {
			// Base time plus some randomness.
			$target_minutes = $min_minutes + ( $i * $interval );
			$random_offset = rand( -30, 30 ); // +/- 30 minutes randomness.
			
			$final_minutes = max( $min_minutes, min( $max_minutes, $target_minutes + $random_offset ) );
			
			$hour = floor( $final_minutes / 60 );
			$minute = $final_minutes % 60;
			
			$times[] = sprintf( '%02d:%02d', $hour, $minute );
		}
		
		// Sort times.
		sort( $times );
		
		return $times;
	}

	/**
	 * Schedule a post for publishing.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return bool Success status.
	 */
	private function schedule_post_for_publishing( $post_id ) {
		try {
			// Get random publish time for tomorrow or later.
			$days_ahead = rand( 1, 3 ); // Publish 1-3 days from now.
			$time = $this->get_random_publish_time();
			
			$publish_date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) ) . ' ' . $time . ':00';
			
			// Update blog record with scheduled time.
			$blog_record = $this->blog_model->get_by_post_id( $post_id );
			if ( $blog_record ) {
				$this->blog_model->update( $blog_record['id'], [
					'status' => 'scheduled',
					'scheduled_time' => $publish_date,
				] );
				
				Logger::info( 'post_scheduled', 'Post scheduled for publishing', [
					'post_id' => $post_id,
					'scheduled_for' => $publish_date,
				] );
				
				return true;
			}
			
		} catch ( \Exception $e ) {
			Logger::error( 'schedule_post_failed', 'Failed to schedule post', [
				'post_id' => $post_id,
				'error' => $e->getMessage(),
			] );
		}
		
		return false;
	}

	/**
	 * Register cron hooks with WordPress.
	 */
	private function register_cron_hooks() {
		// Daily idea generation.
		add_action( 'ai_blog_daily_ideas', [ $this, 'daily_idea_generation' ] );
		
		// Process approved ideas queue.
		add_action( 'ai_blog_process_queue', [ $this, 'process_approved_ideas_queue' ] );
		
		// Publish scheduled posts.
		add_action( 'ai_blog_publish_scheduled', [ $this, 'publish_scheduled_posts' ] );
		
		// Cleanup old data.
		add_action( 'ai_blog_cleanup_logs', [ $this, 'cleanup_old_data' ] );
		
		// Handle missed cron events.
		add_action( 'init', [ $this, 'check_missed_cron_events' ] );
		
		// Only log cron hooks registration once per session to prevent log spam
		if ( ! get_transient( 'ai_blog_cron_hooks_registered_logged' ) ) {
			Logger::info( 'cron_hooks_registered', 'All cron hooks registered successfully' );
			set_transient( 'ai_blog_cron_hooks_registered_logged', true, 300 ); // 5 minutes
		}
	}

	/**
	 * Unregister cron hooks.
	 */
	public function unregister_cron_hooks() {
		// Remove action hooks.
		remove_action( 'ai_blog_daily_ideas', [ $this, 'daily_idea_generation' ] );
		remove_action( 'ai_blog_process_queue', [ $this, 'process_approved_ideas_queue' ] );
		remove_action( 'ai_blog_publish_scheduled', [ $this, 'publish_scheduled_posts' ] );
		remove_action( 'ai_blog_cleanup_logs', [ $this, 'cleanup_old_data' ] );
		remove_action( 'init', [ $this, 'check_missed_cron_events' ] );
		
		Logger::info( 'cron_hooks_unregistered', 'All cron hooks unregistered' );
	}

	/**
	 * Schedule WordPress cron jobs.
	 */
	private function schedule_jobs() {
		// Daily idea generation at 6 AM.
		if ( ! wp_next_scheduled( 'ai_blog_daily_ideas' ) ) {
			$next_run = strtotime( 'tomorrow 6:00am' );
			wp_schedule_event( $next_run, 'daily', 'ai_blog_daily_ideas' );
			Logger::info( 'cron_scheduled', 'Daily ideas cron job scheduled', [
				'next_run' => date( 'Y-m-d H:i:s', $next_run ),
			] );
		}
		
		// Process queue every hour.
		if ( ! wp_next_scheduled( 'ai_blog_process_queue' ) ) {
			$next_run = time() + HOUR_IN_SECONDS;
			wp_schedule_event( $next_run, 'hourly', 'ai_blog_process_queue' );
			Logger::info( 'cron_scheduled', 'Process queue cron job scheduled', [
				'next_run' => date( 'Y-m-d H:i:s', $next_run ),
			] );
		}
		
		// Publish scheduled posts every 15 minutes.
		if ( ! wp_next_scheduled( 'ai_blog_publish_scheduled' ) ) {
			$next_run = time() + ( 15 * MINUTE_IN_SECONDS );
			wp_schedule_event( $next_run, 'fifteen_minutes', 'ai_blog_publish_scheduled' );
			Logger::info( 'cron_scheduled', 'Publish scheduled cron job scheduled', [
				'next_run' => date( 'Y-m-d H:i:s', $next_run ),
			] );
		}
		
		// Cleanup old logs daily at 3 AM.
		if ( ! wp_next_scheduled( 'ai_blog_cleanup_logs' ) ) {
			$next_run = strtotime( 'tomorrow 3:00am' );
			wp_schedule_event( $next_run, 'daily', 'ai_blog_cleanup_logs' );
			Logger::info( 'cron_scheduled', 'Cleanup logs cron job scheduled', [
				'next_run' => date( 'Y-m-d H:i:s', $next_run ),
			] );
		}
	}

	/**
	 * Check for missed cron events and reschedule if necessary.
	 */
	public function check_missed_cron_events() {
		// Only check once per hour.
		$last_check = get_transient( 'ai_blog_cron_check' );
		if ( $last_check ) {
			return;
		}
		
		set_transient( 'ai_blog_cron_check', true, HOUR_IN_SECONDS );
		
		$cron_jobs = [
			'ai_blog_daily_ideas' => 'daily',
			'ai_blog_process_queue' => 'hourly',
			'ai_blog_publish_scheduled' => 'fifteen_minutes',
			'ai_blog_cleanup_logs' => 'daily',
		];
		
		foreach ( $cron_jobs as $hook => $recurrence ) {
			$next_scheduled = wp_next_scheduled( $hook );
			
			if ( ! $next_scheduled ) {
				// Job is not scheduled, reschedule it.
				$this->reschedule_cron_job( $hook, $recurrence );
				
				Logger::warning( 'cron_missed_rescheduled', 'Missed cron job rescheduled', [
					'hook' => $hook,
					'recurrence' => $recurrence,
				] );
			}
		}
	}

	/**
	 * Reschedule a cron job.
	 *
	 * @param string $hook       Cron hook name.
	 * @param string $recurrence Recurrence interval.
	 */
	private function reschedule_cron_job( $hook, $recurrence ) {
		// Clear any existing schedule.
		wp_clear_scheduled_hook( $hook );
		
		// Calculate next run time based on the job type.
		switch ( $hook ) {
			case 'ai_blog_daily_ideas':
				$next_run = strtotime( 'tomorrow 6:00am' );
				break;
			case 'ai_blog_process_queue':
				$next_run = time() + HOUR_IN_SECONDS;
				break;
			case 'ai_blog_publish_scheduled':
				$next_run = time() + ( 15 * MINUTE_IN_SECONDS );
				break;
			case 'ai_blog_cleanup_logs':
				$next_run = strtotime( 'tomorrow 3:00am' );
				break;
			default:
				$next_run = time() + HOUR_IN_SECONDS;
		}
		
		// Schedule the job.
		wp_schedule_event( $next_run, $recurrence, $hook );
	}

	/**
	 * Cleanup old data (logs, expired costs, etc.).
	 */
	public function cleanup_old_data() {
		Logger::info( 'cron_cleanup_start', 'Starting old data cleanup' );
		
		try {
			$retention_days = intval( get_option( 'ai_blog_generator_log_retention_days', 30 ) );
			
			// Clean up old logs.
			$deleted_logs = $this->database_manager->delete( 'logs', [
				'created_at <' => date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) ),
			] );
			
			// Clean up old cost records (keep 90 days).
			$deleted_costs = $this->database_manager->delete( 'cost_analytics', [
				'created_at <' => date( 'Y-m-d H:i:s', strtotime( '-90 days' ) ),
			] );
			
			// Clean up denied ideas older than 180 days.
			$deleted_ideas = $this->idea_model->cleanup_old_denied_ideas( 180 );
			
			Logger::info( 'cron_cleanup_complete', 'Old data cleanup completed', [
				'deleted_logs' => $deleted_logs,
				'deleted_costs' => $deleted_costs,
				'deleted_ideas' => $deleted_ideas,
			] );
			
		} catch ( \Exception $e ) {
			Logger::error( 'cron_cleanup_failed', 'Failed to cleanup old data', [
				'error' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Get cron job status information.
	 *
	 * @return array Cron job status details.
	 */
	public function get_cron_status() {
		$jobs = [
			'ai_blog_daily_ideas' => [
				'name' => __( 'Daily Idea Generation', 'ai-blog-generator' ),
				'next_run' => wp_next_scheduled( 'ai_blog_daily_ideas' ),
				'recurrence' => 'daily',
			],
			'ai_blog_process_queue' => [
				'name' => __( 'Process Approved Ideas', 'ai-blog-generator' ),
				'next_run' => wp_next_scheduled( 'ai_blog_process_queue' ),
				'recurrence' => 'hourly',
			],
			'ai_blog_publish_scheduled' => [
				'name' => __( 'Publish Scheduled Posts', 'ai-blog-generator' ),
				'next_run' => wp_next_scheduled( 'ai_blog_publish_scheduled' ),
				'recurrence' => 'fifteen_minutes',
			],
			'ai_blog_cleanup_logs' => [
				'name' => __( 'Cleanup Old Data', 'ai-blog-generator' ),
				'next_run' => wp_next_scheduled( 'ai_blog_cleanup_logs' ),
				'recurrence' => 'daily',
			],
		];
		
		// Add human-readable next run times.
		foreach ( $jobs as $hook => &$job ) {
			if ( $job['next_run'] ) {
				$job['next_run_human'] = human_time_diff( $job['next_run'] );
				$job['next_run_date'] = date( 'Y-m-d H:i:s', $job['next_run'] );
				$job['status'] = 'scheduled';
			} else {
				$job['next_run_human'] = __( 'Not scheduled', 'ai-blog-generator' );
				$job['next_run_date'] = '';
				$job['status'] = 'missing';
			}
		}
		
		return $jobs;
	}

	/**
	 * Manually trigger a cron job (for testing/admin use).
	 *
	 * @param string $hook Cron hook name.
	 * @return array Result of the operation.
	 */
	public function trigger_cron_job( $hook ) {
		try {
			switch ( $hook ) {
				case 'ai_blog_daily_ideas':
					$this->daily_idea_generation();
					break;
				case 'ai_blog_process_queue':
					$this->process_approved_ideas_queue();
					break;
				case 'ai_blog_publish_scheduled':
					$this->publish_scheduled_posts();
					break;
				case 'ai_blog_cleanup_logs':
					$this->cleanup_old_data();
					break;
				default:
					throw new \Exception( __( 'Invalid cron job hook', 'ai-blog-generator' ) );
			}
			
			Logger::info( 'cron_manual_trigger', 'Manually triggered cron job', [
				'hook' => $hook,
				'user_id' => get_current_user_id(),
			] );
			
			return [
				'success' => true,
				'message' => __( 'Cron job executed successfully', 'ai-blog-generator' ),
			];
			
		} catch ( \Exception $e ) {
			Logger::error( 'cron_manual_trigger_failed', 'Failed to manually trigger cron job', [
				'hook' => $hook,
				'error' => $e->getMessage(),
			] );
			
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}
} 
 
 