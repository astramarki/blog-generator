<?php
/**
 * Test script to verify seed images table
 * Run this from WordPress admin or via direct URL to check table status
 */

// Load WordPress if not already loaded
if ( ! defined( 'ABSPATH' ) ) {
	// Try to find WordPress installation
	$wp_config_paths = [
		'../../../wp-config.php',
		'../../../../wp-config.php',
		'../wp-config.php',
		'../../wp-config.php',
	];
	
	foreach ( $wp_config_paths as $path ) {
		if ( file_exists( dirname( __FILE__ ) . '/' . $path ) ) {
			require_once( dirname( __FILE__ ) . '/' . $path );
			break;
		}
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	die( 'WordPress not found. Please run this script from within WordPress admin.' );
}

// Set content type
header( 'Content-Type: text/html; charset=utf-8' );

echo "<h2>AI Blog Generator - Seed Images Table Test</h2>";

global $wpdb;

// Get table name
$table_name = $wpdb->prefix . 'ai_blog_seed_images';
echo "<p><strong>Expected table name:</strong> {$table_name}</p>";

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
echo "<p><strong>Table exists:</strong> " . ( $table_exists ? 'YES' : 'NO' ) . "</p>";

if ( $table_exists ) {
	echo "<p style='color: green;'>✓ Table found!</p>";
	
	// Get table structure
	$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
	echo "<h3>Table Structure:</h3>";
	echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
	echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
	foreach ( $columns as $column ) {
		echo "<tr>";
		echo "<td>{$column->Field}</td>";
		echo "<td>{$column->Type}</td>";
		echo "<td>{$column->Null}</td>";
		echo "<td>{$column->Key}</td>";
		echo "<td>{$column->Default}</td>";
		echo "<td>{$column->Extra}</td>";
		echo "</tr>";
	}
	echo "</table>";
	
	// Count existing records
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	echo "<p><strong>Existing records:</strong> {$count}</p>";
	
	// Show sample records if any exist
	if ( $count > 0 ) {
		$samples = $wpdb->get_results( "SELECT * FROM {$table_name} LIMIT 5" );
		echo "<h3>Sample Records:</h3>";
		echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
		echo "<tr><th>ID</th><th>Product Name</th><th>Image URL</th><th>Context ID</th><th>Created At</th></tr>";
		foreach ( $samples as $sample ) {
			echo "<tr>";
			echo "<td>{$sample->id}</td>";
			echo "<td>{$sample->product_name}</td>";
			echo "<td><a href='{$sample->image_url}' target='_blank'>" . basename( $sample->image_url ) . "</a></td>";
			echo "<td>{$sample->context_id}</td>";
			echo "<td>{$sample->created_at}</td>";
			echo "</tr>";
		}
		echo "</table>";
	}
	
} else {
	echo "<p style='color: red;'>✗ Table not found!</p>";
	
	// Show all tables with similar names
	$similar_tables = $wpdb->get_results( "SHOW TABLES LIKE '%seed%'" );
	if ( $similar_tables ) {
		echo "<h3>Tables with 'seed' in name:</h3>";
		foreach ( $similar_tables as $table ) {
			$table_name_found = array_values( (array) $table )[0];
			echo "<p>- {$table_name_found}</p>";
		}
	}
	
	// Show all plugin tables
	$plugin_tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}ai_blog%'" );
	if ( $plugin_tables ) {
		echo "<h3>AI Blog Generator Tables:</h3>";
		foreach ( $plugin_tables as $table ) {
			$table_name_found = array_values( (array) $table )[0];
			echo "<p>- {$table_name_found}</p>";
		}
	}
}

// Test WordPress upload directory
echo "<h3>WordPress Upload Directory Test:</h3>";
$upload_dir = wp_upload_dir();
if ( $upload_dir['error'] ) {
	echo "<p style='color: red;'>✗ Upload directory error: {$upload_dir['error']}</p>";
} else {
	echo "<p style='color: green;'>✓ Upload directory OK</p>";
	echo "<p><strong>Upload path:</strong> {$upload_dir['path']}</p>";
	echo "<p><strong>Upload URL:</strong> {$upload_dir['url']}</p>";
	echo "<p><strong>Writable:</strong> " . ( is_writable( $upload_dir['path'] ) ? 'YES' : 'NO' ) . "</p>";
}

// Test constants
echo "<h3>Plugin Constants:</h3>";
if ( defined( 'AI_BLOG_GENERATOR_TABLE_SEED_IMAGES' ) ) {
	echo "<p><strong>AI_BLOG_GENERATOR_TABLE_SEED_IMAGES:</strong> " . AI_BLOG_GENERATOR_TABLE_SEED_IMAGES . "</p>";
} else {
	echo "<p style='color: red;'>AI_BLOG_GENERATOR_TABLE_SEED_IMAGES not defined</p>";
}

echo "<p><em>Test completed at " . date( 'Y-m-d H:i:s' ) . "</em></p>";
?> 