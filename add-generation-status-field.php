<?php
/**
 * Add generation_status field to ai_blog_ideas table
 * Run from WordPress root: wp eval-file wp-content/plugins/blog-generator/add-generation-status-field.php
 */

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    require_once(__DIR__ . '/../../../wp-load.php');
}

echo "=== Adding generation_status Field to Ideas Table ===\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'ai_blog_ideas';

// Fields to check and add
$fields = [
    'generation_status' => ['type' => 'VARCHAR(255) NULL', 'after' => 'status'],
    'generation_error' => ['type' => 'TEXT NULL', 'after' => 'generation_status'],
    'generation_started_at' => ['type' => 'DATETIME NULL', 'after' => 'generation_error'],
    'generation_completed_at' => ['type' => 'DATETIME NULL', 'after' => 'generation_started_at']
];

$added_count = 0;
$existing_count = 0;

foreach ($fields as $field_name => $field_config) {
    // Check if the field already exists
    $column_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE table_name = %s AND column_name = %s",
            $table_name,
            $field_name
        )
    );
    
    if ($column_exists) {
        echo "ℹ️  Field '$field_name' already exists.\n";
        $existing_count++;
        continue;
    }
    
    // Add the field
    echo "Adding '$field_name' field to $table_name...\n";
    $sql = "ALTER TABLE $table_name ADD COLUMN $field_name {$field_config['type']} AFTER {$field_config['after']}";
    
    $result = $wpdb->query($sql);
    
    if (false === $result) {
        echo "❌ Error adding field '$field_name': " . $wpdb->last_error . "\n";
    } else {
        echo "✅ Successfully added '$field_name' field!\n";
        $added_count++;
    }
}

// Add index on generation_status if it doesn't exist
$index_exists = $wpdb->get_var(
    "SELECT COUNT(1) IndexExists FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='$table_name' AND index_name='idx_generation_status'"
);

if (!$index_exists) {
    echo "\nAdding index on generation_status field...\n";
    $index_sql = "ALTER TABLE $table_name ADD INDEX idx_generation_status (generation_status)";
    $index_result = $wpdb->query($index_sql);
    
    if (false === $index_result) {
        echo "⚠️ Warning: Could not add index: " . $wpdb->last_error . "\n";
    } else {
        echo "✅ Successfully added index on generation_status field!\n";
    }
} else {
    echo "ℹ️  Index 'idx_generation_status' already exists.\n";
}

echo "\n✅ Database migration completed!\n";
echo "\nSummary:\n";
echo "- Fields added: $added_count\n";
echo "- Fields already existing: $existing_count\n";
echo "\nAll fields:\n";
echo "- generation_status (VARCHAR 255) - For tracking generation progress\n";
echo "- generation_error (TEXT) - For storing error messages\n";
echo "- generation_started_at (DATETIME) - For tracking start time\n";
echo "- generation_completed_at (DATETIME) - For tracking completion time\n"; 