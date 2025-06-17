<?php
/**
 * Generation Queue Service
 * 
 * Manages concurrent generation queue with max 5 simultaneous generations
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generation Queue Class
 */
class Generation_Queue {

	/**
	 * Maximum concurrent generations
	 *
	 * @var int
	 */
	const MAX_CONCURRENT = 5;

	/**
	 * Queue status transient key
	 *
	 * @var string
	 */
	const QUEUE_STATUS_KEY = 'ai_blog_generation_queue_status';

	/**
	 * Active generations transient key
	 *
	 * @var string
	 */
	const ACTIVE_GENERATIONS_KEY = 'ai_blog_active_generations';

	/**
	 * Ideas model instance
	 *
	 * @var Blog_Ideas_Model_V2
	 */
	private $ideas_model;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->ideas_model = new Blog_Ideas_Model_V2();
	}

	/**
	 * Add ideas to generation queue
	 *
	 * @param array $idea_ids Array of idea IDs to queue.
	 * @return array Result with queued and active counts.
	 */
	public function add_to_queue( $idea_ids ) {
		Logger::info( 'generation_queue_add', 'Adding ideas to generation queue', [
			'idea_ids' => $idea_ids,
			'count' => count( $idea_ids )
		] );

		$queued = [];
		$started = [];
		$errors = [];

		foreach ( $idea_ids as $idea_id ) {
			$result = $this->queue_single_idea( $idea_id );
			
			if ( $result['status'] === 'started' ) {
				$started[] = $idea_id;
			} elseif ( $result['status'] === 'queued' ) {
				$queued[] = $idea_id;
			} else {
				$errors[] = [
					'idea_id' => $idea_id,
					'error' => $result['error'] ?? 'Unknown error'
				];
			}
		}

		return [
			'success' => count( $errors ) === 0,
			'queued' => $queued,
			'started' => $started,
			'errors' => $errors,
			'active_count' => $this->get_active_count(),
			'queue_length' => count( $queued )
		];
	}

	/**
	 * Queue a single idea
	 *
	 * @param int $idea_id The idea ID.
	 * @return array Result with status.
	 */
	private function queue_single_idea( $idea_id ) {
		// Check if idea exists and is approved
		$idea = $this->ideas_model->get_idea( $idea_id );
		
		if ( ! $idea ) {
			return [
				'status' => 'error',
				'error' => 'Idea not found'
			];
		}

		if ( ! in_array( $idea['status'], [ 'approved', 'generating' ], true ) ) {
			return [
				'status' => 'error',
				'error' => 'Idea is not approved for generation'
			];
		}

		// Check if already generating
		if ( $this->is_idea_generating( $idea_id ) ) {
			return [
				'status' => 'error',
				'error' => 'Idea is already generating'
			];
		}

		// Check if we can start immediately
		if ( $this->can_start_generation() ) {
			// Start generation immediately
			$this->start_generation( $idea_id );
			return [
				'status' => 'started',
				'position' => 0
			];
		} else {
			// Add to queue
			$position = $this->add_to_waiting_queue( $idea_id );
			return [
				'status' => 'queued',
				'position' => $position
			];
		}
	}

	/**
	 * Check if we can start a new generation
	 *
	 * @return bool
	 */
	private function can_start_generation() {
		$active_count = $this->get_active_count();
		return $active_count < self::MAX_CONCURRENT;
	}

	/**
	 * Get count of active generations
	 *
	 * @return int
	 */
	public function get_active_count() {
		$active = get_transient( self::ACTIVE_GENERATIONS_KEY ) ?: [];
		
		// Clean up completed or timed out generations
		$cleaned = [];
		foreach ( $active as $idea_id => $data ) {
			// Check if still generating (timeout after 30 minutes)
			if ( isset( $data['started_at'] ) && ( time() - $data['started_at'] ) < 1800 ) {
				// Also check database status
				$idea = $this->ideas_model->get_idea( $idea_id );
				if ( $idea && $idea['status'] === 'generating' ) {
					$cleaned[ $idea_id ] = $data;
				}
			}
		}

		if ( count( $cleaned ) !== count( $active ) ) {
			set_transient( self::ACTIVE_GENERATIONS_KEY, $cleaned, HOUR_IN_SECONDS );
		}

		return count( $cleaned );
	}

	/**
	 * Check if an idea is currently generating
	 *
	 * @param int $idea_id The idea ID.
	 * @return bool
	 */
	public function is_idea_generating( $idea_id ) {
		$active = get_transient( self::ACTIVE_GENERATIONS_KEY ) ?: [];
		return isset( $active[ $idea_id ] );
	}

	/**
	 * Start generation for an idea
	 *
	 * @param int $idea_id The idea ID.
	 * @return bool Success status.
	 */
	private function start_generation( $idea_id ) {
		Logger::info( 'generation_queue_start', 'Starting generation for idea', [
			'idea_id' => $idea_id
		] );

		// Update idea status to generating
		$this->ideas_model->update_idea( $idea_id, [
			'status' => 'generating',
			'generation_status' => 'Starting...',
			'generation_started_at' => current_time( 'mysql' ),
			'generation_error' => null
		] );

		// Add to active generations
		$active = get_transient( self::ACTIVE_GENERATIONS_KEY ) ?: [];
		$active[ $idea_id ] = [
			'started_at' => time(),
			'status' => 'starting'
		];
		set_transient( self::ACTIVE_GENERATIONS_KEY, $active, HOUR_IN_SECONDS );

		// In local development, WordPress cron might not work, so execute directly
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::info( 'generation_direct_execution', 'Executing generation directly (debug mode)', [
				'idea_id' => $idea_id
			] );
			
			// Execute generation directly using Background Processor
			try {
				$background_processor = new Background_Processor();
				$background_processor->start_generation( $idea_id );
			} catch ( \Exception $e ) {
				Logger::error( 'generation_direct_execution_error', 'Error in direct generation', [
					'idea_id' => $idea_id,
					'error' => $e->getMessage()
				] );
				
				// Mark as failed
				$this->mark_failed( $idea_id, $e->getMessage() );
				return false;
			}
		} else {
			// In production, use WordPress cron
			wp_schedule_single_event( time(), 'ai_blog_process_single_generation', [ $idea_id ] );
		}

		return true;
	}

	/**
	 * Add idea to waiting queue
	 *
	 * @param int $idea_id The idea ID.
	 * @return int Queue position.
	 */
	private function add_to_waiting_queue( $idea_id ) {
		$queue = get_transient( self::QUEUE_STATUS_KEY ) ?: [];
		
		// Add to queue if not already there
		if ( ! in_array( $idea_id, $queue, true ) ) {
			$queue[] = $idea_id;
			set_transient( self::QUEUE_STATUS_KEY, $queue, DAY_IN_SECONDS );
		}

		// Update idea status
		$position = array_search( $idea_id, $queue ) + 1;
		$this->ideas_model->update_idea( $idea_id, [
			'generation_status' => "In Queue (Position: $position)"
		] );

		Logger::info( 'idea_queued', 'Idea added to generation queue', [
			'idea_id' => $idea_id,
			'position' => $position,
			'queue_length' => count( $queue )
		] );

		return $position;
	}

	/**
	 * Process the queue (called when a generation completes)
	 *
	 * @param int $completed_idea_id The ID of the completed idea.
	 */
	public function process_queue( $completed_idea_id = null ) {
		Logger::info( 'generation_queue_process', 'Processing generation queue', [
			'completed_idea_id' => $completed_idea_id
		] );

		// Remove from active generations if provided
		if ( $completed_idea_id ) {
			$this->remove_from_active( $completed_idea_id );
		}

		// Check if we can start more generations
		while ( $this->can_start_generation() ) {
			$next_idea_id = $this->get_next_from_queue();
			
			if ( ! $next_idea_id ) {
				break; // No more items in queue
			}

			$this->start_generation( $next_idea_id );
		}
	}

	/**
	 * Remove idea from active generations
	 *
	 * @param int $idea_id The idea ID.
	 */
	public function remove_from_active( $idea_id ) {
		$active = get_transient( self::ACTIVE_GENERATIONS_KEY ) ?: [];
		
		if ( isset( $active[ $idea_id ] ) ) {
			unset( $active[ $idea_id ] );
			set_transient( self::ACTIVE_GENERATIONS_KEY, $active, HOUR_IN_SECONDS );
			
			Logger::debug( 'removed_from_active', 'Removed idea from active generations', [
				'idea_id' => $idea_id,
				'remaining_active' => count( $active )
			] );
		}
	}

	/**
	 * Get next idea from queue
	 *
	 * @return int|null Next idea ID or null if queue is empty.
	 */
	private function get_next_from_queue() {
		$queue = get_transient( self::QUEUE_STATUS_KEY ) ?: [];
		
		if ( empty( $queue ) ) {
			return null;
		}

		// Get the first item
		$next_id = array_shift( $queue );
		
		// Update the queue
		set_transient( self::QUEUE_STATUS_KEY, $queue, DAY_IN_SECONDS );
		
		// Update positions for remaining items
		$position = 1;
		foreach ( $queue as $idea_id ) {
			$this->ideas_model->update_idea( $idea_id, [
				'generation_status' => "In Queue (Position: $position)"
			] );
			$position++;
		}

		return $next_id;
	}

	/**
	 * Get queue status
	 *
	 * @return array Queue status information.
	 */
	public function get_queue_status() {
		$active = get_transient( self::ACTIVE_GENERATIONS_KEY ) ?: [];
		$queue = get_transient( self::QUEUE_STATUS_KEY ) ?: [];

		// Get details for active generations
		$active_details = [];
		foreach ( $active as $idea_id => $data ) {
			$idea = $this->ideas_model->get_idea( $idea_id );
			if ( $idea ) {
				$active_details[] = [
					'id' => $idea_id,
					'title' => $idea['title'],
					'status' => $idea['generation_status'] ?? 'Processing',
					'started_at' => $data['started_at'],
					'duration' => time() - $data['started_at']
				];
			}
		}

		// Get details for queued items
		$queue_details = [];
		$position = 1;
		foreach ( $queue as $idea_id ) {
			$idea = $this->ideas_model->get_idea( $idea_id );
			if ( $idea ) {
				$queue_details[] = [
					'id' => $idea_id,
					'title' => $idea['title'],
					'position' => $position++
				];
			}
		}

		return [
			'active_count' => count( $active_details ),
			'queue_length' => count( $queue_details ),
			'active_generations' => $active_details,
			'queued_ideas' => $queue_details,
			'available_slots' => self::MAX_CONCURRENT - count( $active_details )
		];
	}

	/**
	 * Cancel generation for an idea
	 *
	 * @param int $idea_id The idea ID.
	 * @return bool Success status.
	 */
	public function cancel_generation( $idea_id ) {
		Logger::info( 'generation_cancel', 'Cancelling generation', [
			'idea_id' => $idea_id
		] );

		// Remove from active generations
		$this->remove_from_active( $idea_id );

		// Remove from queue
		$queue = get_transient( self::QUEUE_STATUS_KEY ) ?: [];
		$queue = array_values( array_diff( $queue, [ $idea_id ] ) );
		set_transient( self::QUEUE_STATUS_KEY, $queue, DAY_IN_SECONDS );

		// Update idea status
		$this->ideas_model->update_idea( $idea_id, [
			'status' => 'approved',
			'generation_status' => null,
			'generation_error' => 'Cancelled by user',
			'generation_completed_at' => current_time( 'mysql' )
		] );

		// Process queue to start next item
		$this->process_queue();

		return true;
	}

	/**
	 * Mark generation as failed
	 *
	 * @param int    $idea_id The idea ID.
	 * @param string $error   Error message.
	 */
	public function mark_failed( $idea_id, $error ) {
		Logger::error( 'generation_failed', 'Generation failed', [
			'idea_id' => $idea_id,
			'error' => $error
		] );

		// Update idea status
		$this->ideas_model->update_idea( $idea_id, [
			'status' => 'approved', // Reset to approved so it can be retried
			'generation_status' => 'Failed',
			'generation_error' => $error,
			'generation_completed_at' => current_time( 'mysql' )
		] );

		// Remove from active and process queue
		$this->remove_from_active( $idea_id );
		$this->process_queue();
	}

	/**
	 * Mark generation as complete
	 *
	 * @param int $idea_id The idea ID.
	 */
	public function mark_complete( $idea_id ) {
		Logger::info( 'generation_complete', 'Generation completed', [
			'idea_id' => $idea_id
		] );

		// Update idea status
		$this->ideas_model->update_idea( $idea_id, [
			'status' => 'generated',
			'generation_status' => 'Complete',
			'generation_error' => null,
			'generation_completed_at' => current_time( 'mysql' )
		] );

		// Remove from active and process queue
		$this->remove_from_active( $idea_id );
		$this->process_queue();
	}

	/**
	 * Clear all queues and active generations
	 * Used for system-wide cancel
	 */
	public function clear_all() {
		Logger::info( 'clear_all_queues', 'Clearing all generation queues and active generations' );
		
		// Clear the waiting queue
		delete_transient( self::QUEUE_STATUS_KEY );
		
		// Clear active generations
		delete_transient( self::ACTIVE_GENERATIONS_KEY );
		
		// Clear any other related transients
		global $wpdb;
		
		// Delete all transients that start with ai_blog_generation_
		$wpdb->query( 
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_ai_blog_generation_%' 
			OR option_name LIKE '_transient_timeout_ai_blog_generation_%'"
		);
		
		Logger::info( 'clear_all_complete', 'All queues and transients cleared' );
	}
} 