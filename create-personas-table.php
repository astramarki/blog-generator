<?php
/**
 * Manually create personas table
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

echo "Creating Personas Table...\n";
echo "=========================\n\n";

global $wpdb;

// Get Database Manager instance
$db_manager = AI_Blog_Generator\Models\Database_Manager::get_instance();

// First, let's manually create the personas table
$table_name = $wpdb->prefix . 'ai_blog_personas';

$sql = "CREATE TABLE IF NOT EXISTS $table_name (
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

$result = $wpdb->query( $sql );

if ( $result === false ) {
    echo "ERROR creating table: " . $wpdb->last_error . "\n";
} else {
    echo "✓ Personas table created successfully!\n\n";
}

// Now run schema updates to add persona_id columns to other tables
echo "Running schema updates...\n";
$db_manager->run_schema_updates();
echo "✓ Schema updates complete!\n\n";

// Check if we need to add default personas
$existing_personas = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

if ( $existing_personas == 0 ) {
    echo "Adding default personas...\n";
    
    $default_personas = [
        [
            'name' => 'John',
            'bio' => 'John is a professional blog writer who has a PhD in education. He is a copywriting expert with over 15 years of experience in creating engaging, educational content.',
            'expertise' => 'Education, Copywriting, Academic Writing, SEO Optimization, Content Strategy',
            'writing_style' => 'Academic yet accessible, uses data and research to support points, includes practical examples, structured and logical flow',
            'tone' => 'professional',
            'active' => 1,
        ],
        [
            'name' => 'Ginny',
            'bio' => 'Ginny is a PTA president and community organizer looking for creative ways to fund school projects like their poster maker. She brings a parent\'s perspective and grassroots fundraising experience.',
            'expertise' => 'Community Organizing, Fundraising, Parent Engagement, School Activities, Event Planning',
            'writing_style' => 'Conversational and relatable, uses personal anecdotes, focuses on community and collaboration, practical tips and real-world examples',
            'tone' => 'friendly',
            'active' => 1,
        ],
        [
            'name' => 'Marcus',
            'bio' => 'Marcus is a digital marketing specialist with expertise in e-commerce and conversion optimization. He focuses on data-driven strategies and ROI.',
            'expertise' => 'Digital Marketing, E-commerce, Analytics, Conversion Optimization, Social Media Marketing',
            'writing_style' => 'Data-focused, uses statistics and case studies, action-oriented, includes metrics and KPIs',
            'tone' => 'analytical',
            'active' => 1,
        ],
        [
            'name' => 'Sarah',
            'bio' => 'Sarah is a wellness coach and lifestyle blogger who specializes in holistic health and work-life balance. She has certifications in nutrition and mindfulness.',
            'expertise' => 'Wellness, Nutrition, Mindfulness, Work-Life Balance, Holistic Health',
            'writing_style' => 'Empathetic and encouraging, uses inclusive language, focuses on practical wellness tips, incorporates mindfulness concepts',
            'tone' => 'inspirational',
            'active' => 1,
        ],
    ];
    
    foreach ( $default_personas as $persona ) {
        $result = $wpdb->insert( $table_name, $persona );
        if ( $result ) {
            echo "  ✓ Added persona: {$persona['name']}\n";
        } else {
            echo "  ✗ Failed to add persona: {$persona['name']} - " . $wpdb->last_error . "\n";
        }
    }
    
    echo "\n✓ Default personas added!\n";
} else {
    echo "Personas already exist in the database.\n";
}

// Display final status
echo "\n\nFinal Status:\n";
echo "=============\n";

$total_personas = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
echo "Total personas in database: $total_personas\n";

$active_personas = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE active = 1" );
echo "Active personas: $active_personas\n";

echo "\n✓ Setup complete! You can now access the Personas page at:\n";
echo admin_url( 'admin.php?page=ai-blog-generator-personas' ) . "\n"; 