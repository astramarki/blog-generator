<?php
/**
 * Database Manager
 *
 * @package AI_Blog_Generator
 * @subpackage Models
 */

namespace AI_Blog_Generator\Models;

use AI_Blog_Generator\Utilities\Logger;
use AI_Blog_Generator\Utilities\Loggable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Manager Class
 *
 * Central database interaction point for all plugin operations.
 * Implements singleton pattern to ensure consistent database operations.
 *
 * @since 1.0.0
 */
class Database_Manager {

	use Loggable;

	/**
	 * Single instance of the class.
	 *
	 * @var Database_Manager
	 */
	private static $instance = null;

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Transaction flag.
	 *
	 * @var bool
	 */
	private $in_transaction = false;

	/**
	 * Transaction timeout in seconds.
	 *
	 * @var int
	 */
	private $transaction_timeout = 900; // 15 minutes

	/**
	 * Transaction start time.
	 *
	 * @var float
	 */
	private $transaction_start_time = null;

	/**
	 * Get single instance of the class.
	 *
	 * @return Database_Manager
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Create all plugin tables.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_tables() {
		$start_time = $this->start_timer();
		$this->log_function_entry( [ 'tables_to_create' => 12 ] );

		$success = true;
		$tables_created = [];
		$tables_failed = [];

		try {
			$this->log_debug( 'database_init', 'Starting database table creation process' );

			// Ideas table
			$this->log_debug( 'table_creation', 'Creating ideas table', [ 'table' => 'ideas' ] );
			$ideas_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_IDEAS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL,
				description text,
				category_id bigint(20) unsigned,
				status enum('pending', 'approved', 'denied', 'generated') DEFAULT 'pending',
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_status (status),
				KEY idx_category (category_id),
				KEY idx_created (created_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $ideas_table_sql ) ) {
				$tables_failed[] = 'ideas';
				$this->log_error( 'table_creation_failed', 'Failed to create ideas table', [ 
					'table' => 'ideas',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'ideas';
				$this->log_debug( 'table_creation_success', 'Ideas table created successfully' );
			}

			// Generated posts table  
			$this->log_debug( 'table_creation', 'Creating generated posts table', [ 'table' => 'generated_posts' ] );
			$posts_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_POSTS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				idea_id bigint(20) unsigned,
				post_id bigint(20) unsigned,
				title varchar(255),
				content longtext,
				excerpt text,
				featured_image_id bigint(20) unsigned,
				seo_title varchar(255),
				seo_description text,
				cost decimal(10,4) DEFAULT 0.0000,
				status enum('draft', 'scheduled', 'published') DEFAULT 'draft',
				scheduled_time datetime,
				published_at datetime,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_idea (idea_id),
				KEY idx_post (post_id),
				KEY idx_status (status),
				KEY idx_scheduled (scheduled_time),
				KEY idx_published (published_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $posts_table_sql ) ) {
				$tables_failed[] = 'generated_posts';
				$this->log_error( 'table_creation_failed', 'Failed to create generated posts table', [ 
					'table' => 'generated_posts',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'generated_posts';
				$this->log_debug( 'table_creation_success', 'Generated posts table created successfully' );
			}

			// Contexts table
			$this->log_debug( 'table_creation', 'Creating contexts table', [ 'table' => 'contexts' ] );
			$contexts_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				type varchar(50) NOT NULL,
				content longtext NOT NULL,
				seed_image_id bigint(20) unsigned NULL,
				active tinyint(1) DEFAULT 1,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_type (type),
				KEY idx_active (active),
				KEY idx_seed_image (seed_image_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $contexts_table_sql ) ) {
				$tables_failed[] = 'contexts';
				$this->log_error( 'table_creation_failed', 'Failed to create contexts table', [ 
					'table' => 'contexts',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'contexts';
				$this->log_debug( 'table_creation_success', 'Contexts table created successfully' );
			}

			// Logs table
			$this->log_debug( 'table_creation', 'Creating logs table', [ 'table' => 'logs' ] );
			$logs_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_LOGS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				action varchar(100) NOT NULL,
				message text NOT NULL,
				level enum('info', 'warning', 'error') DEFAULT 'info',
				context longtext,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_action (action),
				KEY idx_level (level),
				KEY idx_created (created_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $logs_table_sql ) ) {
				$tables_failed[] = 'logs';
				$this->log_error( 'table_creation_failed', 'Failed to create logs table', [ 
					'table' => 'logs',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'logs';
				$this->log_debug( 'table_creation_success', 'Logs table created successfully' );
			}

			// Cost analytics table
			$this->log_debug( 'table_creation', 'Creating cost analytics table', [ 'table' => 'cost_analytics' ] );
			$costs_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_COSTS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				service varchar(50) NOT NULL,
				action varchar(100) NOT NULL,
				cost decimal(10,4) NOT NULL DEFAULT 0.0000,
				tokens_used int unsigned DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_service (service),
				KEY idx_action (action),
				KEY idx_created (created_at),
				KEY idx_cost (cost)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $costs_table_sql ) ) {
				$tables_failed[] = 'cost_analytics';
				$this->log_error( 'table_creation_failed', 'Failed to create cost analytics table', [ 
					'table' => 'cost_analytics',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'cost_analytics';
				$this->log_debug( 'table_creation_success', 'Cost analytics table created successfully' );
			}

			// Seed images table
			$this->log_debug( 'table_creation', 'Creating seed images table', [ 'table' => 'seed_images' ] );
			$seed_images_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_SEED_IMAGES . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_name varchar(100) NOT NULL,
				image_url varchar(500) NOT NULL,
				context_id bigint(20) unsigned,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_context (context_id),
				KEY idx_product (product_name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $seed_images_table_sql ) ) {
				$tables_failed[] = 'seed_images';
				$this->log_error( 'table_creation_failed', 'Failed to create seed images table', [ 
					'table' => 'seed_images',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'seed_images';
				$this->log_debug( 'table_creation_success', 'Seed images table created successfully' );
			}

			// Personas table
			$this->log_debug( 'table_creation', 'Creating personas table', [ 'table' => 'personas' ] );
			$personas_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_PERSONAS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL,
				bio text NOT NULL,
				expertise text,
				writing_style text,
				tone varchar(50) DEFAULT 'professional',
				active tinyint(1) DEFAULT 1,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_active (active),
				KEY idx_name (name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $personas_table_sql ) ) {
				$tables_failed[] = 'personas';
				$this->log_error( 'table_creation_failed', 'Failed to create personas table', [ 
					'table' => 'personas',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'personas';
				$this->log_debug( 'table_creation_success', 'Personas table created successfully' );
			}

			// Products table
			$this->log_debug( 'table_creation', 'Creating products table', [ 'table' => 'products' ] );
			$products_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_PRODUCTS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				description text,
				ideal_uses text,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_name (name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $products_table_sql ) ) {
				$tables_failed[] = 'products';
				$this->log_error( 'table_creation_failed', 'Failed to create products table', [ 
					'table' => 'products',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'products';
				$this->log_debug( 'table_creation_success', 'Products table created successfully' );
			}

			// Product images table
			$this->log_debug( 'table_creation', 'Creating product images table', [ 'table' => 'product_images' ] );
			$product_images_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL,
				image_url varchar(500) NOT NULL,
				is_primary tinyint(1) DEFAULT 0,
				display_order int DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_product (product_id),
				KEY idx_primary (is_primary),
				KEY idx_order (display_order)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $product_images_table_sql ) ) {
				$tables_failed[] = 'product_images';
				$this->log_error( 'table_creation_failed', 'Failed to create product images table', [ 
					'table' => 'product_images',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'product_images';
				$this->log_debug( 'table_creation_success', 'Product images table created successfully' );
			}

			// Product links table
			$this->log_debug( 'table_creation', 'Creating product links table', [ 'table' => 'product_links' ] );
			$product_links_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				link_type enum('product_page','purchase','documentation','other') DEFAULT 'other',
				link_text varchar(255) NOT NULL,
				link_url varchar(500) NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_product (product_id),
				KEY idx_type (link_type)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $product_links_table_sql ) ) {
				$tables_failed[] = 'product_links';
				$this->log_error( 'table_creation_failed', 'Failed to create product links table', [ 
					'table' => 'product_links',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'product_links';
				$this->log_debug( 'table_creation_success', 'Product links table created successfully' );
			}

			// Blog idea categories connector table
			$this->log_debug( 'table_creation', 'Creating blog idea categories table', [ 'table' => 'blog_idea_categories' ] );
			$idea_categories_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_IDEA_CATEGORIES . " (
				blog_idea_id int(11) DEFAULT NULL,
				category_id int(11) DEFAULT NULL,
				KEY blog_idea_id (blog_idea_id),
				KEY category_id (category_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $idea_categories_table_sql ) ) {
				$tables_failed[] = 'blog_idea_categories';
				$this->log_error( 'table_creation_failed', 'Failed to create blog idea categories table', [ 
					'table' => 'blog_idea_categories',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'blog_idea_categories';
				$this->log_debug( 'table_creation_success', 'Blog idea categories table created successfully' );
			}

			// Product seed images table
			$this->log_debug( 'table_creation', 'Creating product seed images table', [ 'table' => 'product_seed_images' ] );
			$product_seed_images_table_sql = "CREATE TABLE " . $this->wpdb->prefix . "ai_blog_generator_product_seed_images (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL,
				image_url varchar(500) NOT NULL,
				display_order int DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_product (product_id),
				KEY idx_order (display_order)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $product_seed_images_table_sql ) ) {
				$tables_failed[] = 'product_seed_images';
				$this->log_error( 'table_creation_failed', 'Failed to create product seed images table', [ 
					'table' => 'product_seed_images',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'product_seed_images';
				$this->log_debug( 'table_creation_success', 'Product seed images table created successfully' );
			}

			// Brand features table
			$this->log_debug( 'table_creation', 'Creating brand features table', [ 'table' => 'brand_features' ] );
			$brand_features_table_sql = "CREATE TABLE " . $this->wpdb->prefix . "ai_blog_brand_features (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				description text,
				category enum('informational_page','document','image','video') DEFAULT 'informational_page',
				url varchar(500) NOT NULL,
				active tinyint(1) DEFAULT 1,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_active (active),
				KEY idx_category (category),
				KEY idx_name (name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $brand_features_table_sql ) ) {
				$tables_failed[] = 'brand_features';
				$this->log_error( 'table_creation_failed', 'Failed to create brand features table', [ 
					'table' => 'brand_features',
					'error' => $this->wpdb->last_error 
				] );
				$success = false;
			} else {
				$tables_created[] = 'brand_features';
				$this->log_debug( 'table_creation_success', 'Brand features table created successfully' );
			}

			// Run schema updates for any missing columns
			$this->log_debug( 'schema_update', 'Running schema updates for missing columns' );
			if ( ! $this->update_table_schemas() ) {
				$this->log_warning( 'schema_update_failed', 'Some schema updates failed but continuing' );
			}

			$final_context = [
				'tables_created' => $tables_created,
				'tables_failed' => $tables_failed,
				'total_success' => count( $tables_created ),
				'total_failed' => count( $tables_failed ),
				'overall_success' => $success
			];

			if ( $success ) {
				$this->log_info( 'database_init_complete', 'All database tables created successfully', $final_context );
			} else {
				$this->log_error( 'database_init_partial', 'Database initialization completed with errors', $final_context );
			}

		} catch ( \Exception $e ) {
			$success = false;
			$this->log_exception( 'database_init_exception', $e, [
				'tables_created' => $tables_created,
				'tables_failed' => $tables_failed
			] );
		}

		$this->end_timer( $start_time, 'database_table_creation', $final_context ?? [] );
		$this->log_function_exit( $success );

		return $success;
	}

	/**
	 * Drop plugin tables.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop_tables() {
		$tables = [
			AI_BLOG_GENERATOR_TABLE_IDEAS,
			AI_BLOG_GENERATOR_TABLE_POSTS,
			AI_BLOG_GENERATOR_TABLE_CONTEXTS,
			AI_BLOG_GENERATOR_TABLE_PERSONAS,
			AI_BLOG_GENERATOR_TABLE_LOGS,
			AI_BLOG_GENERATOR_TABLE_COSTS,
			AI_BLOG_GENERATOR_TABLE_SEED_IMAGES,
			AI_BLOG_GENERATOR_TABLE_IDEA_CATEGORIES,
			AI_BLOG_GENERATOR_TABLE_PRODUCTS,
			AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES,
			AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS,
			$this->wpdb->prefix . 'ai_blog_generator_product_seed_images',
			$this->wpdb->prefix . 'ai_blog_brand_features',
		];

		$success = true;
		foreach ( $tables as $table ) {
			$sql = "DROP TABLE IF EXISTS $table";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'table_drop', 'Failed to drop table', [ 'table' => $table, 'error' => $this->wpdb->last_error ] );
			}
		}

		if ( $success ) {
			Logger::info( 'table_drop', 'All plugin tables dropped successfully' );
		}

		return $success;
	}

	/**
	 * Update table schemas to add missing columns.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function update_table_schemas() {
		$success = true;

		// Check if seed_image_id column exists in contexts table
		$table_name = AI_BLOG_GENERATOR_TABLE_CONTEXTS;
		$column_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $table_name LIKE %s",
			'seed_image_id'
		) );

		if ( empty( $column_exists ) ) {
			// Add seed_image_id column to contexts table
			$sql = "ALTER TABLE $table_name ADD COLUMN seed_image_id BIGINT(20) UNSIGNED NULL AFTER content";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add seed_image_id column to contexts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added seed_image_id column to contexts table' );
			}
		}

		// Check if priority column exists in contexts table
		$priority_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $table_name LIKE %s",
			'priority'
		) );

		if ( empty( $priority_exists ) ) {
			// Add priority column to contexts table
			$sql = "ALTER TABLE $table_name ADD COLUMN priority INT(11) DEFAULT 50 AFTER active";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add priority column to contexts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added priority column to contexts table' );
			}
		}

		// Check if usage_flags column exists in contexts table
		$usage_flags_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $table_name LIKE %s",
			'usage_flags'
		) );

		if ( empty( $usage_flags_exists ) ) {
			// Add usage_flags column to contexts table
			$sql = "ALTER TABLE $table_name ADD COLUMN usage_flags VARCHAR(255) DEFAULT 'ideas,content,images' AFTER priority";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add usage_flags column to contexts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added usage_flags column to contexts table' );
			}
		}

		// Check if description column exists in contexts table
		$description_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $table_name LIKE %s",
			'description'
		) );

		if ( empty( $description_exists ) ) {
			// Add description column to contexts table
			$sql = "ALTER TABLE $table_name ADD COLUMN description TEXT NULL AFTER name";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add description column to contexts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added description column to contexts table' );
			}
		}

		// Check if persona_id column exists in ideas table
		$ideas_table = AI_BLOG_GENERATOR_TABLE_IDEAS;
		$persona_id_in_ideas = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $ideas_table LIKE %s",
			'persona_id'
		) );

		if ( empty( $persona_id_in_ideas ) ) {
			// Add persona_id column to ideas table
			$sql = "ALTER TABLE $ideas_table ADD COLUMN persona_id BIGINT(20) UNSIGNED NULL AFTER category_id";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add persona_id column to ideas table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added persona_id column to ideas table' );
				// Add index for persona_id
				$sql = "ALTER TABLE $ideas_table ADD INDEX idx_persona (persona_id)";
				$this->execute_query( $sql );
			}
		}

		// Check if persona_id column exists in generated posts table
		$posts_table = AI_BLOG_GENERATOR_TABLE_POSTS;
		$persona_id_in_posts = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $posts_table LIKE %s",
			'persona_id'
		) );

		if ( empty( $persona_id_in_posts ) ) {
			// Add persona_id column to generated posts table
			$sql = "ALTER TABLE $posts_table ADD COLUMN persona_id BIGINT(20) UNSIGNED NULL AFTER idea_id";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add persona_id column to generated posts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added persona_id column to generated posts table' );
				// Add index for persona_id
				$sql = "ALTER TABLE $posts_table ADD INDEX idx_persona (persona_id)";
				$this->execute_query( $sql );
			}
		}

		// Check if keywords column exists in ideas table and remove it (keywords should only be in blog posts, not ideas)
		$keywords_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $ideas_table LIKE %s",
			'keywords'
		) );

		if ( ! empty( $keywords_exists ) ) {
			// Remove keywords column from ideas table
			$sql = "ALTER TABLE $ideas_table DROP COLUMN keywords";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to remove keywords column from ideas table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Removed keywords column from ideas table - keywords will be generated during blog creation instead' );
			}
		}

		// Check if always_include_content column exists in contexts table
		$always_content_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $table_name LIKE %s",
			'always_include_content'
		) );

		if ( empty( $always_content_exists ) ) {
			// Add always_include_content column to contexts table
			$sql = "ALTER TABLE $table_name ADD COLUMN always_include_content TINYINT(1) DEFAULT 0 AFTER usage_flags";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add always_include_content column to contexts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added always_include_content column to contexts table' );
			}
		}

		// Check if always_include_images column exists in contexts table
		$always_images_exists = $this->wpdb->get_results( $this->wpdb->prepare(
			"SHOW COLUMNS FROM $table_name LIKE %s",
			'always_include_images'
		) );

		if ( empty( $always_images_exists ) ) {
			// Add always_include_images column to contexts table
			$sql = "ALTER TABLE $table_name ADD COLUMN always_include_images TINYINT(1) DEFAULT 0 AFTER always_include_content";
			if ( ! $this->execute_query( $sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to add always_include_images column to contexts table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Added always_include_images column to contexts table' );
			}
		}

		// Check if Blog Ideas V2 categories table exists and create it if missing
		$categories_table_exists = $this->wpdb->get_var( "SHOW TABLES LIKE '" . AI_BLOG_GENERATOR_TABLE_IDEA_CATEGORIES . "'" );
		
		if ( ! $categories_table_exists ) {
			$idea_categories_table_sql = "CREATE TABLE " . AI_BLOG_GENERATOR_TABLE_IDEA_CATEGORIES . " (
				blog_idea_id int(11) DEFAULT NULL,
				category_id int(11) DEFAULT NULL,
				KEY blog_idea_id (blog_idea_id),
				KEY category_id (category_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			if ( ! $this->execute_query( $idea_categories_table_sql ) ) {
				$success = false;
				Logger::error( 'schema_update', 'Failed to create blog idea categories table', [ 'error' => $this->wpdb->last_error ] );
			} else {
				Logger::info( 'schema_update', 'Created blog idea categories table for Blog Ideas V2 functionality' );
			}
		}

		return $success;
	}

	/**
	 * Run database schema updates manually.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function run_schema_updates() {
		return $this->update_table_schemas();
	}

	/**
	 * Insert data into table.
	 *
	 * @param string $table Table name without prefix.
	 * @param array  $data  Data to insert.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function insert( $table, $data ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [ 'table' => $table, 'data_keys' => array_keys( $data ) ] );

		$table_name = $this->get_table_name( $table );
		
		if ( ! $table_name ) {
			$this->log_error( 'database_insert_invalid_table', 'Invalid table name provided', [ 
				'table' => $table,
				'valid_tables' => array_keys( $this->get_table_map() )
			] );
			$this->log_function_exit( false );
			return false;
		}

		$this->log_database( 'insert', $table, $data );

		// Retry-aware insert – handles transient deadlocks / lock waits when multiple generators run in parallel.
		$max_attempts = 3;
		$attempt      = 0;
		$result       = false;

		do {
			$attempt++;
		$result = $this->wpdb->insert( $table_name, $data );

			if ( false !== $result ) {
				// Success, break out of retry loop.
				break;
			}

			// If the error is a lock wait timeout or deadlock we retry; otherwise fail immediately.
			$last_error = strtolower( $this->wpdb->last_error );
			if ( strpos( $last_error, 'lock wait timeout' ) === false && strpos( $last_error, 'deadlock found' ) === false ) {
				break; // Non-retryable error.
			}

			// Log the retry attempt.
			$this->log_warning( 'database_insert_retry', 'Retrying insert after lock wait/deadlock', [
				'table'        => $table,
				'attempt'      => $attempt,
				'max_attempts' => $max_attempts,
				'error'        => $this->wpdb->last_error,
			] );

			// Exponential back-off: 0.1s, 0.2s …
			usleep( 100000 * $attempt );

		} while ( $attempt < $max_attempts );
		
		if ( false === $result ) {
			$this->log_error( 'database_insert_failed', 'Insert operation failed', [
				'table'      => $table,
				'error'      => $this->wpdb->last_error,
				'data_size'  => count( $data ),
				'attempts'   => $attempt,
			] );
			$this->log_function_exit( false );
			return false;
		}

		$insert_id = $this->wpdb->insert_id;
		$this->log_debug( 'database_insert_success', 'Record inserted successfully', [
			'table' => $table,
			'insert_id' => $insert_id,
			'affected_rows' => $result
		] );

		$this->end_timer( $start_time, 'database_insert', [ 'table' => $table, 'insert_id' => $insert_id ] );
		$this->log_function_exit( $insert_id );

		return $insert_id;
	}

	/**
	 * Update data in table.
	 *
	 * @param string $table Table name without prefix.
	 * @param array  $data  Data to update.
	 * @param array  $where Where conditions.
	 * @return int|false Number of rows updated on success, false on failure.
	 */
	public function update( $table, $data, $where ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [ 
			'table' => $table, 
			'data_keys' => array_keys( $data ),
			'where_keys' => array_keys( $where )
		] );

