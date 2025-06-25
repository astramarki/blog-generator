<?php
/**
 * Fix fusion_code content in existing posts
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

$post_id = isset($argv[1]) ? intval($argv[1]) : 0;

if ($post_id) {
    echo "Fixing fusion_code in Post ID: $post_id\n";
    echo "=====================================\n\n";
    
    $posts = [$post_id];
} else {
    echo "Fixing fusion_code in All AI-Generated Posts\n";
    echo "============================================\n\n";
    
    // Get all AI-generated posts
    global $wpdb;
    $post_ids = $wpdb->get_col(
        "SELECT DISTINCT p.ID 
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE pm.meta_key = '_ai_blog_idea_id'
        AND p.post_type = 'post'
        AND p.post_content LIKE '%[fusion_code]%'
        ORDER BY p.ID DESC"
    );
    
    if (empty($post_ids)) {
        echo "No AI-generated posts with fusion_code found.\n";
        exit;
    }
    
    echo "Found " . count($post_ids) . " posts with fusion_code blocks.\n\n";
    $posts = $post_ids;
}

$fixed_count = 0;

foreach ($posts as $post_id) {
    $post = get_post($post_id);
    
    if (!$post) {
        echo "Post $post_id not found, skipping.\n";
        continue;
    }
    
    echo "Processing: {$post->post_title} (ID: $post_id)\n";
    
    $content = $post->post_content;
    $original_content = $content;
    
    // Find and fix all fusion_code blocks
    $content = preg_replace_callback(
        '/\[fusion_code\](.*?)\[\/fusion_code\]/s',
        function( $matches ) {
            $code_content = $matches[1];
            
            // Decode HTML entities
            $code_content = html_entity_decode( $code_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            
            // Remove any p or br tags that might have been added
            $code_content = preg_replace( '/<\/?p[^>]*>/i', '', $code_content );
            $code_content = preg_replace( '/<br\s*\/?>/i', "\n", $code_content );
            
            // Check if already has script tags
            if ( preg_match( '/<script[^>]*>.*<\/script>/is', $code_content ) ) {
                // Already has script tags
                return '[fusion_code]' . $code_content . '[/fusion_code]';
            }
            
            // Check if this is JavaScript code
            $js_indicators = ['function', 'var ', 'const ', 'let ', 'document.', 'window.', 'new ', 'ApexCharts'];
            $is_javascript = false;
            
            foreach ( $js_indicators as $indicator ) {
                if ( stripos( $code_content, $indicator ) !== false ) {
                    $is_javascript = true;
                    break;
                }
            }
            
            // If JavaScript, wrap in script tags
            if ( $is_javascript ) {
                echo "  - Adding <script> tags to fusion_code block\n";
                $code_content = '<script>' . "\n" . trim( $code_content ) . "\n" . '</script>';
            }
            
            return '[fusion_code]' . $code_content . '[/fusion_code]';
        },
        $content
    );
    
    // Only update if content changed
    if ($content !== $original_content) {
        $wpdb->update(
            $wpdb->posts,
            ['post_content' => $content],
            ['ID' => $post_id]
        );
        
        // Clear post cache
        clean_post_cache($post_id);
        
        echo "  ✓ Fixed and saved\n";
        $fixed_count++;
    } else {
        echo "  - No changes needed\n";
    }
    
    echo "\n";
}

echo "Done! Fixed $fixed_count post(s).\n";

if ($fixed_count > 0) {
    echo "\nNote: You may need to clear your page cache if using a caching plugin.\n";
} 