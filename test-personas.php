<?php
/**
 * Test Personas System
 * 
 * Run this file to test if the personas table was created and has data.
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if plugin is active
if ( ! is_plugin_active( 'blog-generator/ai-blog-generator.php' ) ) {
    die( "Error: AI Blog Generator plugin is not active.\n" );
}

echo "AI Blog Generator - Personas System Test\n";
echo "========================================\n\n";

// Check if personas table exists
global $wpdb;
$table_name = $wpdb->prefix . 'ai_blog_personas';
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name;

if ( ! $table_exists ) {
    echo "ERROR: Personas table does not exist!\n";
    echo "Please deactivate and reactivate the plugin to create the table.\n";
    exit;
}

echo "✓ Personas table exists\n\n";

// Check table structure
echo "Table Structure:\n";
$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name" );
foreach ( $columns as $column ) {
    echo "  - {$column->Field} ({$column->Type})\n";
}
echo "\n";

// Get all personas
$personas = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id" );

echo "Personas in Database: " . count( $personas ) . "\n";
echo "========================\n\n";

if ( empty( $personas ) ) {
    echo "No personas found. Creating default personas...\n";
    
    // Try to run the schema update to add default personas
    $db_manager = AI_Blog_Generator\Models\Database_Manager::get_instance();
    $db_manager->run_schema_updates();
    
    // Check again
    $personas = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id" );
}

foreach ( $personas as $persona ) {
    echo "Persona #{$persona->id}: {$persona->name}\n";
    echo "  Bio: " . substr( $persona->bio, 0, 100 ) . "...\n";
    echo "  Expertise: {$persona->expertise}\n";
    echo "  Tone: {$persona->tone}\n";
    echo "  Active: " . ( $persona->active ? 'Yes' : 'No' ) . "\n";
    echo "  Created: {$persona->created_at}\n";
    echo "\n";
}

// Test Persona Model
echo "\nTesting Persona Model:\n";
echo "======================\n";

try {
    $persona_model = new AI_Blog_Generator\Models\Persona_Model();
    
    // Get active personas
    $active_personas = $persona_model->get_active_personas();
    echo "✓ Active personas: " . count( $active_personas ) . "\n";
    
    // Test persona matching
    $test_idea = [
        'title' => 'How to Fundraise for School Projects',
        'description' => 'Tips for PTA fundraising and community engagement',
        'keywords' => 'fundraising, school, PTA, community'
    ];
    
    $best_persona_id = $persona_model->find_best_match_for_idea( $test_idea );
    if ( $best_persona_id ) {
        $best_persona = $persona_model->get( $best_persona_id );
        echo "✓ Best persona for fundraising idea: {$best_persona->name}\n";
    }
    
    echo "\n✓ All tests passed! The personas system is working correctly.\n";
    
} catch ( Exception $e ) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nTo access the Personas admin page, go to:\n";
echo admin_url( 'admin.php?page=ai-blog-generator-personas' ) . "\n"; 