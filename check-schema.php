<?php
/**
 * Database Schema Check and Fix
 * 
 * This script checks if the seed_image_id column exists and adds it if missing.
 * 
 * Place this file in your WordPress root directory and visit it in your browser.
 */

// Define WordPress path
$wp_load_path = __DIR__ . '/wp-load.php';

if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('WordPress not found. Make sure this file is in your WordPress root directory.');
}

// Get WordPress database connection
global $wpdb;

// Define table name
$table_name = $wpdb->prefix . 'ai_blog_contexts';

echo "<h2>AI Blog Generator - Database Schema Check</h2>";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    echo "<p style='color: red;'>❌ Table '$table_name' does not exist!</p>";
    echo "<p>Please activate the AI Blog Generator plugin first.</p>";
    exit;
}

echo "<p style='color: green;'>✅ Table '$table_name' exists.</p>";

// Check if seed_image_id column exists
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
$seed_image_column_exists = false;

echo "<h3>Current Table Structure:</h3>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Key}</td>";
    echo "<td>{$column->Default}</td>";
    echo "</tr>";
    
    if ($column->Field === 'seed_image_id') {
        $seed_image_column_exists = true;
    }
}
echo "</table>";

if ($seed_image_column_exists) {
    echo "<p style='color: green;'>✅ seed_image_id column exists!</p>";
    echo "<p>Your database schema is correct. The issue might be elsewhere.</p>";
} else {
    echo "<p style='color: red;'>❌ seed_image_id column is missing!</p>";
    echo "<p><strong>Adding the missing column...</strong></p>";
    
    // Add the missing column
    $sql = "ALTER TABLE $table_name ADD COLUMN seed_image_id BIGINT(20) UNSIGNED NULL AFTER content";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Successfully added seed_image_id column!</p>";
        echo "<p>The contexts page should now work correctly.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add column. Error: " . $wpdb->last_error . "</p>";
        echo "<p><strong>Manual SQL command:</strong></p>";
        echo "<code style='background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;'>";
        echo "ALTER TABLE $table_name ADD COLUMN seed_image_id BIGINT(20) UNSIGNED NULL AFTER content;";
        echo "</code>";
    }
}

echo "<hr>";
echo "<p><em>You can delete this file after running it.</em></p>";
?> 