<?php
/**
 * Check fusion_code content in a WordPress post
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

$post_id = isset($argv[1]) ? intval($argv[1]) : 0;

if (!$post_id) {
    echo "Usage: php check-post-fusion-code.php <post_id>\n";
    echo "\nTo find recent AI generated posts:\n";
    
    global $wpdb;
    $recent_posts = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_date 
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE pm.meta_key = '_ai_blog_idea_id'
        AND p.post_type = 'post'
        ORDER BY p.ID DESC
        LIMIT 5"
    );
    
    if ($recent_posts) {
        echo "\nRecent AI-generated posts:\n";
        foreach ($recent_posts as $post) {
            echo "- ID {$post->ID}: {$post->post_title} ({$post->post_date})\n";
        }
    }
    exit;
}

echo "Checking fusion_code Content in Post ID: $post_id\n";
echo "==========================================\n\n";

// Get the post
$post = get_post($post_id);

if (!$post) {
    echo "Post not found!\n";
    exit;
}

echo "Post Title: " . $post->post_title . "\n";
echo "Post Status: " . $post->post_status . "\n";
echo "Post Date: " . $post->post_date . "\n\n";

// Get raw content from database
global $wpdb;
$raw_content = $wpdb->get_var($wpdb->prepare(
    "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
    $post_id
));

// Find all fusion_code blocks
preg_match_all('/\[fusion_code\](.*?)\[\/fusion_code\]/s', $raw_content, $matches);

if (empty($matches[0])) {
    echo "No fusion_code blocks found in this post.\n";
} else {
    echo "Found " . count($matches[0]) . " fusion_code block(s):\n\n";
    
    foreach ($matches[1] as $index => $content) {
        echo "=== FUSION_CODE BLOCK " . ($index + 1) . " ===\n";
        echo "Raw content:\n";
        echo "----------\n";
        echo $content;
        echo "\n----------\n";
        
        // Check for script tags
        if (preg_match('/<script[^>]*>.*<\/script>/is', $content)) {
            echo "✓ Has <script> tags\n";
        } else {
            echo "✗ Missing <script> tags\n";
        }
        
        // Check for HTML tags
        if (preg_match('/<p>|<\/p>|<br\s*\/?>/i', $content)) {
            echo "⚠ Contains HTML tags (p or br)\n";
        }
        
        // Check for JavaScript indicators
        $js_indicators = ['function', 'var ', 'const ', 'let ', 'document.', 'window.', 'ApexCharts'];
        $found_js = false;
        foreach ($js_indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                $found_js = true;
                break;
            }
        }
        
        if ($found_js) {
            echo "✓ Contains JavaScript code\n";
        }
        
        echo "\n";
    }
}

// Also show a sample of the raw post content around fusion_code
echo "=== RAW CONTENT SAMPLE ===\n";
$sample_start = strpos($raw_content, '[fusion_code]');
if ($sample_start !== false) {
    $sample_end = strpos($raw_content, '[/fusion_code]', $sample_start) + 14;
    $sample = substr($raw_content, max(0, $sample_start - 50), $sample_end - $sample_start + 100);
    echo $sample;
    echo "\n";
} 