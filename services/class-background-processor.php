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
use AI_Blog_Generator\Models\Blog_Ideas_Model_V2;
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
     * Blog Ideas Model V2 instance
     *
     * @var Blog_Ideas_Model_V2
     */
    private $idea_model;

    /**
     * Blog Model instance
     *
     * @var Blog_Model
     */
    private $blog_model;

    /**
     * Content Generator instance
     *
     * @var Content_Generator
     */
    private $content_generator;

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
     * Process timeout in seconds (15 minutes).
     *
     * @var int
     */
    private $process_timeout = 900;

    /**
     * Initialize the background processor
     */
    public function __construct() {
        // Initialize required models and services
        $this->idea_model = new Blog_Ideas_Model_V2();
        $this->blog_model = new Blog_Model();
        $this->content_generator = new Content_Generator();
        
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
        $this->update_generation_status( $idea_id, 'starting', 'Generation starting...' );

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
        $debug_log = __DIR__ . '/../debug-transaction.log';
        $start_time = microtime( true );
        
        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: process_generation CRON JOB STARTED for idea_id: {$idea_id}\n", FILE_APPEND );
        
        try {
            // Set a process timeout
            set_time_limit( $this->process_timeout );
            
            // Register emergency cleanup
            register_shutdown_function( function() use ( $idea_id, $debug_log ) {
                $error = error_get_last();
                if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
                    file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: FATAL ERROR detected for idea {$idea_id}: " . $error['message'] . "\n", FILE_APPEND );
                    
                    // Try to reset the idea status
                    try {
                        global $wpdb;
                        $ideas_table = $wpdb->prefix . 'ai_blog_ideas';
                        $wpdb->update(
                            $ideas_table,
                            [
                                'status' => 'approved',
                                'generation_status' => 'Fatal error during generation',
                                'generation_error' => 'Fatal error: ' . $error['message'],
                                'updated_at' => current_time( 'mysql' )
                            ],
                            [ 'id' => $idea_id ],
                            [ '%s', '%s', '%s', '%s' ],
                            [ '%d' ]
                        );
                        
                        // Clear locks
                        delete_transient( "ai_blog_generation_lock_{$idea_id}" );
                        delete_transient( "ai_blog_generation_status_{$idea_id}" );
                        
                    } catch ( \Exception $cleanup_e ) {
                        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Emergency cleanup failed: " . $cleanup_e->getMessage() . "\n", FILE_APPEND );
                    }
                }
            } );
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Starting try block for idea_id: {$idea_id}\n", FILE_APPEND );
            
            // Check if idea exists and load it
            $idea = $this->idea_model->get( $idea_id );
            if ( ! $idea ) {
                file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Idea not found for idea_id: {$idea_id}\n", FILE_APPEND );
                Logger::error( 'background_processor_idea_not_found', 'Idea not found for processing', [ 'idea_id' => $idea_id ] );
                return false;
            }
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Idea loaded successfully for idea_id: {$idea_id}\n", FILE_APPEND );
            
            // Check daily limit before proceeding
            $daily_count = $this->blog_model->count_generated_today();
            $daily_limit = (int) get_option( 'ai_blog_generator_daily_limit', 5 );
            
            if ( $daily_count >= $daily_limit ) {
                file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Daily limit reached ({$daily_count}/{$daily_limit}) for idea_id: {$idea_id}\n", FILE_APPEND );
                Logger::warning( 'background_processor_daily_limit', 'Daily generation limit reached', [
                    'daily_count' => $daily_count,
                    'daily_limit' => $daily_limit,
                    'idea_id' => $idea_id
                ] );
                return false;
            }
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Calling Content_Generator for idea_id: {$idea_id}\n", FILE_APPEND );
            
            // Generate the blog post with timeout monitoring
            $generation_start = microtime( true );
            $result = $this->content_generator->generate_blog_post( $idea_id );
            $generation_duration = microtime( true ) - $generation_start;
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Content generation completed in {$generation_duration}s for idea_id: {$idea_id}\n", FILE_APPEND );
            
            if ( $result['success'] ) {
                Logger::info( 'background_processor_success', 'Blog post generated successfully via cron', [
                    'idea_id' => $idea_id,
                    'post_id' => $result['post_id'] ?? null,
                    'generation_duration' => $generation_duration,
                    'total_duration' => microtime( true ) - $start_time
                ] );
                file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: SUCCESS for idea_id: {$idea_id}\n", FILE_APPEND );
            } else {
                Logger::error( 'background_processor_failed', 'Blog post generation failed via cron', [
                    'idea_id' => $idea_id,
                    'error' => $result['message'] ?? 'Unknown error',
                    'generation_duration' => $generation_duration,
                    'total_duration' => microtime( true ) - $start_time
                ] );
                file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: FAILED for idea_id: {$idea_id} - " . ($result['message'] ?? 'Unknown error') . "\n", FILE_APPEND );
            }
            
            return $result['success'];
            
        } catch ( \Exception $e ) {
            $total_duration = microtime( true ) - $start_time;
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: EXCEPTION after {$total_duration}s for idea_id: {$idea_id} - " . $e->getMessage() . "\n", FILE_APPEND );
            
            Logger::error( 'background_processor_exception', 'Exception during background processing', [
                'idea_id' => $idea_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => $total_duration
            ] );
            
            // Try to reset the idea status
            try {
                $this->idea_model->update( $idea_id, [
                    'status' => 'approved',
                    'generation_status' => 'Generation failed: ' . substr( $e->getMessage(), 0, 100 ),
                    'generation_error' => $e->getMessage(),
                    'updated_at' => current_time( 'mysql' )
                ] );
                
                // Clear generation transients
                delete_transient( "ai_blog_generation_lock_{$idea_id}" );
                delete_transient( "ai_blog_generation_status_{$idea_id}" );
                
            } catch ( \Exception $cleanup_e ) {
                file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Cleanup exception: " . $cleanup_e->getMessage() . "\n", FILE_APPEND );
            }
            
            return false;
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

        // CRITICAL: Also update the database generation_status field
        try {
            $blog_ideas_model = new Blog_Ideas_Model_V2();
            
            // Log the update attempt
            Logger::info( 'background_processor_status_update_attempt', 'Starting generation status update', [
                'idea_id' => $idea_id,
                'status' => $status,
                'message' => $message,
                'method' => 'Background_Processor::update_generation_status'
            ] );
            
            $update_result = $blog_ideas_model->update_idea( $idea_id, [
                'generation_status' => $message,
                'updated_at' => current_time( 'mysql' )
            ] );
            
            // Also log the raw SQL query for debugging
            global $wpdb;
            $last_query = $wpdb->last_query;
            $last_error = $wpdb->last_error;
            
            // Log to debug file with safe data
            $debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
            $safe_query = $last_query ? substr( preg_replace( '/[^\x20-\x7E]/', '?', $last_query ), 0, 200 ) . '...' : 'None';
            $safe_error = $last_error ? preg_replace( '/[^\x20-\x7E]/', '?', $last_error ) : 'None';
            $safe_message = preg_replace( '/[^\x20-\x7E]/', '?', $message );
            
            $log_entry = date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR STATUS UPDATE:\n";
            $log_entry .= "  Idea ID: $idea_id\n";
            $log_entry .= "  Status: $status\n";
            $log_entry .= "  Message: $safe_message\n";
            $log_entry .= "  Update Result: " . ($update_result ? 'SUCCESS' : 'FAILED') . "\n";
            $log_entry .= "  Last SQL Query: $safe_query\n";
            $log_entry .= "  Last SQL Error: $safe_error\n";
            $log_entry .= "  Rows Affected: " . intval( $wpdb->rows_affected ) . "\n\n";
            file_put_contents( $debug_log, $log_entry, FILE_APPEND );
            
            Logger::debug( 'background_processor_status_update', 'Generation status updated in database and transient', [
                'idea_id' => $idea_id,
                'status' => $status,
                'message' => $message,
                'progress' => $status_data['progress'],
                'db_update_success' => $update_result,
                'sql_query' => $last_query,
                'sql_error' => $last_error,
                'rows_affected' => $wpdb->rows_affected
            ] );
        } catch ( \Exception $e ) {
            // Log exception details with safe data
            $debug_log = AI_BLOG_GENERATOR_PLUGIN_DIR . 'debug-transaction.log';
            $safe_exception = preg_replace( '/[^\x20-\x7E]/', '?', $e->getMessage() );
            $safe_trace = substr( preg_replace( '/[^\x20-\x7E]/', '?', $e->getTraceAsString() ), 0, 500 ) . '...';
            $safe_message = preg_replace( '/[^\x20-\x7E]/', '?', $message );
            
            $log_entry = date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR STATUS UPDATE EXCEPTION:\n";
            $log_entry .= "  Idea ID: $idea_id\n";
            $log_entry .= "  Status: $status\n";
            $log_entry .= "  Message: $safe_message\n";
            $log_entry .= "  Exception: $safe_exception\n";
            $log_entry .= "  Trace: $safe_trace\n\n";
            file_put_contents( $debug_log, $log_entry, FILE_APPEND );
            
            Logger::error( 'background_processor_status_update_failed', 'Failed to update generation status in database', [
                'idea_id' => $idea_id,
                'status' => $status,
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ] );
        }
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

    /**
     * Check for and clean up stuck generations.
     *
     * @return array Cleanup results.
     */
    public function cleanup_stuck_generations() {
        $debug_log = __DIR__ . '/../debug-transaction.log';
        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Starting stuck generation cleanup\n", FILE_APPEND );
        
        $cleanup_results = [
            'stuck_ideas_found' => 0,
            'stuck_ideas_cleaned' => 0,
            'expired_locks_cleared' => 0,
            'stale_transients_cleared' => 0
        ];
        
        try {
            // Find ideas stuck in 'generating' status for more than the timeout period
            global $wpdb;
            $ideas_table = $wpdb->prefix . 'ai_blog_ideas';
            
            $stuck_ideas = $wpdb->get_results( $wpdb->prepare( "
                SELECT id, title, generation_started_at, generation_status 
                FROM {$ideas_table} 
                WHERE status = 'generating' 
                AND generation_started_at IS NOT NULL 
                AND generation_started_at < %s
            ", date( 'Y-m-d H:i:s', time() - $this->process_timeout ) ) );
            
            $cleanup_results['stuck_ideas_found'] = count( $stuck_ideas );
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Found {$cleanup_results['stuck_ideas_found']} stuck ideas\n", FILE_APPEND );
            
            foreach ( $stuck_ideas as $idea ) {
                try {
                    // Reset idea status
                    $update_result = $wpdb->update(
                        $ideas_table,
                        [
                            'status' => 'approved',
                            'generation_status' => 'Generation timed out and was reset',
                            'generation_error' => 'Process timeout after ' . $this->process_timeout . ' seconds',
                            'updated_at' => current_time( 'mysql' )
                        ],
                        [ 'id' => $idea->id ],
                        [ '%s', '%s', '%s', '%s' ],
                        [ '%d' ]
                    );
                    
                    if ( $update_result !== false ) {
                        $cleanup_results['stuck_ideas_cleaned']++;
                        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Reset stuck idea {$idea->id}: {$idea->title}\n", FILE_APPEND );
                        
                        // Clear related transients
                        delete_transient( "ai_blog_generation_status_{$idea->id}" );
                        delete_transient( "ai_blog_generation_lock_{$idea->id}" );
                        
                        Logger::warning( 'stuck_generation_cleaned', 'Cleaned up stuck generation', [
                            'idea_id' => $idea->id,
                            'idea_title' => $idea->title,
                            'stuck_since' => $idea->generation_started_at,
                            'last_status' => $idea->generation_status
                        ] );
                    }
                } catch ( \Exception $e ) {
                    file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Error cleaning idea {$idea->id}: " . $e->getMessage() . "\n", FILE_APPEND );
                }
            }
            
            // Clear expired generation locks
            $lock_pattern = 'ai_blog_generation_lock_*';
            $expired_locks = 0;
            
            // Get all generation lock transients (WordPress doesn't have a native way to do this, so we'll check common IDs)
            for ( $i = 1; $i <= 1000; $i++ ) { // Check first 1000 idea IDs
                $lock_key = "ai_blog_generation_lock_{$i}";
                $lock_value = get_transient( $lock_key );
                
                if ( $lock_value ) {
                    $lock_timestamp = is_array( $lock_value ) ? 
                        ( $lock_value['started_timestamp'] ?? time() ) : 
                        $lock_value;
                    
                    $lock_age = time() - $lock_timestamp;
                    
                    if ( $lock_age > $this->process_timeout ) {
                        delete_transient( $lock_key );
                        $expired_locks++;
                        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Cleared expired lock for idea {$i} (age: {$lock_age}s)\n", FILE_APPEND );
                    }
                }
            }
            
            $cleanup_results['expired_locks_cleared'] = $expired_locks;
            
            // Clear stale generation status transients
            $stale_statuses = 0;
            for ( $i = 1; $i <= 1000; $i++ ) { // Check first 1000 idea IDs
                $status_key = "ai_blog_generation_status_{$i}";
                $status_value = get_transient( $status_key );
                
                if ( $status_value && is_array( $status_value ) ) {
                    $status_timestamp = $status_value['updated_timestamp'] ?? time();
                    $status_age = time() - $status_timestamp;
                    
                    if ( $status_age > $this->process_timeout ) {
                        delete_transient( $status_key );
                        $stale_statuses++;
                        file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Cleared stale status for idea {$i} (age: {$status_age}s)\n", FILE_APPEND );
                    }
                }
            }
            
            $cleanup_results['stale_transients_cleared'] = $stale_statuses;
            
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Cleanup completed - " . json_encode( $cleanup_results ) . "\n", FILE_APPEND );
            
            Logger::info( 'stuck_generation_cleanup', 'Completed stuck generation cleanup', $cleanup_results );
            
        } catch ( \Exception $e ) {
            file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - BACKGROUND_PROCESSOR: Cleanup exception: " . $e->getMessage() . "\n", FILE_APPEND );
            
            Logger::error( 'stuck_generation_cleanup_failed', 'Failed to complete stuck generation cleanup', [
                'error' => $e->getMessage(),
                'partial_results' => $cleanup_results
            ] );
        }
        
        return $cleanup_results;
    }
} 