		$table_name = $this->get_table_name( $table );
		
		if ( ! $table_name ) {
			$this->log_error( 'database_update_invalid_table', 'Invalid table name provided', [ 
				'table' => $table,
				'valid_tables' => array_keys( $this->get_table_map() )
			] );
			$this->log_function_exit( false );
			return false;
		}

		$this->log_database( 'update', $table, [ 'data' => $data, 'where' => $where ] );
		// Retry-aware update to mitigate deadlocks / lock waits when multiple generators run.
		$max_attempts = 3;
		$attempt      = 0;
		$result       = false;

		do {
			$attempt++;
		$result = $this->wpdb->update( $table_name, $data, $where );

			if ( false !== $result ) {
				break; // success
			}

			$last_error = strtolower( $this->wpdb->last_error );
			if ( strpos( $last_error, 'lock wait timeout' ) === false && strpos( $last_error, 'deadlock found' ) === false ) {
				break; // non-retryable error
			}

			$this->log_warning( 'database_update_retry', 'Retrying update after lock wait/deadlock', [
				'table'        => $table,
				'attempt'      => $attempt,
				'max_attempts' => $max_attempts,
				'error'        => $this->wpdb->last_error,
			] );

			usleep( 100000 * $attempt );

		} while ( $attempt < $max_attempts );
		
