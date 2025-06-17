<?php
/**
 * Fix Contexts Schema
 * 
 * This script ensures the contexts table has all required columns
 * and fixes any schema issues that might cause 500 errors.
 */

// Load WordPress
if (file_exists(__DIR__ . '/wp-config-dev.example.php')) {
    require_once __DIR__ . '/wp-config-dev.example.php';
}
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-includes/wp-db.php';

// Load plugin
require_once __DIR__ . '/ai-blog-generator.php';

use AI_Blog_Generator\Models\Database_Manager;
use AI_Blog_Generator\Utilities\Logger;

global $wpdb;

echo "=== AI Blog Generator Contexts Schema Fix ===\n\n";

try {
    // Get database manager instance
    $db_manager = Database_Manager::get_instance();
    
    echo "1. Checking contexts table existence...\n";
    $table_name = AI_BLOG_GENERATOR_TABLE_CONTEXTS;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        echo "   ❌ Contexts table does not exist. Creating it...\n";
        $success = $db_manager->create_tables();
        if ($success) {
            echo "   ✅ Tables created successfully!\n";
        } else {
            echo "   ❌ Failed to create tables. Check logs for details.\n";
            exit(1);
        }
    } else {
        echo "   ✅ Contexts table exists\n";
    }
    
    echo "\n2. Checking table schema...\n";
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    $existing_columns = array_column($columns, 'Field');
    
    echo "   Existing columns: " . implode(', ', $existing_columns) . "\n";
    
    $required_columns = ['id', 'name', 'description', 'type', 'content', 'priority', 'usage_flags', 'seed_image_id', 'active', 'created_at', 'updated_at'];
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (!empty($missing_columns)) {
        echo "   ❌ Missing columns: " . implode(', ', $missing_columns) . "\n";
        echo "   🔧 Running schema updates...\n";
        
        $schema_success = $db_manager->run_schema_updates();
        if ($schema_success) {
            echo "   ✅ Schema updates completed successfully!\n";
        } else {
            echo "   ⚠️  Some schema updates may have failed. Check logs.\n";
        }
    } else {
        echo "   ✅ All required columns present\n";
    }
    
    echo "\n3. Checking existing contexts...\n";
    $context_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "   Total contexts: $context_count\n";
    
    $active_contexts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE active = 1");
    echo "   Active contexts: $active_contexts\n";
    
    if ($active_contexts == 0) {
        echo "   ❌ No active contexts found!\n";
        echo "   🔧 Creating default contexts...\n";
        
        // Create default contexts with all required fields
        $default_contexts = [
            [
                'name'        => 'General Business Information',
                'description' => 'Core business information and values',
                'type'        => 'general',
                'content'     => 'Replace this with your business description, mission, values, and general information that should be considered when generating blog posts.',
                'priority'    => 75,
                'usage_flags' => 'ideas,content,images',
                'active'      => 1,
            ],
            [
                'name'        => 'Products and Services',
                'description' => 'Product catalog and service offerings',
                'type'        => 'products',
                'content'     => 'List your products and services here. Include features, benefits, and unique selling points.',
                'priority'    => 100,
                'usage_flags' => 'ideas,content,images',
                'active'      => 1,
            ],
            [
                'name'        => 'SEO Guidelines',
                'description' => 'SEO best practices and requirements',
                'type'        => 'seo',
                'content'     => 'Include your SEO guidelines here: keyword density, meta description length, title format preferences, etc.',
                'priority'    => 50,
                'usage_flags' => 'content',
                'active'      => 1,
            ],
            [
                'name'        => 'Target Keywords',
                'description' => 'Primary and secondary keywords for content',
                'type'        => 'keywords',
                'content'     => 'List your target keywords and phrases, one per line.',
                'priority'    => 75,
                'usage_flags' => 'ideas,content',
                'active'      => 1,
            ],
            [
                'name'        => 'Image Generation Guidelines',
                'description' => 'Visual style and image requirements',
                'type'        => 'image',
                'content'     => 'Describe your preferred image style, colors, themes, and any specific requirements for generated images.',
                'priority'    => 50,
                'usage_flags' => 'images',
                'active'      => 1,
            ],
        ];
        
        $created_contexts = 0;
        foreach ($default_contexts as $context) {
            $result = $db_manager->insert('contexts', $context);
            if ($result) {
                $created_contexts++;
                echo "   ✅ Created context: {$context['name']}\n";
            } else {
                echo "   ❌ Failed to create context: {$context['name']}\n";
            }
        }
        
        echo "   📊 Created $created_contexts default contexts\n";
    } else {
        echo "   ✅ Active contexts found\n";
        
        // Check if existing contexts have all required fields
        echo "   🔍 Checking existing contexts for missing fields...\n";
        $contexts_with_issues = $wpdb->get_results(
            "SELECT id, name, description, priority, usage_flags FROM $table_name WHERE active = 1 AND (description IS NULL OR priority IS NULL OR usage_flags IS NULL OR usage_flags = '')"
        );
        
        if (!empty($contexts_with_issues)) {
            echo "   ⚠️  Found " . count($contexts_with_issues) . " contexts with missing fields\n";
            echo "   🔧 Fixing contexts...\n";
            
            foreach ($contexts_with_issues as $context) {
                $updates = [];
                
                if (empty($context->description)) {
                    $updates['description'] = 'Context description';
                }
                
                if (empty($context->priority)) {
                    $updates['priority'] = 50;
                }
                
                if (empty($context->usage_flags)) {
                    $updates['usage_flags'] = 'ideas,content,images';
                }
                
                if (!empty($updates)) {
                    $result = $db_manager->update('contexts', $updates, ['id' => $context->id]);
                    if ($result !== false) {
                        echo "   ✅ Fixed context: {$context->name}\n";
                    } else {
                        echo "   ❌ Failed to fix context: {$context->name}\n";
                    }
                }
            }
        } else {
            echo "   ✅ All contexts have required fields\n";
        }
    }
    
    echo "\n4. Final verification...\n";
    $final_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE active = 1");
    echo "   Active contexts after fixes: $final_count\n";
    
    if ($final_count > 0) {
        echo "\n✅ Context schema fix completed successfully!\n";
        echo "The blog post generation should now work without 500 errors.\n";
    } else {
        echo "\n❌ Still no active contexts. Manual intervention may be required.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error during schema fix: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Schema Fix Complete ===\n"; 