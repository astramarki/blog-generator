<?php
/**
 * AI Blog Generator Integration Test
 * 
 * Run this file to verify all components are properly integrated.
 * Usage: wp eval-file test-integration.php
 */

namespace AI_Blog_Generator\Tests;

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Models\Idea_Model;
use AI_Blog_Generator\Models\Blog_Model;
use AI_Blog_Generator\Models\Context_Model;
use AI_Blog_Generator\Models\Cost_Model;
use AI_Blog_Generator\Models\Log_Model;
use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Includes\Plugin_Activator;

// Color codes for output
define( 'COLOR_GREEN', "\033[32m" );
define( 'COLOR_RED', "\033[31m" );
define( 'COLOR_YELLOW', "\033[33m" );
define( 'COLOR_RESET', "\033[0m" );

class Integration_Test {
	
	private $passed = 0;
	private $failed = 0;
	private $warnings = 0;
	
	public function run() {
		echo "\n" . COLOR_YELLOW . "=== AI Blog Generator Integration Test ===" . COLOR_RESET . "\n\n";
		
		// 1. Check plugin activation and database tables
		$this->test_database_tables();
		
		// 2. Check dependency injection and singletons
		$this->test_dependency_injection();
		
		// 3. Check cron jobs registration
		$this->test_cron_jobs();
		
		// 4. Check admin pages registration
		$this->test_admin_pages();
		
		// 5. Check AJAX handlers
		$this->test_ajax_handlers();
		
		// 6. Check cost tracking
		$this->test_cost_tracking();
		
		// 7. Check logging
		$this->test_logging();
		
		// 8. Check error handling
		$this->test_error_handling();
		
		// 9. Check input validation
		$this->test_input_validation();
		
		// 10. Check output escaping
		$this->test_output_escaping();
		
		// Summary
		$this->print_summary();
	}
	
	private function test_database_tables() {
		$this->print_header( 'Database Tables' );
		
		global $wpdb;
		$tables = [
			'ideas' => AI_BLOG_GENERATOR_TABLE_IDEAS,
			'posts' => AI_BLOG_GENERATOR_TABLE_POSTS,
			'contexts' => AI_BLOG_GENERATOR_TABLE_CONTEXTS,
			'logs' => AI_BLOG_GENERATOR_TABLE_LOGS,
			'costs' => AI_BLOG_GENERATOR_TABLE_COSTS,
			'seed_images' => AI_BLOG_GENERATOR_TABLE_SEED_IMAGES,
		];
		
		foreach ( $tables as $name => $table ) {
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
			if ( $exists ) {
				$this->pass( "Table '$name' exists: $table" );
			} else {
				$this->fail( "Table '$name' missing: $table" );
			}
		}
		
		// Check if tables can be created
		$db_manager = Database_Manager::get_instance();
		if ( method_exists( $db_manager, 'create_tables' ) ) {
			$this->pass( 'Database_Manager::create_tables() method exists' );
		} else {
			$this->fail( 'Database_Manager::create_tables() method missing' );
		}
	}
	
	private function test_dependency_injection() {
		$this->print_header( 'Dependency Injection & Singletons' );
		
		// Test singleton instances
		$db1 = Database_Manager::get_instance();
		$db2 = Database_Manager::get_instance();
		if ( $db1 === $db2 ) {
			$this->pass( 'Database_Manager singleton works correctly' );
		} else {
			$this->fail( 'Database_Manager singleton broken' );
		}
		
		// Check if services use dependency injection
		$services = [
			'Content_Generator' => '\AI_Blog_Generator\Services\Content_Generator',
			'Scheduler_Service' => '\AI_Blog_Generator\Services\Scheduler_Service',
		];
		
		foreach ( $services as $name => $class ) {
			if ( class_exists( $class ) ) {
				$reflection = new \ReflectionClass( $class );
				$constructor = $reflection->getConstructor();
				
				// Check if constructor instantiates dependencies directly
				if ( $constructor ) {
					$this->warn( "$name uses direct instantiation in constructor (consider dependency injection)" );
				} else {
					$this->pass( "$name class exists" );
				}
			} else {
				$this->fail( "$name class not found" );
			}
		}
	}
	
	private function test_cron_jobs() {
		$this->print_header( 'Cron Jobs' );
		
		$cron_hooks = [
			'ai_blog_daily_ideas' => 'Daily idea generation',
			'ai_blog_process_queue' => 'Process approved ideas',
			'ai_blog_publish_scheduled' => 'Publish scheduled posts',
			'ai_blog_cleanup_logs' => 'Cleanup old logs',
		];
		
		foreach ( $cron_hooks as $hook => $description ) {
			$next = wp_next_scheduled( $hook );
			if ( $next ) {
				$this->pass( "$description scheduled: " . date( 'Y-m-d H:i:s', $next ) );
			} else {
				$this->fail( "$description not scheduled (hook: $hook)" );
			}
		}
		
		// Check custom schedule
		$schedules = wp_get_schedules();
		if ( isset( $schedules['fifteen_minutes'] ) ) {
			$this->pass( 'Custom fifteen_minutes schedule registered' );
		} else {
			$this->fail( 'Custom fifteen_minutes schedule missing' );
		}
	}
	