		if ( false === $result ) {
			$this->log_error( 'database_update_failed', 'Update operation failed', [
				'table' => $table,
				'error' => $this->wpdb->last_error,
				'data_size' => count( $data ),
				'where_conditions' => count( $where )
			] );
			$this->log_function_exit( false );
			return false;
		}

		$this->log_debug( 'database_update_success', 'Records updated successfully', [
			'table' => $table,
			'rows_affected' => $result,
			'data_fields' => count( $data )
		] );

		$this->end_timer( $start_time, 'database_update', [ 'table' => $table, 'rows_affected' => $result ] );
		$this->log_function_exit( $result );

		return $result;
	}

	/**
	 * Delete data from table.
	 *
	 * @param string $table Table name without prefix.
	 * @param array  $where Where conditions.
	 * @return int|false Number of rows deleted on success, false on failure.
	 */
	public function delete( $table, $where ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [ 
			'table' => $table,
			'where_keys' => array_keys( $where )
		] );

		$table_name = $this->get_table_name( $table );
		
		if ( ! $table_name ) {
			$this->log_error( 'database_delete_invalid_table', 'Invalid table name provided', [ 
				'table' => $table,
				'valid_tables' => array_keys( $this->get_table_map() )
			] );
			$this->log_function_exit( false );
			return false;
		}

		$this->log_database( 'delete', $table, [ 'where' => $where ] );

		$result = $this->wpdb->delete( $table_name, $where );
		
		if ( false === $result ) {
			$this->log_error( 'database_delete_failed', 'Delete operation failed', [
				'table' => $table,
				'error' => $this->wpdb->last_error,
				'where_conditions' => count( $where )
			] );
			$this->log_function_exit( false );
			return false;
		}

		$this->log_debug( 'database_delete_success', 'Records deleted successfully', [
			'table' => $table,
			'rows_deleted' => $result
		] );

		$this->end_timer( $start_time, 'database_delete', [ 'table' => $table, 'rows_deleted' => $result ] );
		$this->log_function_exit( $result );

		return $result;
	}

	/**
	 * Get single row from table.
	 *
	 * @param string $table  Table name without prefix.
	 * @param array  $where  Where conditions.
	 * @param string $fields Fields to select.
	 * @return object|null Row object on success, null on failure.
	 */
	public function get( $table, $where = [], $fields = '*' ) {
		$start_time = $this->start_timer();
		$this->log_function_entry( [ 
			'table' => $table,
			'where_keys' => array_keys( $where ),
			'fields' => $fields
		] );

		$table_name = $this->get_table_name( $table );
		
		if ( ! $table_name ) {
			$this->log_error( 'database_get_invalid_table', 'Invalid table name provided', [ 
				'table' => $table,
				'valid_tables' => array_keys( $this->get_table_map() )
			] );
			$this->log_function_exit( null );
			return null;
		}

		$sql = "SELECT $fields FROM $table_name";
		$where_clause = $this->build_where_clause( $where );
		
		if ( $where_clause ) {
			$sql .= " WHERE $where_clause";
		}

		$sql .= " LIMIT 1";

		$this->log_info( 'database_get_query', 'Executing get query', [
			'table' => $table,
			'where_conditions' => count( $where ),
			'sql_length' => strlen( $sql ),
			'raw_sql' => $sql,
			'where_clause' => $where_clause,
			'fields' => $fields
		] );

		$result = $this->wpdb->get_row( $sql );
		
		if ( $this->wpdb->last_error ) {
			$this->log_error( 'database_get_failed', 'Get query failed', [
				'table' => $table,
				'error' => $this->wpdb->last_error,
				'sql' => $sql
			] );
			$this->log_function_exit( null );
			return null;
		}

		$found_record = ! is_null( $result );
		
		$this->log_info( 'database_get_success', 'Get query completed', [
			'table' => $table,
			'record_found' => $found_record,
			'raw_sql' => $sql,
			'result_type' => gettype( $result ),
			'result_data' => $found_record ? 'OBJECT[' . implode(', ', array_keys((array)$result)) . ']' : 'NULL'
		] );

		$this->end_timer( $start_time, 'database_get', [ 'table' => $table, 'found' => $found_record ] );
		$this->log_function_exit( $found_record ? 'record_found' : 'no_record' );

		return $result;
	}

	/**
	 * Get multiple rows from table.
	 *
	 * @param string $table    Table name without prefix.
	 * @param array  $where    Where conditions.
	 * @param string $order_by Order by clause.
	 * @param string $limit    Limit clause.
	 * @return array Array of row objects.
	 */
	public function get_all( $table, $where = [], $order_by = '', $limit = '' ) {
		$table_name = $this->get_table_name( $table );
		
		if ( ! $table_name ) {
			Logger::error( 'database_get_all', 'Invalid table name', [ 'table' => $table ] );
			return [];
		}

		$sql = "SELECT * FROM $table_name";
		$where_clause = $this->build_where_clause( $where );
		
		if ( $where_clause ) {
			$sql .= " WHERE $where_clause";
		}

		if ( $order_by ) {
			$sql .= " ORDER BY $order_by";
		}

		if ( $limit ) {
			$sql .= " LIMIT $limit";
		}

		$results = $this->wpdb->get_results( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_get_all', 'Query failed', [
				'table' => $table,
				'error' => $this->wpdb->last_error,
			] );
			return [];
		}

		return $results;
	}

	/**
	 * Get pending ideas.
	 *
	 * @param int $limit Number of ideas to retrieve.
	 * @return array Array of idea objects.
	 */
	public function get_pending_ideas( $limit = 5 ) {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_IDEAS . " 
			WHERE status = 'pending' 
			ORDER BY created_at DESC 
			LIMIT %d",
			$limit
		);

		$results = $this->wpdb->get_results( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get pending ideas', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		return $results;
	}

	/**
	 * Get denied idea titles.
	 *
	 * @return array Array of denied titles.
	 */
	public function get_denied_idea_titles() {
		$sql = "SELECT title FROM " . AI_BLOG_GENERATOR_TABLE_IDEAS . " WHERE status = 'denied'";
		
		$results = $this->wpdb->get_col( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get denied titles', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		return $results;
	}

	/**
	 * Get existing post titles.
	 *
	 * @return array Array of existing post titles.
	 */
	public function get_existing_post_titles() {
		$sql = $this->wpdb->prepare(
			"SELECT post_title FROM {$this->wpdb->posts} 
			WHERE post_type = %s 
			AND post_status IN ('publish', 'draft', 'pending', 'future')",
			'post'
		);

		$results = $this->wpdb->get_col( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get existing post titles', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		return $results;
	}

	/**
	 * Get category post counts.
	 *
	 * @return array Associative array of category IDs and post counts.
	 */
	public function get_category_post_counts() {
		$sql = "SELECT t.term_id, COUNT(tr.object_id) as count 
				FROM {$this->wpdb->terms} t 
				INNER JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
				LEFT JOIN {$this->wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id 
				WHERE tt.taxonomy = 'category' 
				GROUP BY t.term_id";

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get category counts', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row['term_id'] ] = (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Get posts by status.
	 *
	 * @param string $status Post status.
	 * @return array Array of post objects.
	 */
	public function get_posts_by_status( $status ) {
		$sql = $this->wpdb->prepare(
			"SELECT gp.*, p.post_title, p.post_date 
			FROM " . AI_BLOG_GENERATOR_TABLE_POSTS . " gp 
			LEFT JOIN {$this->wpdb->posts} p ON gp.post_id = p.ID 
			WHERE gp.status = %s 
			ORDER BY gp.created_at DESC",
			$status
		);

		$results = $this->wpdb->get_results( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get posts by status', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		return $results;
	}

	/**
	 * Get costs by date range.
	 *
	 * @param string $start_date Start date (Y-m-d format).
	 * @param string $end_date   End date (Y-m-d format).
	 * @return array Array of cost records.
	 */
	public function get_costs_by_date_range( $start_date, $end_date ) {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_COSTS . " 
			WHERE DATE(created_at) BETWEEN %s AND %s 
			ORDER BY created_at DESC",
			$start_date,
			$end_date
		);

		$results = $this->wpdb->get_results( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get costs by date range', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		return $results;
	}

	/**
	 * Get active contexts.
	 *
	 * @param string|null $type Context type filter.
	 * @return array Array of context objects.
	 */
	public function get_active_contexts( $type = null ) {
		$sql = "SELECT * FROM " . AI_BLOG_GENERATOR_TABLE_CONTEXTS . " WHERE active = 1";
		
		if ( $type ) {
			$sql = $this->wpdb->prepare( $sql . " AND type = %s", $type );
		}

		$sql .= " ORDER BY type, name";

		$results = $this->wpdb->get_results( $sql );
		
		if ( $this->wpdb->last_error ) {
			Logger::error( 'database_query', 'Failed to get active contexts', [ 'error' => $this->wpdb->last_error ] );
			return [];
		}

		return $results;
	}

	/**
	 * Start database transaction with timeout protection.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function start_transaction() {
		try {
			// Log entry into start_transaction method using multiple methods
			error_log( 'AI_BLOG_DEBUG: Entering start_transaction method' );
			
			// Also write to a direct log file
			$debug_log = __DIR__ . '/../debug-transaction.log';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - Entering start_transaction method\n", FILE_APPEND );
			
			// Check for stale transactions first
			$this->cleanup_stale_transactions();
			
			// Check transaction state
			error_log( 'AI_BLOG_DEBUG: Checking in_transaction state: ' . ( $this->in_transaction ? 'true' : 'false' ) );
			
			if ( $this->in_transaction ) {
				// Check if transaction has timed out
				if ( $this->transaction_start_time && ( microtime( true ) - $this->transaction_start_time ) > $this->transaction_timeout ) {
					error_log( 'AI_BLOG_DEBUG: Transaction timeout detected, forcing rollback' );
					$this->force_rollback();
				} else {
					error_log( 'AI_BLOG_DEBUG: Transaction already in progress, logging warning' );
					Logger::warning( 'database_transaction', 'Transaction already in progress' );
					error_log( 'AI_BLOG_DEBUG: Warning logged successfully, returning false' );
					return false;
				}
			}

			// Check wpdb object
			error_log( 'AI_BLOG_DEBUG: Checking wpdb object: ' . ( isset( $this->wpdb ) ? 'exists' : 'missing' ) );
			if ( ! $this->wpdb ) {
				error_log( 'AI_BLOG_DEBUG: wpdb object is missing!' );
				return false;
			}

			// Check MySQL connection status
			error_log( 'AI_BLOG_DEBUG: Checking MySQL connection status' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - Checking MySQL connection status\n", FILE_APPEND );
			
			$connection_check = $this->wpdb->query( 'SELECT 1' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - Connection check result: " . ($connection_check !== false ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND );
			error_log( 'AI_BLOG_DEBUG: Connection check result: ' . ($connection_check !== false ? 'SUCCESS' : 'FAILED') );
			
			if ( false === $connection_check ) {
				error_log( 'AI_BLOG_DEBUG: MySQL connection check failed: ' . $this->wpdb->last_error );
				return false;
			}

			// Check if we're already in a transaction (MySQL level)
			error_log( 'AI_BLOG_DEBUG: Checking MySQL autocommit status' );
			$autocommit_result = $this->wpdb->get_var( 'SELECT @@autocommit' );
			error_log( 'AI_BLOG_DEBUG: MySQL autocommit status: ' . ($autocommit_result !== false ? 'SUCCESS' : 'FAILED') );

			error_log( 'AI_BLOG_DEBUG: About to execute START TRANSACTION query' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - About to execute START TRANSACTION query\n", FILE_APPEND );
			
			$start_time = microtime( true );
			$result = $this->wpdb->query( 'START TRANSACTION' );
			$end_time = microtime( true );
			$query_duration = $end_time - $start_time;
			
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - START TRANSACTION completed with result: " . ($result !== false ? 'SUCCESS' : 'FAILED') . " in {$query_duration} seconds\n", FILE_APPEND );
			error_log( 'AI_BLOG_DEBUG: START TRANSACTION query completed with result: ' . ($result !== false ? 'SUCCESS' : 'FAILED') . ' in ' . $query_duration . ' seconds' );
			
			if ( false === $result ) {
				error_log( 'AI_BLOG_DEBUG: Transaction failed, logging error. wpdb last_error: ' . $this->wpdb->last_error );
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - Transaction failed with error: " . $this->wpdb->last_error . "\n", FILE_APPEND );
				Logger::error( 'database_transaction', 'Failed to start transaction', [ 'error' => $this->wpdb->last_error ] );
				error_log( 'AI_BLOG_DEBUG: Error logged successfully, returning false' );
				return false;
			}

			error_log( 'AI_BLOG_DEBUG: Setting in_transaction to true' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - Setting in_transaction to true\n", FILE_APPEND );
			$this->in_transaction = true;
			$this->transaction_start_time = microtime( true );
			
			// Register shutdown function to handle unexpected termination
			register_shutdown_function( [ $this, 'emergency_transaction_cleanup' ] );
			
			error_log( 'AI_BLOG_DEBUG: start_transaction completed successfully, returning true' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - start_transaction completed successfully, returning true\n", FILE_APPEND );
			return true;
			
		} catch ( \Exception $e ) {
			error_log( 'AI_BLOG_DEBUG: Exception in start_transaction: ' . $e->getMessage() );
			error_log( 'AI_BLOG_DEBUG: Exception trace: ' . $e->getTraceAsString() );
			return false;
		} catch ( \Error $e ) {
			error_log( 'AI_BLOG_DEBUG: Fatal error in start_transaction: ' . $e->getMessage() );
			error_log( 'AI_BLOG_DEBUG: Fatal error trace: ' . $e->getTraceAsString() );
			return false;
		}
	}

	/**
	 * Commit database transaction with timeout check.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function commit() {
		$debug_log = __DIR__ . '/../debug-transaction.log';
		
		if ( ! $this->in_transaction ) {
			Logger::warning( 'database_transaction', 'No transaction to commit' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - COMMIT: No transaction to commit\n", FILE_APPEND );
			return false;
		}

		// Check for transaction timeout
		if ( $this->transaction_start_time && ( microtime( true ) - $this->transaction_start_time ) > $this->transaction_timeout ) {
			error_log( 'AI_BLOG_DEBUG: Transaction timeout during commit, forcing rollback' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - COMMIT: Transaction timeout detected, forcing rollback\n", FILE_APPEND );
			return $this->force_rollback();
		}

		$start_time = microtime( true );
		$result = $this->wpdb->query( 'COMMIT' );
		$duration = microtime( true ) - $start_time;
		
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - COMMIT executed in {$duration} seconds, result: " . ($result !== false ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND );
		
		if ( false === $result ) {
			Logger::error( 'database_transaction', 'Failed to commit transaction', [ 'error' => $this->wpdb->last_error ] );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - COMMIT failed with error: " . $this->wpdb->last_error . "\n", FILE_APPEND );
			return false;
		}

		$this->in_transaction = false;
		$this->transaction_start_time = null;
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - COMMIT successful, transaction flags cleared\n", FILE_APPEND );
		return true;
	}

	/**
	 * Rollback database transaction.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function rollback() {
		$debug_log = __DIR__ . '/../debug-transaction.log';
		
		if ( ! $this->in_transaction ) {
			Logger::warning( 'database_transaction', 'No transaction to rollback' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ROLLBACK: No transaction to rollback\n", FILE_APPEND );
			return false;
		}

		$start_time = microtime( true );
		$result = $this->wpdb->query( 'ROLLBACK' );
		$duration = microtime( true ) - $start_time;
		
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ROLLBACK executed in {$duration} seconds, result: " . ($result !== false ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND );
		
		if ( false === $result ) {
			Logger::error( 'database_transaction', 'Failed to rollback transaction', [ 'error' => $this->wpdb->last_error ] );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ROLLBACK failed with error: " . $this->wpdb->last_error . "\n", FILE_APPEND );
			return false;
		}

		$this->in_transaction = false;
		$this->transaction_start_time = null;
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - ROLLBACK successful, transaction flags cleared\n", FILE_APPEND );
		return true;
	}

	/**
	 * Force rollback for timed-out transactions.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function force_rollback() {
		$debug_log = __DIR__ . '/../debug-transaction.log';
		file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FORCE_ROLLBACK: Forcing rollback of timed-out transaction\n", FILE_APPEND );
		
		try {
			$this->wpdb->query( 'ROLLBACK' );
			$this->in_transaction = false;
			$this->transaction_start_time = null;
			
			Logger::warning( 'database_transaction', 'Forced rollback of timed-out transaction' );
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FORCE_ROLLBACK: Completed successfully\n", FILE_APPEND );
			return true;
		} catch ( \Exception $e ) {
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - FORCE_ROLLBACK: Exception - " . $e->getMessage() . "\n", FILE_APPEND );
			return false;
		}
	}

	/**
	 * Emergency cleanup for unexpected process termination.
	 */
	public function emergency_transaction_cleanup() {
		if ( $this->in_transaction ) {
			$debug_log = __DIR__ . '/../debug-transaction.log';
			file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - EMERGENCY_CLEANUP: Process terminated with open transaction, attempting rollback\n", FILE_APPEND );
			
			try {
				$this->wpdb->query( 'ROLLBACK' );
				$this->in_transaction = false;
				$this->transaction_start_time = null;
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - EMERGENCY_CLEANUP: Rollback completed\n", FILE_APPEND );
			} catch ( \Exception $e ) {
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - EMERGENCY_CLEANUP: Exception during rollback - " . $e->getMessage() . "\n", FILE_APPEND );
			}
		}
	}

	/**
	 * Cleanup stale transactions from previous runs.
	 */
	private function cleanup_stale_transactions() {
		try {
			// Kill any long-running transactions (MySQL level)
			$long_running = $this->wpdb->get_results( "
				SELECT id, time, info 
				FROM information_schema.processlist 
				WHERE command = 'Query' 
				AND time > 900 
				AND info LIKE '%ai_blog_%'
			" );
			
			if ( ! empty( $long_running ) ) {
				$debug_log = __DIR__ . '/../debug-transaction.log';
				file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CLEANUP: Found " . count( $long_running ) . " long-running queries\n", FILE_APPEND );
				
				foreach ( $long_running as $process ) {
					$this->wpdb->query( "KILL {$process->id}" );
					file_put_contents( $debug_log, date( 'Y-m-d H:i:s' ) . " - CLEANUP: Killed process {$process->id} (runtime: {$process->time}s)\n", FILE_APPEND );
				}
			}
		} catch ( \Exception $e ) {
			// Ignore errors in cleanup - it's not critical
		}
	}

	/**
	 * Check if transaction is timed out.
	 *
	 * @return bool True if timed out, false otherwise.
	 */
	public function is_transaction_timed_out() {
		return $this->in_transaction && 
		       $this->transaction_start_time && 
		       ( microtime( true ) - $this->transaction_start_time ) > $this->transaction_timeout;
	}

	/**
	 * Execute raw SQL query.
	 *
	 * @param string $sql SQL query.
	 * @return bool|int False on error, number of rows affected/selected on success.
	 */
	private function execute_query( $sql ) {
		$result = $this->wpdb->query( $sql );
		return false !== $result;
	}

	/**
	 * Get full table name with prefix.
	 *
	 * @param string $table Table name without prefix.
	 * @return string|false Full table name or false if invalid.
	 */
	private function get_table_name( $table ) {
		$table_map = [
			'ideas'          => AI_BLOG_GENERATOR_TABLE_IDEAS,
			'generated_posts' => AI_BLOG_GENERATOR_TABLE_POSTS,
			'contexts'       => AI_BLOG_GENERATOR_TABLE_CONTEXTS,
			'personas'       => AI_BLOG_GENERATOR_TABLE_PERSONAS,
			'logs'           => AI_BLOG_GENERATOR_TABLE_LOGS,
			'cost_analytics' => AI_BLOG_GENERATOR_TABLE_COSTS,
			'seed_images'    => AI_BLOG_GENERATOR_TABLE_SEED_IMAGES,
			'products'       => AI_BLOG_GENERATOR_TABLE_PRODUCTS,
			'product_images' => AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES,
			'product_links'  => AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS,
			'product_seed_images' => $this->wpdb->prefix . 'ai_blog_generator_product_seed_images',
			'ai_blog_brand_features' => $this->wpdb->prefix . 'ai_blog_brand_features',
		];

		return isset( $table_map[ $table ] ) ? $table_map[ $table ] : false;
	}

	/**
	 * Get table mapping for validation.
	 *
	 * @return array Table mapping.
	 */
	private function get_table_map() {
		return [
			'ideas'          => AI_BLOG_GENERATOR_TABLE_IDEAS,
			'generated_posts' => AI_BLOG_GENERATOR_TABLE_POSTS,
			'contexts'       => AI_BLOG_GENERATOR_TABLE_CONTEXTS,
			'personas'       => AI_BLOG_GENERATOR_TABLE_PERSONAS,
			'logs'           => AI_BLOG_GENERATOR_TABLE_LOGS,
			'cost_analytics' => AI_BLOG_GENERATOR_TABLE_COSTS,
			'seed_images'    => AI_BLOG_GENERATOR_TABLE_SEED_IMAGES,
			'products'       => AI_BLOG_GENERATOR_TABLE_PRODUCTS,
			'product_images' => AI_BLOG_GENERATOR_TABLE_PRODUCT_IMAGES,
			'product_links'  => AI_BLOG_GENERATOR_TABLE_PRODUCT_LINKS,
			'product_seed_images' => $this->wpdb->prefix . 'ai_blog_generator_product_seed_images',
			'ai_blog_brand_features' => $this->wpdb->prefix . 'ai_blog_brand_features',
		];
	}

	/**
	 * Get allowed fields for a table (for security).
	 *
	 * @param string $table Table name.
	 * @return array Allowed field names.
	 */
	private function get_allowed_fields( $table ) {
		$field_map = [
			'ideas' => [ 'id', 'title', 'description', 'category_id', 'persona_id', 'status', 'generation_status', 'generation_error', 'generation_started_at', 'generation_completed_at', 'created_at', 'updated_at' ],
			'generated_posts' => [ 'id', 'idea_id', 'persona_id', 'post_id', 'title', 'content', 'excerpt', 'featured_image_id', 'seo_title', 'seo_description', 'cost', 'status', 'scheduled_time', 'published_at', 'created_at', 'updated_at' ],
			'contexts' => [ 'id', 'name', 'type', 'content', 'seed_image_id', 'active', 'created_at', 'updated_at' ],
			'personas' => [ 'id', 'name', 'bio', 'expertise', 'writing_style', 'tone', 'active', 'created_at', 'updated_at' ],
			'logs' => [ 'id', 'action', 'message', 'level', 'context', 'created_at' ],
			'cost_analytics' => [ 'id', 'service', 'action', 'cost', 'tokens_used', 'created_at' ],
			'seed_images' => [ 'id', 'product_name', 'image_url', 'context_id', 'created_at' ],
			'products' => [ 'id', 'name', 'description', 'ideal_uses', 'created_at', 'updated_at' ],
			'product_images' => [ 'id', 'product_id', 'attachment_id', 'image_url', 'is_primary', 'display_order', 'created_at' ],
			'product_links' => [ 'id', 'product_id', 'link_type', 'link_text', 'link_url', 'created_at' ],
			'product_seed_images' => [ 'id', 'product_id', 'attachment_id', 'image_url', 'display_order', 'created_at' ],
			'ai_blog_brand_features' => [ 'id', 'name', 'description', 'category', 'url', 'active', 'created_at', 'updated_at' ],
		];

		return $field_map[ $table ] ?? [];
	}

	/**
	 * Build WHERE clause from array with field validation.
	 *
	 * @param array  $where Where conditions.
	 * @param string $table Table name for field validation.
	 * @return string WHERE clause without 'WHERE' keyword.
	 */
	private function build_where_clause( $where, $table = '' ) {
		if ( empty( $where ) ) {
			return '';
		}

		$conditions = [];
		$allowed_fields = ! empty( $table ) ? $this->get_allowed_fields( $table ) : [];

		foreach ( $where as $field => $value ) {
			// Validate field name if table is specified
			if ( ! empty( $allowed_fields ) && ! in_array( $field, $allowed_fields, true ) ) {
				$this->log_warning( 'invalid_field_in_where', 'Skipping invalid field in WHERE clause', [
					'field' => $field,
					'table' => $table,
					'allowed_fields' => $allowed_fields
				] );
				continue;
			}

			// Sanitize field name (basic validation)
			$field = sanitize_key( $field );

			if ( is_null( $value ) ) {
				$conditions[] = "$field IS NULL";
			} elseif ( is_array( $value ) ) {
				$placeholders = array_fill( 0, count( $value ), '%s' );
				$conditions[] = $this->wpdb->prepare( "$field IN (" . implode( ',', $placeholders ) . ")", $value );
			} else {
				$conditions[] = $this->wpdb->prepare( "$field = %s", $value );
			}
		}

		return implode( ' AND ', $conditions );
	}
} 