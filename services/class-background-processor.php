<?php
/**
 * Background Processor Service
 *
 * Handles independent background blog generation with status tracking.
 *
 * @package AI_Blog_Generator
 * @subpackage Services
 */

namespace AI_Blog_Generator\Services;

use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Services\Content_Generator;
use AI_Blog_Generator\Services\Budget_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Background Processor Class
 */
class Background_Processor {

    /**
     * Generation statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_STARTING = 'starting';
    const STATUS_CONTEXTS = 'contexts';
    const STATUS_CONTENT = 'content';
    const STATUS_IMAGES = 'images';
    const STATUS_POST = 'post';
    const STATUS_COMPLETE = 'complete';
    const STATUS_ERROR = 'error';

    /**
     * Progress percentages for each status
     */
    const PROGRESS_MAP = [
        self::STATUS_PENDING => 0,
        self::STATUS_STARTING => 10,
        self::STATUS_CONTEXTS => 20,
        self::STATUS_CONTENT => 60,
        self::STATUS_IMAGES => 85,
        self::STATUS_POST => 95,
        self::STATUS_COMPLETE => 100,
        self::STATUS_ERROR => 0,
    ];

    /**
     * Initialize the background processor
     */
    public function __construct() {
        // Register the background action
        add_action( 'ai_blog_background_generate', [ $this, 'process_generation' ] );
        
        // Register cleanup hook
        add_action( 'ai_blog_cleanup_generations', [ $this, 'cleanup_old_generations' ] );
        
        // Schedule cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'ai_blog_cleanup_generations' ) ) {
            wp_schedule_event( time(), 'hourly', 'ai_blog_cleanup_generations' );
        }
    }

    /**
     * Start background generation for an idea
     *
     * @param int $idea_id The idea ID
     * @return bool True if generation started successfully
     */
    public function start_generation( $idea_id ) {
        // Validate idea ID
        if ( ! $idea_id || ! is_numeric( $idea_id ) ) {
            return false;
        }

        // Check if generation is already in progress
        if ( $this->is_generation_in_progress( $idea_id ) ) {
            return false;
        }

        // Don't create lock here - let Content Generator handle all lock management
        // Just set initial status
        // $this->create_generation_lock( $idea_id );  // REMOVED: Content Generator handles locks
        $this->update_generation_status( $idea_id, 'pending', 'Generation queued...' );

        // Log the generation start
        $debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: start_generation called for idea_id: $idea_id\n", FILE_APPEND );
        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Status set to pending for idea_id: $idea_id (lock creation removed to prevent conflicts)\n", FILE_APPEND );

        // DIRECT EXECUTION: Execute generation immediately instead of scheduling
        try {
            // Execute generation directly in the same request
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Executing generation directly for idea_id: $idea_id\n", FILE_APPEND );
            
            $this->process_generation( $idea_id );
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Direct generation completed for idea_id: $idea_id\n", FILE_APPEND );
            
            return true;
            
        } catch ( Exception $e ) {
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Direct generation failed for idea_id: $idea_id - " . $e->getMessage() . "\n", FILE_APPEND );
            
            // Clean up on error
            $this->remove_generation_lock( $idea_id );
            $this->update_generation_status( $idea_id, 'error', 'Generation failed: ' . $e->getMessage() );
            
            return false;
        }
    }

    /**
     * Schedule immediate background generation
     *
     * @param int $idea_id The idea ID
     */
    private function schedule_immediate_generation( $idea_id ) {
        // Use wp_schedule_single_event with timestamp of now for immediate execution
        wp_schedule_single_event( time(), 'ai_blog_background_generate', [ $idea_id ] );
        
        // Also trigger wp-cron to run immediately if possible
        if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
            spawn_cron();
        }
        
        // FALLBACK: If cron doesn't work (common in local development), 
        // execute immediately in the background using a separate request
        $this->execute_generation_fallback( $idea_id );
    }

    /**
     * Fallback execution method for when WordPress cron is not reliable
     *
     * @param int $idea_id The idea ID
     */
    private function execute_generation_fallback( $idea_id ) {
        // DEBUG: Log the fallback attempt
        $debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Executing fallback generation for idea_id: $idea_id\n", FILE_APPEND );
        
        // Execute in a separate process to avoid blocking the user interface
        if ( function_exists( 'wp_remote_post' ) ) {
            // Make a background HTTP request to trigger generation
            $response = wp_remote_post( admin_url( 'admin-ajax.php' ), [
                'timeout' => 1, // Short timeout to avoid blocking
                'blocking' => false, // Don't wait for response
                'body' => [
                    'action' => 'ai_blog_execute_fallback_generation',
                    'idea_id' => $idea_id,
                    'nonce' => wp_create_nonce( 'ai_blog_fallback_generation' )
                ]
            ] );
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Fallback HTTP request sent for idea_id: $idea_id\n", FILE_APPEND );
        } else {
            // If wp_remote_post not available, execute directly (may block but better than nothing)
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Executing direct fallback for idea_id: $idea_id\n", FILE_APPEND );
            $this->process_generation( $idea_id );
        }
    }

    /**
     * Process the actual generation in background
     *
     * @param int $idea_id The idea ID to process
     */
    public function process_generation( $idea_id ) {
        $idea_id = absint( $idea_id );
        
        // DEBUG: Log to debug-transaction.log
        $debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: process_generation CRON JOB STARTED for idea_id: $idea_id\n", FILE_APPEND );
        
        Logger::info( 'background_processor_processing', 'Starting background generation process', [
            'idea_id' => $idea_id,
            'memory_usage' => memory_get_usage( true ),
            'memory_limit' => ini_get( 'memory_limit' )
        ] );

        try {
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Starting try block for idea_id: $idea_id\n", FILE_APPEND );
            
            // Load models
            $idea_model = new Idea_Model();
            $blog_model = new Blog_Model();

            // Get the idea
            $idea = $idea_model->get( $idea_id );
            if ( ! $idea ) {
                throw new \Exception( 'Idea not found: ' . $idea_id );
            }

            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Idea loaded successfully for idea_id: $idea_id\n", FILE_APPEND );

            // Verify idea is approved or already generating (Content Generator may have updated status)
            if ( ! in_array( $idea['status'], [ 'approved', 'generating' ], true ) ) {
                throw new \Exception( 'Idea is not approved for generation: ' . $idea['status'] );
            }

            // Check budget and daily limits
            $this->check_generation_constraints();

            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Calling Content_Generator for idea_id: $idea_id\n", FILE_APPEND );

            // Initialize content generator - it will handle all status updates
            $generator = new Content_Generator();

            // Generate the blog post - Content Generator will update status throughout
            $result = $generator->generate_blog_post( $idea_id );

            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Content_Generator returned result for idea_id: $idea_id\n", FILE_APPEND );

            if ( is_wp_error( $result ) ) {
                throw new \Exception( 'Generation failed: ' . $result->get_error_message() );
            }

            // Handle different result formats
            $blog_id = null;
            if ( is_array( $result ) ) {
                if ( ! $result['success'] ) {
                    throw new \Exception( 'Generation failed: ' . $result['message'] );
                }
                $blog_id = $result['blog_id'];
            } else {
                $blog_id = $result;
            }

            // Clean up the generation lock (Content Generator sets completion status)
            $this->remove_generation_lock( $idea_id );

            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Generation COMPLETED successfully for idea_id: $idea_id, blog_id: $blog_id\n", FILE_APPEND );

            Logger::info( 'background_processor_complete', 'Background generation completed successfully', [
                'idea_id' => $idea_id,
                'blog_id' => $blog_id,
                'memory_peak' => memory_get_peak_usage( true )
            ] );

        } catch ( \Exception $e ) {
            $error_message = $e->getMessage();
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: ERROR for idea_id: $idea_id - " . $error_message . "\n", FILE_APPEND );
            
            Logger::error( 'background_processor_error', 'Background generation failed', [
                'idea_id' => $idea_id,
                'error' => $error_message,
                'trace' => $e->getTraceAsString(),
                'memory_peak' => memory_get_peak_usage( true )
            ] );

            // Clean up the generation lock
            $this->remove_generation_lock( $idea_id );

            // Set idea status to failed with error message - NO RETRY LOGIC
            $idea_model = new Idea_Model();
            $error_status = 'Failed: ' . $error_message;
            
            // Handle specific error types with cleaner messages
            if (strpos($error_message, 'Overloaded') !== false) {
                $error_status = 'Failed: API Overloaded';
            } elseif (strpos($error_message, 'timeout') !== false) {
                $error_status = 'Failed: Timeout';
            } elseif (strpos($error_message, 'Budget') !== false) {
                $error_status = 'Failed: Budget Limit';
            } elseif (strpos($error_message, 'Daily') !== false) {
                $error_status = 'Failed: Daily Limit';
            }
            
            $idea_model->update( $idea_id, [ 
                'status' => 'failed',
                'generation_error' => $error_status,
                'generation_completed_at' => current_time( 'mysql' )
            ] );
            
            // Update generation status to show failure
            $this->update_generation_status( $idea_id, self::STATUS_ERROR, $error_status );
        }
    }

    /**
     * Check if generation is in progress for an idea
     *
     * @param int $idea_id The idea ID
     * @return bool True if in progress
     */
    public function is_generation_in_progress( $idea_id ) {
        $lock = get_transient( "ai_blog_generation_lock_{$idea_id}" );
        return ! empty( $lock );
    }

    /**
     * Create generation lock
     *
     * @param int $idea_id The idea ID
     */
    private function create_generation_lock( $idea_id ) {
        $lock_data = [
            'idea_id' => $idea_id,
            'started_at' => current_time( 'mysql' ),
            'started_timestamp' => time(),
            'user_id' => get_current_user_id()
        ];
        
        // Lock for 30 minutes maximum
        set_transient( "ai_blog_generation_lock_{$idea_id}", $lock_data, 30 * MINUTE_IN_SECONDS );
    }

    /**
     * Remove generation lock
     *
     * @param int $idea_id The idea ID
     */
    private function remove_generation_lock( $idea_id ) {
        delete_transient( "ai_blog_generation_lock_{$idea_id}" );
        delete_transient( "ai_blog_generation_status_{$idea_id}" );
    }

    /**
     * Update generation status
     *
     * @param int $idea_id The idea ID
     * @param string $status The status
     * @param string $message The status message
     */
    public function update_generation_status( $idea_id, $status, $message = '' ) {
        $status_data = [
            'idea_id' => $idea_id,
            'status' => $status,
            'stage' => $status,  // Add stage field for frontend compatibility
            'message' => $message,
            'progress' => self::PROGRESS_MAP[ $status ] ?? 0,
            'updated_at' => current_time( 'mysql' ),
            'updated_timestamp' => time()
        ];

        // Store status for 1 hour
        set_transient( "ai_blog_generation_status_{$idea_id}", $status_data, HOUR_IN_SECONDS );

        Logger::debug( 'background_processor_status_update', 'Generation status updated vv', [
            'idea_id' => $idea_id,
            'status' => $status,
            'message' => $message,
            'progress' => $status_data['progress']
        ] );
    }

    /**
     * Get generation status for an idea
     *
     * @param int $idea_id The idea ID
     * @return array|null Status data or null if not found
     */
    public function get_generation_status( $idea_id ) {
        return get_transient( "ai_blog_generation_status_{$idea_id}" );
    }

    /**
     * Get generation statuses for multiple ideas
     *
     * @param array $idea_ids Array of idea IDs
     * @return array Array of statuses keyed by idea ID
     */
    public function get_multiple_generation_statuses( $idea_ids ) {
        $statuses = [];
        
        foreach ( $idea_ids as $idea_id ) {
            $status = $this->get_generation_status( $idea_id );
            if ( $status ) {
                $statuses[ $idea_id ] = $status;
            }
        }
        
        return $statuses;
    }

    /**
     * Get all currently running generations
     *
     * @return array Array of running generation data
     */
    public function get_running_generations() {
        global $wpdb;
        
        $running = [];
        
        // Query for all generation locks
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND expiration > %d
        ", '_transient_ai_blog_generation_lock_%', time() ) );
        
        foreach ( $results as $result ) {
            $lock_data = maybe_unserialize( $result->option_value );
            if ( $lock_data && isset( $lock_data['idea_id'] ) ) {
                $idea_id = $lock_data['idea_id'];
                $status = $this->get_generation_status( $idea_id );
                
                $running[ $idea_id ] = [
                    'lock' => $lock_data,
                    'status' => $status
                ];
            }
        }
        
        return $running;
    }

    /**
     * Check generation constraints (budget, daily limits)
     *
     * @throws \Exception If constraints are not met
     */
    private function check_generation_constraints() {
        // Check budget constraints
        $budget_manager = new Budget_Manager();
        if ( ! $budget_manager->can_generate() ) {
            throw new \Exception( 'Monthly budget limit reached. Cannot generate new posts.' );
        }

        // Check daily limit
        $blog_model = new Blog_Model();
        $today_count = $blog_model->count_generated_today();
        $posts_per_day = get_option( 'ai_blog_generator_posts_per_day', 2 );
        
        if ( $today_count >= $posts_per_day ) {
            throw new \Exception( 'Daily generation limit reached.' );
        }
    }

    /**
     * Clean up old generation locks and statuses
     */
    public function cleanup_old_generations() {
        global $wpdb;
        
        $cleaned_locks = 0;
        $cleaned_statuses = 0;
        
        // Clean up expired locks (older than 30 minutes)
        $expired_time = time() - ( 30 * MINUTE_IN_SECONDS );
        
        $expired_locks = $wpdb->get_results( $wpdb->prepare( "
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_value < %d
        ", '_transient_timeout_ai_blog_generation_lock_%', $expired_time ) );
        
        foreach ( $expired_locks as $lock ) {
            $lock_name = str_replace( '_transient_timeout_', '_transient_', $lock->option_name );
            delete_option( $lock->option_name );
            delete_option( $lock_name );
            $cleaned_locks++;
        }
        
        // Clean up old statuses (older than 1 hour)
        $expired_status_time = time() - HOUR_IN_SECONDS;
        
        $expired_statuses = $wpdb->get_results( $wpdb->prepare( "
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_value < %d
        ", '_transient_timeout_ai_blog_generation_status_%', $expired_status_time ) );
        
        foreach ( $expired_statuses as $status ) {
            $status_name = str_replace( '_transient_timeout_', '_transient_', $status->option_name );
            delete_option( $status->option_name );
            delete_option( $status_name );
            $cleaned_statuses++;
        }
        
        if ( $cleaned_locks > 0 || $cleaned_statuses > 0 ) {
            Logger::info( 'background_processor_cleanup', 'Cleaned up old generation data', [
                'cleaned_locks' => $cleaned_locks,
                'cleaned_statuses' => $cleaned_statuses
            ] );
        }
    }

    /**
     * Cancel a generation in progress
     *
     * @param int $idea_id The idea ID
     * @return bool True if cancelled
     */
    public function cancel_generation( $idea_id ) {
        $this->remove_generation_lock( $idea_id );
        
        Logger::info( 'background_processor_cancelled', 'Generation cancelled by user', [
            'idea_id' => $idea_id,
            'user_id' => get_current_user_id()
        ] );
        
        return true;
    }

    /**
     * Get generation statistics
     *
     * @return array Statistics data
     */
    public function get_generation_statistics() {
        $running = $this->get_running_generations();
        
        return [
            'currently_running' => count( $running ),
            'running_details' => $running
        ];
    }
} 