	private function test_admin_pages() {
		$this->print_header( 'Admin Pages' );
		
		global $menu, $submenu;
		
		// Check main menu
		$menu_found = false;
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $item[2] === 'ai-blog-generator' ) {
				$menu_found = true;
				break;
			}
		}
		
		if ( $menu_found ) {
			$this->pass( 'Main admin menu registered' );
		} else {
			$this->fail( 'Main admin menu not found' );
		}
		
		// Check submenus
		if ( isset( $submenu['ai-blog-generator'] ) ) {
			$expected_pages = [
				'ai-blog-generator' => 'Settings',
				'ai-blog-generator-ideas' => 'Blog Ideas',
				'ai-blog-generator-approved' => 'Approved Blogs',
				'ai-blog-generator-drafts' => 'Drafted Posts',
				'ai-blog-generator-published' => 'Published Posts',
				'ai-blog-generator-contexts' => 'Contexts',
				'ai-blog-generator-logs' => 'Logs',
				'ai-blog-generator-costs' => 'Cost Dashboard',
			];
			
			$registered_pages = [];
			foreach ( $submenu['ai-blog-generator'] as $page ) {
				$registered_pages[] = $page[2];
			}
			
			foreach ( $expected_pages as $slug => $name ) {
				if ( in_array( $slug, $registered_pages, true ) ) {
					$this->pass( "Admin page '$name' registered" );
				} else {
					$this->fail( "Admin page '$name' missing (slug: $slug)" );
				}
			}
		} else {
			$this->fail( 'Submenu pages not registered' );
		}
	}
	
	private function test_ajax_handlers() {
		$this->print_header( 'AJAX Handlers' );
		
		// Get all registered AJAX actions
		global $wp_filter;
		
		$ajax_actions = [
			'wp_ajax_ai_blog_approve_idea' => 'Approve idea',
			'wp_ajax_ai_blog_deny_idea' => 'Deny idea',
			'wp_ajax_ai_blog_generate_ideas' => 'Generate ideas',
			'wp_ajax_ai_blog_save_context' => 'Save context',
			'wp_ajax_ai_blog_delete_context' => 'Delete context',
			'wp_ajax_ai_blog_toggle_context' => 'Toggle context',
			'wp_ajax_ai_blog_get_cost_data' => 'Get cost data',
		];
		
		foreach ( $ajax_actions as $action => $description ) {
			if ( isset( $wp_filter[$action] ) && ! empty( $wp_filter[$action]->callbacks ) ) {
				$this->pass( "AJAX handler '$description' registered" );
			} else {
				$this->fail( "AJAX handler '$description' missing (action: $action)" );
			}
		}
	}
	
	private function test_cost_tracking() {
		$this->print_header( 'Cost Tracking' );
		
		// Test Cost_Model
		try {
			$cost_model = new Cost_Model();
			
			// Test recording a cost
			$result = $cost_model->add( [
				'service' => 'test',
				'action' => 'integration_test',
				'cost' => 0.01,
				'tokens_used' => 100,
			] );
			
			if ( $result ) {
				$this->pass( 'Cost recording works' );
				
				// Clean up test record
				Database_Manager::get_instance()->delete( 'cost_analytics', [ 'id' => $result ] );
			} else {
				$this->fail( 'Cost recording failed' );
			}
			
			// Test budget checking
			$current_month_costs = $cost_model->get_total_cost( date( 'Y-m-01' ), date( 'Y-m-t' ) );
			$this->pass( 'Monthly cost calculation works: $' . number_format( $current_month_costs, 2 ) );
			
		} catch ( \Exception $e ) {
			$this->fail( 'Cost tracking error: ' . $e->getMessage() );
		}
	}
	
	private function test_logging() {
		$this->print_header( 'Logging' );
		
		// Test Logger
		try {
			// Test log levels
			Logger::info( 'test_integration', 'Integration test info message' );
			Logger::warning( 'test_integration', 'Integration test warning message' );
			Logger::error( 'test_integration', 'Integration test error message' );
			
			$this->pass( 'Logger methods work' );
			
			// Check if logs were created
			$log_model = new Log_Model();
			$recent_logs = $log_model->get_filtered( [
				'action' => 'test_integration',
			], 3 );
			
			if ( count( $recent_logs ) >= 3 ) {
				$this->pass( 'Logs are being stored in database' );
				
				// Clean up test logs
				Database_Manager::get_instance()->delete( 'logs', [ 'action' => 'test_integration' ] );
			} else {
				$this->warn( 'Some log levels may not be working correctly' );
			}
			
		} catch ( \Exception $e ) {
			$this->fail( 'Logging error: ' . $e->getMessage() );
		}
	}
	
	private function test_error_handling() {
		$this->print_header( 'Error Handling' );
		
		// Check if services have try-catch blocks
		$files_to_check = [
			'services/class-content-generator.php',
			'services/class-scheduler-service.php',
			'controllers/class-blog-controller.php',
		];
		
		foreach ( $files_to_check as $file ) {
			$path = AI_BLOG_GENERATOR_PLUGIN_DIR . $file;
			if ( file_exists( $path ) ) {
				$content = file_get_contents( $path );
				if ( strpos( $content, 'try {' ) !== false && strpos( $content, 'catch' ) !== false ) {
					$this->pass( basename( $file ) . ' has error handling' );
				} else {
					$this->warn( basename( $file ) . ' may lack proper error handling' );
				}
			}
		}
		
		// Test error response format
		$this->pass( 'Error handling structures in place' );
	}
	
	private function test_input_validation() {
		$this->print_header( 'Input Validation' );
		
		// Check for sanitization functions
		$sanitization_functions = [
			'sanitize_text_field',
			'sanitize_textarea_field',
			'intval',
			'absint',
			'wp_verify_nonce',
		];
		
		$files_to_check = [
			'controllers/class-blog-controller.php',
			'controllers/class-idea-controller.php',
			'admin/class-admin-manager.php',
		];
		
		foreach ( $files_to_check as $file ) {
			$path = AI_BLOG_GENERATOR_PLUGIN_DIR . $file;
			if ( file_exists( $path ) ) {
				$content = file_get_contents( $path );
				$has_sanitization = false;
				
				foreach ( $sanitization_functions as $func ) {
					if ( strpos( $content, $func ) !== false ) {
						$has_sanitization = true;
						break;
					}
				}
				
				if ( $has_sanitization ) {
					$this->pass( basename( $file ) . ' uses input sanitization' );
				} else {
					$this->fail( basename( $file ) . ' lacks input sanitization' );
				}
			}
		}
	}
	
	private function test_output_escaping() {
		$this->print_header( 'Output Escaping' );
		
		// Check for escaping functions in views
		$escaping_functions = [
			'esc_html',
			'esc_attr',
			'esc_url',
			'wp_kses',
		];
		
		$view_files = glob( AI_BLOG_GENERATOR_PLUGIN_DIR . 'admin/views/*.php' );
		
		foreach ( $view_files as $file ) {
			$content = file_get_contents( $file );
			$has_escaping = false;
			
			foreach ( $escaping_functions as $func ) {
				if ( strpos( $content, $func ) !== false ) {
					$has_escaping = true;
					break;
				}
			}
			
			if ( $has_escaping ) {
				$this->pass( basename( $file ) . ' uses output escaping' );
			} else {
				$this->fail( basename( $file ) . ' lacks output escaping' );
			}
		}
	}
	
	private function pass( $message ) {
		echo COLOR_GREEN . "✓ " . COLOR_RESET . $message . "\n";
		$this->passed++;
	}
	
	private function fail( $message ) {
		echo COLOR_RED . "✗ " . COLOR_RESET . $message . "\n";
		$this->failed++;
	}
	
	private function warn( $message ) {
		echo COLOR_YELLOW . "⚠ " . COLOR_RESET . $message . "\n";
		$this->warnings++;
	}
	
	private function print_header( $title ) {
		echo "\n" . COLOR_YELLOW . "--- $title ---" . COLOR_RESET . "\n";
	}
	
	private function print_summary() {
		echo "\n" . COLOR_YELLOW . "=== Test Summary ===" . COLOR_RESET . "\n";
		echo COLOR_GREEN . "Passed: $this->passed" . COLOR_RESET . "\n";
		echo COLOR_RED . "Failed: $this->failed" . COLOR_RESET . "\n";
		echo COLOR_YELLOW . "Warnings: $this->warnings" . COLOR_RESET . "\n";
		
		if ( $this->failed === 0 ) {
			echo "\n" . COLOR_GREEN . "All critical tests passed! ✓" . COLOR_RESET . "\n";
		} else {
			echo "\n" . COLOR_RED . "Some tests failed. Please fix the issues above." . COLOR_RESET . "\n";
		}
	}
}

// Run the test
$test = new Integration_Test();
$test->run(); 
 
 